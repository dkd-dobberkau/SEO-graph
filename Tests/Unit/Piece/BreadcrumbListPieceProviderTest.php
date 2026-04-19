<?php

declare(strict_types=1);

namespace Dkd\SeoGraph\Tests\Unit\Piece;

use Dkd\SeoGraph\Assembler\PageContext;
use Dkd\SeoGraph\Configuration\SeoGraphConfiguration;
use Dkd\SeoGraph\Id\IdGenerator;
use Dkd\SeoGraph\Piece\BreadcrumbListPieceProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;

final class BreadcrumbListPieceProviderTest extends TestCase
{
    private function createContext(array $rootline = [], bool $isRootPage = false): PageContext
    {
        return new PageContext(
            site: $this->createMock(Site::class),
            pageRecord: [
                'uid' => $isRootPage ? 1 : 42,
                'title' => 'Current Page',
                'is_siteroot' => $isRootPage ? 1 : 0,
                'tx_seograph_schema_type' => '',
                'tx_seograph_exclude' => 0,
                '_rootline' => $rootline,
            ],
            pageUrl: 'https://example.com/about/',
            siteBaseUrl: 'https://example.com/',
            language: $this->createMock(SiteLanguage::class),
            configuration: new SeoGraphConfiguration([], 'Site'),
        );
    }

    #[Test]
    public function supportsReturnsFalseForRootPage(): void
    {
        $subject = new BreadcrumbListPieceProvider(new IdGenerator());
        self::assertFalse($subject->supports($this->createContext([], true)));
    }

    #[Test]
    public function supportsReturnsTrueForSubPage(): void
    {
        $subject = new BreadcrumbListPieceProvider(new IdGenerator());
        self::assertTrue($subject->supports($this->createContext()));
    }

    #[Test]
    public function provideReturnsBreadcrumbList(): void
    {
        $rootline = [
            ['uid' => 1, 'title' => 'Home', '_pageUrl' => 'https://example.com/'],
            ['uid' => 42, 'title' => 'About', '_pageUrl' => 'https://example.com/about/'],
        ];
        $subject = new BreadcrumbListPieceProvider(new IdGenerator());
        $pieces = [...$subject->provide($this->createContext($rootline))];

        self::assertCount(1, $pieces);
        $bc = $pieces[0];
        self::assertSame('BreadcrumbList', $bc['@type']);
        self::assertSame('https://example.com/about/#breadcrumb', $bc['@id']);
        self::assertCount(2, $bc['itemListElement']);

        self::assertSame(1, $bc['itemListElement'][0]['position']);
        self::assertSame('Home', $bc['itemListElement'][0]['name']);
        self::assertSame(2, $bc['itemListElement'][1]['position']);
        self::assertSame('About', $bc['itemListElement'][1]['name']);
    }

    #[Test]
    public function priorityIsForty(): void
    {
        $subject = new BreadcrumbListPieceProvider(new IdGenerator());
        self::assertSame(40, $subject->getPriority());
    }
}
