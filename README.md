# jardissupport/response

Response building for Jardis request-response use cases: what a finished use case answers, in one shape. The package holds the response objects, the failure forms, the `data` of the response envelope `{status, data, errors, meta}`, the read answers and the door steps in front of a use case (input check, actor check, mapping of thrown failures, logging of the 5xx cause). It does not run a process and has no dependency on the process engine.

Status: 1.0.0 in preparation, not released.

## Groups (`src/`)

| Group | Content |
|---|---|
| Response objects | `Data/Response/ContextResponse`, `Data/Response/DomainResponse`, `Handler/Response/TransformResponse` (context response to `DomainResponseInterface`, injected PSR-20 clock) |
| Failure forms | `Data/Failure/{FailureForm, ValidationFailure, NotFound, Conflict, RuleViolation}`, `Handler/Failure/{BuildFailureForm, BuildFailureOutcome, ValidationPayload, NotFoundPayload, ConcurrencyConflictPayload, RuleViolationPayload, DescribeFieldFinding}` |
| Envelope data | `Handler/Response/BuildResponseData` (the `data` of the envelope from status, response, declared message key and process name; no wire projection) |
| Door steps | `Handler/Door/{ValidateInput, CheckActor}`, `Handler/Failure/{MapThrowable, LogCause}`; results `Data/InputFindings`, `Data/MappedFailure` |
| Read answers | `Data/ReadResult` (list with total, or one optional item), `Data/Reference` (root identifier plus child references) |
| Configuration error | `Exception/ConfigurationException` (unusable runtime wiring) |

25 classes, every handler `final` with exactly one public entry point (`__invoke`).

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

## Failure forms on the wire

The envelope is `{status, data, errors, meta}`; the wire contract (`tests/Smoke/Contract/wire-contract.json`) fixes its bytes per status (200, 200-empty, 400, 401, 404, 409, 422, 500) and ships with the package. 401 and 500 answer `data: {}`.

**400** `ValidationFailure`

```json
{"status": 400, "data": {"@type": "validation", "fields": [
  {"field": "email", "reason": "missing", "message": "Field is required.", "messageKey": "validation.missing"},
  {"field": "items[1].qty", "reason": "invalid", "message": "Must be at least 1", "messageKey": "validation.invalid"}]}}
```

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

Requires PHP 8.3+, `jardissupport/contracts` ^2.13, `psr/log` and `psr/clock` (see Requirements). The transaction bracket (`jardissupport/repository`) and the process engine (`jardissupport/workflow`) are separate packages the door uses next to this one; this package depends on neither.

## Quick Start

The door of a use case in front of the process engine, the way the generator emits it for a process (classes shown without a namespace). `$this->handle(Class::class, ...named arguments)` resolves a step through the kernel container (the same as `new Class(...)`); `$this->payload()`, `$this->actor()`, `$this->result()`, `$this->transformer()` and `$this->resource()` are the request-response context of the generated handler.

```php
use JardisSupport\Contract\Kernel\EventScope;
use JardisSupport\Contract\Kernel\ResponseStatus;
use JardisSupport\Repository\Handler\Transaction\ResolveConnection;
use JardisSupport\Repository\Handler\Transaction\RunInTransaction;
use JardisSupport\Response\Handler\Door\CheckActor;
use JardisSupport\Response\Handler\Door\ValidateInput;
use JardisSupport\Response\Handler\Failure\BuildFailureOutcome;
use JardisSupport\Response\Handler\Failure\LogCause;
use JardisSupport\Response\Handler\Failure\MapThrowable;
use JardisSupport\Response\Handler\Failure\ValidationPayload;
use JardisSupport\Response\Handler\Response\BuildResponseData;
use JardisSupport\Workflow\Data\ProcessOutcome;
use JardisSupport\Workflow\ProcessRunner;
use Throwable;

$definition = (new IssueInvoiceForOrderDefinition())();
$input = $this->payload();

// 1. input check: valid exactly when fields === [], else 400
$findings = ($this->handle(ValidateInput::class, validator: $validator))($input);
if ($findings->fields !== []) {
    foreach ($findings->errors as $error) {
        $this->result()->addError($error);
    }
    $this->result()->setData(
        ($this->handle(BuildResponseData::class))(
            ResponseStatus::ValidationError,
            ($this->handle(ValidationPayload::class))('validation', $findings->fields),
            null,
            ''
        )
    );

    return ($this->transformer())($this->result(), ResponseStatus::ValidationError);
}

// 2. actor check: false = 401
if (!($this->handle(CheckActor::class))($definition->getActorClass() !== null, $this->actor())) {
    $this->result()->setData([]);

    return ($this->transformer())($this->result(), ResponseStatus::Unauthorized);
}

// 3. transaction bracket (jardissupport/repository), 4. engine (jardissupport/workflow)
$connection = ($this->handle(ResolveConnection::class))($this->resource()->dbConnection());
$owner = $connection?->inTransaction() !== true;

try {
    $depth = $this->depth();
    $run = fn(): ProcessOutcome => ($this->handle(ProcessRunner::class))->run(
        $definition,
        $input,
        $this->actor(),
        fn(string $class): object => $this->handle($class),
        $depth
    );
    $outcome = ($this->handle(RunInTransaction::class))(
        $connection,
        'sp_' . $depth,
        $run,
        static fn(ProcessOutcome $outcome): bool => $outcome->status()->value >= 400,
        $depth > 0
    );
    foreach ($outcome->events() as $event) {
        $this->result()->addEvent($event, EventScope::Domain);
    }
    foreach ($outcome->errors() as $error) {
        $this->result()->addError($error);
    }
    $status = $outcome->status();
    $response = $status->value >= 400
        ? ($this->handle(BuildFailureOutcome::class))(
            $status,
            (string) $outcome->messageKey(),
            $outcome->context(),
            $outcome->errors(),
            true
        )
        : $outcome->response();

    // 5. the data of the envelope, then the DomainResponse {status, data, errors, meta}
    $this->result()->setData(
        ($this->handle(BuildResponseData::class))($status, $response, $outcome->messageKey(), $definition->getName())
    );

    return ($this->transformer())($this->result(), $status);
} catch (Throwable $e) {
    // 4. a thrown failure: 4xx mapped, 5xx cause logged once by the owner
    $failure = ($this->handle(MapThrowable::class))($e, $owner, $definition->getName());
    if ($owner && $failure->status->value >= ResponseStatus::InternalError->value) {
        ($this->handle(LogCause::class, logger: $this->resource()->logger()))($e, $definition->getName());
    }
    $this->result()->setData(
        ($this->handle(BuildResponseData::class))($failure->status, $failure->response, null, '')
    );

    return ($this->transformer())($this->result(), $failure->status);
}
```

`$validator` is a `ValidatorInterface` (the generated handler builds an `ObjectValidator` over the validator registry of the process). `TransformResponse` is what `$this->transformer()` returns: it turns the context response into the `DomainResponseInterface`. For a read answer see `Data/ReadResult` and `Data/Reference`.

## Requirements

`php >=8.3`, `jardissupport/contracts ^2.13`, `psr/log ^3.0`, `psr/clock ^1.0`. Dev: `jardissupport/validation`, `jardissupport/scheduling`. Never `jardissupport/workflow`.

## Development

Always through `make`: `make install` · `make phpunit` · `make phpstan` · `make phpcs` · `make shell`.
