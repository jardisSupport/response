# Continuation — request mapper (array-shaped input)

## Request mapper (array-shaped input, 400 with all faults)

For a transport that hands the door plain arrays (path/query values, decoded JSON body) - no PSR-7, no router. One reader per field collects `{field, reason}` findings (`missing`, `type`, `unknown`); the door answers once with all of them.

```php
$fields = [];
$name = (new ReadRequired())($body, 'name', new BodyStr(), '', $fields);              // absent = missing, refused = type, fallback stands in
$due  = (new ReadBodyNullable())($body, 'due', new BodyDate($validator), $fields);    // absent/null = null
$qty  = (new ReadTransportOptional())($query, 'qty', new TransportInt(), $fields);    // absent/''/null = null
(new FindUnknownKeys())($body, ['name', 'due'], $fields);                             // unknown keys, body order
if ($fields !== []) { return (new AnswerInvalidRequest($clock))($fields, $version); } // 400, meta.version
```

**Constructor dependencies (ports, no new runtime dependency):** the six calendar accept closures (`TransportDate|DateTime|Time`, `BodyDate|DateTime|Time`) take a `ValueValidatorInterface` (pass the `DateTime` validator of `jardissupport/validation`); `AnswerInvalidRequest` takes a PSR-20 `ClockInterface`. Both are required, so `Factory::get()` cannot create them without a registered instance; readers and the other accept closures are creatable without arguments. Not in this package (stay in `jardiscore/app`): `ParseJsonBody`, `CreateServerRequest`, `ResolveVersion`.

