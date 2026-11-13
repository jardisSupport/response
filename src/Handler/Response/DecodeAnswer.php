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
 * - `errors` comes back in the contract type, a list of texts per key: a text becomes a list with one entry,
 *   a list keeps its entries, any other value (number, object) becomes one entry holding its JSON text. The
 *   delivery layer of a domain answers 401 and 403 with texts and objects there; those bytes are therefore
 *   not written back identically.
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
            $this->members($this->part($envelope, 'data')),
            [],
            $this->errors($this->part($envelope, 'errors')),
            $this->members($this->part($envelope, 'meta'))
        );
    }

    private function envelope(string $body): stdClass
    {
        try {
            $value = json_decode($body, false, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $failure) {
            throw new NoJardisAnswerException('The body is not JSON: ' . $failure->getMessage(), 0, $failure);
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
     * @return array<string, mixed>
     */
    private function members(stdClass $object): array
    {
        $members = [];
        foreach (get_object_vars($object) as $name => $value) {
            $members[(string) $name] = $this->plain($value);
        }

        return $members;
    }

    private function plain(mixed $value): mixed
    {
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
            $errors[(string) $name] = array_map($this->text(...), is_array($value) ? array_values($value) : [$value]);
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
