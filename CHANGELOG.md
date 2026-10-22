# Changelog

All notable changes to `jardissupport/response` are documented in this file.
The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
versioning follows [SemVer](https://semver.org/).

## [Unreleased]

### Added
- `Handler/Response/ProjectRows`: cuts every row of a list down to the outer contract of the list. `__invoke(array $rows, string ...$fields): array` (`list<array<string, mixed>>`) - each row keeps exactly the named keys in the order of `$fields`, nothing is added, a `null` value stays `null`. Deliberate difference to `ProjectAnswer`: a named key a row does not carry is drift, not tolerance - `\UnexpectedValueException` with the row index and the field (the door answers it through `MapThrowable` as 500); the same for `$rows` that are no list and for a row that is no array. A cut without any field throws `\InvalidArgumentException` (fail closed). The closure knows no field name of its own. Additive, `ProjectAnswer` is unchanged.

## [1.5.0] - 2026-10-21

### Added
- Failure form 403: `Data/Failure/Forbidden` (`messageKey`, `context`, status `ResponseStatus::Forbidden`), built by `Handler/Failure/ForbiddenPayload` and mapped by `BuildFailureForm`. `BuildResponseData` answers it as `{@type: forbidden, context, messageKey}` (an empty `context` as `{}`).
- `MapThrowable` maps `JardisSupport\Contract\Kernel\AccessDeniedExceptionInterface` to 403 with the `getMessageKey()` and `getContext()` of the exception - for the owner and for a nested run alike, never thrown on.
- Wire contract: two new cases `403-query` and `403-process`; the 11 existing cases are byte-for-byte unchanged (the `provenance` text names the new cases). Because the file gains cases, this is a minor addition of the wire, no change of an existing byte.
- Needs `jardissupport/contracts` 2.16 (ships `AccessDeniedExceptionInterface`); the `require` is raised to `^2.16`.

### Changed
- A node reject with the declared status 403 now answers the form (`{@type: forbidden, context, messageKey}`) instead of the flat context plus the declared key. Before, 403 had no form.

## [1.4.0] - 2026-10-21

### Added
- Request mapper, moved from `jardiscore/app` (behaviour unchanged, no PSR-7, no router): `Handler/Request/{ReadRequired, ReadBodyNullable, ReadTransportOptional, ReadTransportList, FindUnknownKeys, RebaseFindings, BuildRequestFindings}`, the 15 accept closures `Handler/Request/Accept/{Body,Transport}*` and the 400 answer `Handler/Answer/AnswerInvalidRequest`. They read the path/query values and the decoded JSON body as arrays, collect every fault (`missing`, `type`, `unknown`) and answer 400 through `AnswerFindings`. Decoding the body, building the server request and resolving the class version stay in `jardiscore/app`.
- No new runtime dependency: the six calendar accept closures (`TransportDate`, `TransportDateTime`, `TransportTime`, `BodyDate`, `BodyDateTime`, `BodyTime`) take a `JardisSupport\Contract\Validation\ValueValidatorInterface` in the constructor (the `DateTime` validator of `jardissupport/validation` judges the form), `AnswerInvalidRequest` takes a PSR-20 `ClockInterface`. Both constructor parameters are required (the former `jardiscore/app` classes created the validator and `SystemClock` themselves); the classes are therefore not creatable by `Factory::get()` without a registered instance.
- Tests for all 23 classes, the 400 envelope pinned against the fixture `tests/Fixture/envelope/route/s2-400.json`.

## [1.3.0] - 2026-10-21

### Added
- `Handler/Response/ProjectAnswer`: cuts an answer down to its outer contract. `__invoke(DomainResponseInterface $response, string ...$fields): DomainResponseInterface` - a 2xx answer keeps `@type` plus exactly the named fields that exist in its data (order as in the data, a missing field is not added, an unknown name is no error); status, errors, metadata and the events of every scope pass through unchanged. Any other status comes back as the same instance. An empty field list leaves only `@type`. The closure knows no field name of its own.

## [1.2.0] - 2026-10-21

### Added
- `Handler/Failure/DescribeFieldFinding` knows the four reasons of a field finding - `missing`, `invalid` (validator) and `type`, `unknown` (request reader) - each with its standard text (`Field has an invalid type.`, `Unknown field`) and the key `validation.<reason>`; any other reason is answered as `invalid`. New constants `REASON_TYPE`, `REASON_UNKNOWN`, `TEXT_TYPE`, `TEXT_UNKNOWN`. A text the caller delivered stays untouched.
- Answer closures in `Handler/Answer/` - the door answers by calls only: `AnswerFindings` (400), `AnswerUnauthorized` (401), `AnswerNotFound` (404), `AnswerRuleViolation` (422), `AnswerOutcome` (Domain events, errors, data and status of a process outcome, read through `JardisSupport\Contract\Kernel\AnswerableOutcomeInterface`), `AnswerThrowable` (maps a thrown failure, logs nothing), `AnswerCommand` (write answer from a `Reference`), `AnswerRead` (single read). Each is creatable without constructor arguments (`$this->handle(X::class)`); collaborators are constructor defaults. Bytes equal the hand-assembled sequence of the generated doors (pinned against `wire-contract.json` and the envelope fixtures).
- `Handler/Failure/LogFailure`: the logging step of a door as its own closure - writes the cause (`LogCause`) only when the caller owns the run and the answer is a 5xx; the owner is a `bool` parameter (no dependency on `repository`).
- `Data/Reference` carries the wire name of the business identifier (third constructor parameter `$identifier`, default `id`, `identifier()`); child references are answered under their keys (a single child as scalar or `null`, a child list as a list). `BuildResponseData` answers a `Reference` as `{@type, <identifier>: <root>, <children>}`.
- `Data/RuleResult`: the outcome of a rule invocation (`pass()` / `reject(rule, messageKey, context)`), answered as 422 by `AnswerRuleViolation`.
- `Data/InputFindings::failed()`: true when at least one field failed, so a door branches on a call instead of comparing `fields`.
- Needs `jardissupport/contracts` 2.15 (ships `AnswerableOutcomeInterface`); the `require` is raised to `^2.15`.
- `jardissupport/factory` as dev dependency (answer closures are created through the container in the tests).

## [1.1.0] - 2026-10-20

### Added
- Wire contract case `200-answer-class` in `tests/Smoke/Contract/wire-contract.json` (11 cases): `Answer::Merged` with a process answer class (`jardissupport/workflow` 4.1 `ProcessDefinition::answerResponse`) - the union of all nodes is projected onto the declared fields, foreign data of the last node (e.g. a sub-process) falls away.

## [1.0.0] - 2026-10-19

### Added
- Package scaffold (Docker, Makefile, QA configuration, CI).
- Response objects `ContextResponse`, `DomainResponse` and `TransformResponse`.
- Failure forms `ValidationFailure`, `NotFound`, `Conflict`, `RuleViolation` with their payload builders and `BuildFailureForm`.
- `ReadResult`, `Reference` and `ConfigurationException`.
- Wire contract `tests/Smoke/Contract/wire-contract.json` (shipped with the package).
- Door steps `Handler/Door/ValidateInput` (result `Data/InputFindings`, valid when `fields === []`) and `Handler/Door/CheckActor` (`bool`, `false` = 401).
- `Handler/Failure/MapThrowable` with the owner rule (`$owner = true` answers 409/400/500, `$owner = false` throws only the stale write and 5xx causes on, every 4xx is mapped), result `Data/MappedFailure`; it catches the contracts markers `ConcurrencyConflictExceptionInterface` (409) and `ValidationExceptionInterface` (400).
- `Handler/Failure/LogCause` (`__invoke(\Throwable, string $process): void`, a failing logger is swallowed; a cause carrying the contracts marker `ReportedFailureExceptionInterface` - already reported where it arose, e.g. the `CalleeFailureException` of a 5xx from an inner door - is skipped, so a cause is never logged twice) and `Handler/Failure/BuildFailureOutcome` (`FailureForm|\stdClass`).
- `Handler/Response/BuildResponseData` takes status, response, declared message key and process name; no wire projection.
- `DescribeFieldFinding` constants are typed `public const string`.
- Skill `support-response`.
