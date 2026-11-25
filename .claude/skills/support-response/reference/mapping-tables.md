# Continuation — MapThrowable table and BuildResponseData per answer

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
| 2xx | `@type` (process name, lcfirst) + public fields of the response; a process answer carries `@outcome` as its first field (set by `MarkOutcome` of `workflow`); a `Reference` answers `@type` + identifier ⇒ root + children |
| 400/403/404/409/422 | the form's data as in the table above; the declared message key joins every 4xx last, the 422 always carries its own; an empty `context` is `{}` |
| other 4xx (401 and 403 included) | declared: the response fields as given plus the key; undeclared: `[]` |
| 5xx | `[]` |

Dates stay as they are: the projection to the wire (RFC 3339, UTC) is the caller's step through `jardissupport/data` `ProjectWire` (from 1.4.0).

