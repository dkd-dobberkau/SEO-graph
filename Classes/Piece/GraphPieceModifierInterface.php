<?php

declare(strict_types=1);

namespace Dkd\SeoGraph\Piece;

use Dkd\SeoGraph\Assembler\PageContext;

interface GraphPieceModifierInterface
{
    /**
     * Returns true if this modifier should be applied to the given piece.
     * Implementations typically check $piece['@type'].
     */
    public function supports(array $piece, PageContext $context): bool;

    /**
     * Decorates the piece and returns the modified version.
     * Must not mutate the original by reference — return a new array.
     *
     * @param array<string, mixed> $piece
     * @return array<string, mixed>
     */
    public function modify(array $piece, PageContext $context): array;

    /**
     * Modifiers are sorted ascending by priority (lowest runs first),
     * consistent with GraphPieceProviderInterface::getPriority().
     */
    public function getPriority(): int;
}
