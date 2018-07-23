<?php declare(strict_types=1);
/* 
 * This file is part of the spf-database package which is distributed under the MIT License.
 * See LICENSE.md or go to https://github.com/simon-downes/spf-database for full license details.
 */
namespace spf\database\adapters;

use spf\database\{DSN, BaseConnection};
use spf\database\exceptions\ConfigurationException;

class PgSQLConnection extends BaseConnection {

    public function __construct( DSN $dsn ) {

        if( !$dsn->isPgSQL() ) {
            throw new ConfigurationException(sprintf("\\%s expects a DSN of type '%s', '%s' given", __CLASS__, DSN::TYPE_PGSQL, $dsn->type));
        }

        parent::__construct($dsn);

    }

}
