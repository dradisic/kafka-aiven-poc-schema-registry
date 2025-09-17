<?php

declare(strict_types=1);

namespace Dradisic\KafkaSchema\Schema\Exception;

class CompatibilityException extends \RuntimeException
{
    public function __construct(string $messageType, string $reason)
    {
        parent::__construct("Schema compatibility violation for '{$messageType}': {$reason}");
    }
}
