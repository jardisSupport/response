<?php

declare(strict_types=1);

namespace JardisSupport\Response\Handler\Response;

use JardisSupport\Contract\Kernel\DomainResponseInterface;
use JardisSupport\Contract\Kernel\ResponseStatus;
use JardisSupport\Response\Data\Response\DomainResponse;
use JardisSupport\Response\Exception\NoJardisAnswerException;
use stdClass;

/**
 * Turns the answer of another Jardis domain - body text and status code - back into a
 * `DomainResponseInterface`: the counterpart of the builders of the envelope `{status, data, errors, meta}`.
 *
 * - The input is text and a number, no HTTP message type: the caller hands in what its client returned.
 * - A 204 carries no body and answers an empty response with status 204.
 * - Every other status needs a body that is JSON with the four keys `status`, `data`, `errors`, `meta`
 *   (`data`, `errors`, `meta` as objects), and the `status` of the envelope must equal the given status.
 *   The status must be one of {@see ResponseStatus}. Anything else is not an answer of a Jardis domain:
 *   {@see NoJardisAnswerException}. A fifth key is not read.
 * - `data` and `meta` come back as arrays, nested objects as arrays too; only an empty object stays an
 *   object (`stdClass`), so that it is written as `{}` again, exactly as the builders write it.
 * - One shape rule for every object at every depth of `data` and `meta` (and for the envelope and `errors` where it
 *   applies): the keys of an object are names. A non-empty object whose keys are `0`, `1`, ... in this order without
 *   a gap would read as a PHP list and be written as a JSON list, so it is {@see NoJardisAnswerException} - on top
 *   as well as nested. A real list (`[...]`) stays a list, `[]` stays an empty list, `{}` stays an empty object
 *   (`stdClass`); an object with a name beside the numbers, with a gap or in another order stays an object.
 * - A key that starts with a NUL byte (`"\u0000x"`) is {@see NoJardisAnswerException} at every depth of the envelope,
 *   `data`, `meta` and `errors`: PHP reserves such property names for mangled private/protected properties and
 *   refuses to decode them into an object (`JSON_ERROR_INVALID_PROPERTY_NAME`). A NUL byte elsewhere in a key is fine.
 * - A number without a JSON form (`1e999` reads as infinity, as does `-1e999`) is no value a domain writes: at any
 *   depth of `data`, `meta` and `errors` it is {@see NoJardisAnswerException}, because writing it back would fail
 *   with a foreign exception. What comes back without that exception can be written into the envelope again.
 * - `errors` comes back in the contract type, a list of texts per key: a text becomes a list with one entry,
 *   a list keeps its entries, any other value (number, object) becomes one entry holding its JSON text. A key
 *   of `errors` is the name of a context, a text: a numeric key (`{"0": ...}`) is not the contract type and is
 *   {@see NoJardisAnswerException}. The delivery layer of a domain answers 401 and 403 with texts and objects
 *   there; those bytes are therefore not written back identically.
 * - Events are not part of the envelope: the response carries none.
 *
 * The closure knows no field name and no status rule of its own.
 *
 * Atomic closure - one entry point.
 */
final class DecodeAnswer
{
    private const KEYS = ['status', 'data', 'errors', 'meta'];

    private const TEXT_FLAGS = JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        | JSON_PRESERVE_ZERO_FRACTION;

    /**
     * @throws NoJardisAnswerException when body and status are not the answer of a Jardis domain
     */
    public function __invoke(string $body, int $status): DomainResponseInterface
    {
        $known = ResponseStatus::tryFrom($status)
            ?? throw new NoJardisAnswerException(
                sprintf('Status %d is not a status of the response envelope.', $status)
            );

        if ($known === ResponseStatus::NoContent) {
            return $body === ''
                ? new DomainResponse($known, [], [], [], [])
                : throw new NoJardisAnswerException('An answer with status 204 carries no body.');
        }

        $envelope = $this->envelope($body);
        if ($envelope->status !== $status) {
            throw new NoJardisAnswerException(
                sprintf('The HTTP status %d differs from the status of the envelope.', $status)
            );
        }

        return new DomainResponse(
            $known,
            $this->fields($envelope, 'data'),
            [],
            $this->errors($this->part($envelope, 'errors')),
            $this->fields($envelope, 'meta')
        );
    }

    private function envelope(string $body): stdClass
    {
        try {
            $value = json_decode($body, false, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $failure) {
            throw new NoJardisAnswerException(
                $failure->getCode() === JSON_ERROR_INVALID_PROPERTY_NAME
                    ? 'A key of the body starts with a NUL byte: PHP reserves such names, no domain writes one.'
                    : 'The body is not JSON: ' . $failure->getMessage(),
                0,
                $failure
            );
        }

        if (!$value instanceof stdClass) {
            throw new NoJardisAnswerException('The body is not a JSON object.');
        }
        foreach (self::KEYS as $key) {
            if (!property_exists($value, $key)) {
                throw new NoJardisAnswerException(sprintf('The envelope lacks the key "%s".', $key));
            }
        }

        return $value;
    }

    private function part(stdClass $envelope, string $key): stdClass
    {
        $part = $envelope->{$key};
        if (!$part instanceof stdClass) {
            throw new NoJardisAnswerException(sprintf('The key "%s" of the envelope is not a JSON object.', $key));
        }

        return $part;
    }

    /**
     * The members of `data` or `meta`; the shape rule of {@see members()} holds for them as for every object below.
     *
     * @return array<string, mixed>
     */
    private function fields(stdClass $envelope, string $key): array
    {
        /** @var array<string, mixed> $fields a numeric key with a name beside it stays an object */
        $fields = $this->members($this->part($envelope, $key));

        return $fields;
    }

    /**
     * The members of one object, at any depth. An object whose keys are `0`, `1`, ... without a gap reads as a list in
     * PHP and would be written as a JSON list, which is not the object it was: no domain writes that.
     *
     * @return array<int|string, mixed> PHP turns the key `"0"` into the number 0
     */
    private function members(stdClass $object): array
    {
        /** @var array<int|string, mixed> $members the key "0" is stored as the number 0 */
        $members = [];
        foreach (get_object_vars($object) as $name => $value) {
            $members[(string) $name] = $this->plain($value);
        }
        if ($members !== [] && array_is_list($members)) {
            throw new NoJardisAnswerException('An object of the envelope has the keys of a list, not names.');
        }

        return $members;
    }

    private function plain(mixed $value): mixed
    {
        if (is_float($value) && !is_finite($value)) {
            throw new NoJardisAnswerException('A number of the envelope has no JSON form (infinite or not a number).');
        }

        if ($value instanceof stdClass) {
            return get_object_vars($value) === [] ? $value : $this->members($value);
        }

        return is_array($value) ? array_map($this->plain(...), $value) : $value;
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function errors(stdClass $object): array
    {
        $errors = [];
        foreach (get_object_vars($object) as $name => $value) {
            if (is_int($name)) {
                throw new NoJardisAnswerException(
                    sprintf('The key "%d" of "errors" is no text: the name of a context is a text.', $name)
                );
            }
            $errors[$name] = array_map($this->text(...), is_array($value) ? array_values($value) : [$value]);
        }

        return $errors;
    }

    private function text(mixed $value): string
    {
        if (is_string($value)) {
            return $value;
        }

        try {
            return json_encode($value, self::TEXT_FLAGS);
        } catch (\JsonException $failure) {
            throw new NoJardisAnswerException('An entry of "errors" cannot be written as text.', 0, $failure);
        }
    }
}
