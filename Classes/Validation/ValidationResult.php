<?php

declare(strict_types=1);

namespace Dkd\SeoGraph\Validation;

final readonly class ValidationResult
{
    private function __construct(
        public string $severity,
        public string $message,
        public string $affectedType,
    ) {}

    public static function warning(string $message, string $affectedType = ''): self
    {
        return new self('warning', $message, $affectedType);
    }

    public static function error(string $message, string $affectedType = ''): self
    {
        return new self('error', $message, $affectedType);
    }
}
