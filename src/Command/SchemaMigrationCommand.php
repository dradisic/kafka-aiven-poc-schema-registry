<?php

declare(strict_types=1);

namespace Dradisic\KafkaSchema\Command;

use Dradisic\KafkaSchema\Schema\SchemaMetadata;
use Dradisic\KafkaSchema\Schema\SchemaMetadataManager;
use Dradisic\KafkaSchema\Schema\SchemaStore;
use Dradisic\KafkaSchema\Service\SchemaManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'schema:migrate',
    description: 'Migrate hardcoded schemas to filesystem storage'
)]
class SchemaMigrationCommand extends Command
{
    public function __construct(
        private readonly SchemaStore $schemaStore,
        private readonly SchemaMetadataManager $metadataManager,
        private readonly SchemaManagerInterface $schemaManager
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('export-hardcoded', null, InputOption::VALUE_NONE, 'Export hardcoded schemas to filesystem')
            ->addOption('validate', null, InputOption::VALUE_NONE, 'Validate migrated schemas')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show what would be migrated without actually doing it')
            ->setHelp('
This command helps migrate from hardcoded schemas to filesystem-based schema storage.

Examples:
  <info>php bin/console schema:migrate --export-hardcoded</info>     Export hardcoded schemas
  <info>php bin/console schema:migrate --validate</info>             Validate existing schemas
  <info>php bin/console schema:migrate --dry-run</info>              Preview migration changes
');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if ($input->getOption('export-hardcoded')) {
            return $this->exportHardcodedSchemas($io, (bool) $input->getOption('dry-run'));
        }

        if ($input->getOption('validate')) {
            return $this->validateSchemas($io);
        }

        $io->info('No operation specified. Use --help to see available options.');

        return Command::SUCCESS;
    }

    private function exportHardcodedSchemas(SymfonyStyle $io, bool $dryRun): int
    {
        $io->title('Exporting Hardcoded Schemas to Filesystem');

        $hardcodedSchemas = $this->getHardcodedSchemas();

        if (empty($hardcodedSchemas)) {
            $io->success('No hardcoded schemas found to export.');

            return Command::SUCCESS;
        }

        $io->info(sprintf('Found %d hardcoded schemas to export:', count($hardcodedSchemas)));

        foreach ($hardcodedSchemas as $messageType => $schema) {
            $io->text("- {$messageType}");
        }

        if ($dryRun) {
            $io->note('Dry run mode - no files will be created.');

            return Command::SUCCESS;
        }

        $exported = 0;
        $errors = 0;

        foreach ($hardcodedSchemas as $messageType => $schema) {
            try {
                $io->text("Exporting {$messageType}...");

                // Save schema file
                $this->schemaStore->saveSchema($messageType, $schema, 1);

                // Create metadata
                $metadata = new SchemaMetadata(
                    name: $messageType,
                    description: 'Migrated from hardcoded schema',
                    compatibility: 'BACKWARD',
                    createdAt: new \DateTimeImmutable(),
                    updatedAt: new \DateTimeImmutable(),
                    version: 1,
                    tags: ['migrated']
                );

                $this->schemaStore->saveMetadata($messageType, $metadata);

                ++$exported;
                $io->text("✓ Exported {$messageType}");
            } catch (\Exception $e) {
                ++$errors;
                $io->error("Failed to export {$messageType}: ".$e->getMessage());
            }
        }

        if ($exported > 0) {
            $io->success("Successfully exported {$exported} schemas.");
        }

        if ($errors > 0) {
            $io->warning("{$errors} schemas failed to export.");

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    private function validateSchemas(SymfonyStyle $io): int
    {
        $io->title('Validating Schemas');

        try {
            $allSchemas = $this->schemaStore->getAllSchemas();

            if (empty($allSchemas)) {
                $io->warning('No schemas found to validate.');

                return Command::SUCCESS;
            }

            $totalSchemas = 0;
            $validSchemas = 0;
            $invalidSchemas = 0;

            foreach ($allSchemas as $messageType => $versions) {
                $io->section("Validating {$messageType}");

                foreach ($versions as $version => $schema) {
                    ++$totalSchemas;
                    $schemaFile = "schemas/{$messageType}/v{$version}.avsc";

                    if ($this->schemaStore->validateSchemaFile($schemaFile)) {
                        ++$validSchemas;
                        $io->text("✓ Version {$version} is valid");
                    } else {
                        ++$invalidSchemas;
                        $io->error("✗ Version {$version} is invalid");
                    }
                }

                // Validate metadata
                try {
                    $metadata = $this->metadataManager->getMetadata($messageType);
                    $io->text('✓ Metadata is valid');
                } catch (\Exception $e) {
                    ++$invalidSchemas;
                    $io->error('✗ Metadata is invalid: '.$e->getMessage());
                }
            }

            $io->success("Validation complete: {$validSchemas}/{$totalSchemas} schemas are valid");

            return $invalidSchemas > 0 ? Command::FAILURE : Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error('Validation failed: '.$e->getMessage());

            return Command::FAILURE;
        }
    }

    private function getHardcodedSchemas(): array
    {
        // These are the hardcoded schemas from the original implementation
        // In a real scenario, these would be extracted from the existing SchemaManager
        return [
            'message' => [
                'type' => 'record',
                'name' => 'Message',
                'namespace' => 'com.example.kafka',
                'fields' => [
                    ['name' => 'id', 'type' => 'string'],
                    ['name' => 'timestamp', 'type' => 'long'],
                    ['name' => 'payload', 'type' => 'string'],
                    ['name' => 'metadata', 'type' => ['null', ['type' => 'map', 'values' => 'string']], 'default' => null],
                ],
            ],
            'user_event' => [
                'type' => 'record',
                'name' => 'UserEvent',
                'namespace' => 'com.example.kafka',
                'fields' => [
                    ['name' => 'user_id', 'type' => 'string'],
                    ['name' => 'event_type', 'type' => 'string'],
                    ['name' => 'timestamp', 'type' => 'long'],
                    ['name' => 'properties', 'type' => ['null', ['type' => 'map', 'values' => 'string']], 'default' => null],
                ],
            ],
            'order_created' => [
                'type' => 'record',
                'name' => 'OrderCreated',
                'namespace' => 'com.example.kafka',
                'fields' => [
                    ['name' => 'order_id', 'type' => 'string'],
                    ['name' => 'customer_id', 'type' => 'string'],
                    ['name' => 'total_amount', 'type' => 'double'],
                    ['name' => 'currency', 'type' => 'string'],
                    ['name' => 'created_at', 'type' => 'long'],
                    ['name' => 'items', 'type' => ['type' => 'array', 'items' => [
                        'type' => 'record',
                        'name' => 'OrderItem',
                        'fields' => [
                            ['name' => 'product_id', 'type' => 'string'],
                            ['name' => 'quantity', 'type' => 'int'],
                            ['name' => 'price', 'type' => 'double'],
                        ],
                    ]]],
                ],
            ],
            'order_updated' => [
                'type' => 'record',
                'name' => 'OrderUpdated',
                'namespace' => 'com.example.kafka',
                'fields' => [
                    ['name' => 'order_id', 'type' => 'string'],
                    ['name' => 'customer_id', 'type' => 'string'],
                    ['name' => 'total_amount', 'type' => 'double'],
                    ['name' => 'currency', 'type' => 'string'],
                    ['name' => 'updated_at', 'type' => 'long'],
                    ['name' => 'status', 'type' => 'string'],
                    ['name' => 'changes', 'type' => ['type' => 'map', 'values' => 'string']],
                ],
            ],
        ];
    }
}
