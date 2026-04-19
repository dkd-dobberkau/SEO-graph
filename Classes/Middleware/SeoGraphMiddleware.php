<?php

declare(strict_types=1);

namespace Dkd\SeoGraph\Middleware;

use Dkd\SeoGraph\Assembler\GraphAssembler;
use Dkd\SeoGraph\Assembler\PageContext;
use Dkd\SeoGraph\Configuration\SeoGraphConfiguration;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Http\StreamFactory;
use TYPO3\CMS\Core\Routing\PageArguments;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;

final class SeoGraphMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly GraphAssembler $assembler,
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = $handler->handle($request);

        $site = $request->getAttribute('site');
        if (!$site instanceof Site) {
            return $response;
        }

        $routing = $request->getAttribute('routing');
        if (!$routing instanceof PageArguments) {
            return $response;
        }

        $contentType = $response->getHeader('Content-Type')[0] ?? '';
        if (!str_contains($contentType, 'text/html')) {
            return $response;
        }

        $language = $request->getAttribute('language');
        if (!$language instanceof SiteLanguage) {
            return $response;
        }

        $siteConfig = $site->getConfiguration();
        $baseUrl = rtrim((string)$site->getBase(), '/') . '/';
        $configuration = new SeoGraphConfiguration($siteConfig, $siteConfig['websiteTitle'] ?? '');

        $pageContext = new PageContext(
            site: $site,
            pageRecord: $request->getAttribute('frontend.page.information')?->getPageRecord()
                ?? $GLOBALS['TSFE']->page
                ?? ['uid' => $routing->getPageId(), 'title' => '', 'tx_seograph_schema_type' => '', 'tx_seograph_exclude' => 0],
            pageUrl: (string)$request->getUri(),
            siteBaseUrl: $baseUrl,
            language: $language,
            configuration: $configuration,
        );

        if (!$pageContext->isGraphEnabled()) {
            return $response;
        }

        $graph = $this->assembler->assemble($pageContext);
        if ($graph === []) {
            return $response;
        }

        $jsonLd = json_encode([
            '@context' => 'https://schema.org',
            '@graph' => $graph,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        $scriptTag = '<script type="application/ld+json">' . $jsonLd . '</script>';

        $html = (string)$response->getBody();

        // Remove existing JSON-LD blocks from EXT:schema
        $html = preg_replace('/<script type="application\/ld\+json">.*?<\/script>/s', '', $html);

        // Inject before </head>
        $html = str_replace('</head>', $scriptTag . "\n</head>", $html);

        $streamFactory = new StreamFactory();
        $newBody = $streamFactory->createStream($html);

        return $response->withBody($newBody);
    }
}
