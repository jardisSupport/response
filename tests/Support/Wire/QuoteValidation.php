<?php

declare(strict_types=1);

namespace JardisSupport\Response\Tests\Support\Wire;

use JardisSupport\Contract\Validation\ValidatorInterface;
use JardisSupport\Validation\CompositeFieldValidator;
use JardisSupport\Validation\ObjectValidator;
use JardisSupport\Validation\Validator\Contain;
use JardisSupport\Validation\Validator\Format;
use JardisSupport\Validation\Validator\Uuid;
use JardisSupport\Validation\ValidatorRegistry;

/**
 * The real validator stack for the quote input, rules as the generated input gate declares them.
 */
final class QuoteValidation
{
    public static function validator(): ValidatorInterface
    {
        $input = new CompositeFieldValidator();
        $input->field('deliveryChannels')->each(Contain::class, Contain::oneOf(['email', 'post', 'portal']));
        $line = new CompositeFieldValidator();
        $line->field('unitPrice')->validates(Format::class, Format::pattern('/^-?\d+(\.\d+)?$/'));
        $line->field('serialNumbers')->each(Uuid::class);

        $registry = (new ValidatorRegistry())
            ->register(QuoteInput::class, $input)
            ->register(QuoteLine::class, $line);

        return new ObjectValidator($registry, fieldPaths: true);
    }
}
