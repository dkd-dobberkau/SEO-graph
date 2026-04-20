<?php

declare(strict_types=1);

namespace Dkd\SeoGraph\Command;

use Dkd\SeoGraph\Assembler\GraphAssembler;
use Dkd\SeoGraph\Assembler\StandalonePageContextFactory;
use Dkd\SeoGraph\Validation\GraphValidator;
use Dkd\SeoGraph\Validation\ValidationResult;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use TYPO3\CMS\Core\Domain\Repository\PageRepository;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\SiteFinder;

final class ValidateGraphCommand extends Command
{
    public function __construct(
        private readonly SiteFinder $siteFinder,
        private readonly GraphAssembler $assembler,
        private readonly GraphValidator $validator,
        private readonly StandalonePageContextFactory $contextFactory,
        private readonly PageRepository $pageRepository,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setDescription('Validates the SEO Graph JSON-LD output for one or all pages of a site.');
        $this->addOption('site', null, InputOption::VALUE_REQUIRED, 'Site identifier (required)');
        $this->addOption('page', null, InputOption::VALUE_OPTIONAL, 'Page UID to validate (omit for all pages)');
        $this->addOption('format', null, InputOption::VALUE_OPTIONAL, 'Output format: text or json (default: text)', 'text');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $siteIdentifier = (string)$input->getOption('site');
        $format = (string)($input->getOption('format') ?? 'text');

        // Load site
        try {
            $site = $this->siteFinder->getSiteByIdentifier($siteIdentifier);
        } catch (SiteNotFoundException $e) {
            $output->writeln(sprintf('<error>Site "%s" not found: %s</error>', $siteIdentifier, $e->getMessage()));
            return 2;
        } catch (\Throwable $e) {
            $output->writeln(sprintf('<error>Technical error loading site "%s": %s</error>', $siteIdentifier, $e->getMessage()));
            return 2;
        }

        $language = $site->getDefaultLanguage();

        // Determine pages to validate
        $pageUids = $this->resolvePageUids($input, $site);
        if ($pageUids === null) {
            $output->writeln('<error>Could not determine pages to validate.</error>');
            return 2;
        }

        // Validate each page
        $pageResults = [];
        $totalErrors = 0;
        $totalWarnings = 0;

        foreach ($pageUids as $pageUid) {
            $context = null;
            try {
                $pageRecord = $this->pageRepository->getPage($pageUid);
                $context = $this->contextFactory->createForPage($pageUid, $site, $language);
                $graph = $this->assembler->assemble($context);
                $issues = $this->validator->validate($graph, $context);
            } catch (\Throwable $e) {
                $issues = [];
                $pageRecord = ['uid' => $pageUid, 'title' => '(error: ' . $e->getMessage() . ')'];
            }

            $errors = array_filter($issues, fn(ValidationResult $r) => $r->severity === 'error');
            $warnings = array_filter($issues, fn(ValidationResult $r) => $r->severity === 'warning');
            $totalErrors += count($errors);
            $totalWarnings += count($warnings);

            $pageResults[] = [
                'uid'    => $pageUid,
                'title'  => $pageRecord['title'] ?? '',
                'url'    => $context !== null ? $context->pageUrl : '',
                'issues' => array_map(
                    fn(ValidationResult $r) => [
                        'severity' => $r->severity,
                        'message'  => $r->message,
                        'type'     => $r->affectedType,
                    ],
                    $issues,
                ),
            ];
        }

        if ($format === 'json') {
            return $this->outputJson($output, $pageResults, $totalErrors, $totalWarnings);
        }

        return $this->outputText($output, $siteIdentifier, $pageResults, $totalErrors, $totalWarnings);
    }

    private function outputText(
        OutputInterface $output,
        string $siteIdentifier,
        array $pageResults,
        int $totalErrors,
        int $totalWarnings,
    ): int {
        $pageCount = count($pageResults);
        $output->writeln(sprintf('Validating site "%s" (%d page%s)...', $siteIdentifier, $pageCount, $pageCount === 1 ? '' : 's'));
        $output->writeln('');

        foreach ($pageResults as $page) {
            $output->writeln(sprintf('Page %d "%s" (%s)', $page['uid'], $page['title'], $page['url']));
            if (empty($page['issues'])) {
                $output->writeln('  <info>OK</info>');
            } else {
                foreach ($page['issues'] as $issue) {
                    $symbol = $issue['severity'] === 'error' ? '<error>x</error>' : '<comment>!</comment>';
                    $output->writeln(sprintf('  %s %s', $symbol, $issue['message']));
                }
            }
            $output->writeln('');
        }

        $output->writeln(sprintf(
            'Result: %d error%s, %d warning%s across %d page%s',
            $totalErrors,
            $totalErrors === 1 ? '' : 's',
            $totalWarnings,
            $totalWarnings === 1 ? '' : 's',
            $pageCount,
            $pageCount === 1 ? '' : 's',
        ));

        return ($totalErrors > 0 || $totalWarnings > 0) ? 1 : 0;
    }

    private function outputJson(OutputInterface $output, array $pageResults, int $totalErrors, int $totalWarnings): int
    {
        $data = [
            'pages'   => $pageResults,
            'summary' => [
                'errors'        => $totalErrors,
                'warnings'      => $totalWarnings,
                'pages_checked' => count($pageResults),
            ],
        ];

        $output->write(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        return ($totalErrors > 0 || $totalWarnings > 0) ? 1 : 0;
    }

    /**
     * Returns a list of page UIDs to validate.
     * Returns null on technical error.
     *
     * @return int[]|null
     */
    private function resolvePageUids(InputInterface $input, Site $site): ?array
    {
        $pageOption = $input->getOption('page');
        if ($pageOption !== null) {
            return [(int)$pageOption];
        }

        // All pages in the site: use the site root page and recurse
        try {
            $rootPageId = $site->getRootPageId();
            return $this->getAllPageUids($rootPageId);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Recursively collects all page UIDs under the given root.
     *
     * @return int[]
     */
    private function getAllPageUids(int $rootPageId): array
    {
        $uids = [$rootPageId];
        try {
            $subPages = $this->pageRepository->getMenu($rootPageId, '*', 'sorting', '', false);
            foreach ($subPages as $subPage) {
                $subUid = (int)$subPage['uid'];
                $uids = [...$uids, ...$this->getAllPageUids($subUid)];
            }
        } catch (\Throwable) {
            // Ignore pages that cannot be loaded
        }
        return $uids;
    }
}
