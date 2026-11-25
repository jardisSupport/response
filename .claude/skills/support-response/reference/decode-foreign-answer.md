# Continuation — reading the answer of another domain (DecodeAnswer)

## Reading the answer of another domain (from 4.3.0)

Hand-written code that called another Jardis domain over HTTP turns the reply back into a `DomainResponseInterface` with `DecodeAnswer` - body text and status code in, no PSR-7 type, no constructor. It is the one class of the package (with `NoJardisAnswerException`) that domain code may import.

```php
try {
    $answer = (new DecodeAnswer())((string) $reply->getBody(), $reply->getStatusCode());   // 200..500 of the envelope, 204 without body
} catch (NoJardisAnswerException $e) {
    // not JSON / key missing / HTTP status != envelope status / status outside ResponseStatus (502, 302): a transport failure, not an answer
}
```

401, 403 and 405 come from the delivery layer of the other domain in the same envelope; their `errors` (texts and objects there) are normalised to a list of texts per key, so those bytes are not written back identically. Events are not in the envelope: the response carries none.

