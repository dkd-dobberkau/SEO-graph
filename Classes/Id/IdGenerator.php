<?php

declare(strict_types=1);

namespace Dkd\SeoGraph\Id;

final class IdGenerator
{
    public function forSite(string $baseUrl, string $fragment): string
    {
        return rtrim($baseUrl, '/') . '/#' . $fragment;
    }

    public function forPage(string $pageUrl, string $fragment): string
    {
        return rtrim($pageUrl, '/') . '/#' . $fragment;
    }
}
