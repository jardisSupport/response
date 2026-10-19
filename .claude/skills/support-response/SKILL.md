---
name: support-response
description: jardissupport/response 1.0 - the answer of one request-response use case in one shape. Response objects (ContextResponse, DomainResponse, TransformResponse), failure forms 400/404/409/422 (ValidationFailure, NotFound, Conflict, RuleViolation, BuildFailureForm, BuildFailureOutcome), the data of the envelope {status, data, errors, meta} (BuildResponseData), read answers (ReadResult, Reference) and the door steps in front of a use case (ValidateInput, CheckActor, MapThrowable, LogCause). No process engine inside. Use when working with jardissupport/response, failure forms, BuildResponseData, MapThrowable, ValidateInput, CheckActor, InputFindings, MappedFailure, the response envelope, wire contract or 401/400/409 door answers.
user-invocable: false
zone: post-active
persona: C
prerequisites: [foundation-architecture, support-contracts]
next: [support-workflow, generated-code-workflow-api]
---

# RESPONSE_COMPONENT_SKILL
> `jardissupport/response` 1.0 | NS: `JardisSupport\Response` | PHP 8.3+ | requires `jardissupport/contracts` ^2.13, `psr/log` ^3.0, `psr/clock` ^1.0 | never `workflow` (arrow rule M5)

## Usage essentials

**Trigger:** jardissupport/response, ContextResponse, DomainResponse, TransformResponse, ValidationFailure, NotFound, Conflict, RuleViolation, FailureForm, BuildFailureForm, BuildFailureOutcome, BuildResponseData, ValidateInput, CheckActor, MapThrowable, LogCause, InputFindings, MappedFailure, ReadResult, Reference, ConfigurationException, response envelope, wire contract.

The package answers one question: what does a finished use case say to its caller, in one shape. It holds objects and single-step closures; it does **not** run a process, route nodes or open transactions. The door of a use case (generated handler) composes the steps in a fixed order:

```
① ValidateInput        findings: fields === [] is valid, else 400   (input object, validator port)
② CheckActor           false = 401                                   (required?, actor)
③ transaction bracket  not here - in jardissupport/repository
④ MapThrowable / LogCause   thrown failure -> status + response; 5xx cause logged once by the owner (a cause already reported - marker `ReportedFailureExceptionInterface` - is skipped)
⑤ BuildResponseData -> TransformResponse   data of the envelope -> DomainResponse
```

```php
// ① input check
$findings = (new ValidateInput($validator))($input);                    // InputFindings
if ($findings->fields !== []) {
    $form = (new BuildFailureOutcome())(ResponseStatus::ValidationError, ValidateInput::MESSAGE_KEY,
        ['fields' => $findings->fields], $findings->errors);            // ValidationFailure
}

// ② actor
if (!(new CheckActor())($actorRequired, $actor)) {                      // false = 401
    $response = (new BuildFailureOutcome())(ResponseStatus::Unauthorized, CheckActor::MESSAGE_KEY);
}

// ④ a thrown failure
try { /* the use case */ } catch (\Throwable $failure) {
    $mapped = (new MapThrowable())($failure, $owner, $aggregate);       // MappedFailure {status, response}
    // $owner = no transaction was open on entry; a non-owner gets 5xx causes and the stale write thrown on
    if ($owner && $mapped->status->value >= 500) { (new LogCause($logger))($failure, $process); }
}

// ⑤ the envelope
$data = (new BuildResponseData())($status, $response, $declaredMessageKey, $processName);   // array
$result->setData($data);
$domainResponse = (new TransformResponse($clock, $version))($result, $status);              // {status, data, errors, meta}
```

| Failure status | Form (`Data/Failure/`) | `data` on the wire |
|---|---|---|
| 400 | `ValidationFailure(messageKey, fields[], context)` | `{@type: validation, fields}` |
| 404 | `NotFound(messageKey, context)` | `{@type: notFound, context}` |
| 409 | `Conflict(messageKey, context, reason, aggregate, violated)` | `{@type: concurrencyConflict, reason, aggregate, context, violated}` |
| 422 | `RuleViolation(rule, messageKey, context)` | `{@type: ruleViolation, rule, messageKey, context}` |
| 401 / 500 | none (`stdClass`) | `{}` |

The wire contract `tests/Smoke/Contract/wire-contract.json` (10 cases: 200, 200-empty, 200-merged, 200-datetime-offset, 400, 401, 404, 409, 422, 500) ships with the package; the package test drives only the door steps, the proof with a generated handler is the consumer's.

## Full reference

All 25 classes, namespace `JardisSupport\Response\`, every handler `final` with exactly one public entry point.

| Class | Public surface |
|---|---|
| `Data\Response\ContextResponse` | `__construct(string $context)`; `addData(string, mixed): self`, `setData(array): self`, `getData(): array`, `addEvent(object, EventScope = Internal): self`, `getEvents(?EventScope = null): array`, `addError(string): self`, `getErrors(): array`, `addResult(ContextResponseInterface): self`, `getResults(): array` |
| `Data\Response\DomainResponse` | `readonly`; `__construct(ResponseStatus, array $data, array $events, array $errors, array $metadata, array $eventsByScope = [])`; `isSuccess() getStatus(): int getData() getEvents(?EventScope) getErrors() getMetadata()` |
| `Data\ReadResult` | `final readonly`; `ofList(array $items, int $total)` (throws `\InvalidArgumentException` when `total < count(items)`), `ofOne(mixed $item)`; `items() total() first() isEmpty()` |
| `Data\Reference` | `final readonly`; `__construct(int\|string $root, array $children = [])`; `root() children()` |
| `Data\InputFindings` | `final readonly`; `__construct(public array $fields, public array $errors)`; `fields: list<{field, reason, message, messageKey}>`, `errors: list<string>`; **valid <=> `fields === []`** |
| `Data\MappedFailure` | `final readonly`; `__construct(public ResponseStatus $status, public FailureForm\|\stdClass $response)` |
| `Data\Failure\FailureForm` | interface: `status(): ResponseStatus`, `messageKey(): string`, `context(): array` |
| `Data\Failure\ValidationFailure` | `__construct(string $messageKey, array $fields, array $context = [])`; `fields()` |
| `Data\Failure\NotFound` | `__construct(string $messageKey, array $context = [])` |
| `Data\Failure\Conflict` | `__construct(string $messageKey, array $context = [], ?string $reason = null, ?string $aggregate = null, array $violated = [])`; `reason() aggregate() violated()` |
| `Data\Failure\RuleViolation` | `__construct(string $rule, string $messageKey, array $context = [])`; `rule()` |
| `Exception\ConfigurationException` | `final`, extends `\RuntimeException` - unusable runtime wiring (generated `requires` lines) |
| `Handler\Failure\ValidationPayload` | `__invoke(string $messageKey, array $fields, array $context = []): ValidationFailure` |
| `Handler\Failure\NotFoundPayload` | `__invoke(string $messageKey, array $context): NotFound` |
| `Handler\Failure\ConcurrencyConflictPayload` | `__invoke(string $messageKey, string $reason, string $aggregate, array $context, array $violated): Conflict` |
| `Handler\Failure\RuleViolationPayload` | `__invoke(string $rule, string $messageKey, array $context): RuleViolation` |
| `Handler\Failure\BuildFailureForm` | `__construct(ValidationPayload = new, NotFoundPayload = new, ConcurrencyConflictPayload = new, RuleViolationPayload = new)`; `__invoke(ResponseStatus, string $messageKey, array $context, array $errors): ?FailureForm` - built from the context keys `fields`, `reason/aggregate/violated`, `rule`; other statuses `null` |
| `Handler\Failure\DescribeFieldFinding` | `public const string KEY_PREFIX, REASON_MISSING, REASON_INVALID, TEXT_MISSING, TEXT_INVALID`; `__invoke(array $finding): array` - adds `messageKey` `validation.missing` / `validation.invalid` and a standard text when the validator delivered none |
| `Handler\Failure\BuildFailureOutcome` | `__construct(BuildFailureForm = new)`; `__invoke(ResponseStatus, string $messageKey, array $context = [], array $errors = [], bool $declared = false): FailureForm\|\stdClass` - form of the status; 5xx `stdClass{}`; 4xx without form: `declared` -> `(object) $context`, else `{messageKey, context}` |
| `Handler\Failure\LogCause` | `__construct(?LoggerInterface $logger)`; `__invoke(\Throwable $cause, string $process): void` - `error` log with `exception` and `process`; no logger = nothing; a cause carrying the marker `ReportedFailureExceptionInterface` (already reported where it arose, e.g. `CalleeFailureException` from workflow) is skipped; a failing logger is swallowed |
| `Handler\Failure\MapThrowable` | `public const string CONFLICT_KEY = 'process.transientConflict'`, `CONFLICT_REASON = 'stale_write'`; `__construct(BuildFailureOutcome = new)`; `__invoke(\Throwable $failure, bool $owner, string $aggregate): MappedFailure` |
| `Handler\Response\BuildResponseData` | `__invoke(ResponseStatus $status, object $response, ?string $declaredMessageKey, string $processName): array` - the `data` of the envelope, **without** wire projection |
| `Handler\Response\TransformResponse` | `final`; `__construct(ClockInterface $clock, string $version = '')`; `__invoke(ContextResponseInterface $result, ?ResponseStatus $status = null): DomainResponseInterface`; time only from the injected PSR-20 clock |
| `Handler\Door\ValidateInput` | `public const string MESSAGE_KEY = 'process.inputInvalid'`; `__construct(ValidatorInterface $validator, DescribeFieldFinding = new)`; `__invoke(object $input): InputFindings`; field paths below the input root (`customer`, `items[1].qty`), root name `lcfirst(shortName)` |
| `Handler\Door\CheckActor` | `public const string MESSAGE_KEY = 'process.actorMissing'`; `__invoke(bool $required, ?object $actor): bool` - `false` = 401 |

**MapThrowable table** (every 4xx is answered, only the owner answers a 5xx):

| Thrown | Owner (no transaction open on entry) | Not the owner |
|---|---|---|
| `TransientConflictException` | 409 `Conflict(CONFLICT_KEY, reason: CONFLICT_REASON, aggregate)` | thrown on - the owner repeats the unit of work |
| `ConcurrencyConflictExceptionInterface` (contracts marker) | 409 with `getReason/getAggregate/getContext/getViolated`, key `concurrencyConflict` | the same 409 |
| `ValidationExceptionInterface` (contracts marker) | 400 `ValidationFailure` with `getFields()`, key `validation` | the same 400 |
| any other `\Throwable` | 500, empty `stdClass` (cause via `LogCause`) | thrown on |

**BuildResponseData per answer:**

| Answer | `data` |
|---|---|
| 2xx | `@type` (process name, first letter lower case) + public fields of the response |
| 400/404/409/422 | the form's data as in the table above; the declared message key joins every 4xx last, the 422 always carries its own; an empty `context` is `{}` |
| other 4xx | declared: the response fields as given plus the key; undeclared (e.g. the missing actor): `[]` |
| 5xx | `[]` |

Dates stay as they are: the projection to the wire (RFC 3339, UTC) is the caller's step through `jardissupport/data` `ProjectWire` (from 1.4.0).

## Pitfalls

| # | Pitfall | Evidence |
|---|---------|----------|
| 1 | The package never imports the process engine (M5); the door step results are plain values (`InputFindings`, `MappedFailure`, `bool`) | `tests/Integration/DecouplingTest.php` |
| 2 | `MapThrowable` with `$owner = false` throws only `TransientConflictException` and 5xx causes; it never throws a 4xx - markers are always mapped (a rejection is an answer, not an exception) | `Handler/Failure/MapThrowable.php` match arms |
| 3 | The owner is decided by the caller (no transaction open on entry), not by the package; `LogCause` logs what it is given - call it once, only for the owner and only for a 5xx; a cause with the marker `ReportedFailureExceptionInterface` is skipped (already reported) | `Handler/Failure/LogCause.php` |
| 4 | Valid input is exactly `fields === []`; `errors` is only the validator's texts. A result that is invalid but yields no field findings reads as valid | `Data/InputFindings.php`, `Handler/Door/ValidateInput.php` |
| 5 | `CheckActor` answers a bool, never a status: `false` is the door's 401; whether the actor may act (403) is the use case's decision | `Handler/Door/CheckActor.php` |
| 6 | `BuildResponseData` does not project dates; hand the result to `ProjectWire` before the envelope | `Handler/Response/BuildResponseData.php` docblock |
| 7 | A 4xx without a failure form (401, 403, 405) answers `{messageKey, context}` unless `declared`; the key reaches the wire only when declared | `Handler/Failure/BuildFailureOutcome.php` |
| 8 | `BuildFailureForm` cuts the context keys it knows (`fields`; `reason/aggregate/violated`; `rule`) - the rest travels as `context` of the form | `Handler/Failure/BuildFailureForm.php` |
| 9 | `ReadResult::ofList` with `total` smaller than the items throws `\InvalidArgumentException` | `Data/ReadResult.php` |
| 10 | The wire contract file is the one shipped test path; changing it is a breaking change of the wire (major version) | `.gitattributes`, `tests/Smoke/WireContractTest.php` |

## Layer rules

- Application layer composes the door steps in the fixed order; the domain core never imports response classes
- Steps stay single closures with one `__invoke`; composition (order, owner decision, transaction) is the door's, not the package's
- A capability the package lacks is built in the package first, never imitated in generated code or project code
- Integration tests with the real validator; mock ports only, the process engine on the other side is a stand-in
