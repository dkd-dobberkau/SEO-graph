<?php

declare(strict_types=1);

namespace Dkd\SeoGraph\Tests\Unit\Validation;

use Dkd\SeoGraph\Assembler\PageContext;
use Dkd\SeoGraph\Configuration\SeoGraphConfiguration;
use Dkd\SeoGraph\Validation\GraphValidator;
use Dkd\SeoGraph\Validation\Rule\ValidationRuleInterface;
use Dkd\SeoGraph\Validation\ValidationResult;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;

final class GraphValidatorTest extends TestCase
{
    private function createContext(): PageContext
    {
        return new PageContext(
            site: $this->createMock(Site::class),
            pageRecord: ['tx_seograph_schema_type' => '', 'tx_seograph_exclude' => 0],
            pageUrl: 'https://example.com/',
            siteBaseUrl: 'https://example.com/',
            language: $this->createMock(SiteLanguage::class),
            configuration: new SeoGraphConfiguration([], 'Site'),
        );
    }

    #[Test]
    public function validateAndFilterReturnsUnmodifiedGraphWhenNoIssues(): void
    {
        $graph = [['@type' => 'Organization', '@id' => '#org', 'name' => 'Test']];
        $rule = $this->createMock(ValidationRuleInterface::class);
        $rule->method('validate')->willReturn([]);

        $subject = new GraphValidator([$rule], new NullLogger());
        $result = $subject->validateAndFilter($graph, $this->createContext(), 'warning');

        self::assertSame($graph, $result);
    }

    #[Test]
    public function validateAndFilterLogsWarningsButKeepsGraph(): void
    {
        $graph = [['@type' => 'Organization', '@id' => '#org']];
        $rule = $this->createMock(ValidationRuleInterface::class);
        $rule->method('validate')->willReturn([
            ValidationResult::warning('Missing name', 'Organization'),
        ]);

        $subject = new GraphValidator([$rule], new NullLogger());
        $result = $subject->validateAndFilter($graph, $this->createContext(), 'warning');

        self::assertCount(1, $result);
    }

    #[Test]
    public function validateAndFilterRemovesPiecesWithErrorsInErrorMode(): void
    {
        $graph = [
            ['@type' => 'Organization', '@id' => '#org', 'name' => 'Test'],
            ['@type' => 'Organization', '@id' => '#org'],
        ];
        $rule = $this->createMock(ValidationRuleInterface::class);
        $rule->method('validate')->willReturn([
            ValidationResult::error('Duplicate @id "#org"', 'Organization'),
        ]);

        $subject = new GraphValidator([$rule], new NullLogger());
        $result = $subject->validateAndFilter($graph, $this->createContext(), 'error');

        // In error mode, pieces with errors are removed — second org dropped
        self::assertCount(1, $result);
    }

    #[Test]
    public function validateReturnsAllResults(): void
    {
        $graph = [['@type' => 'Organization', '@id' => '#org']];
        $rule = $this->createMock(ValidationRuleInterface::class);
        $rule->method('validate')->willReturn([
            ValidationResult::warning('Issue 1', 'Organization'),
            ValidationResult::error('Issue 2', 'Organization'),
        ]);

        $subject = new GraphValidator([$rule], new NullLogger());
        $results = $subject->validate($graph, $this->createContext());

        self::assertCount(2, $results);
    }
}
