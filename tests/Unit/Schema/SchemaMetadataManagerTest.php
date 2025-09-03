<?php

declare(strict_types=1);

namespace Tests\Unit\Schema;

use App\Schema\SchemaMetadata;
use App\Schema\SchemaMetadataManager;
use App\Schema\SchemaStore;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class SchemaMetadataManagerTest extends TestCase
{
    private SchemaMetadataManager $metadataManager;
    private SchemaStore|MockObject $schemaStore;

    protected function setUp(): void
    {
        $this->schemaStore = $this->createMock(SchemaStore::class);
        $this->metadataManager = new SchemaMetadataManager($this->schemaStore);
    }

    public function testGetMetadata(): void
    {
        $metadata = new SchemaMetadata(
            name: 'test',
            description: 'Test schema',
            compatibility: 'BACKWARD',
            createdAt: new \DateTimeImmutable(),
            updatedAt: new \DateTimeImmutable(),
            version: 1
        );

        $this->schemaStore
            ->expects($this->once())
            ->method('loadMetadata')
            ->with('test')
            ->willReturn($metadata);

        $result = $this->metadataManager->getMetadata('test');
        $this->assertEquals($metadata, $result);
    }

    public function testUpdateCompatibility(): void
    {
        $originalMetadata = new SchemaMetadata(
            name: 'test',
            description: 'Test schema',
            compatibility: 'BACKWARD',
            createdAt: new \DateTimeImmutable('2024-01-01'),
            updatedAt: new \DateTimeImmutable('2024-01-01'),
            version: 1
        );

        $this->schemaStore
            ->expects($this->once())
            ->method('loadMetadata')
            ->with('test')
            ->willReturn($originalMetadata);

        $this->schemaStore
            ->expects($this->once())
            ->method('saveMetadata')
            ->with('test', $this->callback(function (SchemaMetadata $metadata) {
                return 'FULL' === $metadata->compatibility
                       && 'test' === $metadata->name
                       && 1 === $metadata->version;
            }));

        $this->metadataManager->updateCompatibility('test', 'FULL');
    }

    public function testUpdateCompatibilityInvalidType(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->metadataManager->updateCompatibility('test', 'INVALID');
    }

    public function testValidateCompatibilityNone(): void
    {
        $metadata = new SchemaMetadata(
            name: 'test',
            description: 'Test',
            compatibility: 'NONE',
            createdAt: new \DateTimeImmutable(),
            updatedAt: new \DateTimeImmutable(),
            version: 1
        );

        $this->schemaStore
            ->method('loadMetadata')
            ->willReturn($metadata);

        $newSchema = ['type' => 'string'];
        $result = $this->metadataManager->validateCompatibility($newSchema, 'test');

        $this->assertTrue($result);
    }

    public function testValidateCompatibilityBackward(): void
    {
        $metadata = new SchemaMetadata(
            name: 'test',
            description: 'Test',
            compatibility: 'BACKWARD',
            createdAt: new \DateTimeImmutable(),
            updatedAt: new \DateTimeImmutable(),
            version: 1
        );

        $this->schemaStore
            ->method('loadMetadata')
            ->willReturn($metadata);

        $this->schemaStore
            ->method('getSchemaVersions')
            ->willReturn([1]);

        $currentSchema = [
            'type' => 'record',
            'name' => 'Test',
            'fields' => [
                ['name' => 'id', 'type' => 'string'],
                ['name' => 'optional', 'type' => 'string', 'default' => ''],
            ],
        ];

        $newSchema = [
            'type' => 'record',
            'name' => 'Test',
            'fields' => [
                ['name' => 'id', 'type' => 'string'],
                ['name' => 'optional', 'type' => 'string', 'default' => ''],
                ['name' => 'new_field', 'type' => 'string', 'default' => 'default'],
            ],
        ];

        $this->schemaStore
            ->method('loadSchema')
            ->willReturn($currentSchema);

        $result = $this->metadataManager->validateCompatibility($newSchema, 'test');
        $this->assertTrue($result);
    }

    public function testCreateMetadataForNewSchema(): void
    {
        $metadata = $this->metadataManager->createMetadataForNewSchema(
            'new_schema',
            'New test schema',
            ['test', 'new']
        );

        $this->assertEquals('new_schema', $metadata->name);
        $this->assertEquals('New test schema', $metadata->description);
        $this->assertEquals('BACKWARD', $metadata->compatibility);
        $this->assertEquals(1, $metadata->version);
        $this->assertEquals(['test', 'new'], $metadata->tags);
    }

    public function testIncrementVersion(): void
    {
        $existingMetadata = new SchemaMetadata(
            name: 'test',
            description: 'Test',
            compatibility: 'BACKWARD',
            createdAt: new \DateTimeImmutable('2024-01-01'),
            updatedAt: new \DateTimeImmutable('2024-01-01'),
            version: 1
        );

        $this->schemaStore
            ->method('loadMetadata')
            ->willReturn($existingMetadata);

        $newMetadata = $this->metadataManager->incrementVersion('test');

        $this->assertEquals(2, $newMetadata->version);
        $this->assertEquals('test', $newMetadata->name);
    }

    public function testGetCompatibilityTypes(): void
    {
        $types = $this->metadataManager->getCompatibilityTypes();

        $this->assertContains('BACKWARD', $types);
        $this->assertContains('FORWARD', $types);
        $this->assertContains('FULL', $types);
        $this->assertContains('NONE', $types);
    }
}
