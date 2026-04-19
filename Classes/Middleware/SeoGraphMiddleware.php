<?php

declare(strict_types=1);

namespace Dkd\SeoGraph\Middleware;

use Dkd\SeoGraph\Assembler\GraphAssembler;
use Dkd\SeoGraph\Assembler\PageContextFactory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Http\StreamFactory;

final class SeoGraphMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly GraphAssembler $assembler,
        private readonly PageContextFactory $pageContextFactory,
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = $handler->handle($request);

        $contentType = $response->getHeader('Content-Type')[0] ?? '';
        if (!str_contains($contentType, 'text/html')) {
            return $response;
        }

        $pageContext = $this->pageContextFactory->createFromRequest($request);
        if ($pageContext === null || !$pageContext->isGraphEnabled()) {
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

        return $response->withBody($newBody)->withoutHeader('Content-Length');
    }
}
