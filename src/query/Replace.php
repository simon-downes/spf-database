<?php
/*
 * This file is part of Yolk - Gamer Network's PHP Framework.
 *
 * Copyright (c) 2015 Gamer Network Ltd.
 *
 * Distributed under the MIT License, a copy of which is available in the
 * LICENSE file that was bundled with this package, or online at:
 * https://github.com/gamernetwork/yolk-database
 */

namespace yolk\database\query;

use yolk\database\exceptions\QueryException;

/**
 * Generic replace query.
 */
class Replace extends Insert {

    public function ignore( $ignore = true ) {
        throw new QueryException('IGNORE flag not valid for REPLACE queries.');
    }

    public function execute( $return_insert_id = false ) {
        return parent::execute(false);
    }

    protected function compile() {

        $sql = parent::compile();
        
        $sql[0] = preg_replace('/^INSERT( IGNORE)?/', 'REPLACE', $sql[0]);
        
        return $sql;

    }

}
