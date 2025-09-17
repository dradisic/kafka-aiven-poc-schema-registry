<?php

declare(strict_types=1);

namespace Dradisic\KafkaSchema\Tests\Unit\Compatibility;

use PHPUnit\Framework\TestCase;

/**
 * Test backwards compatibility for old App\ namespace.
 *
 * This ensures that existing code using App\ namespace continues to work
 * after migration to Dradisic\KafkaSchema\ namespace.
 */
class BackwardsCompatibilityTest extends TestCase
{
    public function testSchemaStoreBackwardsCompatibility(): void
    {
        // Test that old namespace still works
        $this->assertTrue(class_exists('App\\Schema\\SchemaStore'));

        // Test that instances can be created
        $schemaStore = new \App\Schema\SchemaStore('schemas');
        $this->assertInstanceOf('App\\Schema\\SchemaStore', $schemaStore);

        // Test that it's actually the new class under the hood
        $this->assertInstanceOf('Dradisic\\KafkaSchema\\Schema\\SchemaStore', $schemaStore);
    }

    public function testSchemaMetadataBackwardsCompatibility(): void
    {
        $this->assertTrue(class_exists('App\\Schema\\SchemaMetadata'));

        $now = new \DateTimeImmutable();
        $metadata = new \App\Schema\SchemaMetadata(
            'test',
            'description',
            'BACKWARD',
            $now,
            $now,
            1,
            ['test']
        );

        $this->assertInstanceOf('App\\Schema\\SchemaMetadata', $metadata);
        $this->assertInstanceOf('Dradisic\\KafkaSchema\\Schema\\SchemaMetadata', $metadata);
    }

    public function testSchemaManagerBackwardsCompatibility(): void
    {
        $this->assertTrue(class_exists('App\\Service\\SchemaManager'));
        $this->assertTrue(interface_exists('App\\Service\\SchemaManagerInterface'));
    }

    public function testExceptionBackwardsCompatibility(): void
    {
        $this->assertTrue(class_exists('App\\Schema\\Exception\\SchemaNotFoundException'));
        $this->assertTrue(class_exists('App\\Schema\\Exception\\SchemaValidationException'));
        $this->assertTrue(class_exists('App\\Schema\\Exception\\CompatibilityException'));

        // Test exception creation with old namespace
        $exception = new \App\Schema\Exception\SchemaNotFoundException('test');
        $this->assertInstanceOf('App\\Schema\\Exception\\SchemaNotFoundException', $exception);
        $this->assertInstanceOf('Dradisic\\KafkaSchema\\Schema\\Exception\\SchemaNotFoundException', $exception);
    }

    public function testCommandBackwardsCompatibility(): void
    {
        $this->assertTrue(class_exists('App\\Command\\SchemaMigrationCommand'));
        $this->assertTrue(class_exists('App\\Command\\SchemaListCommand'));
        $this->assertTrue(class_exists('App\\Command\\SchemaValidateCommand'));
    }

    public function testResolverBackwardsCompatibility(): void
    {
        $this->assertTrue(class_exists('App\\Schema\\Resolver\\FilesystemSchemaResolver'));
    }

    /**
     * Test that both old and new namespaces are functionally equivalent.
     */
    public function testFunctionalEquivalence(): void
    {
        // Create instances using both namespaces
        $oldSchemaStore = new \App\Schema\SchemaStore('schemas');
        $newSchemaStore = new \Dradisic\KafkaSchema\Schema\SchemaStore('schemas');

        // They should be instances of the same underlying class
        $this->assertEquals(get_class($oldSchemaStore), get_class($newSchemaStore));

        // Both should have the same methods available
        $oldMethods = get_class_methods($oldSchemaStore);
        $newMethods = get_class_methods($newSchemaStore);

        $this->assertEquals($oldMethods, $newMethods);
    }
}