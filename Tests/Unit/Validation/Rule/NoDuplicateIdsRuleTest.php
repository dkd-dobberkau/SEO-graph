<?php

declare(strict_types=1);

namespace Dkd\SeoGraph\Tests\Unit\Validation\Rule;

use Dkd\SeoGraph\Assembler\PageContext;
use Dkd\SeoGraph\Configuration\SeoGraphConfiguration;
use Dkd\SeoGraph\Validation\Rule\NoDuplicateIdsRule;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;

final class NoDuplicateIdsRuleTest extends TestCase
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
    public function validateReturnsEmptyForUniqueIds(): void
    {
        $graph = [
            ['@type' => 'Organization', '@id' => 'https://example.com/#organization'],
            ['@type' => 'WebSite', '@id' => 'https://example.com/#website'],
        ];

        $subject = new NoDuplicateIdsRule();
        $results = $subject->validate($graph, $this->context);

        self::assertSame([], $results);
    }

    #[Test]
    public function validateReportsduplicateIds(): void
    {
        $graph = [
            ['@type' => 'Organization', '@id' => 'https://example.com/#organization'],
            ['@type' => 'WebSite', '@id' => 'https://example.com/#organization'],
        ];

        $subject = new NoDuplicateIdsRule();
        $results = $subject->validate($graph, $this->context);

        self::assertCount(1, $results);
        self::assertSame('error', $results[0]->severity);
        self::assertStringContainsString('#organization', $results[0]->message);
    }
}
