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
     * Default config
     *
     * @var array
     */
    protected $_defaultConfig = [
        'prefix' => 'revision_',
        'field' => 'hash'
    ];

    /**
     * {@inheritdoc}
     */
    public function beforeSave(Event $event, EntityInterface $entity)
    {
        $hash = Text::uuid();

        $revision = $this->_table->newEntity();

        $revision = $this->_table->find()->where([
            $this->_table->getPrimaryKey() => $entity->{$this->_table->getPrimaryKey()}
        ])->first();

        if ($entity->isNew()) {
            if (!isset($entity->{$this->_config['prefix'] . $this->_config['field']})) {
                $entity->{$this->_config['prefix'] . $this->_config['field']} = $hash;
            }
        } else {
            $revision->{$this->_config['prefix'] . $this->_table->getPrimaryKey()} = $entity->{$this->_table->getPrimaryKey()};

            $revision->unsetProperty($this->_table->getPrimaryKey())->isNew(true);

            $this->_table->save($revision);

            // Disable foreign keys
            $this->_table->connection()->disableForeignKeys();

            $entity->{$this->_config['prefix'] . $this->_config['field']} = $hash;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function beforeFind(Event $event, Query $query)
    {
        if ($this->_table->hasField($this->_config['prefix'] . $this->_config['field'])) {
            $revisioned = true;

            if ($query->clause('where')) {
                $query->clause('where')->traverse(function ($expression) use (&$revisioned) {
                    if ($expression instanceof Comparison) {
                        if ($expression->getField() === $this->_table->getAlias() . '.' . $this->_config['prefix'] . $this->_config['field']) {
                            $revisioned = false;
                        }
                    }
                });
            }

            if ($revisioned === true) {
                $query->where($this->_table->getAlias() . '.' . $this->_config['prefix'] . $this->_table->getPrimaryKey() . ' IS NULL');
            }
        }

        return $query;
    }
}
