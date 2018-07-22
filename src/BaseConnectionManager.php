<?php declare(strict_types=1);
/* 
 * This file is part of the spf-contracts package which is distributed under the MIT License.
 * See LICENSE.md or go to https://github.com/simon-downes/spf-contracts for full license details.
 */
namespace spf\database;

use InvalidArgumentException;

use spf\contracts\database\{DatabaseConnection, ConnectionManager};

use spf\database\adapters\{MySQLConnection, PgSQLConnection, SQLiteConnection};
use spf\database\exceptions\{DatabaseException, ConfigurationException};

class BaseConnectionManager implements ConnectionManager {

    /**
     * Array of database connections
     * @var array
     */
    protected $connections = [];

    /**
     * Name of the default connection.
     * @var string
     */
    protected $default = '';

    public function add( string $name, $connection ) {

        $this->checkName($name);

        if( $connection instanceof DatabaseConnection ) {
            $this->connections[$name] = $connection;
        }
        else {
            $this->connections[$name] = $this->create($connection);
        }

        // no default connection so use the first one
        if( empty($this->default) ) {
            $this->default = $name;
        }

        return $this->connections[$name];

    }

    public function remove( $name ) {
        unset($this->connections[$name]);
        return $this;
    }

    public function get( $name ) {
        return $this->connections[$name] ?? null;
    }

    public function has( $name ) {
        return isset($this->connections[$name]);
    }

    public function getDefault() {
        return $this->connections[$this->default];
    }

    public function setDefault( $name ) {

        if( empty($this->connections[$name]) ) {
            throw new InvalidArgumentException("Unknown Connection: {$name}");
        }

        $this->default = $name;

        return $this;

    }

    /**
     * Create a suitable implementation of DatabaseConnection based on the specified DSN.
     * @param  mixed $dsn
     */
    protected function create( $dsn ): DatabaseConnection {

        $dsn = $this->validateDSN($dsn);

        $factories = [
            DSN::TYPE_MYSQL  => MySQLConnection::class,
            DSN::TYPE_PGSQL  => PgSQLConnection::class,
            DSN::TYPE_SQLITE => SQLiteConnection::class,
        ];

        if( empty($factories[$dsn->type]) ) {
            throw new ConfigurationException("Invalid database type: {$dsn->type}");
        }

        $class = $factories[$dsn->type];

        return new $class($dsn);

    }

    /**
     * Ensure we have a valid DSN instance.
     * @param  mixed $dsn a string, array or DSN instance
     */
    protected function validateDSN( $dsn ): DSN {

        if( $dsn instanceof DSN ) {
            return $dsn;
        }
        elseif( is_string($dsn) ) {
            return DSN::fromString($dsn);
        }
        elseif( is_array($dsn) ) {
            return new DSN($dsn);
        }

        throw new ConfigurationException("Invalid DSN: {$dsn}");

    }

    /**
     * Ensure we have a valid connection name, i.e. it's not empty and doesn't already exist.
     */
    protected function checkName( string $name ): void {
        if( empty($name) ) {
            throw new DatabaseException('Managed database connections must have a name');
        }
        if( $this->has($name) ) {
            throw new DatabaseException("Connection already exists with name: {$name}");
        }
    }

}
