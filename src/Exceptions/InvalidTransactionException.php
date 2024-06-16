<?php

namespace SKCheung\ArcadeDB\Exceptions;

use Exception;

class InvalidTransactionException extends Exception
{
    protected $message = 'Transaction expired, not found or not begun.';
}
