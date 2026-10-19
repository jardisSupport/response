# Changelog

All notable changes to `jardissupport/response` are documented in this file.
The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
versioning follows [SemVer](https://semver.org/).

## [1.0.0] - Unreleased

### Added
- Package scaffold (Docker, Makefile, QA configuration, CI).
- Response objects `ContextResponse`, `DomainResponse` and `TransformResponse`.
- Failure forms `ValidationFailure`, `NotFound`, `Conflict`, `RuleViolation` with their payload builders and `BuildFailureForm`.
- `ReadResult`, `Reference` and `ConfigurationException`.
- Wire contract `tests/Smoke/Contract/wire-contract.json` (shipped with the package).
