<?php

namespace Networkteam\RedirectsHealthcheck\Service;

use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Http\RequestFactory;
use TYPO3\CMS\Core\LinkHandling\LinkService;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class HealthcheckService
{
    const TABLE = 'sys_redirect';

    /**
     * @var Site
     */
    protected $site;

    /**
     * @var LinkService
     */
    protected $linkService;

    /**
     * @var RequestFactory
     */
    protected $requestFactory;

    public function __construct(SiteFinder $siteFinder, LinkService $linkService, RequestFactory $requestFactory)
    {
        $sites = $siteFinder->getAllSites();
        $this->site = current($sites);

        $this->linkService = $linkService;
        $this->requestFactory = $requestFactory;
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

    protected function checkUrl($redirect): ?string
    {
        $linkDetails = $this->linkService->resolve($redirect['target']);

        switch ($linkDetails['type']) {
            case 'page':
                if (isset($linkDetails['parameters'])) {
                    parse_str($linkDetails['parameters'], $parameters);
                    $parameters['_language'] = $parameters['L'];
                    unset($parameters['L']);
                }
                try {
                    $url = $this->site->getRouter()->generateUri($linkDetails['pageuid'],
                        $parameters ?? [])->__toString();
                } catch (\Throwable $e) {
                    return $e->getMessage();
                }
                break;
            case 'url':
                $url = $linkDetails['url'];
                break;
            case 'file':
                if (is_null($linkDetails['file'])) {
                    return 'File record does not exist';
                }
                break;
            case 'unknown':
                if (strpos($redirect['target'], '/') == 0) {
                    $scheme = $this->site->getBase()->getScheme();
                    if ($redirect['force_https']) {
                        $scheme = 'https';
                    }
                    $domain = $redirect['source_host'] === '*' ? $this->site->getBase()->getHost() : $redirect['source_host'];
                    $url = implode('', [$scheme, '://', $domain, $redirect['target']]);
                }
        }

        if (isset($url)) {
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

    protected function getQueryBuilder(): QueryBuilder
    {
        return GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable(self::TABLE);
    }
}
