# jardissupport/response

Response building for one request-response use case: response objects (`ContextResponse`, `DomainResponse`), failure forms (400 `ValidationFailure`, 404 `NotFound`, 409 `Conflict`, 422 `RuleViolation`), the `data` of the response envelope `{status, data, errors, meta}`, read answers (`ReadResult`, `Reference`). The door steps (input validation, actor check, failure mapping) follow in P3. Details: `README.md`, https://docs.jardis.io/en/support/response

## Layout (`src/`)

- `Data/` — `ReadResult`, `Reference`, `Failure/*` (the four failure forms behind `FailureForm`), `Response/*` (`ContextResponse`, `DomainResponse`)
- `Handler/` — one closure class per concern with exactly one public entry point (`__invoke`): `Failure/` (payload builders, `BuildFailureForm`, `DescribeFieldFinding`), `Response/` (`TransformResponse`)
- `Exception/` — `ConfigurationException` (extends `\RuntimeException`)

## Commands (always `make`)

`make phpunit` · `make phpunit-coverage` · `make phpstan` · `make phpcs` · `make install` · `make shell`

## Rules

- Closure-Orchestrator: atomic closure classes (one public `__invoke`), no logic in composing classes
- Everything is `final`; constructor injection, no static state; the clock is an injected PSR-20 `ClockInterface`
- Arrow rule: nothing in `src/` or `tests/` imports the process engine namespace (`workflow`); the engine side in tests is a stand-in
- Tests are integration tests; mock ports only, no assertion weakening
- The wire contract `tests/Smoke/Contract/wire-contract.json` is the one `tests/` path that ships; changing it is a breaking change of the wire (major version) and needs a decision
- The domain core never imports response classes
