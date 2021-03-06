<?php declare(strict_types=1);
/*
 * This file is part of the spf-database package which is distributed under the MIT License.
 * See LICENSE.md or go to https://github.com/simon-downes/spf-database for full license details.
 */
namespace spf\database;

use spf\database\exceptions\ConfigurationException;

/**
 * Describes database connection details.
 *
 * @property-read string $type type of database being connected to
 * @property-read string $host hostname or ip address of database server
 * @property-read string $port network port to connect on
 * @property-read string $user user name used for authentication
 * @property-read string $pass password used for authentication
 * @property-read string $db   name of the database schema to use
 * @property-read array  $options array of database specific options
 */
class DSN {

    const TYPE_MYSQL  = 'mysql';
    const TYPE_PGSQL  = 'pgsql';
    const TYPE_SQLITE = 'sqlite';
    const TYPE_SQLSRV = 'sqlsrv';

    protected $config;

    public static function fromString( string $config ) {

        // parse the string into some components
        $parts = parse_url(urldecode($config));

        // no point continuing if it went wrong
        if( empty($parts) || empty($parts['scheme']) ) {
            throw new ConfigurationException("Invalid DSN string: {$config}");
        }

        // use a closure to save loads of duplicate logic
        $select = function( $k, array $arr ) {
            return $arr[$k] ?? null;
        };

        // construct a well-formed array from the available components
        $config = array(
            'type'    => $select('scheme', $parts),
            'host'    => $select('host', $parts),
            'port'    => $select('port', $parts),
            'user'    => $select('user', $parts),
            'pass'    => $select('pass', $parts),
            'db'      => trim((string) $select('path', $parts), '/'),
            'options' => [],
        );

        if( isset($parts['query']) ) {
            parse_str($parts['query'], $config['options']);
        }

        return new static($config);

    }

    public static function fromJSON( $config ) {

        $config = json_decode($config, true);

        if( empty($config) ) {
            throw new ConfigurationException('Invalid JSON configuration');
        }

        return new static($config);

    }

    /**
     * Create a DSN from an array of parameters.
     * scheme - type of database (mysql, pgsql, sqlite) required
     * host - hostname of database server
     * port - network port to connect on
     * user - user to connect as
     * pass - user's password
     * db - name of the database schema to connect to
     * options - an array of database specific options
     */
    public function __construct( array $config ) {

        if( empty($config['type']) ) {
            throw new ConfigurationException('No database type specified');
        }

        $config = $config + array(
            'host'    => 'localhost',
            'port'    => null,
            'user'    => null,
            'pass'    => null,
            'db'      => null,
            'options' => [],
        );

        $this->configure($config);

    }

    public function isMySQL() {
        return $this->config['type'] == static::TYPE_MYSQL;
    }

    public function isPgSQL() {
        return $this->config['type'] == static::TYPE_PGSQL;
    }

    public function isSQLite() {
        return $this->config['type'] == static::TYPE_SQLITE;
    }

    /**
     * Determine if this DSN is setup for SQL Server or not.
     *
     * @return bool
     */
    public function isSQLSrv(): bool {
        return $this->confgi['type'] == static::TYPE_SQLSRV;
    }

    /**
     * Dynamic property access.
     * @return mixed
     */
    public function __get( string $key ) {
        return $this->config[$key] ?? null;
    }

    /**
     * Dynamic property access.
     */
    public function __isset( string $key ): bool {
        return isset($this->config[$key]);
    }

    public function getOption( $name, $default = '' ) {
        return $this->options[$name] ?? $default;
    }

    /**
     * Convert the DSN into a URI-type string.
     */
    public function toString(): string {

        $scheme = $this->config['type']. '://';

        $user = $this->optionalPart('user', 'pass');

        if( $user ) {
            $user .= '@';
        }

        $host = $this->optionalPart('host', 'port');

        $path = '/'. $this->config['db'];

        $options = '';
        if( $this->config['options'] ) {
            $options = '?';
            foreach( $this->config['options'] as $k => $v ) {
                $options .= "$k=>$v&";
            }
            $options = substr($options, 0, -1);
        }

        return "{$scheme}{$user}{$host}{$path}{$options}";

    }

    /**
     * Return a connection string for use by PDO.
     */
    public function getConnectionString(): string {
        return $this->config['pdo'];
    }

    /**
     * Ensure the dsn configuration is valid.
     */
    protected function configure( array $config ): void {

        if( empty($config['db']) ) {
            throw new ConfigurationException('No database schema specified');
        }

        $methods = [
            static::TYPE_MYSQL  => 'configureMySQL',
            static::TYPE_PGSQL  => 'configurePgSQL',
            static::TYPE_SQLITE => 'configureSQLite',
            static::TYPE_SQLSRV => 'configureSQLSrv',
        ];

        if( empty($methods[$config['type']]) ) {
            throw new ConfigurationException("Invalid database type: {$config['type']}");
        }

        $method = $methods[$config['type']];
        $this->config = $this->$method($config);

    }

    /**
     * Configure a MySQL DSN.
     */
    protected function configureMySQL( array $config ): array {

        if( empty($config['port']) ) {
            $config['port'] = 3306;
        }

        // construct a MySQL PDO connection string
        $config['pdo'] = sprintf(
            "mysql:host=%s;port=%s;dbname=%s",
            $config['host'],
            $config['port'],
            $config['db']
        );

        return $config;

    }

    /**
     * Configure a PostgreSQL DSN.
     */
    protected function configurePgSQL( array $config ): array {

        if( empty($config['port']) ) {
            $config['port'] = 5432;
        }

        // construct a PgSQL PDO connection string
        $config['pdo'] = sprintf(
            "pgsql:host=%s;port=%s;dbname=%s",
            $config['host'],
            $config['port'],
            $config['db']
        );

        return $config;

    }

    /**
     * Configure a SQLite DSN.
     */
    protected function configureSQLite( array $config ): array {

        // these should always be null as they're invalid for SQLite connections
        $config['host'] = 'localhost';
        $config['port'] = null;
        $config['user'] = null;
        $config['pass'] = null;

        // construct a SQLite PDO connection string
        $config['pdo'] = sprintf(
            'sqlite::%s',
            $config['db']
        );

        return $config;

    }

    /**
     * Configure a Microsoft SQL Server DSN.
     *
     * @param array $config
     * @return array
     */
    protected function configureSQLSrv( array $config ): array {

        if (empty($config['port'])) {
            $config['port'] = 1433;
        }

        $config['pdo'] = sprintf('sqlsrv:Server=%s;Database=%s', $config['host'], $config['db']);

        return $config;

    }

    protected function optionalPart( $a, $b, $delimiter = ':' ): string {

        $str = '';

        if( $this->config[$a] ) {
            $str .= $this->config[$a];
            if( $this->config[$b] ) {
                $str .= $delimiter. $this->config[$b];
            }
        }

        return $str;

    }

}
