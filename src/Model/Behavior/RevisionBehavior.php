<?php
namespace Revision\Model\Behavior;

use Cake\ORM\Behavior;
use Cake\Datasource\ConnectionManager;
use Cake\Datasource\EntityInterface;
use Cake\Database\Connection;
use Cake\Database\Query;
use Cake\Database\Expression\Comparison;
use Cake\Event\Event;
use Cake\Utility\Text;

class RevisionBehavior extends Behavior
{

    /**
     * @inheritdoc
     */
    protected $_defaultConfig = [
        'prefix' => 'revision_',
        'field' => 'hash',
        'relation' => 'Revisions',
    ];

    /**
     * @inheritdoc
     */
    public function initialize(array $config)
    {
        // Create dynamic relation
        $this->getTable()->hasMany($this->_config['relation'], [
            'className' => $this->getTable()->getRegistryAlias(),
            'foreignKey' => $this->_config['prefix'] . $this->getTable()->getPrimaryKey(),
            'finder' => 'history',
        ]);
    }

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
            $this->getTable()->getConnection()->disableForeignKeys();

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

    /**
     * Custom finder for get history of revisions
     *
     * @param Query $query The original query to modify
     * @return \Cake\ORM\Query
     */
    public function findHistory(Query $query)
    {
        // Remove Revision Behavior
        $this->getTable()->removeBehavior('Revision');

        // Auto add foreign key
        $query->select([
            $this->getTable()->getAlias() . '.' . $this->getTable()->getPrimaryKey(),
            $this->getTable()->getAlias() . '.' . $this->_config['prefix'] . $this->getTable()->getPrimaryKey(),
            $this->getTable()->getAlias() . '.' . $this->_config['prefix'] . $this->_config['field'],
        ]);

        // Default ordering
        $query->order([
            $this->getTable()->getAlias() . '.' . $this->getTable()->getPrimaryKey() => 'DESC',
        ]);

        return $query;
    }
}
