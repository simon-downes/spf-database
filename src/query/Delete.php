<?php declare(strict_types=1);
/* 
 * This file is part of the spf-contracts package which is distributed under the MIT License.
 * See LICENSE.md or go to https://github.com/simon-downes/spf-contracts for full license details.
 */
namespace spf\database\query;

use spf\contracts\database\{DatabaseConnection, InsertQuery};

/**
 * Generic delete query.
 */
class Delete extends BaseQuery implements DeleteQuery {

    protected $from;

    public function __construct( DatabaseConnection $db ) {
        parent::__construct($db);
        $this->from = '';
    }

    public function from( string $table ): DeleteQuery {
        $this->from = $this->quoteIdentifier($table);
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
                "DELETE FROM {$this->from}",
            ],
            $this->compileWhere(),
            $this->compileOrderBy(),
            $this->compileLimit()
        );

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
