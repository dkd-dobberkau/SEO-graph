<?php

declare(strict_types=1);

namespace Dkd\SeoGraph\Tests\Unit\Assembler;

use Dkd\SeoGraph\Assembler\GraphAssembler;
use Dkd\SeoGraph\Assembler\PageContext;
use Dkd\SeoGraph\Configuration\SeoGraphConfiguration;
use Dkd\SeoGraph\Event\AfterGraphAssembledEvent;
use Dkd\SeoGraph\Event\BeforeGraphAssembledEvent;
use Dkd\SeoGraph\Piece\GraphPieceModifierInterface;
use Dkd\SeoGraph\Piece\GraphPieceProviderInterface;
use Dkd\SeoGraph\Validation\GraphValidator;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\NullLogger;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;

final class GraphAssemblerTest extends TestCase
{
    private function createContext(array $siteConfig = []): PageContext
    {
        return new PageContext(
            site: $this->createMock(Site::class),
            pageRecord: ['tx_seograph_schema_type' => '', 'tx_seograph_exclude' => 0],
            pageUrl: 'https://example.com/about/',
            siteBaseUrl: 'https://example.com/',
            language: $this->createMock(SiteLanguage::class),
            configuration: new SeoGraphConfiguration($siteConfig, 'Test Site'),
        );
    }

    #[Test]
    public function assembleCollectsPiecesFromProviders(): void
    {
        $provider = $this->createMock(GraphPieceProviderInterface::class);
        $provider->method('supports')->willReturn(true);
        $provider->method('provide')->willReturn([
            ['@type' => 'Organization', '@id' => 'https://example.com/#organization', 'name' => 'Test'],
        ]);
        $provider->method('getPriority')->willReturn(10);

        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher->method('dispatch')->willReturnCallback(fn($event) => $event);

        $validator = new GraphValidator([], new NullLogger());

        $assembler = new GraphAssembler([$provider], [], $dispatcher, $validator);
        $result = $assembler->assemble($this->createContext());

        self::assertCount(1, $result);
        self::assertSame('Organization', $result[0]['@type']);
    }

    #[Test]
    public function assembleSkipsProvidersThatDoNotSupport(): void
    {
        $provider = $this->createMock(GraphPieceProviderInterface::class);
        $provider->method('supports')->willReturn(false);
        $provider->method('provide')->willReturn([['@type' => 'Thing']]);
        $provider->method('getPriority')->willReturn(10);

        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher->method('dispatch')->willReturnCallback(fn($event) => $event);

        $validator = new GraphValidator([], new NullLogger());

        $assembler = new GraphAssembler([$provider], [], $dispatcher, $validator);
        $result = $assembler->assemble($this->createContext());

        self::assertSame([], $result);
    }

    #[Test]
    public function assembleSortsProvidersByPriority(): void
    {
        $providerA = $this->createMock(GraphPieceProviderInterface::class);
        $providerA->method('supports')->willReturn(true);
        $providerA->method('provide')->willReturn([['@type' => 'Second', '@id' => '#second']]);
        $providerA->method('getPriority')->willReturn(20);

        $providerB = $this->createMock(GraphPieceProviderInterface::class);
        $providerB->method('supports')->willReturn(true);
        $providerB->method('provide')->willReturn([['@type' => 'First', '@id' => '#first']]);
        $providerB->method('getPriority')->willReturn(10);

        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher->method('dispatch')->willReturnCallback(fn($event) => $event);

        $validator = new GraphValidator([], new NullLogger());

        // Pass providers in wrong order — assembler should sort
        $assembler = new GraphAssembler([$providerA, $providerB], [], $dispatcher, $validator);
        $result = $assembler->assemble($this->createContext());

        self::assertSame('First', $result[0]['@type']);
        self::assertSame('Second', $result[1]['@type']);
    }

    #[Test]
    public function assembleIncludesPiecesFromBeforeEvent(): void
    {
        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher->method('dispatch')->willReturnCallback(function ($event) {
            if ($event instanceof BeforeGraphAssembledEvent) {
                $event->addPiece(['@type' => 'PrePopulated', '@id' => '#pre']);
            }
            return $event;
        });

        $validator = new GraphValidator([], new NullLogger());

        $assembler = new GraphAssembler([], [], $dispatcher, $validator);
        $result = $assembler->assemble($this->createContext());

        self::assertCount(1, $result);
        self::assertSame('PrePopulated', $result[0]['@type']);
    }

    #[Test]
    public function assembleAppliesModifierToMatchingPiece(): void
    {
        $provider = $this->createMock(GraphPieceProviderInterface::class);
        $provider->method('supports')->willReturn(true);
        $provider->method('provide')->willReturn([
            ['@type' => 'Article', '@id' => 'https://example.com/#article', 'headline' => 'Original'],
        ]);
        $provider->method('getPriority')->willReturn(10);

        $modifier = $this->createMock(GraphPieceModifierInterface::class);
        $modifier->method('supports')->willReturnCallback(
            fn(array $piece) => ($piece['@type'] ?? '') === 'Article'
        );
        $modifier->method('modify')->willReturnCallback(
            fn(array $piece) => array_merge($piece, ['headline' => 'Modified'])
        );
        $modifier->method('getPriority')->willReturn(10);

        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher->method('dispatch')->willReturnCallback(fn($event) => $event);

        $validator = new GraphValidator([], new NullLogger());

        $assembler = new GraphAssembler([$provider], [$modifier], $dispatcher, $validator);
        $result = $assembler->assemble($this->createContext());

        self::assertCount(1, $result);
        self::assertSame('Modified', $result[0]['headline']);
    }

    #[Test]
    public function assembleSkipsModifierThatDoesNotSupport(): void
    {
        $provider = $this->createMock(GraphPieceProviderInterface::class);
        $provider->method('supports')->willReturn(true);
        $provider->method('provide')->willReturn([
            ['@type' => 'Organization', '@id' => 'https://example.com/#organization', 'name' => 'Unchanged'],
        ]);
        $provider->method('getPriority')->willReturn(10);

        $modifier = $this->createMock(GraphPieceModifierInterface::class);
        $modifier->method('supports')->willReturn(false);
        $modifier->method('modify')->willReturn(['@type' => 'Organization', 'name' => 'CHANGED']);
        $modifier->method('getPriority')->willReturn(10);

        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher->method('dispatch')->willReturnCallback(fn($event) => $event);

        $validator = new GraphValidator([], new NullLogger());

        $assembler = new GraphAssembler([$provider], [$modifier], $dispatcher, $validator);
        $result = $assembler->assemble($this->createContext());

        self::assertSame('Unchanged', $result[0]['name']);
    }

    #[Test]
    public function assembleAppliesModifiersInPriorityOrder(): void
    {
        $provider = $this->createMock(GraphPieceProviderInterface::class);
        $provider->method('supports')->willReturn(true);
        $provider->method('provide')->willReturn([
            ['@type' => 'Article', '@id' => '#article', 'headline' => 'Step0'],
        ]);
        $provider->method('getPriority')->willReturn(10);

        // Modifier with priority 20 runs second
        $modifierB = $this->createMock(GraphPieceModifierInterface::class);
        $modifierB->method('supports')->willReturn(true);
        $modifierB->method('modify')->willReturnCallback(
            fn(array $piece) => array_merge($piece, ['headline' => $piece['headline'] . '+B'])
        );
        $modifierB->method('getPriority')->willReturn(20);

        // Modifier with priority 10 runs first
        $modifierA = $this->createMock(GraphPieceModifierInterface::class);
        $modifierA->method('supports')->willReturn(true);
        $modifierA->method('modify')->willReturnCallback(
            fn(array $piece) => array_merge($piece, ['headline' => $piece['headline'] . '+A'])
        );
        $modifierA->method('getPriority')->willReturn(10);

        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher->method('dispatch')->willReturnCallback(fn($event) => $event);

        $validator = new GraphValidator([], new NullLogger());

        // Pass in wrong order — assembler must sort
        $assembler = new GraphAssembler([$provider], [$modifierB, $modifierA], $dispatcher, $validator);
        $result = $assembler->assemble($this->createContext());

        self::assertSame('Step0+A+B', $result[0]['headline']);
    }

    #[Test]
    public function assembleRunsModifiersAfterProvidersBeforeAfterEvent(): void
    {
        $provider = $this->createMock(GraphPieceProviderInterface::class);
        $provider->method('supports')->willReturn(true);
        $provider->method('provide')->willReturn([
            ['@type' => 'WebPage', '@id' => '#webpage', 'name' => 'Provider'],
        ]);
        $provider->method('getPriority')->willReturn(10);

        $callLog = [];

        $modifier = $this->createMock(GraphPieceModifierInterface::class);
        $modifier->method('supports')->willReturn(true);
        $modifier->method('modify')->willReturnCallback(function (array $piece) use (&$callLog) {
            $callLog[] = 'modifier';
            return $piece;
        });
        $modifier->method('getPriority')->willReturn(10);

        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher->method('dispatch')->willReturnCallback(function ($event) use (&$callLog) {
            $callLog[] = get_class($event);
            return $event;
        });

        $validator = new GraphValidator([], new NullLogger());

        $assembler = new GraphAssembler([$provider], [$modifier], $dispatcher, $validator);
        $assembler->assemble($this->createContext());

        self::assertSame([
            \Dkd\SeoGraph\Event\BeforeGraphAssembledEvent::class,
            'modifier',
            \Dkd\SeoGraph\Event\AfterGraphAssembledEvent::class,
        ], $callLog);
    }
}
