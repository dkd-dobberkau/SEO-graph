<?php

declare(strict_types=1);

namespace Dkd\SeoGraph\Assembler;

use Dkd\SeoGraph\Event\AfterGraphAssembledEvent;
use Dkd\SeoGraph\Event\BeforeGraphAssembledEvent;
use Dkd\SeoGraph\Piece\GraphPieceProviderInterface;
use Dkd\SeoGraph\Validation\GraphValidator;
use Psr\EventDispatcher\EventDispatcherInterface;

final class GraphAssembler
{
    /** @var GraphPieceProviderInterface[] */
    private readonly array $providers;

    /**
     * @param iterable<GraphPieceProviderInterface> $providers
     */
    public function __construct(
        iterable $providers,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly GraphValidator $validator,
    ) {
        $sorted = [...$providers];
        usort($sorted, static fn(GraphPieceProviderInterface $a, GraphPieceProviderInterface $b) => $a->getPriority() <=> $b->getPriority());
        $this->providers = $sorted;
    }

    /** @return array<int, array<string, mixed>> */
    public function assemble(PageContext $context): array
    {
        /** @var BeforeGraphAssembledEvent $beforeEvent */
        $beforeEvent = $this->eventDispatcher->dispatch(new BeforeGraphAssembledEvent($context));
        $pieces = $beforeEvent->getPieces();

        foreach ($this->providers as $provider) {
            if (!$provider->supports($context)) {
                continue;
            }
            foreach ($provider->provide($context) as $piece) {
                $pieces[] = $piece;
            }
        }

        /** @var AfterGraphAssembledEvent $afterEvent */
        $afterEvent = $this->eventDispatcher->dispatch(new AfterGraphAssembledEvent($context, $pieces));
        $pieces = $afterEvent->getPieces();

        $validationMode = $context->configuration->getValidationMode();
        if ($validationMode !== 'off') {
            $pieces = $this->validator->validateAndFilter($pieces, $context, $validationMode);
        }

        return $pieces;
    }
}
