<?php

declare(strict_types=1);

namespace JardisSupport\Response\Handler\Response;

use JardisSupport\Response\Exception\NoJardisAnswerException;

/**
 * Refuses a JSON body that `json_decode` would read with a loss: what is accepted survives the way back.
 *
 * Two forms of the text are lost by the decoder without a trace, at any depth of the body (the envelope, `data`,
 * `meta`, `errors` and a key beside them alike):
 * - an integer beyond 64 bits (`99999999999999999999`, `-9223372036854775809`) is read as a float: the value is
 *   rounded and the type is another one. `PHP_INT_MAX` and `PHP_INT_MIN` fit and stay integers; a number written with
 *   a fraction or an exponent (`1.5`, `1e3`, `1.0`) is a float in the text and stays one;
 * - a key that occurs twice in one object (`{"a":1,"a":2}`) keeps only the last value; keys are compared after the
 *   escapes are resolved (`"a"` and `"a"` are one key), by bytes (`"a"` and `"A"` are two).
 *
 * The body is read as a token stream, not searched as text: a string is one token, so numbers and keys inside a string
 * value (`"x":"\"a\":1,\"a\":2"`, `"n":"99999999999999999999"`) are never looked at. The body must be valid JSON
 * already (`json_decode` ran first); the closure makes no statement about the syntax.
 *
 * Atomic closure - one entry point.
 */
final class RequireLosslessBody
{
    /**
     * One token at the offset: a string (group 1), one of `{ } [ ] , :` (group 2) or a number or
     * literal (group 3).
     */
    private const TOKEN = '~\G[ \t\r\n]*+(?:("[^"\\\\]*+(?:\\\\.[^"\\\\]*+)*+")|([{}\[\],:])'
        . '|([^ \t\r\n"{}\[\],:]++))~s';

    /**
     * @throws NoJardisAnswerException when the body holds an integer beyond 64 bits or a key twice in one object
     */
    public function __invoke(string $body): void
    {
        /** @var list<bool> $isObject one entry per open container: an object (true) or a list (false) */
        $isObject = [];
        /** @var array<int, array<string, true>> $seen the keys seen so far, per depth of an open object */
        $seen = [];
        $expectKey = false;
        $offset = 0;

        while (preg_match(self::TOKEN, $body, $token, PREG_UNMATCHED_AS_NULL, $offset) === 1) {
            $offset += strlen($token[0]);

            if ($token[1] !== null) {
                if ($expectKey) {
                    $key = (string) json_decode($token[1], false, 512, JSON_THROW_ON_ERROR);
                    $this->requireNew($seen[count($isObject) - 1], $key);
                    $expectKey = false;
                }
            } elseif ($token[2] !== null) {
                $mark = $token[2];
                if ($mark === '{' || $mark === '[') {
                    $isObject[] = $mark === '{';
                    $seen[count($isObject) - 1] = [];
                } elseif ($mark === '}' || $mark === ']') {
                    unset($seen[count($isObject) - 1]);
                    array_pop($isObject);
                }
                $expectKey = $mark === '{' || ($mark === ',' && $isObject !== [] && end($isObject));
            } elseif ($this->isIntegerBeyondInt64((string) $token[3])) {
                throw new NoJardisAnswerException(
                    'The body holds an integer beyond 64 bits: it would be read as a float, not as the number it was.'
                );
            }
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

    private function isIntegerBeyondInt64(string $token): bool
    {
        if (preg_match('/^(-?)(\d+)$/', $token, $parts) !== 1) {
            return false;
        }
        $limit = $parts[1] === '-' ? substr((string) PHP_INT_MIN, 1) : (string) PHP_INT_MAX;
        $digits = $parts[2];

        return strlen($digits) > strlen($limit) || (strlen($digits) === strlen($limit) && strcmp($digits, $limit) > 0);
    }
}
