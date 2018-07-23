<?php declare(strict_types=1);
/* 
 * This file is part of the spf-database package which is distributed under the MIT License.
 * See LICENSE.md or go to https://github.com/simon-downes/spf-database for full license details.
 */
namespace spf\database\query;

use BadMethodCallException;

use spf\contracts\database\{DatabaseConnection, SelectQuery};

/**
 * Generic select query.
 */
class Select extends BaseQuery implements SelectQuery {

    protected $cols;
    protected $distinct;
    protected $from;
    protected $group;
    protected $having;


    public function __construct( DatabaseConnection $db ) {
        parent::__construct($db);
        $this->cols     = [];
        $this->distinct = false;
        $this->from     = '';
        $this->group    = [];
        $this->having   = [];
    }

    /**
     * Specify the columns to be included in the resultset.
     * Array format:
     * $columns = [
     *  'column',
     *  ['column', 'alias'],
     * ];
     *
     * @param  array|string  $columns
     */
    public function cols( $columns = ['*'] ): SelectQuery {

        // default to everything
        if( empty($columns) ) {
            $columns = ['*'];
        }
        // if we don't have an array of columns then they were specified as individual arguments
        elseif( !is_array($columns) ) {
            $columns = func_get_args();
        }

        $this->cols = $columns;

        return $this;

    }

    // use raw columns statement
    public function colsRaw( string $sql ): SelectQuery {
        $this->cols = $sql;
        return $this;
    }

    public function distinct( bool $distinct = true ): SelectQuery {
        $this->distinct = (bool) $distinct;
        return $this;
    }

    public function from( string $table ): SelectQuery {
        $this->from = $this->quoteIdentifier($table);
        return $this;
    }

    public function fromRaw( string $sql ): SelectQuery {
        $this->from = $sql;
        return $this;
    }

    public function groupBy( array $columns ): SelectQuery {

        foreach( $columns as $column ) {
            $this->group[] = $this->quoteIdentifier($column);
        }

        return $this;

    }

    public function having( string $having ): SelectQuery {
        $this->having = [$having];
        return $this;
    }

    public function __call( $method, $args ) {

        if( !in_array($method, ['getOne', 'getCol', 'getRow', 'getAssoc', 'getAll']) ) {
            throw new BadMethodCallException("Unknown Method: {$method}");
        }

        return $this->db->$method(
            $this->__toString(),
            $this->params
        );

    }

    public function compile(): array {

        $cols = $this->cols;

        if( is_array($cols) ) {
            $cols = $this->compileCols($cols);
        }

        return array_merge(
            [
                ($this->distinct ? 'SELECT DISTINCT' : 'SELECT'). ' '. $cols,
                'FROM '. $this->from,
            ],
            $this->compileJoins(),
            $this->compileWhere(),
            $this->compileGroupBy(),
            $this->having,
            $this->compileOrderBy(),
            $this->compileOffsetLimit()
        );

    }

    protected function compileCols( array $cols ): string {

        foreach( $cols as &$col ) {

            // if column is an array is should have two elements
            // the first being the column name, the second being the alias
            if( is_array($col) ) {
                list($column, $alias) = $col;
                $col = sprintf(
                    '%s AS %s',
                    $this->quoteIdentifier($column),
                    $this->db->quoteIdentifier($alias)
                );
            }
            else {
                $col = $this->quoteIdentifier($col);
            }

        }

        return implode(', ', $cols);

    }

    protected function compileGroupBy(): array {

        $sql = [];

        if( $this->group ) {
            $sql[] = 'GROUP BY '. implode(', ', $this->group);
        }

        return $sql;

    }

}
