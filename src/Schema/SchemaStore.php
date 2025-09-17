<?php

declare(strict_types=1);

namespace Dradisic\KafkaSchema\Schema;

use Dradisic\KafkaSchema\Schema\Exception\SchemaNotFoundException;
use Dradisic\KafkaSchema\Schema\Exception\SchemaValidationException;
use Symfony\Component\Yaml\Yaml;

class SchemaStore
{
    private array $cache = [];
    private array $metadataCache = [];

    public function __construct(
        private readonly string $schemasDirectory = 'schemas',
        private readonly bool $cacheEnabled = true
    ) {
        if (!is_dir($this->schemasDirectory)) {
            throw new \InvalidArgumentException("Schemas directory '{$this->schemasDirectory}' does not exist");
        }
    }

    public function loadSchema(string $messageType, ?int $version = null): array
    {
        $cacheKey = "{$messageType}:{$version}";

        if ($this->cacheEnabled && isset($this->cache[$cacheKey])) {
            return $this->cache[$cacheKey];
        }

        $schemaPath = $this->getSchemaPath($messageType, $version);

        if (!file_exists($schemaPath)) {
            throw new SchemaNotFoundException($messageType, $version);
        }

        $schemaContent = file_get_contents($schemaPath);
        if (false === $schemaContent) {
            throw new SchemaValidationException("Cannot read schema file: {$schemaPath}");
        }

        $schema = json_decode($schemaContent, true);
        if (JSON_ERROR_NONE !== json_last_error()) {
            throw new SchemaValidationException("Invalid JSON in schema file {$schemaPath}: ".json_last_error_msg());
        }

        if (!is_array($schema)) {
            throw new SchemaValidationException("Schema must be an array in {$schemaPath}");
        }

        if (!$this->validateSchemaStructure($schema)) {
            throw new SchemaValidationException("Invalid Avro schema structure in {$schemaPath}");
        }

        if ($this->cacheEnabled) {
            $this->cache[$cacheKey] = $schema;
        }

        return $schema;
    }

    public function saveSchema(string $messageType, array $schema, int $version = 1): void
    {
        if (!$this->validateSchemaStructure($schema)) {
            throw new SchemaValidationException('Invalid Avro schema structure');
        }

        $schemaDir = $this->schemasDirectory.DIRECTORY_SEPARATOR.$messageType;
        if (!is_dir($schemaDir)) {
            if (!mkdir($schemaDir, 0755, true)) {
                throw new SchemaValidationException("Cannot create directory: {$schemaDir}");
            }
        }

        $schemaPath = $this->getSchemaPath($messageType, $version);
        $schemaJson = json_encode($schema, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        if (false === file_put_contents($schemaPath, $schemaJson)) {
            throw new SchemaValidationException("Cannot write schema file: {$schemaPath}");
        }

        // Clear cache for this schema
        $cacheKey = "{$messageType}:{$version}";
        unset($this->cache[$cacheKey]);
    }

    public function getAllSchemas(): array
    {
        $schemas = [];
        $directories = glob($this->schemasDirectory.DIRECTORY_SEPARATOR.'*', GLOB_ONLYDIR);
        if (false === $directories) {
            return [];
        }

        foreach ($directories as $directory) {
            $messageType = basename($directory);
            $schemaFiles = glob($directory.DIRECTORY_SEPARATOR.'v*.avsc');
            if (false === $schemaFiles) {
                continue;
            }

            foreach ($schemaFiles as $schemaFile) {
                if (preg_match('/v(\d+)\.avsc$/', basename($schemaFile), $matches)) {
                    $version = (int) $matches[1];

                    try {
                        $schemas[$messageType][$version] = $this->loadSchema($messageType, $version);
                    } catch (SchemaNotFoundException|SchemaValidationException) {
                        // Skip invalid schemas
                        continue;
                    }
                }
            }
        }

        return $schemas;
    }

    public function getSchemaVersions(string $messageType): array
    {
        $schemaDir = $this->schemasDirectory.DIRECTORY_SEPARATOR.$messageType;

        if (!is_dir($schemaDir)) {
            throw new SchemaNotFoundException($messageType);
        }

        $versions = [];
        $schemaFiles = glob($schemaDir.DIRECTORY_SEPARATOR.'v*.avsc');
        if (false === $schemaFiles) {
            return [];
        }

        foreach ($schemaFiles as $schemaFile) {
            if (preg_match('/v(\d+)\.avsc$/', basename($schemaFile), $matches)) {
                $versions[] = (int) $matches[1];
            }
        }

        sort($versions);

        return $versions;
    }

    public function validateSchemaFile(string $filePath): bool
    {
        if (!file_exists($filePath)) {
            return false;
        }

        $content = file_get_contents($filePath);
        if (false === $content) {
            return false;
        }

        $schema = json_decode($content, true);
        if (JSON_ERROR_NONE !== json_last_error() || !is_array($schema)) {
            return false;
        }

        return $this->validateSchemaStructure($schema);
    }

    public function loadMetadata(string $messageType): SchemaMetadata
    {
        if ($this->cacheEnabled && isset($this->metadataCache[$messageType])) {
            return $this->metadataCache[$messageType];
        }

        $metadataPath = $this->getMetadataPath($messageType);

        if (!file_exists($metadataPath)) {
            throw new SchemaNotFoundException($messageType);
        }

        try {
            $metadataArray = Yaml::parseFile($metadataPath);
            if (!is_array($metadataArray)) {
                throw new SchemaValidationException("Metadata file must contain array data: {$metadataPath}");
            }
            $metadata = SchemaMetadata::fromArray($metadataArray);

            if ($this->cacheEnabled) {
                $this->metadataCache[$messageType] = $metadata;
            }

            return $metadata;
        } catch (\Exception $e) {
            throw new SchemaValidationException("Invalid metadata file {$metadataPath}: ".$e->getMessage(), $e);
        }
    }

    public function saveMetadata(string $messageType, SchemaMetadata $metadata): void
    {
        $metadataPath = $this->getMetadataPath($messageType);
        $metadataArray = $metadata->toArray();

        try {
            $yamlContent = Yaml::dump($metadataArray, 4, 2);
            if (false === file_put_contents($metadataPath, $yamlContent)) {
                throw new SchemaValidationException("Cannot write metadata file: {$metadataPath}");
            }

            // Clear cache
            unset($this->metadataCache[$messageType]);
        } catch (\Exception $e) {
            throw new SchemaValidationException('Cannot save metadata: '.$e->getMessage(), $e);
        }
    }

    public function getLatestVersion(string $messageType): int
    {
        $versions = $this->getSchemaVersions($messageType);
        if (empty($versions)) {
            throw new SchemaNotFoundException($messageType);
        }

        return max($versions);
    }

    private function getSchemaPath(string $messageType, ?int $version = null): string
    {
        if (null === $version) {
            $version = $this->getLatestVersion($messageType);
        }

        return $this->schemasDirectory.DIRECTORY_SEPARATOR.$messageType.DIRECTORY_SEPARATOR."v{$version}.avsc";
    }

    private function getMetadataPath(string $messageType): string
    {
        return $this->schemasDirectory.DIRECTORY_SEPARATOR.$messageType.DIRECTORY_SEPARATOR.'schema.meta.yaml';
    }

    private function validateSchemaStructure(array $schema): bool
    {
        // Basic Avro schema validation
        if (!isset($schema['type'])) {
            return false;
        }

        if ('record' === $schema['type']) {
            return isset($schema['name']) && isset($schema['fields']) && is_array($schema['fields']);
        }

        return true; // Allow other simple types
    }

    public function clearCache(): void
    {
        $this->cache = [];
        $this->metadataCache = [];
    }
}
