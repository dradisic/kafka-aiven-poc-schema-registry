# Kafka Aiven PoC Schema Library

This repository is a lightweight catalogue of Avro schemas used in the Kafka Aiven proof-of-concept. It deliberately ships **only** versioned schema definitions and human-readable metadata so the assets can be shared across services regardless of runtime or language.

## Layout

- `schemas/registry.yaml` – high-level registry metadata (name, default compatibility, cache hints).
- `schemas/<schema-name>/vN.avsc` – schema definitions per message type and version.
- `schemas/<schema-name>/schema.meta.yaml` – optional descriptive metadata for teams and tooling.

## Consuming the schemas

1. Add this repository as a git submodule, Composer dependency, or copy the `schemas/` folder into your deployment pipeline.
2. Point your serializer/deserializer tooling to the `schemas/` directory.
3. Select the schema by folder name and, if needed, the version via the `vN.avsc` file suffix.

The repository no longer publishes PHP helper classes or Symfony tooling. Any runtime-specific integration should live in the consuming service.

## Contributing

- Keep schemas backward compatible unless there is a coordinated breaking change.
- Update `schema.meta.yaml` when adding context, owners, or tags.
- Reflect repository-wide metadata changes in `schemas/registry.yaml`.
- Validate new schemas with your preferred Avro tooling (e.g. `avro-tools` or the Confluent CLI) before opening a pull request.

## License

MIT License.
