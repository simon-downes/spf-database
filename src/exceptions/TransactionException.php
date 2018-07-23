<?php declare(strict_types=1);
/* 
 * This file is part of the spf-database package which is distributed under the MIT License.
 * See LICENSE.md or go to https://github.com/simon-downes/spf-database for full license details.
 */
namespace spf\database\exceptions;

/**
 * Thrown if an error occurs during a transaction.
 */
class TransactionException extends DatabaseException {}
