<?php

declare(strict_types=1);

namespace Dradisic\KafkaSchema\Command;

use Dradisic\KafkaSchema\Service\SchemaManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'schema:validate',
    description: 'Validate data against a schema'
)]
class SchemaValidateCommand extends Command
{
    public function __construct(
        private readonly SchemaManagerInterface $schemaManager
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('messageType', InputArgument::REQUIRED, 'Message type to validate against')
            ->addArgument('data', InputArgument::REQUIRED, 'JSON data to validate')
            ->addOption('file', 'f', InputOption::VALUE_NONE, 'Read data from file instead of argument')
            ->setHelp('
Validate JSON data against a schema.

Examples:
  <info>php bin/console schema:validate message \'{"id": "123", "timestamp": 1234567890, "payload": "test"}\'</info>
  <info>php bin/console schema:validate message data.json --file</info>
');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $messageType = $input->getArgument('messageType');
        $dataInput = $input->getArgument('data');
        $isFile = (bool) $input->getOption('file');
        
        if (!is_string($messageType) || !is_string($dataInput)) {
            $io->error('Invalid arguments provided');
            return Command::FAILURE;
        }

        try {
            // Load data
            if ($isFile) {
                if (!file_exists($dataInput)) {
                    $io->error("File not found: {$dataInput}");

                    return Command::FAILURE;
                }
                $jsonData = file_get_contents($dataInput);
                if (false === $jsonData) {
                    $io->error("Could not read file: {$dataInput}");
                    return Command::FAILURE;
                }
            } else {
                $jsonData = $dataInput;
            }

            // Parse JSON
            $data = json_decode($jsonData, true);
            if (JSON_ERROR_NONE !== json_last_error()) {
                $io->error('Invalid JSON: '.json_last_error_msg());

                return Command::FAILURE;
            }
            
            if (!is_array($data)) {
                $io->error('JSON data must be an array/object');
                return Command::FAILURE;
            }

            // Validate against schema
            $io->info("Validating data against schema: {$messageType}");

            $isValid = $this->schemaManager->validateData($data, $messageType);

            if ($isValid) {
                $io->success('✓ Data is valid according to the schema');

                return Command::SUCCESS;
            } else {
                $io->error('✗ Data validation failed');

                return Command::FAILURE;
            }
        } catch (\Exception $e) {
            $io->error('Validation error: '.$e->getMessage());

            return Command::FAILURE;
        }
    }
}
