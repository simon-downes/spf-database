<?php declare(strict_types=1);
/* 
 * This file is part of the spf-database package which is distributed under the MIT License.
 * See LICENSE.md or go to https://github.com/simon-downes/spf-database for full license details.
 */
namespace spf\database;

use Closure, PDO, PDOStatement, PDOException;

use spf\contracts\database\{DatabaseConnection, SelectQuery, InsertQuery, UpdateQuery, DeleteQuery};
use spf\contracts\profiler\{ProfilerAware, ProfilerAwareTrait};
use spf\contracts\support\{Dumpable, Dumper};

use spf\database\exceptions\{DatabaseException, ConnectionException, NotConnectedException, QueryException, TransactionException};

/**
 * A wrapper for PDO that provides some handy extra functions and streamlines everything else.
 */
abstract class BaseConnection implements DatabaseConnection, ProfilerAware, Dumpable {

    use ProfilerAwareTrait;

    /**
     * Connection details.
     * @var DSN
     */
    protected $dsn = null;

    /**
     * Underlying PDO object.
     * @var PDO
     */
    protected $pdo = null;

    /**
     * Prepared statement cache.
     * @var array
     */
    protected $statements = [];

    /**
     * Create a new database connection.
     */
    public function __construct( DSN $dsn ) {

        $this->dsn = $dsn;

        // check for PDO extension
        if( !extension_loaded('pdo') ) {
            throw new DatabaseException('The PDO extension is required but the extension is not loaded');
        }
        // check the PDO driver is available
        elseif( !in_array($this->dsn->type, PDO::getAvailableDrivers()) ) {
            throw new DatabaseException("The {$this->dsn->type} PDO driver is not currently installed");
        }

    }

    public function connect(): void {

        if( isset($this->pdo) ) {
            return;
        }

        try {

            $this->pdo = new PDO(
                $this->dsn->getConnectionString(),
                $this->dsn->user,
                $this->dsn->pass,
                $this->dsn->options
            );

            $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);           // always use exceptions

            $this->setCharacterSet(
                $this->dsn->getOption('charset', 'UTF8'),
                $this->dsn->getOption('collation')
            );

        }
        catch( PDOException $e ) {
            throw new ConnectionException($e->getMessage(), $e->getCode(), $e);
        }

    }

    public function disconnect(): void {
        $this->pdo = null;
    }

    public function isConnected(): bool {
        return isset($this->pdo);
    }

    public function select(): SelectQuery {
        return new query\Select($this);
    }

    public function insert(): InsertQuery {
        return new query\Insert($this);
    }

    public function update(): UpdateQuery {
        return new query\Update($this);
    }

    public function delete(): DeleteQuery {
        return new query\Delete($this);
    }

    public function prepare( $statement ): PDOStatement {

        if( is_string($statement) ) {

            $this->connect();

            $key = sha1($statement);

            if( empty($this->statements[$key]) ) {
                $this->statements[$key] = $this->pdo->prepare($statement);
            }

            $statement = $this->statements[$key];

        }

        return $statement;

    }

    public function query( $statement, array $params = [] ): PDOStatement {

        $this->connect();

        $this->profiler && $this->profiler->start('Query');

        try {

            $statement = $this->prepare($statement);

            $this->bindParams($statement, $params);

            $start = microtime(true);
            $statement->execute();
            $duration = microtime(true) - $start;
            
        }
        catch( PDOException $e ) {
            throw new QueryException($e->getMessage(), $e->getCode(), $e);
        }

        if( $this->profiler ) {
            $this->profiler->stop('Query');
            // remove all whitespace at start of lines
            $this->profiler->query(preg_replace("/^\s*/m", "", trim($statement->queryString)), $params, $duration);
        }

        return $statement;

    }

    public function execute( $statement, array $params = [] ): int {
        $statement = $this->query($statement, $params);
        return $statement->rowCount();
    }

    public function getAll( $statement, array $params = [] ): array {
        return $this->getResult(
            $statement,
            $params,
            function( PDOStatement $statement ) {
                $result = $statement->fetchAll();
                if( $result === false ) {
                    $result = [];
                }
                return $result;
            }
        );
    }

    public function getAssoc( $statement, array $params = [] ): array {
        return $this->getResult(
            $statement,
            $params,
            function( PDOStatement $statement ) {
                $result = [];
                while( $row = $statement->fetch() ) {
                    $key = array_shift($row);
                    $result[$key] = count($row) == 1 ? array_shift($row) : $row;
                }
                return $result;
            }
        );
    }

    public function getAssocMulti( $statement, array $params = [] ): array {
        return $this->getResult(
            $statement,
            $params,
            function( PDOStatement $statement ) {
                $result = [];
                while( $row = $statement->fetch() ) {
                    $k1 = array_shift($row);
                    $k2 = array_shift($row);
                    $v  = count($row) == 1 ? array_shift($row) : $row;
                    if( empty($result[$k1]) ) {
                        $result[$k1] = [];
                    }
                    $result[$k1][$k2] = $v;
                }
                return $result;
            }
        );
    }

    public function getRow( $statement, array $params = [] ): array {
        return $this->getResult(
            $statement,
            $params,
            function( PDOStatement $statement ) {
                $result = $statement->fetch();
                if( $result === false ) {
                    $result = [];
                }
                return $result;
            }
        );
    }

    public function getCol( $statement, array $params = [] ): array {
        return $this->getResult(
            $statement,
            $params,
            function( PDOStatement $statement ) {
                $result = [];
                while( $row = $statement->fetch() ) {
                    $result[] = array_shift($row);
                }
                return $result;
            }
        );
    }

    public function getOne( $statement, array $params = [] ) {
        return $this->getResult(
            $statement,
            $params,
            function( PDOStatement $statement ) {
                $result = $statement->fetchColumn();
                if( $result === false ) {
                    $result = null;
                }
                return $result;
            }
        );
    }

    /**
     * Execute a raw SQL string and return the number of affected rows.
     * Primarily used for DDL queries. Do not use this with:
     * - Anything (data/parameters/etc) that comes from userland
     * - Select queries - the answer will always be 0 as no rows are affected.
     * - Everyday queries - use query() or execute()
     */
    public function rawExec( string $sql ): int {

        $this->connect();

        try {
            return $this->pdo->exec($sql);
        }
        catch( PDOException $e ) {
            throw new QueryException($e->getMessage(), $e->getCode(), $e);
        }

    }

    public function begin(): bool {

        $this->connect();

        return $this->transactionMethod('beginTransaction');

    }

    public function commit(): bool {
        return $this->transactionMethod('commit');
    }

    public function rollback(): bool {
        return $this->transactionMethod('rollBack');
    }

    public function inTransaction(): bool {
        return $this->isConnected() ? $this->pdo->inTransaction() : false;
    }

    public function insertId( string $name = '' ): string {
        if( !$this->isConnected() ) {
            throw new NotConnectedException();
        }
        return $this->pdo->lastInsertId($name);
    }

    public function quote( $value, int $type = PDO::PARAM_STR ): string {
        $this->connect();
        return $this->pdo->quote($value, $type);
    }

    public function quoteIdentifier( string $name ): string {

        $name = trim($name);

        if( $name == '*' ) {
            return $name;
        }

        // ANSI-SQL (everything else) says to use double quotes to quote identifiers
        $char = '"';

        // MySQL uses backticks cos it's special
        if( $this->dsn->isMySQL() ) {
            $char = '`';
        }

        return $char. $name. $char;

    }

    public function dump( Dumper $dumper ): string {
        return $dumper->dump(
            $this->dsn->toString()
        );
    }

    /**
     * Bind named and positional parameters to a PDOStatement.
     */
    protected function bindParams( PDOStatement $statement, array $params ): void {

        foreach( $params as $name => $value ) {

            $type = PDO::PARAM_STR;

            if( is_int($value) ) {
                $type = PDO::PARAM_INT;
            }

            // handle positional (?) and named (:name) parameters
            $name = is_numeric($name) ? (int) $name + 1 : ":{$name}";

            $statement->bindValue($name, $value, $type);

        }

    }

    /**
     * Perform a select query and use a callback to extract a result.
     * @param  PDOStatement|string $statement   an existing PDOStatement object or a SQL string.
     * @param  array $params        an array of parameters to pass into the query.
     * @param  \Closure $callback   function to yield a result from the executed statement
     * @return array
     */
    protected function getResult( $statement, $params, Closure $callback ) {

        $statement = $this->query($statement, $params);

        return $callback($statement);

    }

    /**
     * Make sure the connection is using the correct character set
     * 
     * @param string $charset   the character set to use for the connection
     * @param string $collation the collation method to use for the connection
     * @return self
     */
    protected function setCharacterSet( string $charset, string $collation = '' ): DatabaseConnection {

        if( empty($charset) ) {
            throw new DatabaseException('No character set specified');
        }

        $sql = 'SET NAMES '. $this->pdo->quote($charset);

        if( $collation ) {
            $sql .= ' COLLATE '. $this->pdo->quote($collation);
        }

        $this->pdo->exec($sql);

        return $this;

    }

    protected function transactionMethod( $method ) {

        if( !$this->isConnected() ) {
            throw new NotConnectedException();
        }

        try {
            return $this->pdo->$method();
        }
        catch( PDOException $e ) {
            throw new TransactionException($e->getMessage(), $e->getCode(), $e);
        }

    }

}
