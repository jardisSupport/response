<?php

declare(strict_types=1);

namespace JardisSupport\Response\Handler\Response;

use JardisSupport\Response\Exception\NoJardisAnswerException;

/**
 * Refuses a JSON body that `json_decode` would read with a loss: what is accepted survives the way back.
 *
 * Three forms of the text are lost by the decoder without a trace, at any depth of the body (the envelope, `data`,
 * `meta`, `errors` and a key beside them alike):
 * - an integer beyond 64 bits (`99999999999999999999`, `-9223372036854775809`) is read as a float: the value is
 *   rounded and the type is another one. `PHP_INT_MAX` and `PHP_INT_MIN` fit and stay integers; a number written with
 *   a fraction or an exponent (`1.5`, `1e3`, `1.0`) is a float in the text and stays one;
 * - a number with a non-zero mantissa that underflows to `0.0` (`1e-400`, `0.1e-999`) is read as zero: another value.
 *   The border is the zero, not the last digit: any other decimal text is read as the nearest float, and a number
 *   written with more digits than a float holds (`0.1000000000000000055511151231257827`) is rounded the way every
 *   decoder rounds it - that is no loss of the answer and stays one. A number that overflows to infinity is refused
 *   by {@see DecodeAnswer} (no JSON form). `0`, `0.0`, `-0.0`, `0e-400`, `1e-300` and `5e-324` (the smallest float) are
 *   no loss;
 * - a key that occurs twice in one object (`{"a":1,"a":2}`) keeps only the last value; keys are compared after the
 *   escapes are resolved (`"a"` and `"a"` are one key), by bytes (`"a"` and `"A"` are two).
 *
 * The body is read as a token stream, not searched as text: a string is one token, so numbers and keys inside a string
 * value (`"x":"\"a\":1,\"a\":2"`, `"n":"99999999999999999999"`) are never looked at. The scan is a linear walk with
 * `strspn`/`strcspn` - no regular expression, so no backtrack, JIT-stack or recursion limit can end it half way. A
 * check that could not run to the end of the body never means "accepted": if the walk meets a byte it cannot place, or
 * ends inside a string or an open container, the body is refused (fail closed). The body must be valid JSON already
 * (`json_decode` ran first); the closure makes no statement about the syntax.
 *
 * Atomic closure - one entry point.
 */
final class RequireLosslessBody
{
    private const SPACE = " \t\r\n";

    /** Bytes that end a number or literal. */
    private const DELIMITERS = " \t\r\n\"{}[],:";

    /**
     * @throws NoJardisAnswerException when the body holds an integer beyond 64 bits, a number that underflows to zero,
     *                                 a key twice in one object - or when the scan cannot reach the end of the body
     */
    public function __invoke(string $body): void
    {
        /** @var list<bool> $isObject one entry per open container: an object (true) or a list (false) */
        $isObject = [];
        /** @var array<int, array<string, true>> $seen the keys seen so far, per depth of an open object */
        $seen = [];
        $expectKey = false;
        $offset = 0;
        $length = strlen($body);

        while ($offset < $length) {
            $offset += strspn($body, self::SPACE, $offset);
            if ($offset >= $length) {
                break;
            }
            $byte = $body[$offset];

            if ($byte === '"') {
                $end = $this->endOfString($body, $offset);
                if ($expectKey) {
                    $key = $this->keyOf(substr($body, $offset, $end - $offset));
                    $this->requireNew($seen[count($isObject) - 1], $key);
                    $expectKey = false;
                }
                $offset = $end;
            } elseif (str_contains('{}[],:', $byte)) {
                if ($byte === '{' || $byte === '[') {
                    $isObject[] = $byte === '{';
                    $seen[count($isObject) - 1] = [];
                } elseif ($byte === '}' || $byte === ']') {
                    unset($seen[count($isObject) - 1]);
                    array_pop($isObject);
                }
                $expectKey = $byte === '{' || ($byte === ',' && $isObject !== [] && end($isObject));
                $offset++;
            } else {
                $size = strcspn($body, self::DELIMITERS, $offset);
                $this->requireLosslessNumber(substr($body, $offset, $size));
                $offset += $size;
            }
        }

        if ($isObject !== []) {
            throw new NoJardisAnswerException('The check of the body could not reach its end: a container stays open.');
        }
    }

    /**
     * The offset after the closing quote of the string that starts at `$start`.
     */
    private function endOfString(string $body, int $start): int
    {
        $length = strlen($body);
        $offset = $start + 1;
        while ($offset < $length) {
            $offset += strcspn($body, '"\\', $offset);
            if ($offset >= $length) {
                break;
            }
            if ($body[$offset] === '"') {
                return $offset + 1;
            }
            $offset += 2;
        }

        throw new NoJardisAnswerException('The check of the body could not reach its end: a string stays open.');
    }

    /**
     * The key a string token names, escapes resolved.
     */
    private function keyOf(string $token): string
    {
        if (!str_contains($token, '\\')) {
            return substr($token, 1, -1);
        }
        try {
            return (string) json_decode($token, false, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $failure) {
            throw new NoJardisAnswerException('The check of the body could not read a key.', 0, $failure);
        }
    }

    /**
     * @param array<string, true> $seen
     */
    private function requireNew(array &$seen, string $key): void
    {
        if (isset($seen[$key])) {
            throw new NoJardisAnswerException(
                'An object of the body holds a key twice: the decoder would keep only the last value.'
            );
        }
        $seen[$key] = true;
    }

    /**
     * A number or a literal (`true`, `false`, `null`): only a number can lose something.
     */
    private function requireLosslessNumber(string $token): void
    {
        $digits = $token[0] === '-' ? substr($token, 1) : $token;
        if ($digits === '' || !ctype_digit($digits[0])) {
            return;
        }

        if (ctype_digit($digits)) {
            if ($this->isBeyondInt64($digits, $token[0] === '-')) {
                throw new NoJardisAnswerException(
                    'The body holds an integer beyond 64 bits: it would be read as a float, not as the number it was.'
                );
            }

            return;
        }

        $mantissa = substr($digits, 0, strcspn($digits, 'eE'));
        if (trim($mantissa, '0.') !== '' && (float) $token === 0.0) {
            throw new NoJardisAnswerException(
                'The body holds a number that underflows to zero: it would be read as 0.0, not as the number it was.'
            );
        }
    }

    private function isBeyondInt64(string $digits, bool $negative): bool
    {
        $limit = $negative ? substr((string) PHP_INT_MIN, 1) : (string) PHP_INT_MAX;

        return strlen($digits) > strlen($limit) || (strlen($digits) === strlen($limit) && strcmp($digits, $limit) > 0);
    }
}
