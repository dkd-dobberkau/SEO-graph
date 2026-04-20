<?php

declare(strict_types=1);

namespace Dkd\SeoGraph\Tests\Unit\Validation\Rule;

use Dkd\SeoGraph\Assembler\PageContext;
use Dkd\SeoGraph\Configuration\SeoGraphConfiguration;
use Dkd\SeoGraph\Validation\Rule\RichResultsArticleRule;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;

final class RichResultsArticleRuleTest extends TestCase
{
    private RichResultsArticleRule $subject;

    private function createContext(): PageContext
    {
        return new PageContext(
            site: $this->createMock(Site::class),
            pageRecord: ['tx_seograph_schema_type' => 'Article', 'tx_seograph_exclude' => 0],
            pageUrl: 'https://example.com/blog/post/',
            siteBaseUrl: 'https://example.com/',
            language: $this->createMock(SiteLanguage::class),
            configuration: new SeoGraphConfiguration([], 'Site'),
        );
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->subject = new RichResultsArticleRule();
    }

    #[Test]
    public function validateReturnsNoResultsForNonArticlePieces(): void
    {
        $graph = [
            ['@type' => 'Organization', '@id' => 'https://example.com/#organization', 'name' => 'Example'],
        ];
        $results = $this->subject->validate($graph, $this->createContext());
        self::assertSame([], $results);
    }

    #[Test]
    public function validateReturnsWarningForMissingHeadline(): void
    {
        $graph = [
            [
                '@type'         => 'Article',
                '@id'           => 'https://example.com/blog/post/#article',
                'image'         => ['@id' => 'https://example.com/blog/post/#primaryimage'],
                'datePublished' => '2024-01-01T00:00:00+00:00',
                'author'        => ['@id' => 'https://example.com/#author-jane-doe'],
            ],
        ];
        $results = $this->subject->validate($graph, $this->createContext());

        self::assertCount(1, $results);
        self::assertSame('warning', $results[0]->severity);
        self::assertStringContainsString('headline', $results[0]->message);
    }

    #[Test]
    public function validateReturnsWarningForHeadlineExceeding110Characters(): void
    {
        $graph = [
            [
                '@type'         => 'Article',
                '@id'           => 'https://example.com/blog/post/#article',
                'headline'      => str_repeat('A', 111),
                'image'         => ['@id' => '#image'],
                'datePublished' => '2024-01-01T00:00:00+00:00',
                'author'        => ['@id' => '#author'],
            ],
        ];
        $results = $this->subject->validate($graph, $this->createContext());

        self::assertCount(1, $results);
        self::assertStringContainsString('110', $results[0]->message);
    }

    #[Test]
    public function validateReturnsNoWarningForHeadlineAtExactly110Characters(): void
    {
        $graph = [
            [
                '@type'         => 'Article',
                '@id'           => 'https://example.com/blog/post/#article',
                'headline'      => str_repeat('A', 110),
                'image'         => ['@id' => '#image'],
                'datePublished' => '2024-01-01T00:00:00+00:00',
                'author'        => ['@id' => '#author'],
            ],
        ];
        $results = $this->subject->validate($graph, $this->createContext());
        self::assertSame([], $results);
    }

    #[Test]
    public function validateReturnsWarningForMissingImage(): void
    {
        $graph = [
            [
                '@type'         => 'Article',
                '@id'           => 'https://example.com/blog/post/#article',
                'headline'      => 'Test Article',
                'datePublished' => '2024-01-01T00:00:00+00:00',
                'author'        => ['@id' => '#author'],
            ],
        ];
        $results = $this->subject->validate($graph, $this->createContext());

        self::assertCount(1, $results);
        self::assertStringContainsString('image', $results[0]->message);
    }

    #[Test]
    public function validateReturnsWarningForMissingDatePublished(): void
    {
        $graph = [
            [
                '@type'    => 'Article',
                '@id'      => 'https://example.com/blog/post/#article',
                'headline' => 'Test Article',
                'image'    => ['@id' => '#image'],
                'author'   => ['@id' => '#author'],
            ],
        ];
        $results = $this->subject->validate($graph, $this->createContext());

        self::assertCount(1, $results);
        self::assertStringContainsString('datePublished', $results[0]->message);
    }

    #[Test]
    public function validateReturnsWarningForMissingAuthor(): void
    {
        $graph = [
            [
                '@type'         => 'Article',
                '@id'           => 'https://example.com/blog/post/#article',
                'headline'      => 'Test Article',
                'image'         => ['@id' => '#image'],
                'datePublished' => '2024-01-01T00:00:00+00:00',
            ],
        ];
        $results = $this->subject->validate($graph, $this->createContext());

        self::assertCount(1, $results);
        self::assertStringContainsString('author', $results[0]->message);
    }

    #[Test]
    public function validateAcceptsAuthorAsIdReference(): void
    {
        $graph = [
            [
                '@type'         => 'Article',
                '@id'           => '#article',
                'headline'      => 'Test',
                'image'         => ['@id' => '#image'],
                'datePublished' => '2024-01-01T00:00:00+00:00',
                'author'        => ['@id' => 'https://example.com/#author-jane-doe'],
            ],
        ];
        $results = $this->subject->validate($graph, $this->createContext());
        self::assertSame([], $results);
    }

    #[Test]
    public function validateAcceptsAuthorAsInlineObjectWithName(): void
    {
        $graph = [
            [
                '@type'         => 'Article',
                '@id'           => '#article',
                'headline'      => 'Test',
                'image'         => ['@id' => '#image'],
                'datePublished' => '2024-01-01T00:00:00+00:00',
                'author'        => ['@type' => 'Person', 'name' => 'Jane Doe'],
            ],
        ];
        $results = $this->subject->validate($graph, $this->createContext());
        self::assertSame([], $results);
    }

    #[Test]
    public function validateAppliesToBlogPostingAndNewsArticle(): void
    {
        foreach (['BlogPosting', 'NewsArticle'] as $type) {
            $graph = [
                [
                    '@type'    => $type,
                    '@id'      => '#article',
                    'headline' => 'Test',
                    'image'    => ['@id' => '#image'],
                    // Missing datePublished and author — should produce warnings
                ],
            ];
            $results = $this->subject->validate($graph, $this->createContext());
            self::assertCount(2, $results, "Expected 2 warnings for $type");
        }
    }

    #[Test]
    public function validateReturnsNoResultsForCompleteArticle(): void
    {
        $graph = [
            [
                '@type'         => 'Article',
                '@id'           => 'https://example.com/blog/post/#article',
                'headline'      => 'A Complete Article',
                'image'         => ['@id' => 'https://example.com/blog/post/#primaryimage'],
                'datePublished' => '2024-11-15T10:00:00+01:00',
                'author'        => ['@id' => 'https://example.com/#author-jane-doe'],
            ],
        ];
        $results = $this->subject->validate($graph, $this->createContext());
        self::assertSame([], $results);
    }
}
