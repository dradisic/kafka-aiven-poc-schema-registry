<?php

declare(strict_types=1);

/**
 * Backwards Compatibility Layer for App Namespace
 *
 * This file provides class aliases to maintain backwards compatibility
 * when migrating from App\ namespace to Dradisic\KafkaSchema\ namespace.
 *
 * All existing code using App\ namespace will continue to work without changes.
 */

// Schema Core Classes
class_alias('Dradisic\\KafkaSchema\\Schema\\SchemaStore', 'App\\Schema\\SchemaStore');
class_alias('Dradisic\\KafkaSchema\\Schema\\SchemaMetadata', 'App\\Schema\\SchemaMetadata');
class_alias('Dradisic\\KafkaSchema\\Schema\\SchemaMetadataManager', 'App\\Schema\\SchemaMetadataManager');

// Schema Exceptions
class_alias('Dradisic\\KafkaSchema\\Schema\\Exception\\SchemaNotFoundException', 'App\\Schema\\Exception\\SchemaNotFoundException');
class_alias('Dradisic\\KafkaSchema\\Schema\\Exception\\SchemaValidationException', 'App\\Schema\\Exception\\SchemaValidationException');
class_alias('Dradisic\\KafkaSchema\\Schema\\Exception\\CompatibilityException', 'App\\Schema\\Exception\\CompatibilityException');

// Schema Resolver
class_alias('Dradisic\\KafkaSchema\\Schema\\Resolver\\FilesystemSchemaResolver', 'App\\Schema\\Resolver\\FilesystemSchemaResolver');

// Service Classes
class_alias('Dradisic\\KafkaSchema\\Service\\SchemaManager', 'App\\Service\\SchemaManager');
class_alias('Dradisic\\KafkaSchema\\Service\\SchemaManagerInterface', 'App\\Service\\SchemaManagerInterface');

// Command Classes
class_alias('Dradisic\\KafkaSchema\\Command\\SchemaMigrationCommand', 'App\\Command\\SchemaMigrationCommand');
class_alias('Dradisic\\KafkaSchema\\Command\\SchemaListCommand', 'App\\Command\\SchemaListCommand');
class_alias('Dradisic\\KafkaSchema\\Command\\SchemaValidateCommand', 'App\\Command\\SchemaValidateCommand');

// Note: Kernel class alias is excluded to prevent dependency issues in library context