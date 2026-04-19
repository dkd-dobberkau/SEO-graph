<?php

declare(strict_types=1);

namespace Dkd\SeoGraph\Assembler;

use Dkd\SeoGraph\Configuration\SeoGraphConfiguration;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;

final readonly class PageContext
{
    private const ARTICLE_TYPES = ['Article', 'BlogPosting', 'NewsArticle'];

    public function __construct(
        public Site $site,
        public array $pageRecord,
        public string $pageUrl,
        public string $siteBaseUrl,
        public SiteLanguage $language,
        public SeoGraphConfiguration $configuration,
    ) {}

    public function getSchemaType(): string
    {
        $type = $this->pageRecord['tx_seograph_schema_type'] ?? '';
        return $type !== '' ? $type : 'WebPage';
    }

    public function isArticleType(): bool
    {
        return in_array($this->getSchemaType(), self::ARTICLE_TYPES, true);
    }

    public function hasPlugin(string $name): bool
    {
        $contentElements = $this->pageRecord['_contentElements'] ?? [];
        foreach ($contentElements as $ce) {
            if (($ce['list_type'] ?? '') === $name) {
                return true;
            }
        }
        return false;
    }

    public function isGraphEnabled(): bool
    {
        return (int)($this->pageRecord['tx_seograph_exclude'] ?? 0) === 0;
    }
}
