<?php

declare(strict_types=1);

namespace Dkd\SeoGraph\Tests\Unit\Piece;

use Dkd\SeoGraph\Assembler\PageContext;
use Dkd\SeoGraph\Configuration\SeoGraphConfiguration;
use Dkd\SeoGraph\Id\IdGenerator;
use Dkd\SeoGraph\Piece\OrganizationPieceProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;

final class OrganizationPieceProviderTest extends TestCase
{
    private function createContext(array $publisherConfig = []): PageContext
    {
        $siteConfig = [];
        if ($publisherConfig !== []) {
            $siteConfig = ['seoGraph' => ['publisher' => $publisherConfig]];
        }
        return new PageContext(
            site: $this->createMock(Site::class),
            pageRecord: ['tx_seograph_schema_type' => '', 'tx_seograph_exclude' => 0],
            pageUrl: 'https://example.com/about/',
            siteBaseUrl: 'https://example.com/',
            language: $this->createMock(SiteLanguage::class),
            configuration: new SeoGraphConfiguration($siteConfig, 'Fallback Site'),
        );
    }

    #[Test]
    public function supportsAlwaysReturnsTrue(): void
    {
        $subject = new OrganizationPieceProvider(new IdGenerator());
        self::assertTrue($subject->supports($this->createContext()));
    }

    #[Test]
    public function provideReturnsOrganizationWithConfiguredData(): void
    {
        $subject = new OrganizationPieceProvider(new IdGenerator());
        $context = $this->createContext([
            'type' => 'Organization',
            'name' => 'Example GmbH',
            'url' => 'https://example.com/',
            'logo' => 'https://example.com/logo.png',
            'sameAs' => ['https://linkedin.com/company/example'],
        ]);

        $pieces = [...$subject->provide($context)];

        self::assertCount(2, $pieces);

        $org = $pieces[0];
        self::assertSame('Organization', $org['@type']);
        self::assertSame('https://example.com/#organization', $org['@id']);
        self::assertSame('Example GmbH', $org['name']);
        self::assertSame('https://example.com/', $org['url']);
        self::assertSame(['https://linkedin.com/company/example'], $org['sameAs']);
        self::assertSame(['@id' => 'https://example.com/#logo'], $org['logo']);

        $logo = $pieces[1];
        self::assertSame('ImageObject', $logo['@type']);
        self::assertSame('https://example.com/#logo', $logo['@id']);
        self::assertSame('https://example.com/logo.png', $logo['url']);
    }

    #[Test]
    public function provideOmitsLogoWhenNotConfigured(): void
    {
        $subject = new OrganizationPieceProvider(new IdGenerator());
        $context = $this->createContext([
            'name' => 'No Logo Corp',
        ]);

        $pieces = [...$subject->provide($context)];

        self::assertCount(1, $pieces);
        self::assertArrayNotHasKey('logo', $pieces[0]);
    }

    #[Test]
    public function provideOmitsSameAsWhenEmpty(): void
    {
        $subject = new OrganizationPieceProvider(new IdGenerator());
        $context = $this->createContext(['name' => 'Minimal Corp']);

        $pieces = [...$subject->provide($context)];
        self::assertArrayNotHasKey('sameAs', $pieces[0]);
    }

    #[Test]
    public function priorityIsTen(): void
    {
        $subject = new OrganizationPieceProvider(new IdGenerator());
        self::assertSame(10, $subject->getPriority());
    }
}
