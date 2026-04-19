<?php

declare(strict_types=1);

namespace Dkd\SeoGraph\Tests\Unit\Integration;

use Dkd\SeoGraph\Assembler\GraphAssembler;
use Dkd\SeoGraph\Assembler\PageContext;
use Dkd\SeoGraph\Configuration\SeoGraphConfiguration;
use Dkd\SeoGraph\Id\IdGenerator;
use Dkd\SeoGraph\Piece\ArticlePieceProvider;
use Dkd\SeoGraph\Piece\BreadcrumbListPieceProvider;
use Dkd\SeoGraph\Piece\ImageObjectPieceProvider;
use Dkd\SeoGraph\Piece\OrganizationPieceProvider;
use Dkd\SeoGraph\Piece\WebPagePieceProvider;
use Dkd\SeoGraph\Piece\WebSitePieceProvider;
use Dkd\SeoGraph\Validation\GraphValidator;
use Dkd\SeoGraph\Validation\Rule\NoDuplicateIdsRule;
use Dkd\SeoGraph\Validation\Rule\ReferencesResolveRule;
use Dkd\SeoGraph\Validation\Rule\RequiredPropertiesRule;
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

        $assembler = new GraphAssembler($providers, $dispatcher, $validator);

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
        $assembler = new GraphAssembler($providers, $dispatcher, $validator);

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
        self::assertSame('Jane Doe', $articlePiece['author']['name']);
        self::assertSame(['@id' => 'https://example.com/blog/my-post/#webpage'], $articlePiece['isPartOf']);

        // WebPage should also use BlogPosting type since that's what's in the TCA field
        $webPageEntry = null;
        foreach ($graph as $piece) {
            if (($piece['@type'] ?? '') === 'BlogPosting' && isset($piece['url'])) {
                $webPageEntry = $piece;
                break;
            }
        }
        self::assertNotNull($webPageEntry);
    }
}
