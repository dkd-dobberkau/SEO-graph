<?php

declare(strict_types=1);

namespace Dkd\SeoGraph\Tests\Unit\Validation\Rule;

use Dkd\SeoGraph\Assembler\PageContext;
use Dkd\SeoGraph\Configuration\SeoGraphConfiguration;
use Dkd\SeoGraph\Validation\Rule\ReferencesResolveRule;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;

final class ReferencesResolveRuleTest extends TestCase
{
    private PageContext $context;

    protected function setUp(): void
    {
        parent::setUp();
        $this->context = new PageContext(
            site: $this->createMock(Site::class),
            pageRecord: ['tx_seograph_schema_type' => '', 'tx_seograph_exclude' => 0],
            pageUrl: 'https://example.com/',
            siteBaseUrl: 'https://example.com/',
            language: $this->createMock(SiteLanguage::class),
            configuration: new SeoGraphConfiguration([], 'Site'),
        );
    }

    #[Test]
    public function validateReturnsEmptyWhenAllReferencesResolve(): void
    {
        $graph = [
            ['@type' => 'Organization', '@id' => 'https://example.com/#organization'],
            ['@type' => 'WebSite', '@id' => 'https://example.com/#website', 'publisher' => ['@id' => 'https://example.com/#organization']],
        ];

        $subject = new ReferencesResolveRule();
        self::assertSame([], $subject->validate($graph, $this->context));
    }

    #[Test]
    public function validateReportsUnresolvedReferences(): void
    {
        $graph = [
            ['@type' => 'WebSite', '@id' => 'https://example.com/#website', 'publisher' => ['@id' => 'https://example.com/#missing']],
        ];

        $subject = new ReferencesResolveRule();
        $results = $subject->validate($graph, $this->context);

        self::assertCount(1, $results);
        self::assertSame('warning', $results[0]->severity);
        self::assertStringContainsString('#missing', $results[0]->message);
    }

    #[Test]
    public function validateHandlesNestedReferences(): void
    {
        $graph = [
            ['@type' => 'WebPage', '@id' => 'https://example.com/#webpage', 'isPartOf' => ['@id' => 'https://example.com/#website'], 'breadcrumb' => ['@id' => 'https://example.com/#breadcrumb']],
        ];

        $subject = new ReferencesResolveRule();
        $results = $subject->validate($graph, $this->context);

        // Two unresolved refs: #website and #breadcrumb
        self::assertCount(2, $results);
    }
}
