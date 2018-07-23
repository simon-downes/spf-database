<?php declare(strict_types=1);
/* 
 * This file is part of the spf-contracts package which is distributed under the MIT License.
 * See LICENSE.md or go to https://github.com/simon-downes/spf-contracts for full license details.
 */
namespace spf\database\query;

use spf\contracts\database\{DatabaseConnection, UpdateQuery};

/**
 * Generic.
 */
class Update extends BaseQuery implements UpdateQuery {

    protected $table;
    protected $set;

    public function __construct( DatabaseConnection $db ) {
        parent::__construct($db);
        $this->table = '';
        $this->set   = [];
    }

    public function table( string $table ): UpdateQuery {
        $this->table = $this->quoteIdentifier($table);
        return $this;
    }

    public function set( array $data, bool $replace = false ): UpdateQuery {

        if( $replace ) {
            $this->set = [];
        }

        $this->set = array_merge($this->set, $data);

        return $this;

    }

    public function execute(): int {
        return $this->db->execute(
            $this->__toString(),
            $this->params
        );
    }

    protected function compile(): array {

        return array_merge(
            [
                "UPDATE {$this->table}",
            ],
            $this->compileSet(),
            $this->compileWhere(),
            $this->compileOrderBy(),
            $this->compileLimit()
        );

    }

    protected function compileSet(): array {

        $sql = [];
        $end = -1;

        foreach( $this->set as $column => $value ) {
            $this->bindParam($column, $value);
            $sql[] = "{$column} = :{$column},";
            $end++;
        }

        $sql[0]    = 'SET '. $sql[0];
        $sql[$end] = trim($sql[$end], ',');

        return $sql;

    }

    protected function compileLimit(): array {

        $sql = [];

        if( $this->limit ) {
            $sql[] = "LIMIT :limit";
            $this->params['limit']  = $this->limit;
        }

        return $sql;

    }

}
