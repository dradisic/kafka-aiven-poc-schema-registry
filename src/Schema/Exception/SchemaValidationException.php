<?php

declare(strict_types=1);

namespace App\Schema\Exception;

class SchemaValidationException extends \RuntimeException
{
    public function __construct(string $message, ?\Throwable $previous = null)
    {
        parent::__construct("Schema validation failed: {$message}", 0, $previous);
    }
}
