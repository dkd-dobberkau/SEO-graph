<?php

declare(strict_types=1);

namespace Dkd\SeoGraph\Tests\Unit\Piece;

use Dkd\SeoGraph\Assembler\PageContext;
use Dkd\SeoGraph\Configuration\SeoGraphConfiguration;
use Dkd\SeoGraph\Id\IdGenerator;
use Dkd\SeoGraph\Piece\ArticlePieceProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;

final class ArticlePieceProviderTest extends TestCase
{
    private function createContext(string $schemaType = 'WebPage', string $author = '', array $authorConfig = []): PageContext
    {
        $siteConfig = [];
        if ($authorConfig !== []) {
            $siteConfig = ['seoGraph' => ['defaultAuthor' => $authorConfig]];
        }
        return new PageContext(
            site: $this->createMock(Site::class),
            pageRecord: [
                'uid' => 42,
                'title' => 'Test Article',
                'tx_seograph_schema_type' => $schemaType,
                'tx_seograph_exclude' => 0,
                'tx_seograph_author' => $author,
                'crdate' => 1700000000,
                'tstamp' => 1700100000,
            ],
            pageUrl: 'https://example.com/blog/test/',
            siteBaseUrl: 'https://example.com/',
            language: $this->createMock(SiteLanguage::class),
            configuration: new SeoGraphConfiguration($siteConfig, 'Site'),
        );
    }

    #[Test]
    public function supportsReturnsFalseForWebPage(): void
    {
        $subject = new ArticlePieceProvider(new IdGenerator());
        self::assertFalse($subject->supports($this->createContext('WebPage')));
    }

    #[Test]
    public function supportsReturnsTrueForArticle(): void
    {
        $subject = new ArticlePieceProvider(new IdGenerator());
        self::assertTrue($subject->supports($this->createContext('Article')));
    }

    #[Test]
    public function supportsReturnsTrueForBlogPosting(): void
    {
        $subject = new ArticlePieceProvider(new IdGenerator());
        self::assertTrue($subject->supports($this->createContext('BlogPosting')));
    }

    #[Test]
    public function provideReturnsArticleWithCorrectReferences(): void
    {
        $subject = new ArticlePieceProvider(new IdGenerator());
        $pieces = [...$subject->provide($this->createContext('Article'))];

        self::assertCount(1, $pieces);
        $article = $pieces[0];
        self::assertSame('Article', $article['@type']);
        self::assertSame('https://example.com/blog/test/#article', $article['@id']);
        self::assertSame('Test Article', $article['headline']);
        self::assertSame(['@id' => 'https://example.com/blog/test/#webpage'], $article['isPartOf']);
        self::assertSame(['@id' => 'https://example.com/blog/test/#webpage'], $article['mainEntityOfPage']);
        self::assertSame(['@id' => 'https://example.com/#organization'], $article['publisher']);
        self::assertSame(['@id' => 'https://example.com/blog/test/#primaryimage'], $article['image']);
    }

    #[Test]
    public function provideUsesAuthorReferenceFromTcaViaId(): void
    {
        $subject = new ArticlePieceProvider(new IdGenerator());
        $pieces = [...$subject->provide($this->createContext('Article', 'John Smith'))];

        $article = $pieces[0];
        // Author must be an @id reference, not an inline object
        self::assertArrayHasKey('author', $article);
        self::assertArrayHasKey('@id', $article['author']);
        self::assertSame('https://example.com/#author-john-smith', $article['author']['@id']);
        self::assertArrayNotHasKey('@type', $article['author']);
        self::assertArrayNotHasKey('name', $article['author']);
    }

    #[Test]
    public function provideUsesDefaultAuthorReferenceViaId(): void
    {
        $subject = new ArticlePieceProvider(new IdGenerator());
        $context = $this->createContext('Article', '', [
            'type' => 'Person',
            'name' => 'Jane Doe',
            'slug' => 'jane-doe',
        ]);
        $pieces = [...$subject->provide($context)];

        $article = $pieces[0];
        self::assertArrayHasKey('author', $article);
        self::assertSame('https://example.com/#author-jane-doe', $article['author']['@id']);
        self::assertArrayNotHasKey('@type', $article['author']);
    }

    #[Test]
    public function provideOmitsAuthorWhenNotConfigured(): void
    {
        $subject = new ArticlePieceProvider(new IdGenerator());
        $pieces = [...$subject->provide($this->createContext('Article'))];

        self::assertArrayNotHasKey('author', $pieces[0]);
    }

    #[Test]
    public function provideIncludesDatePublishedAndModified(): void
    {
        $subject = new ArticlePieceProvider(new IdGenerator());
        $pieces = [...$subject->provide($this->createContext('Article'))];

        $article = $pieces[0];
        self::assertArrayHasKey('datePublished', $article);
        self::assertArrayHasKey('dateModified', $article);
    }

    #[Test]
    public function priorityIsSixty(): void
    {
        $subject = new ArticlePieceProvider(new IdGenerator());
        self::assertSame(60, $subject->getPriority());
    }
}
