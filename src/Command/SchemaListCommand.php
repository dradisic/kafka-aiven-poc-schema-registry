<?php

declare(strict_types=1);

namespace Dradisic\KafkaSchema\Command;

use Dradisic\KafkaSchema\Schema\SchemaMetadataManager;
use Dradisic\KafkaSchema\Schema\SchemaStore;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'schema:list',
    description: 'List all available schemas and their versions'
)]
class SchemaListCommand extends Command
{
    public function __construct(
        private readonly SchemaStore $schemaStore,
        private readonly SchemaMetadataManager $metadataManager
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('messageType', InputArgument::OPTIONAL, 'Show details for specific message type')
            ->setHelp('
List all available schemas with their versions and metadata.

Examples:
  <info>php bin/console schema:list</info>                    List all schemas
  <info>php bin/console schema:list message</info>           Show details for "message" schema
');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $messageType = $input->getArgument('messageType');

        if (is_string($messageType)) {
            return $this->showSchemaDetails($io, $messageType);
        }

        return $this->listAllSchemas($io);
    }

    private function listAllSchemas(SymfonyStyle $io): int
    {
        $io->title('Schema Registry Contents');

        try {
            $allSchemas = $this->schemaStore->getAllSchemas();

            if (empty($allSchemas)) {
                $io->info('No schemas found in the registry.');

                return Command::SUCCESS;
            }

            $rows = [];
            foreach ($allSchemas as $messageType => $versions) {
                $versionList = array_keys($versions);
                sort($versionList);
                $latestVersion = max($versionList);

                try {
                    $metadata = $this->metadataManager->getMetadata($messageType);
                    $compatibility = $metadata->compatibility;
                    $description = $metadata->description;
                } catch (\Exception) {
                    $compatibility = 'Unknown';
                    $description = 'No metadata';
                }

                $rows[] = [
                    $messageType,
                    implode(', ', $versionList),
                    $latestVersion,
                    $compatibility,
                    $description,
                ];
            }

            $io->table(
                ['Message Type', 'Versions', 'Latest', 'Compatibility', 'Description'],
                $rows
            );

            $io->info(sprintf('Total schemas: %d', count($allSchemas)));

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error('Failed to list schemas: '.$e->getMessage());

            return Command::FAILURE;
        }
    }

    private function showSchemaDetails(SymfonyStyle $io, string $messageType): int
    {
        $io->title("Schema Details: {$messageType}");

        try {
            // Show versions
            $versions = $this->schemaStore->getSchemaVersions($messageType);
            if (empty($versions)) {
                $io->error("Schema '{$messageType}' not found.");

                return Command::FAILURE;
            }

            // Show metadata
            try {
                $metadata = $this->metadataManager->getMetadata($messageType);
                $io->definitionList(
                    ['Name' => $metadata->name],
                    ['Description' => $metadata->description],
                    ['Compatibility' => $metadata->compatibility],
                    ['Current Version' => $metadata->version],
                    ['Created' => $metadata->createdAt->format('Y-m-d H:i:s')],
                    ['Updated' => $metadata->updatedAt->format('Y-m-d H:i:s')],
                    ['Tags' => implode(', ', $metadata->tags) ?: 'None']
                );
            } catch (\Exception $e) {
                $io->warning('Metadata not available: '.$e->getMessage());
            }

            // Show all versions
            $io->section('Available Versions');
            sort($versions);

            foreach ($versions as $version) {
                try {
                    $schema = $this->schemaStore->loadSchema($messageType, $version);
                    $fieldCount = isset($schema['fields']) ? count($schema['fields']) : 0;
                    $io->text("Version {$version}: {$fieldCount} fields");
                } catch (\Exception $e) {
                    $io->text("Version {$version}: Error loading - ".$e->getMessage());
                }
            }

            // Show latest schema structure
            $io->section('Latest Schema Structure');
            $latestVersion = max($versions);
            $schema = $this->schemaStore->loadSchema($messageType, $latestVersion);

            if (isset($schema['fields'])) {
                $fieldRows = [];
                foreach ($schema['fields'] as $field) {
                    $fieldRows[] = [
                        $field['name'],
                        $this->formatFieldType($field['type']),
                        isset($field['default']) ? json_encode($field['default']) : 'Required',
                    ];
                }

                $io->table(['Field', 'Type', 'Default'], $fieldRows);
            }

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error('Error showing schema details: '.$e->getMessage());

            return Command::FAILURE;
        }
    }

    private function formatFieldType(mixed $type): string
    {
        if (is_array($type)) {
            if (isset($type['type'])) {
                return $type['type'];
            }

            return 'union['.implode('|', $type).']';
        }

        return is_string($type) ? $type : 'unknown';
    }
}
