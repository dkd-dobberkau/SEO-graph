<?php

declare(strict_types=1);

namespace Dkd\SeoGraph\Tests\Unit\Piece;

use Dkd\SeoGraph\Assembler\PageContext;
use Dkd\SeoGraph\Configuration\SeoGraphConfiguration;
use Dkd\SeoGraph\Id\IdGenerator;
use Dkd\SeoGraph\Piece\WebSitePieceProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;

final class WebSitePieceProviderTest extends TestCase
{
    private function createContext(string $siteTitle = 'Example'): PageContext
    {
        $site = $this->createMock(Site::class);
        return new PageContext(
            site: $site,
            pageRecord: ['tx_seograph_schema_type' => '', 'tx_seograph_exclude' => 0],
            pageUrl: 'https://example.com/about/',
            siteBaseUrl: 'https://example.com/',
            language: $this->createMock(SiteLanguage::class),
            configuration: new SeoGraphConfiguration([], $siteTitle),
        );
    }

    #[Test]
    public function provideReturnsWebSiteWithPublisherReference(): void
    {
        $subject = new WebSitePieceProvider(new IdGenerator());
        $pieces = [...$subject->provide($this->createContext('My Site'))];

        self::assertCount(1, $pieces);
        $ws = $pieces[0];
        self::assertSame('WebSite', $ws['@type']);
        self::assertSame('https://example.com/#website', $ws['@id']);
        self::assertSame('https://example.com/', $ws['url']);
        self::assertSame('My Site', $ws['name']);
        self::assertSame(['@id' => 'https://example.com/#organization'], $ws['publisher']);
    }

    #[Test]
    public function priorityIsTwenty(): void
    {
        $subject = new WebSitePieceProvider(new IdGenerator());
        self::assertSame(20, $subject->getPriority());
    }
}
