# jardissupport/response

Response building for Jardis request-response use cases: what a finished use case answers, in one shape. The package holds the response objects, the failure forms, the `data` of the response envelope and (from P3) the door steps in front of a use case. It does not run a process; it has no dependency on the process engine.

Status: 1.0.0 in preparation, not released.

## Four groups (`src/`)

| Group | Content |
|---|---|
| Response objects | `Data/Response/ContextResponse`, `Data/Response/DomainResponse`, `Handler/Response/TransformResponse` (context response to `DomainResponseInterface`, injected PSR-20 clock) |
| Failure forms | `Data/Failure/{FailureForm, ValidationFailure, NotFound, Conflict, RuleViolation}`, `Handler/Failure/{BuildFailureForm, ValidationPayload, NotFoundPayload, ConcurrencyConflictPayload, RuleViolationPayload, DescribeFieldFinding}` |
| Read answers | `Data/ReadResult` (list with total, or one optional item), `Data/Reference` (root identifier plus child references) |
| Configuration error | `Exception/ConfigurationException` (unusable runtime wiring) |

The envelope is `{status, data, errors, meta}`; the wire contract (`tests/Smoke/Contract/wire-contract.json`) fixes its bytes per status (200, 200-empty, 400, 401, 404, 409, 422, 500) and ships with the package.

## Requirements

`php >=8.3`, `jardissupport/contracts ^2.13`, `psr/log ^3.0`, `psr/clock ^1.0`.

## Door steps

Tür-Schritte folgen in P3 (input validation, actor check, failure mapping, `BuildResponseData`).

## Development

Always through `make`: `make install` · `make phpunit` · `make phpstan` · `make phpcs` · `make shell`.
