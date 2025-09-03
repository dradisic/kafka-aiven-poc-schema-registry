<?php

declare(strict_types=1);

namespace App\Service;

use App\Schema\Exception\SchemaNotFoundException;
use App\Schema\Exception\SchemaValidationException;
use App\Schema\SchemaMetadataManager;
use App\Schema\SchemaStore;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class SchemaManager implements SchemaManagerInterface
{
    private array $schemaIdMap = [];
    private int $nextSchemaId = 1;

    public function __construct(
        private readonly SchemaStore $schemaStore,
        private readonly SchemaMetadataManager $metadataManager,
        private readonly LoggerInterface $logger = new NullLogger()
    ) {
        $this->initializeSchemaIdMap();
    }

    public function getSchema(string $messageType): array
    {
        try {
            $this->logger->debug("Loading schema for message type: {$messageType}");

            return $this->schemaStore->loadSchema($messageType);
        } catch (SchemaNotFoundException $e) {
            $this->logger->error("Schema not found for message type: {$messageType}", [
                'exception' => $e,
            ]);

            throw $e;
        }
    }

    public function getSchemaById(int $schemaId): array
    {
        $messageType = $this->getMessageTypeBySchemaId($schemaId);

        return $this->getSchema($messageType);
    }

    public function validateData(array $data, string $messageType): bool
    {
        try {
            $schema = $this->getSchema($messageType);

            return $this->performSchemaValidation($data, $schema);
        } catch (SchemaNotFoundException $e) {
            $this->logger->error("Cannot validate data - schema not found: {$messageType}");

            return false;
        }
    }

    public function getAllSchemas(): array
    {
        try {
            $this->logger->debug('Loading all schemas');

            return $this->schemaStore->getAllSchemas();
        } catch (\Exception $e) {
            $this->logger->error('Error loading all schemas', ['exception' => $e]);

            return [];
        }
    }

    public function registerSchema(string $messageType, array $schema): int
    {
        try {
            // Validate compatibility
            if (!$this->metadataManager->validateCompatibility($schema, $messageType)) {
                throw new SchemaValidationException('Schema is not compatible with existing versions');
            }

            // Determine version
            $versions = $this->schemaStore->getSchemaVersions($messageType);
            $nextVersion = empty($versions) ? 1 : max($versions) + 1;

            // Save schema
            $this->schemaStore->saveSchema($messageType, $schema, $nextVersion);

            // Update or create metadata
            try {
                $metadata = $this->metadataManager->getMetadata($messageType);
                $updatedMetadata = $metadata->withVersion($nextVersion);
            } catch (SchemaNotFoundException) {
                $updatedMetadata = $this->metadataManager->createMetadataForNewSchema($messageType);
            }

            $this->schemaStore->saveMetadata($messageType, $updatedMetadata);

            // Assign and return schema ID
            $schemaId = $this->assignSchemaId($messageType);

            $this->logger->info("Registered schema for {$messageType} version {$nextVersion} with ID {$schemaId}");

            return $schemaId;
        } catch (\Exception $e) {
            $this->logger->error("Error registering schema for {$messageType}", [
                'exception' => $e,
                'schema' => $schema,
            ]);

            throw new SchemaValidationException('Cannot register schema: '.$e->getMessage(), $e);
        }
    }

    public function getSchemaVersions(string $messageType): array
    {
        try {
            return $this->schemaStore->getSchemaVersions($messageType);
        } catch (SchemaNotFoundException $e) {
            $this->logger->warning("No versions found for message type: {$messageType}");

            return [];
        }
    }

    public function getLatestSchemaVersion(string $messageType): int
    {
        try {
            return $this->schemaStore->getLatestVersion($messageType);
        } catch (SchemaNotFoundException $e) {
            $this->logger->error("No schema versions found for message type: {$messageType}");

            throw $e;
        }
    }

    public function getSchemaByVersion(string $messageType, int $version): array
    {
        try {
            return $this->schemaStore->loadSchema($messageType, $version);
        } catch (SchemaNotFoundException $e) {
            $this->logger->error("Schema not found for {$messageType} version {$version}");

            throw $e;
        }
    }

    public function getSchemaMetadata(string $messageType): array
    {
        try {
            $metadata = $this->metadataManager->getMetadata($messageType);

            return $metadata->toArray();
        } catch (SchemaNotFoundException $e) {
            $this->logger->warning("Metadata not found for message type: {$messageType}");

            return [];
        }
    }

    private function initializeSchemaIdMap(): void
    {
        try {
            $allSchemas = $this->schemaStore->getAllSchemas();
            $schemaId = 1;

            foreach ($allSchemas as $messageType => $versions) {
                if (!isset($this->schemaIdMap[$messageType])) {
                    $this->schemaIdMap[$messageType] = $schemaId++;
                }
            }

            $this->nextSchemaId = $schemaId;
        } catch (\Exception $e) {
            $this->logger->warning('Could not initialize schema ID map', ['exception' => $e]);
            $this->nextSchemaId = 1;
        }
    }

    private function assignSchemaId(string $messageType): int
    {
        if (!isset($this->schemaIdMap[$messageType])) {
            $this->schemaIdMap[$messageType] = $this->nextSchemaId++;
        }

        return $this->schemaIdMap[$messageType];
    }

    private function getMessageTypeBySchemaId(int $schemaId): string
    {
        $messageType = array_search($schemaId, $this->schemaIdMap, true);

        if (false === $messageType || !is_string($messageType)) {
            throw new SchemaNotFoundException("No message type found for schema ID: {$schemaId}");
        }

        return $messageType;
    }

    private function performSchemaValidation(array $data, array $schema): bool
    {
        try {
            // Basic validation - check if required fields are present
            if ('record' === $schema['type'] && isset($schema['fields'])) {
                foreach ($schema['fields'] as $field) {
                    $fieldName = $field['name'];
                    $isRequired = !isset($field['default']);

                    if ($isRequired && !array_key_exists($fieldName, $data)) {
                        $this->logger->debug("Validation failed: missing required field {$fieldName}");

                        return false;
                    }
                }
            }

            // TODO: Implement more comprehensive Avro schema validation
            // This could use the flix-tech/avro-serde-php library for proper validation

            return true;
        } catch (\Exception $e) {
            $this->logger->error('Schema validation error', [
                'exception' => $e,
                'data' => $data,
                'schema' => $schema,
            ]);

            return false;
        }
    }
}
