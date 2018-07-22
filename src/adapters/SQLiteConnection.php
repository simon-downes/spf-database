<?php declare(strict_types=1);
/* 
 * This file is part of the spf-contracts package which is distributed under the MIT License.
 * See LICENSE.md or go to https://github.com/simon-downes/spf-contracts for full license details.
 */
namespace spf\database\adapters;

use spf\database\{DSN, BaseConnection};
use spf\database\exceptions\ConfigurationException;

class SQLiteConnection extends BaseConnection {

    public function __construct( DSN $dsn ) {

        if( !$dsn->isSQLite() ) {
            throw new ConfigurationException(sprintf("\\%s expects a DSN of type '%s', '%s' given", __CLASS__, DSN::TYPE_SQLITE, $dsn->type));
        }

        parent::__construct($dsn);

    }

}
