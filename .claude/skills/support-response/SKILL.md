---
name: support-response
description: jardissupport/response 1.0 - the answer of one request-response use case in one shape. Response objects (ContextResponse, DomainResponse, TransformResponse), failure forms 400/404/409/422 (ValidationFailure, NotFound, Conflict, RuleViolation, BuildFailureForm, BuildFailureOutcome), the data of the envelope {status, data, errors, meta} (BuildResponseData), read answers (ReadResult, Reference, ProjectAnswer, ProjectRows) the door steps in front of a use case (ValidateInput, MapThrowable, LogCause) and the request mapper for array-shaped input (ReadRequired, ReadBodyNullable, ReadTransportOptional, ReadTransportList, FindUnknownKeys, RebaseFindings, BuildRequestFindings, Accept closures, AnswerInvalidRequest). No process engine inside. Use when working with jardissupport/response, request mapper, Accept closures, AnswerInvalidRequest, failure forms, BuildResponseData, MapThrowable, ValidateInput, InputFindings, MappedFailure, the response envelope, wire contract or 400/404/409/422 door answers.
user-invocable: false
zone: post-active
persona: C
prerequisites: [foundation-architecture, support-contracts]
next: [support-workflow, generated-code-workflow-api]
---

# RESPONSE_COMPONENT_SKILL
> `jardissupport/response` 2.0 | NS: `JardisSupport\Response` | PHP 8.3+ | requires `jardissupport/contracts` ^2.16, `psr/log` ^3.0, `psr/clock` ^1.0 | never `workflow` (arrow rule M5)

## Usage essentials

**Trigger:** jardissupport/response, ContextResponse, DomainResponse, TransformResponse, ValidationFailure, NotFound, Conflict, RuleViolation, FailureForm, BuildFailureForm, BuildFailureOutcome, BuildResponseData, ValidateInput, MapThrowable, LogCause, InputFindings, MappedFailure, ReadResult, Reference, ProjectAnswer, ProjectRows, AnswerInvalidRequest, ReadRequired, ReadBodyNullable, ReadTransportOptional, ReadTransportList, FindUnknownKeys, RebaseFindings, BuildRequestFindings, Accept closures, ConfigurationException, response envelope, wire contract.

The package answers one question: what does a finished use case say to its caller, in one shape. It holds objects and single-step closures; it does **not** run a process, route nodes or open transactions. The door of a use case (generated handler) composes the steps in a fixed order:

```
① ValidateInput        findings: fields === [] is valid, else 400   (input object, validator port)
② transaction bracket  not here - in jardissupport/repository
③ MapThrowable / LogCause   thrown failure -> status + response; 5xx cause logged once by the owner (a cause already reported - marker `ReportedFailureExceptionInterface` - is skipped)
④ BuildResponseData -> TransformResponse   data of the envelope -> DomainResponse
```

```php
// ① input check
$findings = (new ValidateInput($validator))($input);                    // InputFindings
if ($findings->fields !== []) {
    $form = (new BuildFailureOutcome())(ResponseStatus::ValidationError, ValidateInput::MESSAGE_KEY,
        ['fields' => $findings->fields], $findings->errors);            // ValidationFailure
}

// 401 and 403 are not made here: the delivery layer answers them (RequireSession, RequireRight)

// ③ a thrown failure
try { /* the use case */ } catch (\Throwable $failure) {
    $mapped = (new MapThrowable())($failure, $owner, $aggregate);       // MappedFailure {status, response}
    // $owner = no transaction was open on entry; a non-owner gets 5xx causes and the stale write thrown on
    if ($owner && $mapped->status->value >= 500) { (new LogCause($logger))($failure, $process); }
}

// ④ the envelope
$data = (new BuildResponseData())($status, $response, $declaredMessageKey, $processName);   // array
$result->setData($data);
$domainResponse = (new TransformResponse($clock, $version))($result, $status);              // {status, data, errors, meta}
```

The same door with the answer closures (`Handler\Answer\*`) - calls and branches on what a call returns, nothing else:

```php
$findings = ($this->handle(ValidateInput::class, validator: $validator))($input);
if ($findings->failed()) { return ($this->handle(AnswerFindings::class))($this->result(), $this->transformer(), $findings); }   // 400
try {
    // ... run in the transaction bracket -> $outcome (AnswerableOutcomeInterface), $owner
    return ($this->handle(AnswerOutcome::class))($this->result(), $this->transformer(), $outcome, $processName);   // events, errors, data, status
} catch (\Throwable $e) {
    $answer = ($this->handle(AnswerThrowable::class))($this->result(), $this->transformer(), $e, $owner, $processName);   // maps, logs nothing
    ($this->handle(LogFailure::class, logger: $logger))($e, $processName, $owner, $answer);                            // owner + 5xx only
    return $answer;
}
```

| Failure status | Form (`Data/Failure/`) | `data` on the wire |
|---|---|---|
| 400 | `ValidationFailure(messageKey, fields[], context)` | `{@type: validation, fields}` |
| 404 | `NotFound(messageKey, context)` | `{@type: notFound, context}` |
| 409 | `Conflict(messageKey, context, reason, aggregate, violated)` | `{@type: concurrencyConflict, reason, aggregate, context, violated}` |
| 422 | `RuleViolation(rule, messageKey, context)` | `{@type: ruleViolation, rule, messageKey, context}` |
| 500 | none (`stdClass`) | `{}` |

The wire contract `tests/Smoke/Contract/wire-contract.json` (10 cases: 200, 200-empty, 200-merged, 200-answer-class, 200-datetime-offset, 400, 404, 409, 422, 500) ships with the package; the package test drives only the door steps, the proof with a generated handler is the consumer's.

## Request mapper (array-shaped input, 400 with all faults)

For a transport that hands the door plain arrays (path/query values, decoded JSON body) - no PSR-7, no router. One reader per field collects `{field, reason}` findings (`missing`, `type`, `unknown`); the door answers once with all of them.

```php
$fields = [];
$name = (new ReadRequired())($body, 'name', new BodyStr(), '', $fields);              // absent = missing, refused = type, fallback stands in
$due  = (new ReadBodyNullable())($body, 'due', new BodyDate($validator), $fields);    // absent/null = null
$qty  = (new ReadTransportOptional())($query, 'qty', new TransportInt(), $fields);    // absent/''/null = null
(new FindUnknownKeys())($body, ['name', 'due'], $fields);                             // unknown keys, body order
if ($fields !== []) { return (new AnswerInvalidRequest($clock))($fields, $version); } // 400, meta.version
```

**Constructor dependencies (ports, no new runtime dependency):** the six calendar accept closures (`TransportDate|DateTime|Time`, `BodyDate|DateTime|Time`) take a `ValueValidatorInterface` (pass the `DateTime` validator of `jardissupport/validation`); `AnswerInvalidRequest` takes a PSR-20 `ClockInterface`. Both are required, so `Factory::get()` cannot create them without a registered instance; readers and the other accept closures are creatable without arguments. Not in this package (stay in `jardiscore/app`): `ParseJsonBody`, `CreateServerRequest`, `ResolveVersion`.

## Full reference

All 58 classes, namespace `JardisSupport\Response\`, every handler `final` with exactly one public entry point.

| Class | Public surface |
|---|---|
| `Data\Response\ContextResponse` | `__construct(string $context)`; `addData(string, mixed): self`, `setData(array): self`, `getData(): array`, `addEvent(object, EventScope = Internal): self`, `getEvents(?EventScope = null): array`, `addError(string): self`, `getErrors(): array`, `addResult(ContextResponseInterface): self`, `getResults(): array` |
| `Data\Response\DomainResponse` | `readonly`; `__construct(ResponseStatus, array $data, array $events, array $errors, array $metadata, array $eventsByScope = [])`; `isSuccess() getStatus(): int getData() getEvents(?EventScope) getErrors() getMetadata()` |
| `Data\ReadResult` | `final readonly`; `ofList(array $items, int $total)` (throws `\InvalidArgumentException` when `total < count(items)`), `ofOne(mixed $item)`; `items() total() first() isEmpty()` |
| `Data\Reference` | `final readonly`; `__construct(int\|string $root, array $children = [], string $identifier = 'id')`; `root() children() identifier()` - answered as `{@type, <identifier>: root, <child key>: scalar\|null\|list}` |
| `Data\InputFindings` | `final readonly`; `__construct(public array $fields, public array $errors)`; `fields: list<{field, reason, message, messageKey}>`, `errors: list<string>`; **valid <=> `fields === []`**; `failed(): bool` answers the same |
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
| `Handler\Failure\BuildFailureForm` | `__construct(ValidationPayload = new, NotFoundPayload = new, ConcurrencyConflictPayload = new, RuleViolationPayload = new)`; `__invoke(ResponseStatus, string $messageKey, array $context, array $errors): ?FailureForm` - built from the context keys `fields`, `reason/aggregate/violated`, `rule`; 404 takes the whole context; other statuses `null` |
| `Handler\Failure\DescribeFieldFinding` | `public const string KEY_PREFIX, REASON_MISSING, REASON_INVALID, REASON_TYPE, REASON_UNKNOWN, TEXT_MISSING, TEXT_INVALID, TEXT_TYPE, TEXT_UNKNOWN`; `__invoke(array $finding): array` - adds `messageKey` `validation.<reason>` for `missing`/`invalid`/`type`/`unknown` (any other reason: `validation.invalid`) and the standard text of the reason when the caller delivered none |
| `Handler\Failure\BuildFailureOutcome` | `__construct(BuildFailureForm = new)`; `__invoke(ResponseStatus, string $messageKey, array $context = [], array $errors = [], bool $declared = false): FailureForm\|\stdClass` - form of the status; 5xx `stdClass{}`; 4xx without form: `declared` -> `(object) $context`, else `{messageKey, context}` |
| `Handler\Failure\LogCause` | `__construct(?LoggerInterface $logger)`; `__invoke(\Throwable $cause, string $process): void` - `error` log with `exception` and `process`; no logger = nothing; a cause carrying the marker `ReportedFailureExceptionInterface` (already reported where it arose, e.g. `CalleeFailureException` from workflow) is skipped; a failing logger is swallowed |
| `Handler\Failure\MapThrowable` | `public const string CONFLICT_KEY = 'process.transientConflict'`, `CONFLICT_REASON = 'stale_write'`; `__construct(BuildFailureOutcome = new)`; `__invoke(\Throwable $failure, bool $owner, string $aggregate): MappedFailure` |
| `Handler\Response\BuildResponseData` | `__invoke(ResponseStatus $status, object $response, ?string $declaredMessageKey, string $processName): array` - the `data` of the envelope, **without** wire projection |
| `Handler\Response\TransformResponse` | `final`; `__construct(ClockInterface $clock, string $version = '')`; `__invoke(ContextResponseInterface $result, ?ResponseStatus $status = null): DomainResponseInterface`; time only from the injected PSR-20 clock |
| `Handler\Door\ValidateInput` | `public const string MESSAGE_KEY = 'process.inputInvalid'`; `__construct(ValidatorInterface $validator, DescribeFieldFinding = new)`; `__invoke(object $input): InputFindings`; field paths below the input root (`customer`, `items[1].qty`), root name `lcfirst(shortName)` |
| `Data\RuleResult` | `final readonly`; `pass()`, `reject(string $rule, string $messageKey, array $context = [])`; public `passed rule messageKey context` |
| `Handler\Failure\LogFailure` | `__construct(?LoggerInterface $logger = null, ?LogCause = null)`; `__invoke(\Throwable $cause, string $process, bool $owner, DomainResponseInterface $answer): void` - logs (`LogCause`) only when `$owner` and the answer is a 5xx |
| `Handler\Response\ProjectAnswer` | `final`; `__invoke(DomainResponseInterface $response, string ...$fields): DomainResponseInterface` - outer-door cut: 2xx keeps `@type` + the named fields that exist in `data` (order as in data, missing not added, unknown no error), status/errors/metadata/events of every scope unchanged; non-2xx = the same instance; knows no field name of its own |
| `Handler\Response\ProjectRows` | `final`; `__invoke(array $rows, string ...$fields): array` (`list<array<string,mixed>>`) - row cut of a list: each row keeps exactly the named keys in `$fields` order, nothing added, `null` stays; **unlike `ProjectAnswer` a missing key is drift**: `\UnexpectedValueException` with row index and field (also `$rows` no list, row no array) -> `MapThrowable` 500; empty `$fields` -> `\InvalidArgumentException`; knows no field name of its own |
| `Handler\Answer\AnswerFindings` | `__invoke(ContextResponseInterface $result, TransformResponse $transform, InputFindings $findings)` -> 400 `{@type: validation, fields}`, every validator text added as error |
| `Handler\Answer\AnswerNotFound` | `__invoke($result, $transform, array $context)` -> 404 `{@type: notFound, context}` |
| `Handler\Answer\AnswerRuleViolation` | `__invoke($result, $transform, RuleResult $rejection)` -> 422 `{@type: ruleViolation, rule, messageKey, context}` |
| `Handler\Answer\AnswerOutcome` | `__invoke($result, $transform, AnswerableOutcomeInterface $outcome, string $processName)` - Domain events and errors into the result, then response (success) or failure form of the reject, under the outcome's status; no engine type |
| `Handler\Answer\AnswerThrowable` | `__invoke($result, $transform, \Throwable $failure, bool $owner, string $aggregate)` - `MapThrowable` + `BuildResponseData`; throws on what is not the owner's to answer; logs nothing |
| `Handler\Answer\AnswerCommand` | `__invoke($result, $transform, Reference $reference, string $aggregate)` - `{@type, <identifier>: root, <children>}`, status from collected errors |
| `Handler\Answer\AnswerRead` | `__invoke($result, $transform, ReadResult $found, string $type)` - `@type` plus public fields of the first item, `[]` when empty |
| `Handler\Request\ReadRequired` | `__invoke(array $source, string $name, callable $accept, mixed $fallback, array &$fields): mixed` - absent = `missing`; `null` goes to the accept closure; refused = `type`; findings collected, fallback returned |
| `Handler\Request\ReadBodyNullable` | `__invoke(array $body, string $name, callable $accept, array &$fields): mixed` - absent or `null` = `null`; refused = `type` + `null` |
| `Handler\Request\ReadTransportOptional` | `__invoke(array $source, string $name, callable $accept, array &$fields): mixed` - absent, `''`, `null` = `null`; refused = `type` + `null` |
| `Handler\Request\ReadTransportList` | `__invoke(array $source, string $name, callable $accept, array &$fields): array` - repeated query parameter; absent/empty = `missing`; one refused element = one `type`, list `[]` |
| `Handler\Request\FindUnknownKeys` | `__invoke(array $source, array $known, array &$fields): void` - every key not in `$known` = `unknown`, body order |
| `Handler\Request\RebaseFindings` | `__invoke(string $path, array $found, array &$fields): void` - `<path>.<field>` onto the carrying path |
| `Handler\Request\BuildRequestFindings` | `public const string REASON_MISSING, REASON_TYPE, REASON_UNKNOWN`; `__construct(DescribeFieldFinding = new)`; `__invoke(array $fields): InputFindings` |
| `Handler\Request\Accept\Transport{Str,Int,Float,Bool}` | `__invoke(mixed): ?T` - a path/query value is a string, validated first, coerced second (`Bool`: exactly `true false 1 0`) |
| `Handler\Request\Accept\TransportDecimal` | `public const string LEXICAL_PATTERN`; `__construct(string $pattern = LEXICAL_PATTERN)`; `__invoke(mixed): ?string` - stays a string |
| `Handler\Request\Accept\Transport{Date,DateTime,Time}` | `__construct(ValueValidatorInterface $validator)`; `__invoke(mixed): ?string` - `Y-m-d` / RFC 3339 with offset / `H:i:s`, judged by the validator, string handed on |
| `Handler\Request\Accept\Body{Str,Int,Float,Bool}` | `__invoke(mixed): ?T` - a JSON value keeps its type (`Int` only a JSON integer, `Float` int or float) |
| `Handler\Request\Accept\Body{Date,DateTime,Time}` | `__construct(ValueValidatorInterface $validator)`; `__invoke(mixed): ?DateTimeImmutable` - `BodyDateTime` normalised to UTC |
| `Handler\Answer\AnswerInvalidRequest` | `public const string CONTEXT = 'validation'`; `__construct(ClockInterface $clock, BuildRequestFindings = new, AnswerFindings = new)`; `__invoke(array $fields, string $version = ''): DomainResponseInterface` - the 400 with all findings, `meta.version` |

**MapThrowable table** (every 4xx is answered, only the owner answers a 5xx):

| Thrown | Owner (no transaction open on entry) | Not the owner |
|---|---|---|
| `TransientConflictException` | 409 `Conflict(CONFLICT_KEY, reason: CONFLICT_REASON, aggregate)` | thrown on - the owner repeats the unit of work |
| `ConcurrencyConflictExceptionInterface` (contracts marker) | 409 with `getReason/getAggregate/getContext/getViolated`, key `concurrencyConflict` | the same 409 |
| `ValidationExceptionInterface` (contracts marker) | 400 `ValidationFailure` with `getFields()`, key `validation` | the same 400 |
| any other `\Throwable` (an access denial, too: the delivery layer answers 403 before the domain runs) | 500, empty `stdClass` (cause via `LogCause`) | thrown on |

**BuildResponseData per answer:**

| Answer | `data` |
|---|---|
| 2xx | `@type` (process name, first letter lower case) + public fields of the response |
| 400/404/409/422 | the form's data as in the table above; the declared message key joins every 4xx last, the 422 always carries its own; an empty `context` is `{}` |
| other 4xx (401 and 403 included) | declared: the response fields as given plus the key; undeclared: `[]` |
| 5xx | `[]` |

Dates stay as they are: the projection to the wire (RFC 3339, UTC) is the caller's step through `jardissupport/data` `ProjectWire` (from 1.4.0).

## Pitfalls

| # | Pitfall | Evidence |
|---|---------|----------|
| 1 | The package never imports the process engine (M5); the door step results are plain values (`InputFindings`, `MappedFailure`, `bool`) | `tests/Integration/DecouplingTest.php` |
| 2 | `MapThrowable` with `$owner = false` throws only `TransientConflictException` and 5xx causes; it never throws a 4xx - markers (conflict, validation) are always mapped (a rejection is an answer, not an exception) | `Handler/Failure/MapThrowable.php` match arms |
| 3 | The owner is decided by the caller (no transaction open on entry), not by the package; `LogCause` logs what it is given - call it once, only for the owner and only for a 5xx; a cause with the marker `ReportedFailureExceptionInterface` is skipped (already reported) | `Handler/Failure/LogCause.php` |
| 4 | Valid input is exactly `fields === []`; `errors` is only the validator's texts. A result that is invalid but yields no field findings reads as valid | `Data/InputFindings.php`, `Handler/Door/ValidateInput.php` |
| 5 | The package produces no 401 and no 403 (3.0.0): the delivery layer answers them before the domain runs; a node reject with the declared status 401/403 is flat like any other 4xx; a thrown access marker is an unknown failure (500 for the owner) | `Handler/Response/BuildResponseData.php`, `Handler/Failure/MapThrowable.php` |
| 6 | `BuildResponseData` does not project dates; hand the result to `ProjectWire` before the envelope | `Handler/Response/BuildResponseData.php` docblock |
| 7 | A 4xx without a failure form (401, 403, 405) answers `{messageKey, context}` unless `declared`; the key reaches the wire only when declared | `Handler/Failure/BuildFailureOutcome.php` |
| 8 | `BuildFailureForm` cuts the context keys it knows (`fields`; `reason/aggregate/violated`; `rule`) - the rest travels as `context` of the form | `Handler/Failure/BuildFailureForm.php` |
| 9 | `ReadResult::ofList` with `total` smaller than the items throws `\InvalidArgumentException` | `Data/ReadResult.php` |
| 10 | A calendar accept closure without its `ValueValidatorInterface` or `AnswerInvalidRequest` without its clock does not construct - there is no default, the package has no runtime dependency on `validation`/`scheduling`; pass them (`new BodyDate(new DateTime())`, `new AnswerInvalidRequest($clock)`) | `Handler/Request/Accept/BodyDate.php`, `Handler/Answer/AnswerInvalidRequest.php` |
| 11 | The wire contract file is the one shipped test path; changing it is a breaking change of the wire (major version) | `.gitattributes`, `tests/Smoke/WireContractTest.php` |

## Layer rules

- Application layer composes the door steps in the fixed order; the domain core never imports response classes
- Steps stay single closures with one `__invoke`; composition (order, owner decision, transaction) is the door's, not the package's
- A capability the package lacks is built in the package first, never imitated in generated code or project code
- Integration tests with the real validator; mock ports only, the process engine on the other side is a stand-in
