# Changelog

All notable changes to `jardissupport/response` are documented in this file.
The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
versioning follows [SemVer](https://semver.org/).

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
