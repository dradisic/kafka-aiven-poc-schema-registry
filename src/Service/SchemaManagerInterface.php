<?php

declare(strict_types=1);

namespace Dradisic\KafkaSchema\Service;

interface SchemaManagerInterface
{
    public function getSchema(string $messageType): array;

    public function getSchemaById(int $schemaId): array;

    public function validateData(array $data, string $messageType): bool;

    public function getAllSchemas(): array;

    public function registerSchema(string $messageType, array $schema): int;

    public function getSchemaVersions(string $messageType): array;

    public function getLatestSchemaVersion(string $messageType): int;
}
