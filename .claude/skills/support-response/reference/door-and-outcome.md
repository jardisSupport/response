# Continuation — the door with answer closures, wire contract, outcome projection

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

The wire contract `tests/Smoke/Contract/wire-contract.json` (12 cases, every 2xx case carries `@outcome` behind `@type`: 200, 200-empty, 200-merged, 200-answer-class, 200-datetime-offset (carrier: process `VoucherOverview`, `vouchers[].createdAt`), 400, 403, 404, 409, 422, 404-rule-subject, 500) ships with the package; the package test drives only the door steps, the proof with a generated handler is the consumer's.

## Outcome projection (`@outcome`, from 4.2.0)

A process that ends in one of several outcomes marks its 2xx answer with `@outcome` (written by `MarkOutcome` of `workflow`; this package only reads the literal `ProjectOutcome::KEY`). The outer door cuts the answer to the form of the outcome that ran:

```php
$outer = ($this->handle(ProjectOutcome::class))($inner, [
    'released' => new OutcomeForm(['orderNumbers', 'invoiceNumber']),
    'deferred' => new OutcomeForm(['reason']),
]);
```

Missing `@outcome` or an outcome without a form is a wiring fault (`ConfigurationException`, the door answers 500 through `MapThrowable`); non-2xx passes unchanged. Additive minor: `ProjectAnswer` is unchanged.

