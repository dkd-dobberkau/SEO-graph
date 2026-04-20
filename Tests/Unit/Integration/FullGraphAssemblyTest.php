<?php

declare(strict_types=1);

namespace Dkd\SeoGraph\Tests\Unit\Integration;

use Dkd\SeoGraph\Assembler\GraphAssembler;
use Dkd\SeoGraph\Assembler\PageContext;
use Dkd\SeoGraph\Configuration\SeoGraphConfiguration;
use Dkd\SeoGraph\Id\IdGenerator;
use Dkd\SeoGraph\Piece\ArticlePieceProvider;
use Dkd\SeoGraph\Piece\BreadcrumbListPieceProvider;
use Dkd\SeoGraph\Piece\GraphPieceModifierInterface;
use Dkd\SeoGraph\Piece\ImageObjectPieceProvider;
use Dkd\SeoGraph\Piece\OrganizationPieceProvider;
use Dkd\SeoGraph\Piece\PersonPieceProvider;
use Dkd\SeoGraph\Piece\WebPagePieceProvider;
use Dkd\SeoGraph\Piece\WebSitePieceProvider;
use Dkd\SeoGraph\Validation\GraphValidator;
use Dkd\SeoGraph\Validation\Rule\NoDuplicateIdsRule;
use Dkd\SeoGraph\Validation\Rule\ReferencesResolveRule;
use Dkd\SeoGraph\Validation\Rule\RequiredPropertiesRule;
use Dkd\SeoGraph\Validation\Rule\RichResultsArticleRule;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\NullLogger;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;

final class FullGraphAssemblyTest extends TestCase
{
    #[Test]
    public function assemblesCompleteGraphForContentPage(): void
    {
        $idGenerator = new IdGenerator();
        $providers = [
            new OrganizationPieceProvider($idGenerator),
            new WebSitePieceProvider($idGenerator),
            new WebPagePieceProvider($idGenerator),
            new BreadcrumbListPieceProvider($idGenerator),
            new ImageObjectPieceProvider($idGenerator),
        ];

        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher->method('dispatch')->willReturnCallback(fn($event) => $event);

        $validator = new GraphValidator([
            new NoDuplicateIdsRule(),
            new ReferencesResolveRule(),
            new RequiredPropertiesRule(),
        ], new NullLogger());

        $assembler = new GraphAssembler($providers, [], $dispatcher, $validator);

        $context = new PageContext(
            site: $this->createMock(Site::class),
            pageRecord: [
                'uid' => 42,
                'title' => 'About us',
                'tx_seograph_schema_type' => '',
                'tx_seograph_exclude' => 0,
                'tx_seograph_primary_image' => 'https://example.com/fileadmin/about-hero.jpg',
                '_rootline' => [
                    ['uid' => 1, 'title' => 'Home', '_pageUrl' => 'https://example.com/'],
                    ['uid' => 42, 'title' => 'About us', '_pageUrl' => 'https://example.com/about/'],
                ],
            ],
            pageUrl: 'https://example.com/about/',
            siteBaseUrl: 'https://example.com/',
            language: $this->createMock(SiteLanguage::class),
            configuration: new SeoGraphConfiguration([
                'seoGraph' => [
                    'publisher' => [
                        'type' => 'Organization',
                        'name' => 'Example GmbH',
                        'url' => 'https://example.com/',
                        'logo' => 'https://example.com/logo.png',
                        'sameAs' => ['https://www.linkedin.com/company/example'],
                    ],
                    'validation' => [
                        'mode' => 'warning',
                        'rules' => ['references_resolve', 'no_duplicate_ids', 'required_properties'],
                    ],
                ],
            ], 'Example'),
        );

        $graph = $assembler->assemble($context);

        // Should have: Organization, Logo ImageObject, WebSite, WebPage, BreadcrumbList, PrimaryImage
        self::assertCount(6, $graph);

        $types = array_column($graph, '@type');
        self::assertContains('Organization', $types);
        self::assertContains('WebSite', $types);
        self::assertContains('WebPage', $types);
        self::assertContains('BreadcrumbList', $types);
        self::assertSame(2, count(array_filter($types, fn($t) => $t === 'ImageObject')));

        // Verify @id cross-references
        $ids = array_column($graph, '@id');
        $webPage = $graph[array_search('WebPage', $types)];
        self::assertContains($webPage['isPartOf']['@id'], $ids);
        self::assertContains($webPage['breadcrumb']['@id'], $ids);

        // Verify JSON-LD output
        $jsonLd = json_encode([
            '@context' => 'https://schema.org',
            '@graph' => $graph,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        self::assertJson($jsonLd);
        self::assertStringContainsString('"@graph"', $jsonLd);
        self::assertStringContainsString('Example GmbH', $jsonLd);
    }

    #[Test]
    public function assemblesGraphWithArticlePiece(): void
    {
        $idGenerator = new IdGenerator();
        $providers = [
            new OrganizationPieceProvider($idGenerator),
            new WebSitePieceProvider($idGenerator),
            new WebPagePieceProvider($idGenerator),
            new ArticlePieceProvider($idGenerator),
        ];

        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher->method('dispatch')->willReturnCallback(fn($event) => $event);

        $validator = new GraphValidator([], new NullLogger());
        $assembler = new GraphAssembler($providers, [], $dispatcher, $validator);

        $context = new PageContext(
            site: $this->createMock(Site::class),
            pageRecord: [
                'uid' => 10,
                'title' => 'My Blog Post',
                'tx_seograph_schema_type' => 'BlogPosting',
                'tx_seograph_exclude' => 0,
                'tx_seograph_author' => 'Jane Doe',
                'crdate' => 1700000000,
                'tstamp' => 1700100000,
            ],
            pageUrl: 'https://example.com/blog/my-post/',
            siteBaseUrl: 'https://example.com/',
            language: $this->createMock(SiteLanguage::class),
            configuration: new SeoGraphConfiguration([], 'Blog'),
        );

        $graph = $assembler->assemble($context);

        $types = array_column($graph, '@type');
        self::assertContains('BlogPosting', $types);

        // Find the Article piece (has headline) among BlogPosting entries
        $articlePiece = null;
        foreach ($graph as $piece) {
            if (($piece['@type'] ?? '') === 'BlogPosting' && isset($piece['headline'])) {
                $articlePiece = $piece;
                break;
            }
        }
        self::assertNotNull($articlePiece);
        self::assertSame('My Blog Post', $articlePiece['headline']);
        self::assertSame('https://example.com/#author-jane-doe', $articlePiece['author']['@id']);
        self::assertSame(['@id' => 'https://example.com/blog/my-post/#webpage'], $articlePiece['isPartOf']);

        // WebPage piece should always use WebPage type, even when TCA schema type is an article type
        $webPageEntry = null;
        foreach ($graph as $piece) {
            if (($piece['@type'] ?? '') === 'WebPage' && isset($piece['url'])) {
                $webPageEntry = $piece;
                break;
            }
        }
        self::assertNotNull($webPageEntry);
        self::assertSame('https://example.com/blog/my-post/', $webPageEntry['url']);
    }

    #[Test]
    public function assemblesGraphWithPersonPiece(): void
    {
        $idGenerator = new IdGenerator();
        $providers = [
            new OrganizationPieceProvider($idGenerator),
            new WebSitePieceProvider($idGenerator),
            new WebPagePieceProvider($idGenerator),
            new ArticlePieceProvider($idGenerator),
            new PersonPieceProvider($idGenerator),
        ];

        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher->method('dispatch')->willReturnCallback(fn($event) => $event);

        $validator = new GraphValidator([
            new NoDuplicateIdsRule(),
        ], new NullLogger());

        $assembler = new GraphAssembler($providers, [], $dispatcher, $validator);

        $context = new PageContext(
            site: $this->createMock(Site::class),
            pageRecord: [
                'uid' => 20,
                'title' => 'Tech Article',
                'tx_seograph_schema_type' => 'Article',
                'tx_seograph_exclude' => 0,
                'tx_seograph_author' => 'Jane Doe',
                'crdate' => 1700000000,
                'tstamp' => 1700100000,
            ],
            pageUrl: 'https://example.com/tech-article/',
            siteBaseUrl: 'https://example.com/',
            language: $this->createMock(SiteLanguage::class),
            configuration: new SeoGraphConfiguration([
                'seoGraph' => [
                    'publisher' => [
                        'type' => 'Organization',
                        'name' => 'Tech Corp',
                        'url' => 'https://example.com/',
                        'logo' => 'https://example.com/logo.png',
                    ],
                    'validation' => [
                        'mode' => 'warning',
                    ],
                ],
            ], 'Tech'),
        );

        $graph = $assembler->assemble($context);

        $types = array_column($graph, '@type');
        self::assertContains('Person', $types);
        self::assertContains('Article', $types);

        // Collect all @id values — should have no duplicates
        $ids = array_filter(array_column($graph, '@id'));
        self::assertCount(count($ids), array_unique($ids), 'Graph must not contain duplicate @ids');

        // Find the Person piece
        $personPiece = null;
        foreach ($graph as $piece) {
            if (($piece['@type'] ?? '') === 'Person') {
                $personPiece = $piece;
                break;
            }
        }
        self::assertNotNull($personPiece, 'Person piece should be present in the graph');
        self::assertSame('Jane Doe', $personPiece['name']);
        self::assertSame('https://example.com/#author-jane-doe', $personPiece['@id']);

        // The Article piece's author @id must match the Person piece @id (deduplication)
        $articlePiece = null;
        foreach ($graph as $piece) {
            if (($piece['@type'] ?? '') === 'Article' && isset($piece['author'])) {
                $articlePiece = $piece;
                break;
            }
        }
        self::assertNotNull($articlePiece, 'Article piece should be present in the graph');
        self::assertSame($personPiece['@id'], $articlePiece['author']['@id']);
    }

    #[Test]
    public function richResultsRuleWarnsOnIncompleteArticle(): void
    {
        $idGenerator = new IdGenerator();
        $providers = [
            new WebPagePieceProvider($idGenerator),
            new ArticlePieceProvider($idGenerator),
        ];

        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher->method('dispatch')->willReturnCallback(fn($event) => $event);

        // Use the RichResultsArticleRule directly via the validator
        $richResultsRule = new RichResultsArticleRule();
        $validator = new GraphValidator([$richResultsRule], new NullLogger());

        // Disable graph-level validation filtering (mode=off) so we can inspect raw results
        $assembler = new GraphAssembler($providers, [], $dispatcher, new GraphValidator([], new NullLogger()));

        $context = new PageContext(
            site: $this->createMock(Site::class),
            pageRecord: [
                'uid' => 30,
                'title' => 'Incomplete Article',
                'tx_seograph_schema_type' => 'BlogPosting',
                'tx_seograph_exclude' => 0,
                // Deliberately missing: tx_seograph_author, crdate (datePublished)
                // Note: image is always emitted as an @id reference by ArticlePieceProvider
            ],
            pageUrl: 'https://example.com/incomplete/',
            siteBaseUrl: 'https://example.com/',
            language: $this->createMock(SiteLanguage::class),
            configuration: new SeoGraphConfiguration([], 'Test'),
        );

        $graph = $assembler->assemble($context);
        $results = $validator->validate($graph, $context);

        // Should warn about: datePublished, author (headline present from title; image emitted as @id ref)
        self::assertNotEmpty($results, 'Validation should produce warnings for incomplete article');

        $messages = array_map(fn($r) => $r->message, $results);
        $affectedTypes = array_map(fn($r) => $r->affectedType, $results);

        // All issues should be on BlogPosting type
        foreach ($affectedTypes as $type) {
            self::assertSame('BlogPosting', $type, 'All warnings should be for BlogPosting type');
        }

        // Should warn about missing datePublished (no crdate in page record)
        $hasDateWarning = false;
        foreach ($messages as $msg) {
            if (str_contains($msg, 'datePublished')) {
                $hasDateWarning = true;
                break;
            }
        }
        self::assertTrue($hasDateWarning, 'Should warn about missing datePublished on BlogPosting');

        // Should warn about missing author (no tx_seograph_author and no site default author)
        $hasAuthorWarning = false;
        foreach ($messages as $msg) {
            if (str_contains($msg, 'author')) {
                $hasAuthorWarning = true;
                break;
            }
        }
        self::assertTrue($hasAuthorWarning, 'Should warn about missing author on BlogPosting');
    }

    #[Test]
    public function modifierDecoratesPiece(): void
    {
        $idGenerator = new IdGenerator();
        $providers = [
            new OrganizationPieceProvider($idGenerator),
            new WebSitePieceProvider($idGenerator),
            new WebPagePieceProvider($idGenerator),
        ];

        // Create a modifier that appends a custom property to WebPage pieces
        $modifier = new class implements GraphPieceModifierInterface {
            public function supports(array $piece, PageContext $context): bool
            {
                return ($piece['@type'] ?? '') === 'WebPage';
            }

            public function modify(array $piece, PageContext $context): array
            {
                return array_merge($piece, ['x-custom-decorated' => true]);
            }

            public function getPriority(): int
            {
                return 50;
            }
        };

        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher->method('dispatch')->willReturnCallback(fn($event) => $event);

        $validator = new GraphValidator([], new NullLogger());
        $assembler = new GraphAssembler($providers, [$modifier], $dispatcher, $validator);

        $context = new PageContext(
            site: $this->createMock(Site::class),
            pageRecord: [
                'uid' => 5,
                'title' => 'Home',
                'tx_seograph_schema_type' => '',
                'tx_seograph_exclude' => 0,
                '_rootline' => [
                    ['uid' => 5, 'title' => 'Home', '_pageUrl' => 'https://example.com/'],
                ],
            ],
            pageUrl: 'https://example.com/',
            siteBaseUrl: 'https://example.com/',
            language: $this->createMock(SiteLanguage::class),
            configuration: new SeoGraphConfiguration([
                'seoGraph' => [
                    'publisher' => [
                        'type' => 'Organization',
                        'name' => 'Example Corp',
                        'url' => 'https://example.com/',
                        'logo' => 'https://example.com/logo.png',
                    ],
                    'validation' => ['mode' => 'off'],
                ],
            ], 'Example'),
        );

        $graph = $assembler->assemble($context);

        // Find the WebPage piece
        $webPagePiece = null;
        foreach ($graph as $piece) {
            if (($piece['@type'] ?? '') === 'WebPage') {
                $webPagePiece = $piece;
                break;
            }
        }

        self::assertNotNull($webPagePiece, 'WebPage piece should be present');
        self::assertTrue($webPagePiece['x-custom-decorated'] ?? false, 'Modifier should have decorated the WebPage piece');

        // Other pieces should NOT have the custom property
        foreach ($graph as $piece) {
            if (($piece['@type'] ?? '') !== 'WebPage') {
                self::assertArrayNotHasKey('x-custom-decorated', $piece, sprintf(
                    '%s piece should not be decorated by the WebPage modifier',
                    $piece['@type'] ?? 'unknown',
                ));
            }
        }
    }
}
