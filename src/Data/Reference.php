<?php

declare(strict_types=1);

namespace JardisSupport\Response\Data;

/**
 * Write-side answer value: the business identifier of an aggregate root plus the
 * references of its children, never the objects themselves.
 *
 * The identifier carries its wire name (`orderNumber`): `BuildResponseData` (`Handler\Response`) answers
 * `{@type, <identifier name>: <root>, <child key>: <child reference(s)>}`. Without a name the root answers
 * as `id`, the fallback of an aggregate without a public business key.
 */
final readonly class Reference
{
    /** The wire name of the root when its business identifier was not named. */
    public const string DEFAULT_IDENTIFIER = 'id';

    /**
     * @param array<string, int|string|list<int|string>|null> $children wire key => the reference of one child
     *        (scalar, null when absent) or of a child list (list of identifiers)
     * @param string $identifier wire name of the root's business identifier
     */
    public function __construct(
        private int|string $root,
        private array $children = [],
        private string $identifier = self::DEFAULT_IDENTIFIER,
    ) {
    }

    public function root(): int|string
    {
        return $this->root;
    }

    /**
     * @return array<string, int|string|list<int|string>|null>
     */
    public function children(): array
    {
        return $this->children;
    }

    /**
     * The wire name of the root's business identifier.
     */
    public function identifier(): string
    {
        return $this->identifier;
    }
}
