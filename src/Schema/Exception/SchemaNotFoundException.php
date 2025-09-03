<?php

declare(strict_types=1);

namespace App\Schema\Exception;

class SchemaNotFoundException extends \RuntimeException
{
    public function __construct(string $messageType, ?int $version = null)
    {
        $versionInfo = null !== $version ? " (version {$version})" : '';
        parent::__construct("Schema not found for message type '{$messageType}'{$versionInfo}");
    }
}
