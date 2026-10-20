<?php

declare(strict_types=1);

namespace JardisSupport\Response\Handler\Failure;

use JardisSupport\Contract\Validation\ValidationResult;

/**
 * Gives one field finding of the 400 answer its vocabulary: a `messageKey` and, when the
 * caller delivered no text, a standard text.
 *
 * The validator contract hands out `field`, `reason` and `message` and leaves the answer
 * vocabulary to the caller ({@see ValidationResult::toFields()}); a request reader collects the same
 * three. This is that caller's one place: the key is `validation.<reason>` for the four reasons the
 * answer knows - `missing`, `invalid` (validator) and `type`, `unknown` (request reader); any other
 * reason is answered as `invalid`. The standard text is `Field is required.` for `missing`,
 * `Field has an invalid type.` for `type`, `Unknown field` for `unknown` and `Field is invalid.` for
 * everything else. A text the caller did deliver stays untouched.
 */
final class DescribeFieldFinding
{
    public const string KEY_PREFIX = 'validation';
    public const string REASON_MISSING = ValidationResult::REASON_MISSING;
    public const string REASON_INVALID = ValidationResult::REASON_INVALID;
    public const string REASON_TYPE = 'type';
    public const string REASON_UNKNOWN = 'unknown';
    public const string TEXT_MISSING = 'Field is required.';
    public const string TEXT_INVALID = 'Field is invalid.';
    public const string TEXT_TYPE = 'Field has an invalid type.';
    public const string TEXT_UNKNOWN = 'Unknown field';

    private const array TEXTS = [
        self::REASON_MISSING => self::TEXT_MISSING,
        self::REASON_INVALID => self::TEXT_INVALID,
        self::REASON_TYPE => self::TEXT_TYPE,
        self::REASON_UNKNOWN => self::TEXT_UNKNOWN,
    ];

    /**
     * @param array{field: string, reason: string, message: string} $finding
     * @return array{field: string, reason: string, message: string, messageKey: string}
     */
    public function __invoke(array $finding): array
    {
        $reason = isset(self::TEXTS[$finding['reason']]) ? $finding['reason'] : self::REASON_INVALID;

        return [
            'field' => $finding['field'],
            'reason' => $finding['reason'],
            'message' => $finding['message'] !== '' ? $finding['message'] : self::TEXTS[$reason],
            'messageKey' => self::KEY_PREFIX . '.' . $reason,
        ];
    }
}
