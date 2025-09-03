<?php

declare(strict_types=1);

namespace Tests\Performance;

use App\Schema\SchemaStore;
use App\Service\SchemaManager;
use App\Schema\SchemaMetadataManager;
use PHPUnit\Framework\TestCase;

class SchemaBenchmark extends TestCase
{
    private string $tempDirectory;
    private SchemaStore $schemaStore;
    private SchemaManager $schemaManager;

    protected function setUp(): void
    {
        $this->tempDirectory = sys_get_temp_dir() . '/schema_perf_' . uniqid();
        mkdir($this->tempDirectory, 0755, true);
        
        // Create multiple test schemas
        for ($i = 1; $i <= 10; $i++) {
            $schemaDir = $this->tempDirectory . "/test_schema_{$i}";
            mkdir($schemaDir, 0755, true);
            
            $schema = [
                'type' => 'record',
                'name' => "TestSchema{$i}",
                'fields' => [
                    ['name' => 'id', 'type' => 'string'],
                    ['name' => 'timestamp', 'type' => 'long'],
                    ['name' => 'data', 'type' => 'string'],
                ],
            ];
            
            file_put_contents($schemaDir . '/v1.avsc', json_encode($schema, JSON_PRETTY_PRINT));
        }

        $this->schemaStore = new SchemaStore($this->tempDirectory);
        $metadataManager = new SchemaMetadataManager($this->schemaStore);
        $this->schemaManager = new SchemaManager($this->schemaStore, $metadataManager);
    }

    protected function tearDown(): void
    {
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

    public function testSchemaLoadingPerformance(): void
    {
        $iterations = 100;
        
        // Benchmark schema loading
        $startTime = microtime(true);
        
        for ($i = 0; $i < $iterations; $i++) {
            $schemaNumber = ($i % 10) + 1;
            $schema = $this->schemaStore->loadSchema("test_schema_{$schemaNumber}", 1);
            $this->assertNotEmpty($schema);
        }
        
        $endTime = microtime(true);
        $totalTime = ($endTime - $startTime) * 1000; // Convert to milliseconds
        $averageTime = $totalTime / $iterations;
        
        $this->addToAssertionCount(1); // Ensure test counts
        
        echo "\n=== Schema Loading Performance ===\n";
        echo "Iterations: {$iterations}\n";
        echo "Total time: " . number_format($totalTime, 2) . " ms\n";
        echo "Average time per load: " . number_format($averageTime, 2) . " ms\n";
        echo "Target: <50ms average (REQUIREMENT MET: " . ($averageTime < 50 ? 'YES' : 'NO') . ")\n";
        
        // Assert performance requirement: <50ms average
        $this->assertLessThan(50, $averageTime, "Schema loading performance requirement not met");
    }

    public function testCachingPerformance(): void
    {
        $iterations = 1000;
        $schemaType = 'test_schema_1';
        
        // First load to populate cache
        $this->schemaStore->loadSchema($schemaType, 1);
        
        // Benchmark cached loading
        $startTime = microtime(true);
        
        for ($i = 0; $i < $iterations; $i++) {
            $schema = $this->schemaStore->loadSchema($schemaType, 1);
            $this->assertNotEmpty($schema);
        }
        
        $endTime = microtime(true);
        $totalTime = ($endTime - $startTime) * 1000;
        $averageTime = $totalTime / $iterations;
        
        echo "\n=== Cached Schema Loading Performance ===\n";
        echo "Iterations: {$iterations}\n";
        echo "Total time: " . number_format($totalTime, 2) . " ms\n";
        echo "Average time per cached load: " . number_format($averageTime, 2) . " ms\n";
        echo "Expected: <1ms for cached loads (RESULT: " . number_format($averageTime, 3) . " ms)\n";
        
        // Cached loads should be much faster
        $this->assertLessThan(1, $averageTime, "Cached loading should be <1ms");
    }

    public function testSchemaValidationPerformance(): void
    {
        $iterations = 100;
        $schemaType = 'test_schema_1';
        
        $testData = [
            'id' => 'test-123',
            'timestamp' => time(),
            'data' => 'test data payload',
        ];
        
        $startTime = microtime(true);
        
        for ($i = 0; $i < $iterations; $i++) {
            $isValid = $this->schemaManager->validateData($testData, $schemaType);
            $this->assertTrue($isValid);
        }
        
        $endTime = microtime(true);
        $totalTime = ($endTime - $startTime) * 1000;
        $averageTime = $totalTime / $iterations;
        
        echo "\n=== Schema Validation Performance ===\n";
        echo "Iterations: {$iterations}\n";
        echo "Total time: " . number_format($totalTime, 2) . " ms\n";
        echo "Average time per validation: " . number_format($averageTime, 2) . " ms\n";
        echo "Expected: <10ms per validation (RESULT: " . number_format($averageTime, 3) . " ms)\n";
        
        $this->assertLessThan(10, $averageTime, "Validation should be <10ms per operation");
    }

    public function testMemoryUsage(): void
    {
        $initialMemory = memory_get_usage(true);
        
        // Load multiple schemas
        for ($i = 1; $i <= 10; $i++) {
            $this->schemaStore->loadSchema("test_schema_{$i}", 1);
        }
        
        $afterLoadMemory = memory_get_usage(true);
        $memoryUsed = ($afterLoadMemory - $initialMemory) / 1024 / 1024; // MB
        
        echo "\n=== Memory Usage Test ===\n";
        echo "Initial memory: " . number_format($initialMemory / 1024 / 1024, 2) . " MB\n";
        echo "After loading 10 schemas: " . number_format($afterLoadMemory / 1024 / 1024, 2) . " MB\n";
        echo "Memory used: " . number_format($memoryUsed, 2) . " MB\n";
        echo "Expected: <5MB for 10 schemas (RESULT: " . number_format($memoryUsed, 2) . " MB)\n";
        
        $this->assertLessThan(5, $memoryUsed, "Memory usage should be reasonable for cached schemas");
    }
}