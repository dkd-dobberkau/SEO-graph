<?php

declare(strict_types=1);

namespace Dkd\SeoGraph\Event;

use Dkd\SeoGraph\Assembler\PageContext;

final class AfterGraphAssembledEvent
{
    public function __construct(
        private readonly PageContext $context,
        private array $pieces,
    ) {}

    public function getContext(): PageContext
    {
        return $this->context;
    }

    public function getPieces(): array
    {
        return $this->pieces;
    }

    public function setPieces(array $pieces): void
    {
        $this->pieces = $pieces;
    }

    public function addPiece(array $piece): void
    {
        $this->pieces[] = $piece;
    }
}
