<?php

declare(strict_types=1);

namespace Dkd\SeoGraph\Tests\Unit\Piece;

use Dkd\SeoGraph\Assembler\PageContext;
use Dkd\SeoGraph\Configuration\SeoGraphConfiguration;
use Dkd\SeoGraph\Id\IdGenerator;
use Dkd\SeoGraph\Piece\ImageObjectPieceProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;

final class ImageObjectPieceProviderTest extends TestCase
{
    private function createContext(string $primaryImage = '', array $media = []): PageContext
    {
        return new PageContext(
            site: $this->createMock(Site::class),
            pageRecord: [
                'uid' => 42,
                'tx_seograph_schema_type' => '',
                'tx_seograph_exclude' => 0,
                'tx_seograph_primary_image' => $primaryImage,
                '_media' => $media,
            ],
            pageUrl: 'https://example.com/about/',
            siteBaseUrl: 'https://example.com/',
            language: $this->createMock(SiteLanguage::class),
            configuration: new SeoGraphConfiguration([], 'Site'),
        );
    }

    #[Test]
    public function supportsReturnsFalseWhenNoImage(): void
    {
        $subject = new ImageObjectPieceProvider(new IdGenerator());
        self::assertFalse($subject->supports($this->createContext()));
    }

    #[Test]
    public function supportsReturnsTrueWhenPrimaryImageSet(): void
    {
        $subject = new ImageObjectPieceProvider(new IdGenerator());
        self::assertTrue($subject->supports($this->createContext('https://example.com/image.jpg')));
    }

    #[Test]
    public function supportsReturnsTrueWhenMediaExists(): void
    {
        $subject = new ImageObjectPieceProvider(new IdGenerator());
        self::assertTrue($subject->supports($this->createContext('', ['https://example.com/media.jpg'])));
    }

    #[Test]
    public function provideReturnsPrimaryImageWhenSet(): void
    {
        $subject = new ImageObjectPieceProvider(new IdGenerator());
        $context = $this->createContext('https://example.com/hero.jpg');
        $pieces = [...$subject->provide($context)];

        self::assertCount(1, $pieces);
        self::assertSame('ImageObject', $pieces[0]['@type']);
        self::assertSame('https://example.com/about/#primaryimage', $pieces[0]['@id']);
        self::assertSame('https://example.com/hero.jpg', $pieces[0]['url']);
    }

    #[Test]
    public function provideUsesFirstMediaAsFallback(): void
    {
        $subject = new ImageObjectPieceProvider(new IdGenerator());
        $context = $this->createContext('', ['https://example.com/fallback.jpg', 'https://example.com/second.jpg']);
        $pieces = [...$subject->provide($context)];

        self::assertSame('https://example.com/fallback.jpg', $pieces[0]['url']);
    }

    #[Test]
    public function priorityIsFifty(): void
    {
        $subject = new ImageObjectPieceProvider(new IdGenerator());
        self::assertSame(50, $subject->getPriority());
    }
}
