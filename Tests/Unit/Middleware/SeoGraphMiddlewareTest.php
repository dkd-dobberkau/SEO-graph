<?php

declare(strict_types=1);

namespace Dkd\SeoGraph\Tests\Unit\Middleware;

use Dkd\SeoGraph\Assembler\GraphAssembler;
use Dkd\SeoGraph\Assembler\PageContextFactory;
use Dkd\SeoGraph\Middleware\SeoGraphMiddleware;
use Dkd\SeoGraph\Validation\GraphValidator;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\NullLogger;
use TYPO3\CMS\Core\Resource\FileRepository;

final class SeoGraphMiddlewareTest extends TestCase
{
    private function makeAssembler(): GraphAssembler
    {
        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher->method('dispatch')->willReturnCallback(fn($event) => $event);
        $validator = new GraphValidator([], new NullLogger());
        return new GraphAssembler([], [], $dispatcher, $validator);
    }

    private function makeFactory(): PageContextFactory
    {
        $fileRepository = $this->createMock(FileRepository::class);
        return new PageContextFactory($fileRepository);
    }

    #[Test]
    public function processPassesThroughWhenNotHtml(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getHeader')->with('Content-Type')->willReturn(['application/json']);

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->method('handle')->willReturn($response);

        $request = $this->createMock(ServerRequestInterface::class);

        $middleware = new SeoGraphMiddleware($this->makeAssembler(), $this->makeFactory());
        $result = $middleware->process($request, $handler);

        self::assertSame($response, $result);
    }

    #[Test]
    public function processPassesThroughWhenFactoryReturnsNull(): void
    {
        // The factory returns null when request attributes are missing (no site, routing, etc.)
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getHeader')->with('Content-Type')->willReturn(['text/html; charset=utf-8']);

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->method('handle')->willReturn($response);

        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('getAttribute')->willReturn(null);

        $middleware = new SeoGraphMiddleware($this->makeAssembler(), $this->makeFactory());
        $result = $middleware->process($request, $handler);

        self::assertSame($response, $result);
    }
}
