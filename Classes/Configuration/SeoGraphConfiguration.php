<?php

declare(strict_types=1);

namespace Dkd\SeoGraph\Configuration;

final class SeoGraphConfiguration
{
    private readonly array $seoGraphConfig;

    public function __construct(
        array $siteConfiguration,
        private readonly string $siteTitle,
    ) {
        $this->seoGraphConfig = $siteConfiguration['seoGraph'] ?? [];
    }

    public function getPublisherType(): string
    {
        return $this->seoGraphConfig['publisher']['type'] ?? 'Organization';
    }

    public function getPublisherName(): string
    {
        return $this->seoGraphConfig['publisher']['name'] ?? $this->siteTitle;
    }

    public function getPublisherUrl(): string
    {
        return $this->seoGraphConfig['publisher']['url'] ?? '';
    }

    public function getPublisherLogo(): string
    {
        return $this->seoGraphConfig['publisher']['logo'] ?? '';
    }

    /** @return string[] */
    public function getPublisherSameAs(): array
    {
        return $this->seoGraphConfig['publisher']['sameAs'] ?? [];
    }

    public function getDefaultAuthorType(): string
    {
        return $this->seoGraphConfig['defaultAuthor']['type'] ?? 'Person';
    }

    public function getDefaultAuthorName(): ?string
    {
        return $this->seoGraphConfig['defaultAuthor']['name'] ?? null;
    }

    public function getDefaultAuthorSlug(): string
    {
        return $this->seoGraphConfig['defaultAuthor']['slug'] ?? '';
    }

    public function getValidationMode(): string
    {
        return $this->seoGraphConfig['validation']['mode'] ?? 'off';
    }

    /** @return string[] */
    public function getValidationRules(): array
    {
        return $this->seoGraphConfig['validation']['rules'] ?? [];
    }
}
