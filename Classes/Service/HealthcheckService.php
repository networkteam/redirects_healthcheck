<?php

namespace Networkteam\RedirectsHealthcheck\Service;

use Psr\Http\Message\UriInterface;
use Symfony\Component\Console\Output\OutputInterface;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Http\RequestFactory;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\Authentication\FrontendUserAuthentication;
use TYPO3\CMS\Redirects\Service\RedirectService;

class HealthcheckService
{
    const TABLE = 'sys_redirect';

    /**
     * @var Site
     */
    protected $defaultSite;

    /**
     * @var SiteFinder
     */
    protected $siteFinder;

    /**
     * @var RequestFactory
     */
    protected $requestFactory;

    /**
     * @var RedirectService
     */
    protected $redirectService;

    /**
     * @var FrontendUserAuthentication
     */
    protected $frontendUserAuthentication;

    /**
     * @var OutputInterface
     */
    protected $output;

    public function __construct(
        SiteFinder $siteFinder,
        RequestFactory $requestFactory,
        RedirectService $redirectService,
        FrontendUserAuthentication $frontendUserAuthentication
    ) {
        $this->siteFinder = $siteFinder;
        $this->requestFactory = $requestFactory;
        $this->redirectService = $redirectService;
        $this->frontendUserAuthentication = $frontendUserAuthentication;
    }

    public function runHealthcheck(): void
    {
        $queryBuilder = $this->getQueryBuilder();
        $statement = $queryBuilder
            ->select('*')
            ->from(self::TABLE)
            ->where(
                $queryBuilder->expr()->eq('is_regexp', 0),
                $queryBuilder->expr()->eq('disabled', 0)
            )
            ->execute();

        while ($redirect = $statement->fetch()) {
            $inactiveReason = $this->checkUrl($redirect);
            $this->updateRedirect($redirect['uid'], $inactiveReason);
        }
    }

    public function setDefaultSite(string $siteIdentifier): void
    {
        $this->defaultSite = $this->siteFinder->getSiteByIdentifier($siteIdentifier);
    }

    public function setOutput(OutputInterface $output) {
        $this->output = $output;
    }

    protected function checkUrl($redirect): ?string
    {
        $site = $this->findSiteBySourceHost($redirect['source_host']);
        // Resolving pages/records needs to boot TSFE. This fails in \TYPO3\CMS\Core\Http\Uri::parseUri() without a
        // request since cli script path is not a valid url.
        $GLOBALS['TYPO3_REQUEST'] = new ServerRequest($site->getBase());

        $uri = $this->redirectService->getTargetUrl(
            $redirect,
            [],
            $this->frontendUserAuthentication,
            $site->getBase(),
            $site
        );

        if ($this->output) {
            $sourceUrl = $site->getBase()->getScheme() . '://' . $site->getBase()->getHost() . $redirect['source_path'];
            $this->output->write(sprintf('<info>Checking #%s: %s =></info> ', $redirect['uid'], $sourceUrl));
        }

        if ($uri instanceof UriInterface) {
            $isFileOrFolder = empty($uri->getHost());
            if ($isFileOrFolder) {
                $url = sprintf('%s://%s/%s',
                    $site->getBase()->getScheme(),
                    $site->getBase()->getHost(),
                    $uri->getPath());
            } else {
                $url = $uri->__toString();
            }

            try {
                $requestOptions = [
                    'allow_redirects' => true,
                    'http_errors' => false,
                    'verify' => false
                ];
                $response = $this->requestFactory->request($url, 'HEAD', $requestOptions);
                if ($response->getStatusCode() !== 200) {
                    $inactiveReason = sprintf("Got response: %s %s", $response->getStatusCode(),
                        $response->getReasonPhrase());
                }
            } catch (\Throwable $e) {
                $inactiveReason = 'Unknown: ' . $e->getMessage();
            }
        }

        if ($this->output) {
            if (!$uri) {
                $this->output->writeln('<error>Could not resolve target</error>');
            } else {
                $this->output->write(sprintf('<info>%s</info> ', $url));
                if ($inactiveReason) {
                    $this->output->writeln(sprintf('<error>%s</error>', $inactiveReason));
                } else {
                    $this->output->writeln('<info>OK</info>');
                }
            }

        }

        return $inactiveReason ?? null;
    }

    protected function updateRedirect(int $redirectUid, $inactiveReason = null): void
    {
        $queryBuilder = $this->getQueryBuilder();
        $query = $queryBuilder
            ->update(self::TABLE)
            ->set('last_checked', $GLOBALS['EXEC_TIME'])
            ->where(
                $queryBuilder->expr()->eq('uid', $redirectUid)
            );

        if ($inactiveReason) {
            $query
                ->set('inactive_reason', $inactiveReason)
                ->set('disabled', 1);
        }
        $query->execute();
    }

    protected function findSiteBySourceHost($sourceHost): Site
    {
        if ($sourceHost === '*') {
            if ($this->defaultSite instanceof Site) {
                return $this->defaultSite;
            }

            $allSites = $this->siteFinder->getAllSites();
            return current($allSites);
        }

        foreach ($this->siteFinder->getAllSites() as $site) {
            if ($site->getBase()->getHost() === $sourceHost) {
                return $site;
            }
        }

        throw new \Exception('No site found', 1605950458);
    }

    protected function getQueryBuilder(): QueryBuilder
    {
        return GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable(self::TABLE);
    }
}
