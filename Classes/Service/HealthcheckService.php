<?php

namespace Networkteam\RedirectsHealthcheck\Service;

use Networkteam\RedirectsHealthcheck\Domain\Model\Dto\CheckResult;
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

    const GOOD_CHECK_RESULT = 'OK';

    const BAD_CHECK_RESULT = 'Not OK';

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
     * @var bool
     */
    protected $shouldDisableUnhealthyRedirects = false;

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
            $result = $this->checkUrl($redirect);
            if ($this->output) {
                $this->printCheckResult($result);
            }
            $this->updateRedirect($result);
        }
    }

    public function setDefaultSite(string $siteIdentifier): void
    {
        $this->defaultSite = $this->siteFinder->getSiteByIdentifier($siteIdentifier);
    }

    public function setDisableUnhealthyRedirects(bool $shouldDisableUnhealthyRedirects): void
    {
        $this->shouldDisableUnhealthyRedirects = $shouldDisableUnhealthyRedirects;
    }

    public function setOutput(OutputInterface $output)
    {
        $this->output = $output;
    }

    protected function checkUrl($redirect): CheckResult
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
                    $unhealthyReason = sprintf("Got response: %s %s", $response->getStatusCode(),
                        $response->getReasonPhrase());
                }
            } catch (\Throwable $e) {
                $unhealthyReason = 'Unknown: ' . $e->getMessage();
            }
        } else {
            $unhealthyReason = 'Can not build target url';
        }

        $result = new CheckResult(
            $redirect,
            $unhealthyReason ? false : true,
            $unhealthyReason ? sprintf("%s. %s", self::BAD_CHECK_RESULT, $unhealthyReason) : self::GOOD_CHECK_RESULT,
            $url
        );

        return $result;
    }

    protected function updateRedirect(CheckResult $result): void
    {
        $queryBuilder = $this->getQueryBuilder();
        $query = $queryBuilder
            ->update(self::TABLE)
            ->set('last_checked', $GLOBALS['EXEC_TIME'])
            ->set('check_result', $result->getResultText())
            ->where(
                $queryBuilder->expr()->eq('uid', $result->getRedirect()['uid'])
            );

        if (!$result->isHealthy() && $this->shouldDisableUnhealthyRedirects) {
            $query->set('disabled', 1);
        }
        $query->execute();
    }

    protected function printCheckResult(CheckResult $result): void
    {
        $redirect = $result->getRedirect();
        $this->output->write(sprintf('<info>Redirect #%s: %s%s =></info> ', $redirect['uid'], $redirect['source_host'], $redirect['source_path']));
        if (!$result->getTargetUrl()) {
            $this->output->writeln(sprintf('<error>%</error>', $result->getResultText()));
        } else {
            $this->output->write(sprintf('<info>%s</info> ', $result->getTargetUrl()));
            if ($result->isHealthy()) {
                $this->output->writeln(sprintf('<info>%s</info>', $result->getResultText()));
            } else {
                $this->output->writeln(sprintf('<error>%s</error>', $result->getResultText()));
            }
        }
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
