<?php

declare(strict_types=1);

namespace Dradisic\KafkaSchema\Schema;

class SchemaMetadata
{
    public function __construct(
        public readonly string $name,
        public readonly string $description,
        public readonly string $compatibility,
        public readonly \DateTimeImmutable $createdAt,
        public readonly \DateTimeImmutable $updatedAt,
        public readonly int $version,
        public readonly array $tags = []
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'],
            description: $data['description'],
            compatibility: $data['compatibility'],
            createdAt: new \DateTimeImmutable($data['created_at']),
            updatedAt: new \DateTimeImmutable($data['updated_at']),
            version: $data['version'],
            tags: $data['tags'] ?? []
        );
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'description' => $this->description,
            'compatibility' => $this->compatibility,
            'created_at' => $this->createdAt->format(\DateTimeInterface::ISO8601),
            'updated_at' => $this->updatedAt->format(\DateTimeInterface::ISO8601),
            'version' => $this->version,
            'tags' => $this->tags,
        ];
    }

    public function withUpdatedTimestamp(): self
    {
        return new self(
            $this->name,
            $this->description,
            $this->compatibility,
            $this->createdAt,
            new \DateTimeImmutable(),
            $this->version,
            $this->tags
        );
    }

    public function withVersion(int $version): self
    {
        return new self(
            $this->name,
            $this->description,
            $this->compatibility,
            $this->createdAt,
            new \DateTimeImmutable(),
            $version,
            $this->tags
        );
    }
}
