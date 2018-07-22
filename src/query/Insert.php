<?php declare(strict_types=1);
/* 
 * This file is part of the spf-contracts package which is distributed under the MIT License.
 * See LICENSE.md or go to https://github.com/simon-downes/spf-contracts for full license details.
 */
namespace spf\database\query;

use spf\contracts\database\{DatabaseConnection, InsertQuery};

/**
 * Generic insert query.
 */
class Insert extends BaseQuery {

    protected $ignore;
    protected $into;
    protected $columns;
    protected $values;

    public function __construct( DatabaseConnection $db ) {
        parent::__construct($db);
        $this->ignore  = false;
        $this->into    = '';
        $this->columns = [];
        $this->values  = [];
    }

    public function ignore( bool $ignore = true ): InsertQuery {
        $this->ignore = $ignore;
        return $this;
    }

    public function into( string $table ): InsertQuery {
        $this->into = $this->quoteIdentifier($table);
        return $this;
    }

    public function cols( array $columns ): InsertQuery {
        $this->columns = [];
        foreach( $columns as $column ) {
            $this->columns[] = $this->quoteIdentifier($column);
        }
        return $this;
    }

    public function item( array $item ): InsertQuery {

        if( empty($this->columns) ) {
            $this->cols(array_keys($item));
        }

        $values = [];
        $index  = count($this->values) + 1;

        foreach( $item as $column => $value ) {
            $column = "{$column}_{$index}";
            $values[] = ":{$column}";
            $this->params[$column] = $value;
        }

        $this->values[] = $values;

        return $this;

    }

    public function execute( bool $return_insert_id = true ) {

        $result = $this->db->execute(
            $this->__toString(),
            $this->params
        );

        if( $return_insert_id ) {
            $result = $this->db->insertId();
        }

        return $result;

    }

    protected function compile(): array {

        $sql = [
            ($this->ignore ? 'INSERT IGNORE' : 'INSERT'). ' '. $this->into,
            '('. implode(', ', $this->columns). ')',
            'VALUES',
        ];

        foreach( $this->values as $list ) {
            $sql[] = sprintf('(%s),', implode(', ', $list));
        }

        // remove comma from last values item
        $tmp = substr(array_pop($sql), 0, -1);
        array_push($sql, $tmp);

        return $sql;

    }

}
