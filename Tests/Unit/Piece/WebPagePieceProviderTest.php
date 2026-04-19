<?php

declare(strict_types=1);

namespace Dkd\SeoGraph\Tests\Unit\Piece;

use Dkd\SeoGraph\Assembler\PageContext;
use Dkd\SeoGraph\Configuration\SeoGraphConfiguration;
use Dkd\SeoGraph\Id\IdGenerator;
use Dkd\SeoGraph\Piece\WebPagePieceProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;

final class WebPagePieceProviderTest extends TestCase
{
    private function createContext(string $schemaType = '', string $pageTitle = 'About us'): PageContext
    {
        return new PageContext(
            site: $this->createMock(Site::class),
            pageRecord: [
                'uid' => 42,
                'title' => $pageTitle,
                'tx_seograph_schema_type' => $schemaType,
                'tx_seograph_exclude' => 0,
            ],
            pageUrl: 'https://example.com/about/',
            siteBaseUrl: 'https://example.com/',
            language: $this->createMock(SiteLanguage::class),
            configuration: new SeoGraphConfiguration([], 'Site'),
        );
    }

    #[Test]
    public function provideReturnsWebPageWithCorrectReferences(): void
    {
        $subject = new WebPagePieceProvider(new IdGenerator());
        $pieces = [...$subject->provide($this->createContext())];

        self::assertCount(1, $pieces);
        $wp = $pieces[0];
        self::assertSame('WebPage', $wp['@type']);
        self::assertSame('https://example.com/about/#webpage', $wp['@id']);
        self::assertSame('https://example.com/about/', $wp['url']);
        self::assertSame('About us', $wp['name']);
        self::assertSame(['@id' => 'https://example.com/#website'], $wp['isPartOf']);
    }

    #[Test]
    public function provideUsesConfiguredSchemaType(): void
    {
        $subject = new WebPagePieceProvider(new IdGenerator());
        $pieces = [...$subject->provide($this->createContext('FAQPage'))];

        self::assertSame('FAQPage', $pieces[0]['@type']);
    }

    #[Test]
    public function provideIncludesBreadcrumbReference(): void
    {
        $subject = new WebPagePieceProvider(new IdGenerator());
        $pieces = [...$subject->provide($this->createContext())];

        self::assertSame(['@id' => 'https://example.com/about/#breadcrumb'], $pieces[0]['breadcrumb']);
    }

    #[Test]
    public function priorityIsThirty(): void
    {
        $subject = new WebPagePieceProvider(new IdGenerator());
        self::assertSame(30, $subject->getPriority());
    }
}
