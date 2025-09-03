<?php

declare(strict_types=1);

namespace App\Schema;

use App\Schema\Exception\CompatibilityException;
use App\Schema\Exception\SchemaNotFoundException;

class SchemaMetadataManager
{
    private const COMPATIBILITY_TYPES = [
        'NONE', 'BACKWARD', 'BACKWARD_TRANSITIVE',
        'FORWARD', 'FORWARD_TRANSITIVE', 'FULL', 'FULL_TRANSITIVE',
    ];

    public function __construct(
        private readonly SchemaStore $schemaStore
    ) {
    }

    public function getMetadata(string $messageType): SchemaMetadata
    {
        return $this->schemaStore->loadMetadata($messageType);
    }

    public function updateCompatibility(string $messageType, string $compatibility): void
    {
        if (!in_array($compatibility, self::COMPATIBILITY_TYPES, true)) {
            throw new \InvalidArgumentException("Invalid compatibility type: {$compatibility}");
        }

        try {
            $metadata = $this->schemaStore->loadMetadata($messageType);
            $updatedMetadata = new SchemaMetadata(
                $metadata->name,
                $metadata->description,
                $compatibility,
                $metadata->createdAt,
                new \DateTimeImmutable(),
                $metadata->version,
                $metadata->tags
            );

            $this->schemaStore->saveMetadata($messageType, $updatedMetadata);
        } catch (SchemaNotFoundException $e) {
            throw new CompatibilityException($messageType, 'Schema metadata not found');
        }
    }

    public function validateCompatibility(array $newSchema, string $messageType): bool
    {
        try {
            $metadata = $this->getMetadata($messageType);
            $compatibility = $metadata->compatibility;

            if ('NONE' === $compatibility) {
                return true; // No compatibility requirements
            }

            $currentVersions = $this->schemaStore->getSchemaVersions($messageType);
            if (empty($currentVersions)) {
                return true; // First schema version
            }

            // Get the latest version for comparison
            $latestVersion = max($currentVersions);
            $currentSchema = $this->schemaStore->loadSchema($messageType, $latestVersion);

            return $this->checkSchemaCompatibility($currentSchema, $newSchema, $compatibility);
        } catch (SchemaNotFoundException) {
            return true; // If no current schema exists, new schema is compatible
        }
    }

    public function createMetadataForNewSchema(string $messageType, string $description = '', array $tags = []): SchemaMetadata
    {
        $now = new \DateTimeImmutable();

        return new SchemaMetadata(
            name: $messageType,
            description: $description,
            compatibility: 'BACKWARD',
            createdAt: $now,
            updatedAt: $now,
            version: 1,
            tags: $tags
        );
    }

    public function incrementVersion(string $messageType): SchemaMetadata
    {
        try {
            $metadata = $this->getMetadata($messageType);

            return $metadata->withVersion($metadata->version + 1);
        } catch (SchemaNotFoundException) {
            return $this->createMetadataForNewSchema($messageType);
        }
    }

    public function getCompatibilityTypes(): array
    {
        return self::COMPATIBILITY_TYPES;
    }

    private function checkSchemaCompatibility(array $currentSchema, array $newSchema, string $compatibility): bool
    {
        switch ($compatibility) {
            case 'BACKWARD':
            case 'BACKWARD_TRANSITIVE':
                return $this->isBackwardCompatible($currentSchema, $newSchema);

            case 'FORWARD':
            case 'FORWARD_TRANSITIVE':
                return $this->isForwardCompatible($currentSchema, $newSchema);

            case 'FULL':
            case 'FULL_TRANSITIVE':
                return $this->isBackwardCompatible($currentSchema, $newSchema)
                    && $this->isForwardCompatible($currentSchema, $newSchema);

            default:
                return true;
        }
    }

    private function isBackwardCompatible(array $currentSchema, array $newSchema): bool
    {
        // Simplified backward compatibility check
        // In a full implementation, this would use proper Avro compatibility rules

        if ($currentSchema['type'] !== $newSchema['type']) {
            return false;
        }

        if ('record' === $currentSchema['type']) {
            return $this->isRecordBackwardCompatible($currentSchema, $newSchema);
        }

        return true;
    }

    private function isForwardCompatible(array $currentSchema, array $newSchema): bool
    {
        // Simplified forward compatibility check
        return $this->isBackwardCompatible($newSchema, $currentSchema);
    }

    private function isRecordBackwardCompatible(array $currentSchema, array $newSchema): bool
    {
        $currentFields = $this->indexFieldsByName($currentSchema['fields'] ?? []);
        $newFields = $this->indexFieldsByName($newSchema['fields'] ?? []);

        // Check that all current fields exist in new schema or have defaults
        foreach ($currentFields as $fieldName => $currentField) {
            if (!isset($newFields[$fieldName])) {
                // Field was removed - only OK if it had a default value
                if (!isset($currentField['default'])) {
                    return false;
                }
            } else {
                // Field exists - check type compatibility
                $newField = $newFields[$fieldName];
                if (!$this->isFieldTypeCompatible($currentField['type'], $newField['type'])) {
                    return false;
                }
            }
        }

        // Check that new fields have default values
        foreach ($newFields as $fieldName => $newField) {
            if (!isset($currentFields[$fieldName]) && !isset($newField['default'])) {
                return false; // New field without default is not backward compatible
            }
        }

        return true;
    }

    private function indexFieldsByName(array $fields): array
    {
        $indexed = [];
        foreach ($fields as $field) {
            $indexed[$field['name']] = $field;
        }

        return $indexed;
    }

    private function isFieldTypeCompatible(mixed $currentType, mixed $newType): bool
    {
        // Simplified type compatibility check
        if ($currentType === $newType) {
            return true;
        }

        // Handle union types
        if (is_array($currentType) && is_array($newType)) {
            // Both are unions - simplified check
            return count(array_intersect($currentType, $newType)) > 0;
        }

        // Add more sophisticated type compatibility rules here
        return false;
    }
}
