<?php

declare(strict_types=1);

namespace Dkd\SeoGraph\Tests\Unit\Validation\Rule;

use Dkd\SeoGraph\Assembler\PageContext;
use Dkd\SeoGraph\Configuration\SeoGraphConfiguration;
use Dkd\SeoGraph\Validation\Rule\RequiredPropertiesRule;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;

final class RequiredPropertiesRuleTest extends TestCase
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
    public function validateReturnsEmptyForCompleteOrganization(): void
    {
        $graph = [
            ['@type' => 'Organization', '@id' => 'https://example.com/#org', 'name' => 'Test', 'url' => 'https://example.com/'],
        ];

        $subject = new RequiredPropertiesRule();
        self::assertSame([], $subject->validate($graph, $this->context));
    }

    #[Test]
    public function validateReportsMissingNameOnOrganization(): void
    {
        $graph = [
            ['@type' => 'Organization', '@id' => 'https://example.com/#org'],
        ];

        $subject = new RequiredPropertiesRule();
        $results = $subject->validate($graph, $this->context);

        self::assertNotEmpty($results);
        self::assertStringContainsString('name', $results[0]->message);
    }

    #[Test]
    public function validateReportsMissingUrlOnWebSite(): void
    {
        $graph = [
            ['@type' => 'WebSite', '@id' => 'https://example.com/#website', 'name' => 'Site'],
        ];

        $subject = new RequiredPropertiesRule();
        $results = $subject->validate($graph, $this->context);

        self::assertNotEmpty($results);
        self::assertStringContainsString('url', $results[0]->message);
    }

    #[Test]
    public function validateIgnoresUnknownTypes(): void
    {
        $graph = [
            ['@type' => 'CustomThing', '@id' => 'https://example.com/#custom'],
        ];

        $subject = new RequiredPropertiesRule();
        self::assertSame([], $subject->validate($graph, $this->context));
    }
}
