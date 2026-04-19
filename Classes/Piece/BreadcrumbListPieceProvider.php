<?php

declare(strict_types=1);

namespace Dkd\SeoGraph\Piece;

use Dkd\SeoGraph\Assembler\PageContext;
use Dkd\SeoGraph\Id\IdGenerator;

final class BreadcrumbListPieceProvider implements GraphPieceProviderInterface
{
    public function __construct(
        private readonly IdGenerator $idGenerator,
    ) {}

    public function supports(PageContext $context): bool
    {
        return (int)($context->pageRecord['is_siteroot'] ?? 0) === 0;
    }

    public function provide(PageContext $context): iterable
    {
        $rootline = $context->pageRecord['_rootline'] ?? [];
        $items = [];
        $position = 1;

        foreach ($rootline as $page) {
            $items[] = [
                '@type' => 'ListItem',
                'position' => $position,
                'name' => $page['title'] ?? '',
                'item' => $page['_pageUrl'] ?? '',
            ];
            $position++;
        }

        yield [
            '@type' => 'BreadcrumbList',
            '@id' => $this->idGenerator->forPage($context->pageUrl, 'breadcrumb'),
            'itemListElement' => $items,
        ];
    }

    public function getPriority(): int
    {
        return 40;
    }
}
