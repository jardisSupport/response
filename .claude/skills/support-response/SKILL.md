---
name: support-response
description: "jardissupport/response 4.5 - one shape for the answer of a request-response use case: Response objects, failure forms 400/403/404/409/422, envelope data, read answers, door steps, request mapper, reading the answer of another Jardis domain. Use when working with ValidateInput, MapThrowable, AnswerInvalidRequest, BuildResponseData, DecodeAnswer."
user-invocable: false
zone: post-active
persona: C
prerequisites: [foundation-architecture, support-contracts]
next: [support-workflow, generated-code-workflow-api]
---

This skill is an index; the detail lives in `reference/`, one leaf per topic. Read exactly the leaf whose question matches your task, not all of them.
The triggers, the door in four steps, the failure forms, the pitfalls and the layer rules stay in this index.

## Triggers

jardissupport/response 4.5 - the answer of one request-response use case in one shape. Response objects (ContextResponse, DomainResponse, TransformResponse), failure forms 400/403/404/409/422 (ValidationFailure, Forbidden, NotFound, Conflict, RuleViolation, BuildFailureForm, BuildFailureOutcome), the data of the envelope {status, data, errors, meta} (BuildResponseData), read answers (ReadResult, Reference, ProjectAnswer, ProjectRows, ReshapeAnswer, ProjectAnswerRows, ProjectOutcome, OutcomeForm, the `@outcome` marker), reading the answer of another Jardis domain (DecodeAnswer, NoJardisAnswerException), the door steps in front of a use case (ValidateInput, RequireResource, MapThrowable, LogCause) and the request mapper for array-shaped input (ReadRequired, ReadBodyNullable, ReadTransportOptional, ReadTransportList, FindUnknownKeys, RebaseFindings, BuildRequestFindings, Accept closures, AnswerInvalidRequest). No process engine inside. Use when working with jardissupport/response, DecodeAnswer, the answer of another Jardis domain, request mapper, Accept closures, AnswerInvalidRequest, failure forms, BuildResponseData, MapThrowable, ValidateInput, InputFindings, MappedFailure, the response envelope, wire contract or 400/403/404/409/422 door answers.

# RESPONSE_COMPONENT_SKILL
> `jardissupport/response` 4.5.0 | NS: `JardisSupport\Response` | PHP 8.3+ | requires `jardissupport/contracts` ^2.16, `psr/log` ^3.0, `psr/clock` ^1.0 | never `workflow` (arrow rule M5)

## Usage essentials

**Trigger:** jardissupport/response, ContextResponse, DomainResponse, TransformResponse, ValidationFailure, Forbidden, NotFound, Conflict, RuleViolation, FailureForm, BuildFailureForm, BuildFailureOutcome, BuildResponseData, AnswerForbidden, ValidateInput, MapThrowable, LogCause, InputFindings, MappedFailure, ReadResult, Reference, ProjectAnswer, ProjectRows, ReshapeAnswer, ProjectAnswerRows, ProjectOutcome, OutcomeForm, @outcome, DecodeAnswer, NoJardisAnswerException, RequireResource, AnswerInvalidRequest, ReadRequired, ReadBodyNullable, ReadTransportOptional, ReadTransportList, FindUnknownKeys, RebaseFindings, BuildRequestFindings, Accept closures, ConfigurationException, response envelope, wire contract.

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

// 401 is not made here: the delivery layer answers it (RequireSession), as it does the door rejection 403 (RequireRight); a 403 as a value is AnswerForbidden

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

| Failure status | Form (`Data/Failure/`) | `data` on the wire |
|---|---|---|
| 400 | `ValidationFailure(messageKey, fields[], context)` | `{@type: validation, fields}` |
| 403 | `Forbidden(messageKey, context)` (answered by `AnswerForbidden`) | `{@type: forbidden, context}` |
| 404 | `NotFound(messageKey, context)` | `{@type: notFound, context}` |
| 409 | `Conflict(messageKey, context, reason, aggregate, violated)` | `{@type: concurrencyConflict, reason, aggregate, context, violated}` |
| 422 | `RuleViolation(rule, messageKey, context)` | `{@type: ruleViolation, rule, messageKey, context}` |
| 500 | none (`stdClass`) | `{}` |

## Navigation

Navigation: one leaf per topic under `reference/`. Pick the row whose question fits the task; the table of the failure forms above, the pitfalls and the layer rules stay in this index.

| Question the leaf answers | Leaf |
| --- | --- |
| How do I write the door with the answer closures (AnswerFindings, AnswerOutcome, AnswerThrowable, LogFailure), what does the wire contract fix, and how does ProjectOutcome cut an `@outcome` answer? | reference/door-and-outcome.md |
| How do I map array-shaped input (path/query values, decoded JSON body) to one 400 with all faults - ReadRequired, accept closures, FindUnknownKeys, AnswerInvalidRequest? | reference/request-mapper.md |
| How do I turn the reply of another Jardis domain back into a DomainResponseInterface - DecodeAnswer, NoJardisAnswerException? | reference/decode-foreign-answer.md |
| What is the exact public surface (constructor, `__invoke`, constants) of one of the 70 classes? | reference/class-reference.md |
| Which status does MapThrowable give for which thrown failure, and what does BuildResponseData put into `data` per answer? | reference/mapping-tables.md |

## Pitfalls

| # | Pitfall | Evidence |
|---|---------|----------|
| 1 | The package never imports the process engine (M5); the door step results are plain values (`InputFindings`, `MappedFailure`, `bool`) | `tests/Integration/DecouplingTest.php` |
| 2 | `MapThrowable` with `$owner = false` throws only `TransientConflictException` and 5xx causes; it never throws a 4xx - markers (conflict, validation) are always mapped (a rejection is an answer, not an exception) | `Handler/Failure/MapThrowable.php` match arms |
| 3 | The owner is decided by the caller (no transaction open on entry), not by the package; `LogCause` logs what it is given - call it once, only for the owner and only for a 5xx; a cause with the marker `ReportedFailureExceptionInterface` is skipped (already reported) | `Handler/Failure/LogCause.php` |
| 4 | Valid input is exactly `fields === []`; `errors` is only the validator's texts. A result that is invalid but yields no field findings reads as valid | `Data/InputFindings.php`, `Handler/Door/ValidateInput.php` |
| 5 | The package produces no 401 (3.0.0) and no 403 on its own: the delivery layer answers them before the domain runs. A door that has to answer a refusal as a value calls `AnswerForbidden` (403, `{@type: forbidden, context}`, `errors` empty); `MapThrowable` and `BuildFailureOutcome` still make no 403. A node reject with the declared status 401/403 is flat like any other 4xx; a thrown access marker is an unknown failure (500 for the owner) | `Handler/Response/BuildResponseData.php`, `Handler/Failure/MapThrowable.php` |
| 6 | `BuildResponseData` does not project dates; hand the result to `ProjectWire` before the envelope | `Handler/Response/BuildResponseData.php` docblock |
| 7 | A 4xx without a failure form (401, 403, 405) answers `{messageKey, context}` unless `declared`; the key reaches the wire only when declared | `Handler/Failure/BuildFailureOutcome.php` |
| 8 | `BuildFailureForm` cuts the context keys it knows (`fields`; `reason/aggregate/violated`; `rule`) - the rest travels as `context` of the form | `Handler/Failure/BuildFailureForm.php` |
| 9 | `ReadResult::ofList` with `total` smaller than the items and `ofCappedList` with `cap` smaller than the items throw `\InvalidArgumentException`; `ofCappedList` reports at most `cap` as `total()` and `isCapped()` says the cap was hit | `Data/ReadResult.php` |
| 10 | A calendar accept closure without its `ValueValidatorInterface` or `AnswerInvalidRequest` without its clock does not construct - there is no default, the package has no runtime dependency on `validation`/`scheduling`; pass them (`new BodyDate(new DateTime())`, `new AnswerInvalidRequest($clock)`) | `Handler/Request/Accept/BodyDate.php`, `Handler/Answer/AnswerInvalidRequest.php` |
| 11 | The wire contract file is the one shipped test path; changing it is a breaking change of the wire (major version) | `.gitattributes`, `tests/Smoke/WireContractTest.php` |
| 12 | `ProjectOutcome` fails loudly on a 2xx answer without `@outcome` or with an unknown outcome - it never falls back to a default form | `Handler/Response/ProjectOutcome.php` |

## Layer rules

- Application layer composes the door steps in the fixed order; the domain core never imports response classes
- Steps stay single closures with one `__invoke`; composition (order, owner decision, transaction) is the door's, not the package's
- A capability the package lacks is built in the package first, never imitated in generated code or project code
- Integration tests with the real validator; mock ports only, the process engine on the other side is a stand-in
