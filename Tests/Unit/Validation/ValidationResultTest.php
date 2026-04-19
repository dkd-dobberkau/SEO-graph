<?php

declare(strict_types=1);

namespace Dkd\SeoGraph\Tests\Unit\Validation;

use Dkd\SeoGraph\Validation\ValidationResult;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ValidationResultTest extends TestCase
{
    #[Test]
    public function warningResultHasCorrectSeverity(): void
    {
        $result = ValidationResult::warning('Missing name', 'Organization');
        self::assertSame('warning', $result->severity);
        self::assertSame('Missing name', $result->message);
        self::assertSame('Organization', $result->affectedType);
    }

    #[Test]
    public function errorResultHasCorrectSeverity(): void
    {
        $result = ValidationResult::error('Duplicate @id', 'WebPage');
        self::assertSame('error', $result->severity);
    }
}
