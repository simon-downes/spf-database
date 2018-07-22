# SPF Database

A simple database abstraction layer that provides a lightweight wrapper around PDO for ease-of-use.
It currently supports MySQL, Postgres and SQLite.

Also included are simple query generators and a class for handling a tree structure within a relational
database via modified preorder tree traversal.

## Requirements

This library requires only PHP 7.2 or later and the SPF Contracts package (`simon-downes/spf-contracts`).

## Installation

It is installable and autoloadable via Composer as `simon-downes/spf-database`.

Alternatively, download a release or clone this repository, and add the `\spf\database` namespace to an autoloader.

## License

SPF Database is open-sourced software licensed under the MIT license

## Quick Start

```php
use spf\database\DSN;
use spf\database\adapters\MySQLConnection;

// create a DSN
$dsn = DSN::fromString('mysql://localhost/mydb');

// create a connection instance
$db = new MySQLConnection($dsn);

// get some data
$user = $db->getAssoc("SELECT * FROM users WHERE user_id = ?", 123);

// update some data
$updated = $db->execute(
    "UPDATE users SET last_seen = :now WHERE id = :id",
    [
        'id'  => 123,
        'now' => date('Y-m-d H:i:s'),
    ]
);
```

## DSNs
 
A DSN is an object that specifies the properties of a database connection.

Common properties are:
* `type` - the type of database to connect to (mysql, postgres or sqlite)
* `host` - the host to connect to
* `port` - the port number to connect on
* `user` - the user to authenticate as
* `pass` - the user's password
* `db` - the name of the database schema to connect to
* `options` - an array of driver specific options

DSNs can be created by passing an array of properties to the constructor:

```php
$dsn = new DSN([
	'type' => 'mysql',
	'host' => 'localhost',
	'db'   => 'myapp',
]);
```

or by calling the static `fromString()` method with a URI:

```php
$dsn = DSN::fromString('mysql://root:abc123@myapp.db/myapp?charset=utf-8');
```

## ConnectionManager

The ConnectionManager is a service to handle multiple database connections. A client can register a connection or DSN under a specific name and retrieve the connection at a later time.

When a DSN is registered, a suitable connection object is created automatically.

```php
use spf\database\BaseConnectionManager;
use spf\database\adapters\SQLiteConnection;

// create a ConnectionManager instance
$m = new BaseConnectionManager();

// register a DSN
$m->add('mydb1', 'mysql://localhost/mydb');

// register an existing connection
$db = new SQLiteConnection('sqlite://var/www/myapp/myapp.db');
$m->add('mydb2', $db);

// determine if a connection with the specified name exists
$exists = $m->has('mydb1');

// retrieve a previously added connection
$db = $m->get('mydb1');

// remove a connection from the manager and return it
// NOTE: this does not disconnect the connection
$db = $m->remove('mydb1');
```

## Query Method Reference

```php
 // Execute a query and return the resulting PDO_Statement
$stmt = $db->query($statement, $params = []);

// Execute a query and return the number of affected rows
$rows = $db->execute($statement, $params = []);

// Execute a query and return all matching data as an array of associative arrays of matching rows
// Each row array has column names as keys
$db->getAll($statement, $params = []);

// Execute a query and return all matching data as an associative array,
// the first selected column is used as the array key
$db->getAssoc($statement, $params = []);

// Execute a query and return all matching data as a two-dimensioanl
// associative array, the first two selected columns are used as the array keys
$db->getAssocMulti($statement, $params = []);

// Execute a query and return the first matching row as an associative array
$db->getRow($statement, $params = []);

// Execute a query and return all values of the first selected column as an array
$db->getCol($statement, $params = []);

// Execute a query and return the value of the first column in the first array
$db->getOne($statement, $params = []);
```

The above methods accept the following parameters:
* `$statement`: a `PDO_Statement` instance or a SQL string
* `$params`: an array of parameters to bind to the statement

Query parameters may be bound name:
```php
$user_id = $db->getOne(
    "SELECT id FROM user WHERE type = :type AND name LIKE :name", 
    [
        'type' => 'NORMAL',
        'name' => 'Jim%',
    ]
);
```
 or by position:
```php
$user_id = $db->getOne(
    "SELECT id FROM user WHERE type = ? AND name LIKE ?",
    ['NORMAL', 'Jim%']
);
```
If the query has only a single parameter it may be specified directly and will
be automatically converted to a positional parameter:
```php
$user_id = $db->getOne("SELECT id FROM user WHERE login = ?", 'jimbob');
```

## Other Methods
```php
// Returns the ID of the last inserted row or sequence value.
$id = $db->insertId($name = '');

// Escape/quote a value for use in a query string
$db->quote($value, $type = \PDO::PARAM_STR);

// Escape/quote an identifier name (table, column, etc)
// Allows reserved words to be used as identifiers.
$db->quoteIdentifier('key');

// Execute a raw SQL string and return the number of affected rows.
// Primarily used for DDL queries
$db->rawExec($sql);
```

## Transactions
```php
// Begin a transaction
$db->begin();

// Commit the current transaction
$db->commit();

// Rollback the current transaction
$db->rollback();

// Determines if a transaction is currently active
$db->inTransaction();
```

## Query Generators

OO query generators are available for `SELECT`, `INSERT`, `UPDATE` and `DELETE`.
An instance of each can be created by calling the corresponding method on the `DatabaseConnection`.

### Select
```php
$db->select()

   // accepts true (default) or false as argument
   ->distinct()

   // comma-separated list or array of column names
   ->cols('*')

   // table to select from
   ->from('table')

   // append a where clause - column, operator, value
   // multiple calls add additional clauses
   ->where('created', '>=', '2016-01-01')		

   // with two arguments, operator is assumed to be '='
   ->where('id', 123)

   // array of columns to group by
   ->groupBy(['type', 'status'])

   // second parameter specifies ascending (true) or descending (false)
   // multiple calls add additional clauses
   ->orderBy('column', true)

   // return result as associative array
   // can also use the other fetch* methods defined by DatabaseConnection
   ->fetchAssoc();
```

### Insert
```php
$db->insert()

   // accepts true (default) or false as argument
   ->ignore()

   // table to insert to
   ->into('table')

   // item to insert as an associative array of column names/values
   ->item([
       'col1' => 'value1',
       'col2' => 'value1',
   ])

   // run the query
   ->execute();
```

### Update
```php
$db->insert()

   // accepts true (default) or false as argument
   ->ignore()

   // table to insert to
   ->into('table')

   // columns to update as an associative array of column names/values
   ->set([
       'col1' => 'value1',
       'col2' => 'value1',
   ])

   // same usage as for SELECT
   ->where('id', 123)

   // run the query
   ->execute();
```

### Delete
```php
$db->delete()

   // table to insert to
   ->from('table')

   // same usage as for SELECT
   ->where('id', 123)

   // run the query
   ->execute();
```
