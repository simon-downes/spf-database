<?php declare(strict_types=1);
/*
 * This file is part of the spf-database package which is distributed under the MIT License.
 * See LICENSE.md or go to https://github.com/simon-downes/spf-database for full license details.
 */
namespace spf\database\exceptions;

use Exception;

/**
 * Thrown if a database connection could not be established.
 */
class ConnectionException extends DatabaseException {

    public function __construct( string $message = 'An error occurred attempting to connect to the database', $code = 0, Exception $previous = null ) {
        parent::__construct($message, $code, $previous);
    }

}
