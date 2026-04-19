<?php

declare(strict_types=1);

namespace Dkd\SeoGraph\Assembler;

use Dkd\SeoGraph\Configuration\SeoGraphConfiguration;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Resource\FileRepository;
use TYPO3\CMS\Core\Routing\PageArguments;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;
use TYPO3\CMS\Frontend\Page\PageInformation;

final class PageContextFactory
{
    public function __construct(
        private readonly FileRepository $fileRepository,
    ) {}

    public function createFromRequest(ServerRequestInterface $request): ?PageContext
    {
        $site = $request->getAttribute('site');
        if (!$site instanceof Site) {
            return null;
        }

        $routing = $request->getAttribute('routing');
        if (!$routing instanceof PageArguments) {
            return null;
        }

        $language = $request->getAttribute('language');
        if (!$language instanceof SiteLanguage) {
            return null;
        }

        $pageInformation = $request->getAttribute('frontend.page.information');
        if (!$pageInformation instanceof PageInformation) {
            return null;
        }

        $pageRecord = $pageInformation->getPageRecord();
        $pageUid = (int)($pageRecord['uid'] ?? $routing->getPageId());

        $siteConfig = $site->getConfiguration();
        $baseUrl = rtrim((string)$site->getBase(), '/') . '/';
        $configuration = new SeoGraphConfiguration($siteConfig, $siteConfig['websiteTitle'] ?? '');

        // Enrich with rootline data
        $rootline = $this->buildRootline($pageInformation->getRootLine(), $site, $language);
        $pageRecord['_rootline'] = $rootline;

        // Enrich with FAL-resolved media URLs
        $pageRecord['_media'] = $this->resolveMediaUrls($pageUid, 'media');
        $pageRecord['tx_seograph_primary_image'] = $this->resolvePrimaryImageUrl($pageUid);

        return new PageContext(
            site: $site,
            pageRecord: $pageRecord,
            pageUrl: (string)$request->getUri(),
            siteBaseUrl: $baseUrl,
            language: $language,
            configuration: $configuration,
        );
    }

    private function buildRootline(array $rawRootline, Site $site, SiteLanguage $language): array
    {
        $router = $site->getRouter();
        $rootline = [];

        // getRootLine() returns highest key = deepest page, we need ordered root-to-current
        $sorted = $rawRootline;
        ksort($sorted);

        foreach ($sorted as $page) {
            $pageUrl = '';
            try {
                $uri = $router->generateUri(
                    (int)$page['uid'],
                    ['_language' => $language],
                );
                $pageUrl = (string)$uri;
            } catch (\Throwable) {
                // Skip URL generation errors (e.g., page has no slug)
            }

            $rootline[] = [
                'uid' => (int)$page['uid'],
                'title' => $page['title'] ?? '',
                '_pageUrl' => $pageUrl,
            ];
        }

        return $rootline;
    }

    /** @return string[] */
    private function resolveMediaUrls(int $pageUid, string $fieldName): array
    {
        $urls = [];
        try {
            $fileReferences = $this->fileRepository->findByRelation('pages', $fieldName, $pageUid);
            foreach ($fileReferences as $fileReference) {
                $url = $fileReference->getPublicUrl();
                if ($url !== null) {
                    $urls[] = $url;
                }
            }
        } catch (\Throwable) {
            // FAL errors should not break the graph
        }
        return $urls;
    }

    private function resolvePrimaryImageUrl(int $pageUid): string
    {
        $urls = $this->resolveMediaUrls($pageUid, 'tx_seograph_primary_image');
        return $urls[0] ?? '';
    }
}
