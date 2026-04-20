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

    public function forAuthor(string $baseUrl, string $slug): string
    {
        return rtrim($baseUrl, '/') . '/#author-' . $slug;
    }

    public function slugify(string $name): string
    {
        // Transliterate umlauts and accented characters to ASCII equivalents
        $transliterated = transliterator_transliterate('Any-Latin; Latin-ASCII; Lower()', $name);
        if ($transliterated === false) {
            $transliterated = mb_strtolower($name);
        }
        // Replace any non-alphanumeric character (except hyphens) with a hyphen
        $slug = preg_replace('/[^a-z0-9]+/', '-', $transliterated);
        // Collapse multiple hyphens and trim
        return trim((string)$slug, '-');
    }
}
