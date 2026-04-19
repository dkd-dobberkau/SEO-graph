<?php

declare(strict_types=1);

namespace Dkd\SeoGraph\Piece;

use Dkd\SeoGraph\Assembler\PageContext;
use Dkd\SeoGraph\Id\IdGenerator;

final class WebPagePieceProvider implements GraphPieceProviderInterface
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
        $pageUrl = $context->pageUrl;
        $baseUrl = $context->siteBaseUrl;

        $webPage = [
            '@type' => $context->getSchemaType(),
            '@id' => $this->idGenerator->forPage($pageUrl, 'webpage'),
            'url' => $pageUrl,
            'name' => $context->pageRecord['title'] ?? '',
            'isPartOf' => ['@id' => $this->idGenerator->forSite($baseUrl, 'website')],
            'breadcrumb' => ['@id' => $this->idGenerator->forPage($pageUrl, 'breadcrumb')],
        ];

        yield $webPage;
    }

    public function getPriority(): int
    {
        return 30;
    }
}
