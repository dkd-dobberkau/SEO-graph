<?php

declare(strict_types=1);

namespace Dkd\SeoGraph\Tests\Unit\Assembler;

use Dkd\SeoGraph\Assembler\PageContext;
use Dkd\SeoGraph\Configuration\SeoGraphConfiguration;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;

final class PageContextTest extends TestCase
{
    private PageContext $subject;

    protected function setUp(): void
    {
        parent::setUp();
        $site = $this->createMock(Site::class);
        $language = $this->createMock(SiteLanguage::class);
        $configuration = new SeoGraphConfiguration([], 'Test Site');

        $this->subject = new PageContext(
            site: $site,
            pageRecord: [
                'uid' => 42,
                'title' => 'About us',
                'tx_seograph_schema_type' => '',
                'tx_seograph_exclude' => 0,
                'tx_seograph_author' => '',
            ],
            pageUrl: 'https://example.com/about/',
            siteBaseUrl: 'https://example.com/',
            language: $language,
            configuration: $configuration,
        );
    }

    #[Test]
    public function getSchemaTypeReturnsWebPageByDefault(): void
    {
        self::assertSame('WebPage', $this->subject->getSchemaType());
    }

    #[Test]
    public function getSchemaTypeReturnsConfiguredValue(): void
    {
        $site = $this->createMock(Site::class);
        $language = $this->createMock(SiteLanguage::class);
        $configuration = new SeoGraphConfiguration([], 'Site');
        $subject = new PageContext(
            site: $site,
            pageRecord: ['tx_seograph_schema_type' => 'Article', 'tx_seograph_exclude' => 0],
            pageUrl: 'https://example.com/',
            siteBaseUrl: 'https://example.com/',
            language: $language,
            configuration: $configuration,
        );
        self::assertSame('Article', $subject->getSchemaType());
    }

    #[Test]
    public function isArticleTypeReturnsTrueForArticle(): void
    {
        $site = $this->createMock(Site::class);
        $language = $this->createMock(SiteLanguage::class);
        $configuration = new SeoGraphConfiguration([], 'Site');
        $subject = new PageContext(
            site: $site,
            pageRecord: ['tx_seograph_schema_type' => 'Article', 'tx_seograph_exclude' => 0],
            pageUrl: 'https://example.com/',
            siteBaseUrl: 'https://example.com/',
            language: $language,
            configuration: $configuration,
        );
        self::assertTrue($subject->isArticleType());
    }

    #[Test]
    public function isArticleTypeReturnsTrueForBlogPosting(): void
    {
        $site = $this->createMock(Site::class);
        $language = $this->createMock(SiteLanguage::class);
        $configuration = new SeoGraphConfiguration([], 'Site');
        $subject = new PageContext(
            site: $site,
            pageRecord: ['tx_seograph_schema_type' => 'BlogPosting', 'tx_seograph_exclude' => 0],
            pageUrl: 'https://example.com/',
            siteBaseUrl: 'https://example.com/',
            language: $language,
            configuration: $configuration,
        );
        self::assertTrue($subject->isArticleType());
    }

    #[Test]
    public function isArticleTypeReturnsFalseForWebPage(): void
    {
        self::assertFalse($this->subject->isArticleType());
    }

    #[Test]
    public function isGraphEnabledReturnsTrueByDefault(): void
    {
        self::assertTrue($this->subject->isGraphEnabled());
    }

    #[Test]
    public function isGraphEnabledReturnsFalseWhenExcluded(): void
    {
        $site = $this->createMock(Site::class);
        $language = $this->createMock(SiteLanguage::class);
        $configuration = new SeoGraphConfiguration([], 'Site');
        $subject = new PageContext(
            site: $site,
            pageRecord: ['tx_seograph_schema_type' => '', 'tx_seograph_exclude' => 1],
            pageUrl: 'https://example.com/',
            siteBaseUrl: 'https://example.com/',
            language: $language,
            configuration: $configuration,
        );
        self::assertFalse($subject->isGraphEnabled());
    }
}
