# Origin of the counterpart bytes

The four `.json` files next to this one are the bytes (no trailing newline, compact, as `json_encode` without flags writes
them) that the delivery layer of another Jardis domain, `jardiscore/app`, sends for the statuses the wire contract does not
hold. They are read by `tests/Integration/Response/DecodeAnswerTest.php` and never edited by hand. Collected 2026-11-13
(phase P0.4 of the builder plan `docs/fremd-domain-rueckbau`).

Source repository for all four: `jardis/core/app`, tag `v3.3.1`, commit `4eba2ea61672b3d5ae18235f94f51830f3963f00`
(working tree clean at collection).

| File | Status | Where the bytes come from |
|---|---|---|
| `201.json` | 201 | Produced by running the real `App` with `MapDomainResponse` and `BuildErrorResponse` (`src/Handler/Response/MapDomainResponse.php:50-65`, `BuildErrorResponse.php:60`): the answer of a create handler passes through unchanged. Input of the run: the `data` and `meta` of the handler bytes in the builder fixture `jardis/tools/builder/tests/Fixture/envelope/aggregate-door/created.json` (commit `dd4367630f0ccfdc40d6a35a8fbcdae0a9661286`); `core/app` writes `meta.duration` as `0`. `core/app` itself sets 201 never. |
| `401.json` | 401 | Produced by running the real `App` with the global `RequireSession` and a request without a token (`src/Middleware/RequireSession.php:64-70`, `tests/Integration/Middleware/RequireSessionTest.php:171-182` asserts keys and message only). Header `WWW-Authenticate: Bearer`. |
| `403.json` | 403 | Literal of `tests/Integration/Middleware/RequireRightTest.php:76-87` (`testSessionLackingTheRightIsAnswered403WithTheExactBytes`), written by `src/Middleware/RequireRight.php:62-72`. |
| `405.json` | 405 | Literal of `tests/Integration/Handler/Response/BuildErrorResponseTest.php:41-50` (line 49); also produced by running the real `App` with a `DELETE` on a route that knows `GET` and `POST` (`src/Handler/Routing/DispatchAndRespond.php:78-84`, header `Allow`). |

Runs were made with the PHP 8.3 image of the package (`headgent/phpcli:8.3`), `core/app` mounted read only, `vendor/` of `core/app`.
