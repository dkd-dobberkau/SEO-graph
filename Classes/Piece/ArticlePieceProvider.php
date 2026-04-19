<?php

declare(strict_types=1);

namespace Dkd\SeoGraph\Piece;

use Dkd\SeoGraph\Assembler\PageContext;
use Dkd\SeoGraph\Id\IdGenerator;

final class ArticlePieceProvider implements GraphPieceProviderInterface
{
    public function __construct(
        private readonly IdGenerator $idGenerator,
    ) {}

    public function supports(PageContext $context): bool
    {
        return $context->isArticleType();
    }

    public function provide(PageContext $context): iterable
    {
        $pageUrl = $context->pageUrl;
        $baseUrl = $context->siteBaseUrl;

        $article = [
            '@type' => $context->getSchemaType(),
            '@id' => $this->idGenerator->forPage($pageUrl, 'article'),
            'headline' => $context->pageRecord['title'] ?? '',
            'isPartOf' => ['@id' => $this->idGenerator->forPage($pageUrl, 'webpage')],
            'mainEntityOfPage' => ['@id' => $this->idGenerator->forPage($pageUrl, 'webpage')],
            'publisher' => ['@id' => $this->idGenerator->forSite($baseUrl, 'organization')],
            'image' => ['@id' => $this->idGenerator->forPage($pageUrl, 'primaryimage')],
        ];

        $crdate = $context->pageRecord['crdate'] ?? 0;
        if ($crdate > 0) {
            $article['datePublished'] = date('c', $crdate);
        }
        $tstamp = $context->pageRecord['tstamp'] ?? 0;
        if ($tstamp > 0) {
            $article['dateModified'] = date('c', $tstamp);
        }

        $author = $this->resolveAuthor($context);
        if ($author !== null) {
            $article['author'] = $author;
        }

        yield $article;
    }

    public function getPriority(): int
    {
        return 60;
    }

    private function resolveAuthor(PageContext $context): ?array
    {
        $tcaAuthor = $context->pageRecord['tx_seograph_author'] ?? '';
        if ($tcaAuthor !== '') {
            return [
                '@type' => 'Person',
                'name' => $tcaAuthor,
            ];
        }

        $config = $context->configuration;
        $defaultAuthor = $config->getDefaultAuthorName();
        if ($defaultAuthor !== null) {
            return [
                '@type' => $config->getDefaultAuthorType(),
                'name' => $defaultAuthor,
            ];
        }

        return null;
    }
}
