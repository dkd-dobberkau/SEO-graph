<?php

declare(strict_types=1);

namespace Dkd\SeoGraph\Tests\Unit\Id;

use Dkd\SeoGraph\Id\IdGenerator;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class IdGeneratorTest extends TestCase
{
    private IdGenerator $subject;

    protected function setUp(): void
    {
        parent::setUp();
        $this->subject = new IdGenerator();
    }

    #[Test]
    public function forSiteGeneratesIdWithFragment(): void
    {
        $result = $this->subject->forSite('https://example.com/', 'organization');
        self::assertSame('https://example.com/#organization', $result);
    }

    #[Test]
    public function forSiteStripsTrailingSlashBeforeFragment(): void
    {
        $result = $this->subject->forSite('https://example.com', 'website');
        self::assertSame('https://example.com/#website', $result);
    }

    #[Test]
    public function forPageGeneratesIdWithFragment(): void
    {
        $result = $this->subject->forPage('https://example.com/about/', 'webpage');
        self::assertSame('https://example.com/about/#webpage', $result);
    }

    #[Test]
    public function forPageStripsTrailingSlashBeforeFragment(): void
    {
        $result = $this->subject->forPage('https://example.com/about', 'breadcrumb');
        self::assertSame('https://example.com/about/#breadcrumb', $result);
    }
}
