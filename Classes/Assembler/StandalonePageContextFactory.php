<?php

declare(strict_types=1);

namespace Dkd\SeoGraph\Assembler;

use Dkd\SeoGraph\Configuration\SeoGraphConfiguration;
use TYPO3\CMS\Core\Domain\Repository\PageRepository;
use TYPO3\CMS\Core\Resource\FileRepository;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;
use TYPO3\CMS\Core\Utility\RootlineUtility;

final class StandalonePageContextFactory
{
    public function __construct(
        private readonly FileRepository $fileRepository,
        private readonly PageRepository $pageRepository,
    ) {}

    /**
     * Creates a PageContext for use outside of a frontend request context.
     * Used by ValidateGraphCommand and SeoGraphController.
     */
    public function createForPage(int $pageUid, Site $site, SiteLanguage $language): PageContext
    {
        $pageRecord = $this->pageRepository->getPage($pageUid);

        $siteConfig = $site->getConfiguration();
        $baseUrl = rtrim((string)$site->getBase(), '/') . '/';
        $configuration = new SeoGraphConfiguration($siteConfig, $siteConfig['websiteTitle'] ?? '');

        // Build rootline
        $rootline = $this->buildRootline($pageUid, $site, $language);
        $pageRecord['_rootline'] = $rootline;

        // Resolve FAL-based media fields
        $pageRecord['_media'] = $this->resolveMediaUrls($pageUid, 'media');
        $pageRecord['tx_seograph_primary_image'] = $this->resolvePrimaryImageUrl($pageUid);

        // Generate the page URL via site router
        $pageUrl = $baseUrl;
        try {
            $uri = $site->getRouter()->generateUri($pageUid, ['_language' => $language]);
            $pageUrl = (string)$uri;
        } catch (\Throwable) {
            // Fall back to base URL if URL cannot be generated (e.g., page has no slug)
        }

        return new PageContext(
            site: $site,
            pageRecord: $pageRecord,
            pageUrl: $pageUrl,
            siteBaseUrl: $baseUrl,
            language: $language,
            configuration: $configuration,
        );
    }

    private function buildRootline(int $pageUid, Site $site, SiteLanguage $language): array
    {
        $router = $site->getRouter();
        $rootline = [];

        try {
            $rootlineUtility = new RootlineUtility($pageUid);
            $rawRootline = $rootlineUtility->get();
            ksort($rawRootline);

            foreach ($rawRootline as $page) {
                $pageUrl = '';
                try {
                    $uri = $router->generateUri((int)$page['uid'], ['_language' => $language]);
                    $pageUrl = (string)$uri;
                } catch (\Throwable) {
                    // Skip pages that cannot be routed
                }

                $rootline[] = [
                    'uid'      => (int)$page['uid'],
                    'title'    => $page['title'] ?? '',
                    '_pageUrl' => $pageUrl,
                ];
            }
        } catch (\Throwable) {
            // If rootline resolution fails, return minimal entry for current page
            $rootline[] = ['uid' => $pageUid, 'title' => '', '_pageUrl' => ''];
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
            // FAL errors must not break the graph
        }
        return $urls;
    }

    private function resolvePrimaryImageUrl(int $pageUid): string
    {
        $urls = $this->resolveMediaUrls($pageUid, 'tx_seograph_primary_image');
        return $urls[0] ?? '';
    }
}
