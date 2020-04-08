# Revision plugin for CakePHP

**NOTE:** It's still in development mode, do not use in production yet!

## Requirements

It is developed for CakePHP 3.x.

## Installation

You can install plugin into your CakePHP application using [composer](http://getcomposer.org).

The recommended way to install composer packages is:
```
composer require kicaj/revision dev-master
```

Load the Behavior
---------------------

Load the Behavior in your src/Model/Table/YourTable.php (or if You have AppTable.php). Your table should have two additionals columns: `revision_id` and `revision_hash`.
```
public function initialize(array $config)
{
    parent::initialize($config);

    $this->addBehavior('Revision.Revision');
}
```
The field `revision_id` should have the same type like primary key of table.

## TODOs

- [ ] Block updating `modified` field when highest parent is deleted
- [ ] Check with complex conditions
- [ ] Check configuration
- [ ] Exceptions for missing fields/columns
- [ ] Create history view
- [ ] Revert method
- [ ] Field with hash should check unique before save (recurring)
- [ ] Set excluded fields
