# Extending the plugin architecture

This guide describes how to add a use case or an external integration without bypassing the plugin's
architectural boundaries. Apply the change independently to every supported branch and validate it against the
matching OMP checkout.

## Dependency direction

The plugin uses five main areas:

```text
Presentation -> Application -> Domain
                    ^
                    |
             Infrastructure
```

- `Domain` contains rules, identifiers, and operation results that do not depend on OMP, PKP, HTTP, or Thoth
  client classes.
- `Application` coordinates a workflow and owns ports under `Application/<Capability>/Port`.
- A port protects a real boundary needed by its capability, such as PKP persistence, a Thoth request, files,
  cache, notifications, or logging.
- `Infrastructure` implements those ports with PKP APIs, the Thoth client, storage, or an existing service.
- `Presentation` adapts hooks, listeners, handlers, forms, and API requests to application inputs and translates
  results or exceptions into responses and notifications.
- `Bootstrap/ThothCompositionRoot` is the central place for container bindings and object graph assembly.

Dependencies must point inward. Domain and Application code must not call `Application::get()`, `Repo::`,
`DAORegistry`, the global container, request objects, or concrete Thoth services. Do not add a facade or service
locator to make a dependency available.

## Extension workflow

Use this sequence for a new feature:

1. Define the observable behavior and the affected OMP and plugin versions.
2. Add or reuse domain identifiers, value objects, policies, and results only when they protect an invariant.
3. Define the smallest port needed by the use case in
   `classes/Application/<Capability>/Port`.
4. Add the use case under a feature-oriented directory in `classes/Application` and inject every dependency
   through its constructor.
5. Implement the port in `classes/Infrastructure`. Keep PKP entities and Thoth client objects inside this layer
   whenever possible.
6. Bind the port, adapter, and use case in `classes/Bootstrap/ThothCompositionRoot`.
7. Add a Presentation adapter and connect the real hook, listener, handler, form, or API entrypoint.
8. Add common tests for rules and orchestration, plus target-specific tests for adapters, wiring, and the public
   entrypoint.
9. Run the focused tests first, then the relevant plugin suite and static analysis on every supported branch.

Do not create an interface merely to mirror one concrete class. Introduce a port when the application needs to
cross a boundary or when multiple implementations are meaningful.

## Example: a read operation

`GetWorkStatus` is the smallest reference flow:

- `Application/Work/Port/WorkGateway` declares the remote capability using the domain `WorkId`.
- `Application/Work/GetWorkStatus` receives that port and coordinates the operation.
- `Infrastructure/Thoth/Work/ThothWorkGateway` translates the domain identifier to the remote integration.
- `Bootstrap/ThothCompositionRoot` binds `WorkGateway`, `GetWorkStatus`, and its controller.
- `Presentation/Api/GetWorkStatusController` adapts the HTTP-facing call.
- `tests/classes/Application/Work/GetWorkStatusTest` proves orchestration without bootstrapping OMP.
- Infrastructure, composition-root, and Presentation tests prove the target-specific integration.

Name an adapter for the technology and capability it integrates. Do not introduce compatibility adapters,
aliases, or dual resolution paths.

## Defining a port and use case

A port should use domain or application-owned values, not request payloads or mutable PKP entities. Prefer
specific methods and explicit results. Document collection shapes in PHPDoc when a portable PHP type cannot
express them.

A use case should:

- expose one public operation;
- validate its application-level preconditions before performing side effects;
- coordinate ports in an explicit order;
- return a domain result or throw an application exception with a stable meaning;
- avoid translating exceptions into HTTP responses or user notifications;
- avoid constructing repositories, clients, or adapters internally.

If an external API can fail, translate client exceptions in Infrastructure into the exceptions defined under
`Application/FailureReporting`. Presentation may then publish a sanitized notification while
`ExternalFailureReporter` records technical context without secrets or complete payloads.

## Implementing adapters

Adapters own framework and provider-specific details. They may:

- read or update OMP entities through the API available in that target;
- create a Thoth client for an explicit context;
- map PKP objects to request data and remote data to domain-owned values;
- translate nulls, provider errors, and ambiguous matches into the application's contract;
- perform cache, file, notification, or logging operations requested by a port.

Keep `contextId` explicit whenever configuration, repositories, URLs, or permissions depend on the press.
Only `thothWorkId` on the submission is a persistent local-to-remote link. Other Thoth identifiers must remain
inside the current snapshot, result, or operation. Resolve them from the remote snapshot by normalized semantic
identity, and report a conflict when more than one match remains.

Use `Infrastructure/Thoth/Client/ThothClientProvider` to create the remote boundary for an explicit context.
It reads credentials through `ThothConfigurationRepository`, validates a custom HTTPS endpoint before creating
the provider client, and returns `ThothRemoteGateway`. The gateway is the single place where provider query
failures are translated to sanitized application failures; adapters must supply the business operation name and
must not expose credentials or complete payloads in failure context. Configuration persistence belongs in
`Infrastructure/Pkp/Configuration`; on OMP 3.3, `PkpTokenCipher` preserves the existing `base64:` encrypted
`token` setting derived from `security.api_key_secret`.

On OMP 3.3, local publication and submission persistence is isolated in
`Infrastructure/Pkp/Publication/PkpPublicationReader` and
`Infrastructure/Pkp/Work/PkpSubmissionLinkRepository`. The adapters receive the core DAOs explicitly and use
`getById()`/`updateObject()`; the `thothWorkId` property must be present in the submission schema before writes.
Temporary-file adapters receive `TemporaryFileManager`, preserve the file-owner scope, and delegate metadata
validation to `TemporaryFileMetadataReader`. Cache invalidation receives `CacheManager` and flushes the historical
file-cache contexts and keys directly. Construction and schema-hook registration remain responsibilities of the
composition phase.

Do not hide a missing required entity with a default value. Use a guard clause and preserve a useful,
non-sensitive identifier in the error report. Treat an optional missing collection as empty only when that is
part of the confirmed contract.

## Binding and entrypoints

Register container bindings in `ThothCompositionRoot::register()`. Bind ports to adapters before binding the
use case that consumes them. Use a singleton only for stateless values or policies whose lifetime is intentionally
shared; context-sensitive clients and mutable operation state must not leak between requests.

Metadata synchronizers are a special case. Their ordered list is assembled explicitly by
`Bootstrap/MetadataSynchronizationFactory`. When adding a synchronizer:

1. implement `Application/Synchronization/Port/DomainSynchronizer`;
2. construct it with its gateways and mappers in the version-specific plugin bootstrap;
3. insert it at the required position in the factory result;
4. test ordering, warning aggregation, and interruption after a failure.

Presentation code may resolve the final use case or controller from the container. It must retain authorization,
CSRF protection, context scoping, input validation, and output escaping appropriate to the target OMP version.

## Version-specific implementation

### OMP 3.3

Use PHP 7.4, `.inc.php`, and PKP `import()` where required. Follow the namespace and import convention of the
existing file, and use DAO or legacy APIs confirmed in the 3.3 checkout. Do not use PHP 8 syntax or APIs.

### OMP 3.4

Use PHP 8.0-compatible syntax and namespaced `.php` classes. Use the Repo, hook, and container APIs confirmed in
the 3.4 checkout. Do not copy 3.5-only signatures or syntax.

### OMP 3.5

Use PHP 8.2-compatible syntax and PSR-4 namespaced `.php` classes. Use current PKP/Laravel APIs and modern
language features only when they strengthen the contract. Keep framework details at the boundary.

The common behavior, port meaning, fixtures, and result semantics should remain equivalent. File names, imports,
callback signatures, repository access, and bootstrap code may differ. Never use one target's test result as
proof that another target's adapter works.

## Testing strategy

Add the cheapest test that proves the behavior, then cover the integration boundary:

- Domain tests cover invariants and pure transformations with no PKP bootstrap.
- Application tests inject mocks or small fakes for ports and verify outputs, call order, warnings, and
  compensation.
- Infrastructure tests exercise mappings, null handling, provider error translation, and the actual PKP or Thoth
  API contract where practical.
- Composition-root tests prove that each port and use case resolves to the expected target-specific adapter.
- Presentation tests prove authorization, context, validation, and translation of results at the public
  entrypoint.
- An integrated test against the matching OMP checkout proves hooks, repositories, forms, routes, or persistence
  when those behaviors are part of the change.

Run the framework-independent core suite with the PHPUnit binary from the matching OMP checkout, but only the
plugin autoloader. PHPUnit 9 accepts one positional test path, so execute the two capabilities separately:

```sh
php /path/to/omp/lib/pkp/lib/vendor/bin/phpunit \
  --no-configuration \
  --bootstrap vendor/autoload.php \
  --no-coverage \
  --do-not-cache-result \
  tests/classes/Domain

php /path/to/omp/lib/pkp/lib/vendor/bin/phpunit \
  --no-configuration \
  --bootstrap vendor/autoload.php \
  --no-coverage \
  --do-not-cache-result \
  tests/classes/Application
```

Run PHP lint with the minimum PHP supported by the branch. Run the branch's PHPUnit command and
`vendor/bin/phpstan analyse --no-progress`. Apply the core PHP-CS-Fixer configuration where available and finish
with `git diff --check`. A bootstrap failure is a blocked test run, not a test failure or success.

## Review checklist

Before merging an extension, confirm that:

- Application and Domain contain no new PKP, HTTP, container, or concrete Thoth dependencies;
- every dependency is constructor-injected and bound in the composition root;
- context-sensitive state is scoped to the explicit `contextId`;
- no remote identifier other than the submission's `thothWorkId` is persisted;
- external errors are translated, sanitized for users, and logged without credentials or full payloads;
- ambiguous remote matches fail explicitly;
- common behavior and version-specific integration are both tested;
- lint, formatting, static analysis, focused tests, and the relevant suite were run on every affected branch.
