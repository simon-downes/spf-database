<?php declare(strict_types=1);
/* 
 * This file is part of the spf-contracts package which is distributed under the MIT License.
 * See LICENSE.md or go to https://github.com/simon-downes/spf-contracts for full license details.
 */
namespace spf\database\query;

use LogicException;

use spf\contracts\database\{DatabaseConnection, Query};

/**
 * Generic query class.
 */
abstract class BaseQuery implements Query {

    /**
     * Database connection the query is associated with.
     * @var DatabaseConnection
     */
    protected $db;

    /**
     * Array of join clauses.
     * @var array
     */
    protected $joins;

    /**
     * Array of where clauses.
     * @var array
     */
    protected $where;

    /**
     * Array of order by clauses.
     * @var array
     */
    protected $order;

    /**
     * Query offset.
     * @var integer
     */
    protected $offset;

    /**
     * Query limit.
     * @var integer|null
     */
    protected $limit;

    /**
     * Array of query parameters
     * @var array
     */
    protected $params;

    public function __construct( DatabaseConnection $db ) {
        $this->db       = $db;
        $this->joins    = [];
        $this->where    = [];
        $this->order    = [];
        $this->offset   = 0;
        $this->limit    = null;
        $this->params   = [];
    }

    public function __toString() {
        return implode("\n", $this->compile());
    }

    public function innerJoin( string $table, array $on ): Query {
        $this->joins[] = ['INNER', $table, $on];
        return $this;
    }

    public function leftJoin( string $table, array $on ): Query {
        $this->joins[] = ['LEFT', $table, $on];
        return $this;
    }

    public function joinRaw( string $sql, array $parameters = [] ): Query {
        $this->joins[] = $sql;
        $this->params = array_merge($this->params, $parameters);
        return $this;
    }

    public function where( string $column, $operator, $value = null ): Query {

        // shortcut for equals
        if( func_num_args() == 2 ) {
            $value    = $operator;
            $operator = '=';
        }

        $operator = trim(strtoupper($operator));

        // can't bind IN values as parameters so we escape them and embed them directly
        if( in_array($operator, ['IN', 'NOT IN']) && is_array($value) ) {
            $value = $this->makeInClause($value);
        }
        // do parameter binding
        else {
            $value = $this->bindParam(
                $this->getParameterName($column, $operator),
                $value
            );
        }

        $this->where[] = [$this->quoteIdentifier($column), $operator, $value];

        return $this;

    }

    public function whereArray( array $where ): Query {

        foreach( $where as $k => $v ) {
            $operator = is_array($v) ? 'IN' : '=';
            $this->where($k, $operator, $v);
        }

        return $this;

    }

    public function whereRaw( string $sql, array $parameters = [] ): Query {
        $this->where[] = $sql;
        $this->params = array_merge($this->params, $parameters);
        return $this;
    }

    public function orderBy( string $column, bool $ascending = true ): Query {
        $column = $this->quoteIdentifier($column);
        $this->order[$column] = (bool) $ascending ? 'ASC' : 'DESC';
        return $this;
    }

    public function offset( int $offset ): Query {
        $this->offset = max(0, (int) $offset);
        return $this;
    }

    public function limit( int $limit ): Query {
        $this->limit = max(1, (int) $limit);
        return $this;
    }

    public function getParameters(): array {
        return $this->params;
    }

    public function setParameters( array $params, $replace = false ): Query {

        if( $replace ) {
            $this->params = [];
        }

        $this->params = array_merge($this->params, $params);

        return $this;

    }

    /**
     * Generate a SQL string as an array.
     */
    abstract protected function compile(): array;

    protected function compileJoins(): array {

        $sql = [];

        foreach( $this->joins as $join ) {
            if( is_array($join) ) {
                list($type, $table, $on) = $join;
                $join = sprintf("%s JOIN %s\nON %s", $type, $this->quoteIdentifier($table), $this->compileOn($on));
            }
            $sql[] = $join;
        }

        return $sql;

    }

    protected function compileOn( array $on ): string {

        $sql = [];

        foreach( $on as $column => $value ) {
            // if it's not a number or a quoted sring it much be an identifier, so quote it
            if( !is_numeric($value) && !preg_match('/^\'.*\'$/', $value) ) {
                $value = $this->quoteIdentifier($value);
            }
            $sql[] = sprintf("%s = %s", $this->quoteIdentifier($column), $value);
        }

        return implode("\nAND ", $sql);

    }

    protected function compileWhere(): array {

        $sql = [];

        foreach( $this->where as $i => $clause ) {
            if( is_array($clause) ) {
                $clause = implode(' ', $clause);
            }
            $sql[] = ($i ? 'AND ' : 'WHERE '). $clause;
        }

        return $sql;

    }

    protected function compileOrderBy(): array {

        $sql = [];

        if( $this->order ) {
            $order = 'ORDER BY ';
            foreach( $this->order as $column => $dir ) {
                $order .= $column. ' '. $dir. ', ';
            }
            $sql[] = trim($order, ', ');
        }

        return $sql;

    }

    protected function compileOffsetLimit(): array {

        $sql = [];

        $limit  = $this->limit;
        $offset = $this->offset;

        if( $limit || $offset ) {

            if( !$limit ) {
                $limit = PHP_INT_MAX;
            }

            $sql[] = sprintf(
                "LIMIT %s OFFSET %s",
                $this->bindParam('_limit', $limit),
                $this->bindParam('_offset', $offset)
            );

        }

        return $sql;

    }

    protected function quoteIdentifier( $spec ): string {

        // don't quote things that are functions/expressions
        if( strpos($spec, '(') !== false ) {
            return $spec;
        }

        foreach( [' AS ', ' ', '.'] as $sep) {
            if( $pos = strripos($spec, $sep) ) {
                return
                    $this->quoteIdentifier(substr($spec, 0, $pos)).
                    $sep.
                    $this->db->quoteIdentifier(substr($spec, $pos + strlen($sep)));
            }
        }

        return $this->db->quoteIdentifier($spec);

    }

    /**
     * Join an array of values to form a string suitable for use in a SQL IN clause.
     * The numeric parameter determines whether values are escaped and quoted;
     * a null value (the default) will cause the function to auto-detect whether
     * values should be escaped and quoted.
     */
    protected function makeInClause( array $values, $numeric = null ): string {

        // if numeric flag wasn't specified then detected it
        // by checking all items in the array are numeric
        if( $numeric === null ) {
            $numeric = count(array_filter($values, 'is_numeric')) == count($values);
        }

        // not numeric so we need to escape all the values
        if( !$numeric ) {
            $values = array_map([$this->db, 'quote'], $values);
        }
            
        return sprintf('(%s)', implode(', ', $values));

    }

    protected function getParameterName( string $column, string $operator ): string {

        $suffixes = [
            '='    => 'eq',
            '!='   => 'neq',
            '<>'   => 'neq',
            '<'    => 'max',
            '<='   => 'max',
            '>'    => 'min',
            '>='   => 'min',
            'LIKE' => 'like',
            'NOT LIKE' => 'notlike',
        ];

        $name = $column;

        // strip the table identifier
        if( $pos = strpos($name, '.') ) {
            $name = substr($name, $pos + 1);
        }

        if( isset($suffixes[$operator]) ) {
            $name .= '_'. $suffixes[$operator];
        }

        return $name;

    }

    /**
     * Add a parameter and return the placeholder to be inserted into the query string.
     */
    protected function bindParam( string $name, $value ): string {

        if( isset($this->params[$name]) ) {
            throw new LogicException("Parameter: {$name} has already been defined");
        }

        $this->params[$name] = $value;

        return ":{$name}";

    }

}
