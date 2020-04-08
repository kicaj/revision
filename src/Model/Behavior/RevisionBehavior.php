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
     * {@inheritDoc}
     */
    protected $_defaultConfig = [
        'prefix' => 'revision_',
        'field' => 'hash',
        'relation' => 'Revisions',
    ];

    /**
     * {@inheritDoc}
     */
    public function initialize(array $config): void
    {
        // Create dynamic relation.
        $this->getTable()->hasMany($this->getConfig('relation'), [
            'className' => $this->getTable()->getRegistryAlias(),
            'foreignKey' => $this->getConfig('prefix') . $this->getTable()->getPrimaryKey(),
            'finder' => 'history',
        ]);
    }

    /**
     * {@inheritDoc}
     */
    public function beforeSave(Event $event, EntityInterface $entity)
    {
        $hash = Text::uuid();

        if ($entity->isNew()) {
            if (!isset($entity->{$this->getConfig('prefix') . $this->getConfig('field')})) {
                $entity->{$this->getConfig('prefix') . $this->getConfig('field')} = $hash;
            }
        } else {
            $revision = $this->getTable()->find()->select($this->getTable())->where([
                $this->getTable()->getPrimaryKey() . ' IS' => $entity->{$this->getTable()->getPrimaryKey()}
            ]);

            if (!$revision->isEmpty()) {
                $revision = $revision->first();

                $revision->{$this->getConfig('prefix') . $this->getTable()->getPrimaryKey()} = $entity->{$this->getTable()->getPrimaryKey()};

                $revision->unsetProperty($this->getTable()->getPrimaryKey())->setNew(true);

                // Accessibility of fields
                if (!empty($accessible = $this->getConfig('accessible'))) {
                    foreach ($accessible as $accessibleProperty => $accessibleSet) {
                        $revision->setAccess($accessibleProperty, $accessibleSet);
                    }
                }

                $this->getTable()->saveOrFail($revision);

                // Disable foreign keys.
                $this->getTable()->getConnection()->disableForeignKeys();

                $entity->{$this->getConfig('prefix') . $this->getConfig('field')} = $hash;
            }
        }
    }

    /**
     * {@inheritDoc}
     */
    public function beforeFind(Event $event, Query $query)
    {
        if ($this->getTable()->hasField($this->getConfig('prefix') . $this->getConfig('field'))) {
            $revisioned = true;

            if ($query->clause('where')) {
                $query->clause('where')->traverse(function ($expression) use (&$revisioned) {
                    if ($expression instanceof Comparison) {
                        if ($expression->getField() === $this->getTable()->getAlias() . '.' . $this->getConfig('prefix') . $this->getConfig('field')) {
                            $revisioned = false;
                        }
                    }
                });
            }

            if ($revisioned === true) {
                $query->where($this->getTable()->getAlias() . '.' . $this->getConfig('prefix') . $this->getTable()->getPrimaryKey() . ' IS NULL');
            }
        }

        return $query;
    }

    /**
     * Custom finder history revisions
     *
     * @param Query $query Query object.
     * @param array $options The options for the find.
     * @return Query The query builder.
     */
    public function findHistory(Query $query, array $options): Query
    {
        // Remove Revision Behavior.
        $this->getTable()->removeBehavior('Revision');

        // Auto add foreign key.
        $query->select([
            $this->getTable()->getAlias() . '.' . $this->getTable()->getPrimaryKey(),
            $this->getTable()->getAlias() . '.' . $this->getConfig('prefix') . $this->getTable()->getPrimaryKey(),
            $this->getTable()->getAlias() . '.' . $this->getConfig('prefix') . $this->getConfig('field'),
        ]);

        // Default ordering.
        $query->order([
            $this->getTable()->getAlias() . '.' . $this->getTable()->getPrimaryKey() => 'DESC',
        ]);

        return $query;
    }
}
