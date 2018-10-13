<?php
namespace Revision\Model\Behavior;

use Cake\Datasource\EntityInterface;
use Cake\ORM\Behavior;
use Cake\Database\Query;
use Cake\Database\Expression\Comparison;
use Cake\Event\Event;
use Cake\Utility\Text;
use Cake\Database\Connection;
use Cake\Datasource\ConnectionManager;

class RevisionBehavior extends Behavior
{

    /**
     * @inheritdoc
     */
    protected $_defaultConfig = [
        'prefix' => 'revision_',
        'field' => 'hash'
    ];

    /**
     * @inheritdoc
     */
    public function beforeSave(Event $event, EntityInterface $entity)
    {
        $hash = Text::uuid();

        $revision = $this->getTable()->newEntity();

        $revision = $this->getTable()->find()->where([
            $this->getTable()->getPrimaryKey() => $entity->{$this->getTable()->getPrimaryKey()}
        ])->first();

        if ($entity->isNew()) {
            if (!isset($entity->{$this->_config['prefix'] . $this->_config['field']})) {
                $entity->{$this->_config['prefix'] . $this->_config['field']} = $hash;
            }
        } else {
            $revision->{$this->_config['prefix'] . $this->getTable()->getPrimaryKey()} = $entity->{$this->getTable()->getPrimaryKey()};

            $revision->unsetProperty($this->getTable()->getPrimaryKey())->isNew(true);

            $this->getTable()->save($revision);

            // Disable foreign keys
            $this->getTable()->connection()->disableForeignKeys();

            $entity->{$this->_config['prefix'] . $this->_config['field']} = $hash;
        }
    }

    /**
     * @inheritdoc
     */
    public function beforeFind(Event $event, Query $query)
    {
        if ($this->getTable()->hasField($this->_config['prefix'] . $this->_config['field'])) {
            $revisioned = true;

            if ($query->clause('where')) {
                $query->clause('where')->traverse(function ($expression) use (&$revisioned) {
                    if ($expression instanceof Comparison) {
                        if ($expression->getField() === $this->getTable()->getAlias() . '.' . $this->_config['prefix'] . $this->_config['field']) {
                            $revisioned = false;
                        }
                    }
                });
            }

            if ($revisioned === true) {
                $query->where($this->getTable()->getAlias() . '.' . $this->_config['prefix'] . $this->getTable()->getPrimaryKey() . ' IS NULL');
            }
        }

        return $query;
    }
}
