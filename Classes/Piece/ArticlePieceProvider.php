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
            '@type'            => $context->getSchemaType(),
            '@id'              => $this->idGenerator->forPage($pageUrl, 'article'),
            'headline'         => $context->pageRecord['title'] ?? '',
            'isPartOf'         => ['@id' => $this->idGenerator->forPage($pageUrl, 'webpage')],
            'mainEntityOfPage' => ['@id' => $this->idGenerator->forPage($pageUrl, 'webpage')],
            'publisher'        => ['@id' => $this->idGenerator->forSite($baseUrl, 'organization')],
            'image'            => ['@id' => $this->idGenerator->forPage($pageUrl, 'primaryimage')],
        ];

        $crdate = $context->pageRecord['crdate'] ?? 0;
        if ($crdate > 0) {
            $article['datePublished'] = date('c', $crdate);
        }
        $tstamp = $context->pageRecord['tstamp'] ?? 0;
        if ($tstamp > 0) {
            $article['dateModified'] = date('c', $tstamp);
        }

        $authorId = $this->resolveAuthorId($context);
        if ($authorId !== null) {
            $article['author'] = ['@id' => $authorId];
        }

        yield $article;
    }

    public function getPriority(): int
    {
        return 60;
    }

    private function resolveAuthorId(PageContext $context): ?string
    {
        $baseUrl = $context->siteBaseUrl;

        $tcaAuthor = $context->pageRecord['tx_seograph_author'] ?? '';
        if ($tcaAuthor !== '') {
            $slug = $this->idGenerator->slugify($tcaAuthor);
            return $this->idGenerator->forAuthor($baseUrl, $slug);
        }

        $config = $context->configuration;
        $defaultAuthor = $config->getDefaultAuthorName();
        if ($defaultAuthor !== null) {
            $slug = $config->getDefaultAuthorSlug();
            if ($slug === '') {
                $slug = $this->idGenerator->slugify($defaultAuthor);
            }
            return $this->idGenerator->forAuthor($baseUrl, $slug);
        }

        return null;
    }
}
