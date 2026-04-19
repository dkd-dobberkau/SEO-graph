<?php

declare(strict_types=1);

namespace Dkd\SeoGraph\Tests\Unit\Middleware;

use Dkd\SeoGraph\Assembler\GraphAssembler;
use Dkd\SeoGraph\Middleware\SeoGraphMiddleware;
use Dkd\SeoGraph\Validation\GraphValidator;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\NullLogger;

final class SeoGraphMiddlewareTest extends TestCase
{
    private function makeAssembler(): GraphAssembler
    {
        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher->method('dispatch')->willReturnCallback(fn($event) => $event);
        $validator = new GraphValidator([], new NullLogger());
        return new GraphAssembler([], $dispatcher, $validator);
    }

    #[Test]
    public function processPassesThroughWhenNoSite(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->method('handle')->willReturn($response);

        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('getAttribute')->willReturn(null);

        $middleware = new SeoGraphMiddleware($this->makeAssembler());
        $result = $middleware->process($request, $handler);

        self::assertSame($response, $result);
    }

    #[Test]
    public function processPassesThroughWhenNoRouting(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->method('handle')->willReturn($response);

        $site = $this->createMock(\TYPO3\CMS\Core\Site\Entity\Site::class);

        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('getAttribute')->willReturnCallback(function (string $name) use ($site) {
            return match ($name) {
                'site' => $site,
                default => null,
            };
        });

        $middleware = new SeoGraphMiddleware($this->makeAssembler());
        $result = $middleware->process($request, $handler);

        self::assertSame($response, $result);
    }
}
