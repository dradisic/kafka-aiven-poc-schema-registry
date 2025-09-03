<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Schema\Exception\SchemaNotFoundException;
use App\Schema\Exception\SchemaValidationException;
use App\Schema\SchemaStore;
use PHPUnit\Framework\TestCase;

class SchemaStoreIntegrationTest extends TestCase
{
    private SchemaStore $schemaStore;
    private string $tempDirectory;

    protected function setUp(): void
    {
        // Create a temporary directory for real filesystem tests
        $this->tempDirectory = sys_get_temp_dir() . '/schema_test_' . uniqid();
        mkdir($this->tempDirectory, 0755, true);
        
        // Create test schema structure
        $messageDir = $this->tempDirectory . '/message';
        mkdir($messageDir, 0755, true);
        
        $schema = [
            'type' => 'record',
            'name' => 'Message',
            'fields' => [
                ['name' => 'id', 'type' => 'string'],
                ['name' => 'content', 'type' => 'string'],
            ],
        ];
        
        file_put_contents($messageDir . '/v1.avsc', json_encode($schema, JSON_PRETTY_PRINT));
        
        $metadata = 'name: message
description: Test message schema
compatibility: BACKWARD
created_at: "2024-01-15T10:30:00Z"
updated_at: "2024-01-15T10:30:00Z"
version: 1
tags:
  - test';
        
        file_put_contents($messageDir . '/schema.meta.yaml', $metadata);

        $this->schemaStore = new SchemaStore($this->tempDirectory);
    }

    protected function tearDown(): void
    {
        // Clean up temp directory
        if (is_dir($this->tempDirectory)) {
            $this->removeDirectory($this->tempDirectory);
        }
    }

    private function removeDirectory(string $dir): void
    {
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . DIRECTORY_SEPARATOR . $file;
            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                unlink($path);
            }
        }
        rmdir($dir);
    }

    public function testLoadSchemaSuccess(): void
    {
        $schema = $this->schemaStore->loadSchema('message', 1);
        
        $this->assertEquals('record', $schema['type']);
        $this->assertEquals('Message', $schema['name']);
        $this->assertCount(2, $schema['fields']);
    }

    public function testGetAllSchemas(): void
    {
        $schemas = $this->schemaStore->getAllSchemas();

        $this->assertArrayHasKey('message', $schemas);
        $this->assertArrayHasKey(1, $schemas['message']);
    }

    public function testGetSchemaVersions(): void
    {
        $versions = $this->schemaStore->getSchemaVersions('message');

        $this->assertEquals([1], $versions);
    }

    public function testLoadMetadata(): void
    {
        $metadata = $this->schemaStore->loadMetadata('message');

        $this->assertEquals('message', $metadata->name);
        $this->assertEquals('Test message schema', $metadata->description);
        $this->assertEquals('BACKWARD', $metadata->compatibility);
        $this->assertEquals(1, $metadata->version);
        $this->assertEquals(['test'], $metadata->tags);
    }

    public function testSaveNewSchema(): void
    {
        $newSchema = [
            'type' => 'record',
            'name' => 'UserEvent',
            'fields' => [
                ['name' => 'user_id', 'type' => 'string'],
                ['name' => 'event_type', 'type' => 'string'],
            ],
        ];

        $this->schemaStore->saveSchema('user_event', $newSchema, 1);
        
        $loadedSchema = $this->schemaStore->loadSchema('user_event', 1);
        $this->assertEquals($newSchema, $loadedSchema);
        
        $versions = $this->schemaStore->getSchemaVersions('user_event');
        $this->assertEquals([1], $versions);
    }

    public function testGetLatestVersion(): void
    {
        $latestVersion = $this->schemaStore->getLatestVersion('message');
        $this->assertEquals(1, $latestVersion);
    }

    public function testLoadSchemaLatestVersion(): void
    {
        $schema = $this->schemaStore->loadSchema('message');
        $this->assertEquals('Message', $schema['name']);
    }
}