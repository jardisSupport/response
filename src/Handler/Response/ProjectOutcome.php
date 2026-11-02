<?php

declare(strict_types=1);

namespace JardisSupport\Response\Handler\Response;

use JardisSupport\Contract\Kernel\DomainResponseInterface;
use JardisSupport\Response\Data\Response\OutcomeForm;
use JardisSupport\Response\Exception\ConfigurationException;

/**
 * Cuts the 2xx answer of a process down to the form of the outcome that ran.
 *
 * The data of the answer carries the marker `@outcome` (the name of the outcome that ended the
 * run). The closure picks the {@see OutcomeForm} of that name and keeps `@type`, `@outcome` and
 * exactly the fields of that form, in the order of the form; the rows of the lists are cut by
 * the form's row cuts ({@see ProjectAnswerRows}). A field of the form that the data lacks is not
 * added; a field of another form is gone. Status, errors, metadata and events pass through
 * (see {@see ReshapeAnswer}); every answer that is not 2xx is returned as the very same instance.
 *
 * A 2xx answer without `@outcome`, or with an outcome the forms do not know, is a wiring fault
 * and raises a {@see ConfigurationException}.
 *
 * The closure knows no outcome or field name of its own - only the forms it is given.
 *
 * Atomic closure - one entry point.
 */
final class ProjectOutcome
{
    /** The marker key in the data; the same literal as `MarkOutcome::KEY` of the workflow package. */
    public const string KEY = '@outcome';

    private const string TYPE = '@type';

    public function __construct(
        private readonly ReshapeAnswer $reshapeAnswer = new ReshapeAnswer(),
        private readonly ProjectAnswerRows $projectAnswerRows = new ProjectAnswerRows(),
    ) {
    }

    /**
     * @param array<string, OutcomeForm> $forms outcome name => the form of that outcome
     *
     * @throws ConfigurationException when a 2xx answer has no `@outcome` or one that has no form
     * @throws \UnexpectedValueException when a cut list is no list of rows or a row lacks a named key
     */
    public function __invoke(DomainResponseInterface $response, array $forms): DomainResponseInterface
    {
        $kept = ($this->reshapeAnswer)(
            $response,
            fn (array $data): array => $this->keep($data, $this->formOf($data, $forms))
        );
        if ($kept === $response) {
            return $response;
        }

        return ($this->projectAnswerRows)($kept, $this->formOf($kept->getData(), $forms)->rowCuts);
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, OutcomeForm> $forms
     */
    private function formOf(array $data, array $forms): OutcomeForm
    {
        $outcome = $data[self::KEY] ?? null;
        if (!is_string($outcome)) {
            throw new ConfigurationException(sprintf('The answer carries no "%s" marker.', self::KEY));
        }
        if (!isset($forms[$outcome])) {
            throw new ConfigurationException(sprintf(
                'No form for outcome "%s" (known: %s).',
                $outcome,
                implode(', ', array_keys($forms))
            ));
        }

        return $forms[$outcome];
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    private function keep(array $data, OutcomeForm $form): array
    {
        $kept = [];
        foreach ([self::TYPE, self::KEY, ...$form->fields] as $field) {
            if (array_key_exists($field, $data) && !array_key_exists($field, $kept)) {
                $kept[$field] = $data[$field];
            }
        }

        return $kept;
    }
}
