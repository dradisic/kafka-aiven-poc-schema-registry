<?php

declare(strict_types=1);

namespace Dradisic\KafkaSchema\Tests\Unit\Service;

use Dradisic\KafkaSchema\Schema\Exception\SchemaNotFoundException;
use Dradisic\KafkaSchema\Schema\SchemaMetadataManager;
use Dradisic\KafkaSchema\Schema\SchemaStore;
use Dradisic\KafkaSchema\Service\SchemaManager;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class SchemaManagerTest extends TestCase
{
    private SchemaManager $schemaManager;
    private SchemaStore|MockObject $schemaStore;
    private SchemaMetadataManager|MockObject $metadataManager;

    protected function setUp(): void
    {
        $this->schemaStore = $this->createMock(SchemaStore::class);
        $this->metadataManager = $this->createMock(SchemaMetadataManager::class);

        $this->schemaManager = new SchemaManager(
            $this->schemaStore,
            $this->metadataManager,
            new NullLogger()
        );
    }

    public function testGetSchema(): void
    {
        $expectedSchema = [
            'type' => 'record',
            'name' => 'Test',
            'fields' => [['name' => 'id', 'type' => 'string']],
        ];

        $this->schemaStore
            ->expects($this->once())
            ->method('loadSchema')
            ->with('test')
            ->willReturn($expectedSchema);

        $schema = $this->schemaManager->getSchema('test');
        $this->assertEquals($expectedSchema, $schema);
    }

    public function testGetSchemaNotFound(): void
    {
        $this->schemaStore
            ->method('loadSchema')
            ->willThrowException(new SchemaNotFoundException('test'));

        $this->expectException(SchemaNotFoundException::class);
        $this->schemaManager->getSchema('test');
    }

    public function testValidateDataValid(): void
    {
        $schema = [
            'type' => 'record',
            'name' => 'Test',
            'fields' => [
                ['name' => 'id', 'type' => 'string'],
                ['name' => 'optional', 'type' => 'string', 'default' => ''],
            ],
        ];

        $data = ['id' => '123', 'optional' => 'test'];

        $this->schemaStore
            ->method('loadSchema')
            ->willReturn($schema);

        $result = $this->schemaManager->validateData($data, 'test');
        $this->assertTrue($result);
    }

    public function testValidateDataMissingRequiredField(): void
    {
        $schema = [
            'type' => 'record',
            'name' => 'Test',
            'fields' => [
                ['name' => 'id', 'type' => 'string'], // Required field
                ['name' => 'optional', 'type' => 'string', 'default' => ''],
            ],
        ];

        $data = ['optional' => 'test']; // Missing 'id'

        $this->schemaStore
            ->method('loadSchema')
            ->willReturn($schema);

        $result = $this->schemaManager->validateData($data, 'test');
        $this->assertFalse($result);
    }

    public function testGetAllSchemas(): void
    {
        $expectedSchemas = [
            'message' => [1 => ['type' => 'record', 'name' => 'Message']],
            'event' => [1 => ['type' => 'record', 'name' => 'Event']],
        ];

        $this->schemaStore
            ->expects($this->once())
            ->method('getAllSchemas')
            ->willReturn($expectedSchemas);

        $schemas = $this->schemaManager->getAllSchemas();
        $this->assertEquals($expectedSchemas, $schemas);
    }

    public function testGetSchemaVersions(): void
    {
        $this->schemaStore
            ->expects($this->once())
            ->method('getSchemaVersions')
            ->with('test')
            ->willReturn([1, 2, 3]);

        $versions = $this->schemaManager->getSchemaVersions('test');
        $this->assertEquals([1, 2, 3], $versions);
    }

    public function testGetLatestSchemaVersion(): void
    {
        $this->schemaStore
            ->expects($this->once())
            ->method('getLatestVersion')
            ->with('test')
            ->willReturn(3);

        $version = $this->schemaManager->getLatestSchemaVersion('test');
        $this->assertEquals(3, $version);
    }

    public function testRegisterSchema(): void
    {
        $schema = [
            'type' => 'record',
            'name' => 'NewSchema',
            'fields' => [['name' => 'id', 'type' => 'string']],
        ];

        $this->metadataManager
            ->expects($this->once())
            ->method('validateCompatibility')
            ->with($schema, 'new_schema')
            ->willReturn(true);

        $this->schemaStore
            ->expects($this->once())
            ->method('getSchemaVersions')
            ->with('new_schema')
            ->willReturn([]);

        $this->schemaStore
            ->expects($this->once())
            ->method('saveSchema')
            ->with('new_schema', $schema, 1);

        $schemaId = $this->schemaManager->registerSchema('new_schema', $schema);
        $this->assertIsInt($schemaId);
        $this->assertGreaterThan(0, $schemaId);
    }

    public function testGetSchemaById(): void
    {
        $schema = [
            'type' => 'record',
            'name' => 'Test',
        ];

        // First register a schema to get an ID
        $this->metadataManager
            ->method('validateCompatibility')
            ->willReturn(true);

        $this->schemaStore
            ->method('getSchemaVersions')
            ->willReturn([]);

        $this->schemaStore
            ->method('saveSchema');

        $this->schemaStore
            ->method('saveMetadata');

        $schemaId = $this->schemaManager->registerSchema('test', $schema);

        // Now test getting by ID
        $this->schemaStore
            ->method('loadSchema')
            ->with('test')
            ->willReturn($schema);

        $retrievedSchema = $this->schemaManager->getSchemaById($schemaId);
        $this->assertEquals($schema, $retrievedSchema);
    }
}
