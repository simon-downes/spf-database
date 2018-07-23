<?php declare(strict_types=1);
/* 
 * This file is part of the spf-database package which is distributed under the MIT License.
 * See LICENSE.md or go to https://github.com/simon-downes/spf-database for full license details.
 */
namespace spf\database\exceptions;

use Exception;

/**
 * Base database exception.
 */
class DatabaseException extends \Exception {

    /**
     * https://bugs.php.net/bug.php?id=51742
     * @var integer|string
     */
    protected $code;

    public function __construct( string $message = 'An unknown database error occured', int $code = 0, Exception $previous = null ) {
        parent::__construct($message, (int) $code, $previous);
        $this->code = $code;
    }

}

