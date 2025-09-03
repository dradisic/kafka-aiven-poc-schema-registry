<?php

declare(strict_types=1);

namespace App\Schema\Resolver;

use App\Schema\Exception\SchemaNotFoundException;
use App\Schema\SchemaStore;
use FlixTech\AvroSerializer\Objects\RecordSerializer;

class FilesystemSchemaResolver
{
    private array $schemaCache = [];

    public function __construct(
        private readonly SchemaStore $schemaStore
    ) {
    }

    public function resolveRecordSchema(RecordSerializer $serializer): \AvroSchema
    {
        $schemaName = $this->extractSchemaName($serializer);

        if (isset($this->schemaCache[$schemaName])) {
            return $this->schemaCache[$schemaName];
        }

        try {
            $schemaArray = $this->schemaStore->loadSchema($schemaName);
            $schemaJson = json_encode($schemaArray);
            if (false === $schemaJson) {
                throw new \RuntimeException("Cannot encode schema to JSON for: {$schemaName}");
            }
            $schema = \AvroSchema::parse($schemaJson);

            $this->schemaCache[$schemaName] = $schema;

            return $schema;
        } catch (SchemaNotFoundException $e) {
            throw new \RuntimeException("Cannot resolve schema for: {$schemaName}", 0, $e);
        }
    }

    public function resolveWriterSchema(RecordSerializer $serializer): \AvroSchema
    {
        return $this->resolveRecordSchema($serializer);
    }

    public function resolveReaderSchema(string $messageType, ?int $version = null): \AvroSchema
    {
        $cacheKey = "{$messageType}:{$version}";

        if (isset($this->schemaCache[$cacheKey])) {
            return $this->schemaCache[$cacheKey];
        }

        try {
            $schemaArray = $this->schemaStore->loadSchema($messageType, $version);
            $schemaJson = json_encode($schemaArray);
            if (false === $schemaJson) {
                throw new \RuntimeException("Cannot encode schema to JSON for: {$messageType} (version: {$version})");
            }
            $schema = \AvroSchema::parse($schemaJson);

            $this->schemaCache[$cacheKey] = $schema;

            return $schema;
        } catch (SchemaNotFoundException $e) {
            throw new \RuntimeException("Cannot resolve schema for: {$messageType} (version: {$version})", 0, $e);
        }
    }

    public function getSchemaForMessageType(string $messageType, ?int $version = null): array
    {
        return $this->schemaStore->loadSchema($messageType, $version);
    }

    private function extractSchemaName(RecordSerializer $serializer): string
    {
        // Extract schema name from RecordSerializer
        // This may need adjustment based on the specific RecordSerializer implementation
        $reflectionClass = new \ReflectionClass($serializer);

        // Try to get schema name from serializer properties
        if ($reflectionClass->hasProperty('schemaName')) {
            $property = $reflectionClass->getProperty('schemaName');
            $property->setAccessible(true);
            $value = $property->getValue($serializer);
            if (!is_string($value)) {
                throw new \RuntimeException('Schema name property must be string: '.$reflectionClass->getName());
            }

            return $value;
        }

        // Try to extract from class name
        $className = $reflectionClass->getShortName();
        if (str_ends_with($className, 'Serializer')) {
            return strtolower(substr($className, 0, -10)); // Remove 'Serializer' suffix
        }

        throw new \RuntimeException('Cannot extract schema name from RecordSerializer: '.$reflectionClass->getName());
    }

    public function clearCache(): void
    {
        $this->schemaCache = [];
    }
}
