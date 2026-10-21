# jardissupport/response

Response building for Jardis request-response use cases: what a finished use case answers, in one shape. The package holds the response objects, the failure forms, the `data` of the response envelope `{status, data, errors, meta}`, the read answers and the door steps in front of a use case (input check, actor check, mapping of thrown failures, logging of the 5xx cause). It does not run a process and has no dependency on the process engine.

Status: 1.0.0 in preparation, not released.

## Groups (`src/`)

| Group | Content |
|---|---|
| Response objects | `Data/Response/ContextResponse`, `Data/Response/DomainResponse`, `Handler/Response/TransformResponse` (context response to `DomainResponseInterface`, injected PSR-20 clock) |
| Failure forms | `Data/Failure/{FailureForm, ValidationFailure, NotFound, Conflict, RuleViolation}`, `Handler/Failure/{BuildFailureForm, BuildFailureOutcome, ValidationPayload, NotFoundPayload, ConcurrencyConflictPayload, RuleViolationPayload, DescribeFieldFinding}` |
| Envelope data | `Handler/Response/BuildResponseData` (the `data` of the envelope from status, response, declared message key and process name; no wire projection) |
| Door steps | `Handler/Door/{ValidateInput, CheckActor}`, `Handler/Failure/{MapThrowable, LogCause, LogFailure}`; results `Data/InputFindings`, `Data/MappedFailure` |
| Answer closures | `Handler/Answer/{AnswerFindings, AnswerUnauthorized, AnswerNotFound, AnswerRuleViolation, AnswerOutcome, AnswerThrowable, AnswerCommand, AnswerRead}`, `Handler/Response/ProjectAnswer` (see below): each turns one situation of a door into the finished `DomainResponseInterface` (data into the context response, then the transformer) |
| Read and write answers | `Data/ReadResult` (list with total, or one optional item), `Data/Reference` (root identifier under its wire name plus child references) |
| Rule outcome | `Data/RuleResult` (`pass()` or `reject(rule, messageKey, context)`; answered as 422 by `AnswerRuleViolation`) |
| Configuration error | `Exception/ConfigurationException` (unusable runtime wiring) |

36 classes, every handler `final` with exactly one public entry point (`__invoke`).

## Door steps in order

The door of a use case (the generated handler) composes the steps. The package ships the steps, not the composition.

| # | Step | Does | Answer |
|---|---|---|---|
| 1 | `ValidateInput` | checks the input with the injected `ValidatorInterface` | `InputFindings`; valid exactly when `fields === []`, else the door answers 400 |
| 2 | `CheckActor` | `required` and no actor | `false` = the door answers 401 |
| 3 | transaction bracket | not in this package - `jardissupport/repository` | - |
| 4 | `MapThrowable`, `LogCause` | maps a thrown failure to status and response; logs a 5xx cause (skips a cause marked as already reported) | `MappedFailure {status, response}`; `LogCause` returns nothing |
| 5 | `BuildResponseData`, `TransformResponse` | the `data` of the envelope, then the `DomainResponse` | `{status, data, errors, meta}` |

`MapThrowable` knows the owner: `$owner = true` (no transaction was open on entry) answers a stale write with 409, the two contract markers (`ConcurrencyConflictExceptionInterface` with 409, `ValidationExceptionInterface` with 400) with their own fields and everything else with 500. `$owner = false` (a nested run) throws only the `TransientConflictException` and 5xx causes on, so the owner repeats the work and logs the cause once. Every 4xx is always mapped, never thrown.

## Answer closures

A door answers by calls only: no loop, no status comparison, no data assembly. Every closure takes the result of the run (`$this->result()`) and the transformer (`$this->transformer()`, it carries the clock and the class version) and returns the finished `DomainResponseInterface`.

| Closure | Situation | Status |
|---|---|---|
| `AnswerFindings($result, $transform, InputFindings)` | the input check found failing fields | 400 |
| `AnswerUnauthorized($result, $transform)` | the use case needs an actor and none is there | 401 |
| `AnswerNotFound($result, $transform, array $context)` | the addressed aggregate does not exist | 404 |
| `AnswerRuleViolation($result, $transform, RuleResult)` | a business rule rejected the request | 422 |
| `AnswerOutcome($result, $transform, AnswerableOutcomeInterface, string $processName)` | a process run ended: its Domain events and errors, then the response (200) or the failure form of the reject (4xx) | outcome status |
| `AnswerThrowable($result, $transform, Throwable, bool $owner, string $aggregate)` | a thrown failure: maps it (`MapThrowable`), logs nothing | 400 / 409 / 500, or thrown on |
| `AnswerCommand($result, $transform, Reference, string $aggregate)` | a finished write: the reference, never the objects | 200 |
| `AnswerRead($result, $transform, ReadResult, string $type)` | a single read: `@type` plus the item's public fields, empty data when nothing was found | 200 |

`LogFailure($cause, $process, $owner, $answer)` is the logging step, deliberately not part of any answer closure: it writes the cause (`LogCause`) only when the caller owns the run (`owner`, no transaction was open on entry) and the answer is a 5xx. A nested run throws the cause on (`AnswerThrowable` does), its owner logs it once.

Every closure is creatable without constructor arguments (`$this->handle(AnswerFindings::class)`); the collaborators are constructor defaults. `LogFailure` takes the logger by name: `$this->handle(LogFailure::class, logger: $this->resource()->logger())`. `AnswerOutcome` reads the outcome through `JardisSupport\Contract\Kernel\AnswerableOutcomeInterface` (contracts), so the package still knows no process engine.

`Reference` carries the wire name of the business identifier (`new Reference('ORD-1', [], 'orderNumber')` answers `{"@type": "order", "orderNumber": "ORD-1"}`); child references are answered under their keys - one child as its identifier (or `null`), a child list as a list. Without a name the root answers as `id`.

`ProjectAnswer($response, string ...$fields)` is the outer-door closure: it cuts a finished answer down to its outer contract. Inside, an answer may carry more than the outside promises (`{@type, id, orderNumber}` inside, `{@type, orderNumber}` outside). A 2xx answer keeps `@type` plus exactly the named fields that exist in its data (order as in the data; a missing field is not added, an unknown name is no error); status, errors, metadata and the events of every scope are carried over unchanged. A non-2xx answer comes back as the same instance. The closure knows no field name of its own - only the list it is given.

```php
$outer = ($this->handle(ProjectAnswer::class))($inner, 'orderNumber');   // {@type, orderNumber}, status and events as inside
```

## Failure forms on the wire

The envelope is `{status, data, errors, meta}`; the wire contract (`tests/Smoke/Contract/wire-contract.json`) fixes its bytes per status (200, 200-empty, 400, 401, 404, 409, 422, 500) and ships with the package. 401 and 500 answer `data: {}`.

**400** `ValidationFailure`

```json
{"status": 400, "data": {"@type": "validation", "fields": [
  {"field": "email", "reason": "missing", "message": "Field is required.", "messageKey": "validation.missing"},
  {"field": "items[1].qty", "reason": "invalid", "message": "Must be at least 1", "messageKey": "validation.invalid"}]}}
```

The reasons of a field finding are `missing`, `invalid` (validator), `type` and `unknown` (request reader); each is answered with the key `validation.<reason>` and, when the caller delivered no text, its standard text (`Field is required.`, `Field is invalid.`, `Field has an invalid type.`, `Unknown field`). Any other reason is answered as `invalid`. `DescribeFieldFinding` is the one place of this vocabulary.

**404** `NotFound`

```json
{"status": 404, "data": {"@type": "notFound", "context": {"orderNumber": "ORD-404"}, "messageKey": "order.notFound"}}
```

**409** `Conflict`

```json
{"status": 409, "data": {"@type": "concurrencyConflict", "reason": "stale_write", "aggregate": "PlaceOrder",
  "context": {}, "violated": []}}
```

**422** `RuleViolation`

```json
{"status": 422, "data": {"@type": "ruleViolation", "rule": "OrderMustBeOpen", "messageKey": "order.rule.open",
  "context": {"status": "closed"}}}
```

The `messageKey` a node declared joins the data of a 404/409/400 last; the 422 form always carries its own.

## Installation

```bash
composer require jardissupport/response
```

Requires PHP 8.3+, `jardissupport/contracts` ^2.15, `psr/log` and `psr/clock` (see Requirements). The transaction bracket (`jardissupport/repository`) and the process engine (`jardissupport/workflow`) are separate packages the door uses next to this one; this package depends on neither.

## Quick Start

The door of a use case in front of the process engine, the way the generator emits it for a process (classes shown without a namespace). `$this->handle(Class::class, ...named arguments)` resolves a step through the kernel container; `$this->payload()`, `$this->actor()`, `$this->result()`, `$this->transformer()` and `$this->resource()` are the request-response context of the generated handler. The door only calls and branches on what a call returns.

```php
$definition = $this->handle(IssueInvoiceForOrderDefinition::class)();
$input = $this->payload();

// 1. input check: failed() = 400
$findings = ($this->handle(ValidateInput::class, validator: $validator))($input);
if ($findings->failed()) {
    return ($this->handle(AnswerFindings::class))($this->result(), $this->transformer(), $findings);
}

// 2. actor check: false = 401
if (!($this->handle(CheckActor::class))($definition->getActorClass() !== null, $this->actor())) {
    return ($this->handle(AnswerUnauthorized::class))($this->result(), $this->transformer());
}

// 3. transaction bracket (jardissupport/repository) around 4. the engine (jardissupport/workflow);
//    $owned = no transaction was open on entry, $outcome = the ProcessOutcome (an AnswerableOutcomeInterface)
try {
    // ... run the process in the bracket, yielding $outcome and $owner ...

    // 5. the answer from the outcome: events, errors, data, status
    return ($this->handle(AnswerOutcome::class))(
        $this->result(),
        $this->transformer(),
        $outcome,
        $definition->getName()
    );
} catch (Throwable $e) {
    // a thrown failure: mapped to its answer (a nested run throws it on) ...
    $answer = ($this->handle(AnswerThrowable::class))(
        $this->result(),
        $this->transformer(),
        $e,
        $owner,
        $definition->getName()
    );
    // ... and the cause logged once by the owner, only for a 5xx
    ($this->handle(LogFailure::class, logger: $this->resource()->logger()))($e, $definition->getName(), $owner, $answer);

    return $answer;
}
```

`$validator` is a `ValidatorInterface` (the generated handler builds an `ObjectValidator` over the validator registry of the process). `TransformResponse` is what `$this->transformer()` returns: it turns the context response into the `DomainResponseInterface`. For a write answer see `AnswerCommand` and `Data/Reference`, for a read answer `AnswerRead` and `Data/ReadResult`.

## Requirements

`php >=8.3`, `jardissupport/contracts ^2.15`, `psr/log ^3.0`, `psr/clock ^1.0`. `AnswerOutcome` needs `AnswerableOutcomeInterface`, shipped since contracts 2.15. Dev: `jardissupport/validation`, `jardissupport/scheduling`, `jardissupport/factory` (proves that every answer closure is creatable by `handle()`). Never `jardissupport/workflow`.

## Development

Always through `make`: `make install` · `make phpunit` · `make phpstan` · `make phpcs` · `make shell`.
