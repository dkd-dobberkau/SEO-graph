<?php

declare(strict_types=1);

namespace Dkd\SeoGraph\Piece;

use Dkd\SeoGraph\Assembler\PageContext;

interface GraphPieceProviderInterface
{
    public function supports(PageContext $context): bool;

    /** @return iterable<array<string, mixed>> */
    public function provide(PageContext $context): iterable;

    public function getPriority(): int;
}
