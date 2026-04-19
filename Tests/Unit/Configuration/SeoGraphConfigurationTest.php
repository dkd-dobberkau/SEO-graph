<?php

declare(strict_types=1);

namespace Dkd\SeoGraph\Tests\Unit\Configuration;

use Dkd\SeoGraph\Configuration\SeoGraphConfiguration;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SeoGraphConfigurationTest extends TestCase
{
    #[Test]
    public function publisherReturnsConfiguredValues(): void
    {
        $config = [
            'seoGraph' => [
                'publisher' => [
                    'type' => 'Organization',
                    'name' => 'Example GmbH',
                    'url' => 'https://example.com/',
                    'logo' => 'https://example.com/logo.png',
                    'sameAs' => ['https://linkedin.com/company/example'],
                ],
            ],
        ];
        $subject = new SeoGraphConfiguration($config, 'Example Site');

        self::assertSame('Organization', $subject->getPublisherType());
        self::assertSame('Example GmbH', $subject->getPublisherName());
        self::assertSame('https://example.com/', $subject->getPublisherUrl());
        self::assertSame('https://example.com/logo.png', $subject->getPublisherLogo());
        self::assertSame(['https://linkedin.com/company/example'], $subject->getPublisherSameAs());
    }

    #[Test]
    public function publisherNameFallsBackToSiteTitle(): void
    {
        $subject = new SeoGraphConfiguration([], 'Fallback Title');
        self::assertSame('Fallback Title', $subject->getPublisherName());
        self::assertSame('Organization', $subject->getPublisherType());
    }

    #[Test]
    public function defaultAuthorReturnsConfiguredValues(): void
    {
        $config = [
            'seoGraph' => [
                'defaultAuthor' => [
                    'type' => 'Person',
                    'name' => 'Jane Doe',
                    'slug' => 'jane-doe',
                ],
            ],
        ];
        $subject = new SeoGraphConfiguration($config, 'Site');
        self::assertSame('Person', $subject->getDefaultAuthorType());
        self::assertSame('Jane Doe', $subject->getDefaultAuthorName());
        self::assertSame('jane-doe', $subject->getDefaultAuthorSlug());
    }

    #[Test]
    public function defaultAuthorReturnsNullWhenNotConfigured(): void
    {
        $subject = new SeoGraphConfiguration([], 'Site');
        self::assertNull($subject->getDefaultAuthorName());
    }

    #[Test]
    public function validationModeDefaultsToOff(): void
    {
        $subject = new SeoGraphConfiguration([], 'Site');
        self::assertSame('off', $subject->getValidationMode());
        self::assertSame([], $subject->getValidationRules());
    }

    #[Test]
    public function validationReturnsConfiguredValues(): void
    {
        $config = [
            'seoGraph' => [
                'validation' => [
                    'mode' => 'warning',
                    'rules' => ['references_resolve', 'no_duplicate_ids'],
                ],
            ],
        ];
        $subject = new SeoGraphConfiguration($config, 'Site');
        self::assertSame('warning', $subject->getValidationMode());
        self::assertSame(['references_resolve', 'no_duplicate_ids'], $subject->getValidationRules());
    }
}
