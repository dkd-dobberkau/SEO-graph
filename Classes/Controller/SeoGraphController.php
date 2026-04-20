<?php
declare(strict_types=1);
namespace Dkd\SeoGraph\Controller;

use Dkd\SeoGraph\Assembler\GraphAssembler;
use Dkd\SeoGraph\Assembler\StandalonePageContextFactory;
use Dkd\SeoGraph\Validation\GraphValidator;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Core\Site\SiteFinder;

final class SeoGraphController
{
    public function __construct(
        private readonly ModuleTemplateFactory $moduleTemplateFactory,
        private readonly GraphAssembler $assembler,
        private readonly GraphValidator $validator,
        private readonly StandalonePageContextFactory $pageContextFactory,
        private readonly SiteFinder $siteFinder,
    ) {}

    public function indexAction(ServerRequestInterface $request): ResponseInterface
    {
        $moduleTemplate = $this->moduleTemplateFactory->create($request);
        $pageUid = (int)($request->getQueryParams()['id'] ?? 0);

        $jsonLd = '';
        $validationResults = [];
        $pageUrl = '';

        if ($pageUid > 0) {
            try {
                $site = $this->siteFinder->getSiteByPageId($pageUid);
                $language = $site->getDefaultLanguage();
                $pageContext = $this->pageContextFactory->createForPage($pageUid, $site, $language);
                $graph = $this->assembler->assemble($pageContext);
                $validationResults = $this->validator->validate($graph, $pageContext);
                $pageUrl = $pageContext->pageUrl;
                $jsonLd = json_encode([
                    '@context' => 'https://schema.org',
                    '@graph' => $graph,
                ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            } catch (\Throwable $e) {
                $jsonLd = 'Error: ' . $e->getMessage();
            }
        }

        $moduleTemplate->assignMultiple([
            'pageUid' => $pageUid,
            'jsonLd' => $jsonLd,
            'validationResults' => $validationResults,
            'pageUrl' => $pageUrl,
            'richResultsTestUrl' => $pageUrl !== '' ? 'https://search.google.com/test/rich-results?url=' . urlencode($pageUrl) : '',
        ]);

        return $moduleTemplate->renderResponse('SeoGraph/Index');
    }
}
