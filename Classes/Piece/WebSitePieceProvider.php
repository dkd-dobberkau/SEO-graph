<?php

declare(strict_types=1);

namespace Dkd\SeoGraph\Piece;

use Dkd\SeoGraph\Assembler\PageContext;
use Dkd\SeoGraph\Id\IdGenerator;

final class WebSitePieceProvider implements GraphPieceProviderInterface
{
    public function __construct(
        private readonly IdGenerator $idGenerator,
    ) {}

    public function supports(PageContext $context): bool
    {
        return true;
    }

    public function provide(PageContext $context): iterable
    {
        $baseUrl = $context->siteBaseUrl;

        yield [
            '@type' => 'WebSite',
            '@id' => $this->idGenerator->forSite($baseUrl, 'website'),
            'url' => $baseUrl,
            'name' => $context->configuration->getPublisherName(),
            'publisher' => ['@id' => $this->idGenerator->forSite($baseUrl, 'organization')],
        ];
    }

    public function getPriority(): int
    {
        return 20;
    }
}
