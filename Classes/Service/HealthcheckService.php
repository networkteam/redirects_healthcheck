<?php

namespace Networkteam\RedirectsHealthcheck\Service;

use Networkteam\RedirectsHealthcheck\Domain\Model\Dto\CheckResult;
use Psr\Http\Message\UriInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Mime\Address;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Http\RequestFactory;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Mail\FluidEmail;
use TYPO3\CMS\Core\Mail\Mailer;
use TYPO3\CMS\Core\Mail\MailMessage;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\VersionNumberUtility;
use TYPO3\CMS\Frontend\Authentication\FrontendUserAuthentication;
use TYPO3\CMS\Redirects\Service\RedirectService;

class HealthcheckService
{
    const TABLE = 'sys_redirect';

    const GOOD_CHECK_RESULT = 'OK';

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
    protected $shouldDisableBrokenRedirects = false;

    /**
     * @var OutputInterface
     */
    protected $output;

    /**
     * @var string
     */
    protected $mailAddress;

    /**
     * @var bool
     */
    protected $shouldSendMailReport = false;

    /**
     * @var array<CheckResult
     */
    protected $badCheckResults;

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

            if (!$result->isHealthy() && $this->shouldSendMailReport) {
                $this->badCheckResults[] = $result;
            }

            $this->updateRedirect($result);
        }

        if ($this->shouldSendMailReport && $this->badCheckResults) {
            $this->sendMailReport();
        }
    }

    public function setDefaultSite(string $siteIdentifier): void
    {
        $this->defaultSite = $this->siteFinder->getSiteByIdentifier($siteIdentifier);
    }

    public function setDisableBrokenRedirects(bool $shouldDisableBrokenRedirects): void
    {
        $this->shouldDisableBrokenRedirects = $shouldDisableBrokenRedirects;
    }

    public function setOutput(OutputInterface $output): void
    {
        $this->output = $output;
    }

    public function setMailAddress(string $mailAddress): void
    {
        if (!filter_var($mailAddress, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('Mailaddress is invalid', 1606062072);
        }
        $this->mailAddress = $mailAddress;
        $this->shouldSendMailReport = true;
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
                    $badResultText = sprintf("Got response: %s %s", $response->getStatusCode(),
                        $response->getReasonPhrase());
                }
            } catch (\Throwable $e) {
                $badResultText = 'Unknown: ' . $e->getMessage();
            }
        } else {
            $badResultText = 'Can not build target url';
        }

        $result = new CheckResult(
            $redirect,
            $badResultText ? false : true,
            $badResultText ?: self::GOOD_CHECK_RESULT,
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

        if (!$result->isHealthy() && $this->shouldDisableBrokenRedirects) {
            $query->set('disabled', 1);
        }
        $query->execute();
    }

    protected function printCheckResult(CheckResult $result): void
    {
        $redirect = $result->getRedirect();
        $this->output->write(sprintf('<info>Redirect #%s: %s%s =></info> ', $redirect['uid'], $redirect['source_host'],
            $redirect['source_path']));
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

    protected function sendMailReport(): void
    {
        $csvFile = GeneralUtility::tempnam('broken-redirects', '.csv');
        $fileHandle = fopen($csvFile, 'w');
        fputs($fileHandle, $bom = (chr(0xEF) . chr(0xBB) . chr(0xBF)));

        $languageService = $this->getLanguageService();
        $languageFile = 'LLL:EXT:redirects_healthcheck/Resources/Private/Language/locallang_db.xlf:';
        $redirectsLanguageFile = 'LLL:EXT:redirects/Resources/Private/Language/locallang_db.xlf:';
        fputcsv($fileHandle, [
            $languageService->sL($redirectsLanguageFile . 'sys_redirect'),
            $languageService->sL($redirectsLanguageFile . 'sys_redirect.source_host'),
            $languageService->sL($redirectsLanguageFile . 'sys_redirect.source_path'),
            $languageService->sL($redirectsLanguageFile . 'sys_redirect.target'),
            $languageService->sL($languageFile . 'sys_redirect.check_result')
        ]);

        /** @var CheckResult $checkResult */
        foreach ($this->badCheckResults as $checkResult) {
            $redirect = $checkResult->getRedirect();
            fputcsv(
                $fileHandle,
                [
                    $redirect['uid'],
                    $redirect['source_host'],
                    $redirect['source_path'],
                    $checkResult->getTargetUrl(),
                    $checkResult->getResultText()
                ]
            );
        }
        fclose($fileHandle);

        try {
            $site = $this->defaultSite ?: $this->findSiteBySourceHost('*');
            $siteUrl = sprintf('%s://%s/', $site->getBase()->getScheme(), $site->getBase()->getHost());
            $senderEmail = $GLOBALS['TYPO3_CONF_VARS']['MAIL']['defaultMailFromAddress'];
            $senderName = $GLOBALS['TYPO3_CONF_VARS']['MAIL']['defaultMailFromName'];
            $subject = $languageService->sL($languageFile . 'email.subject');
            $body = $languageService->sL($languageFile . 'email.body');

            if (VersionNumberUtility::convertVersionNumberToInteger(VersionNumberUtility::getNumericTypo3Version()) > 10000000) {
                $email = GeneralUtility::makeInstance(FluidEmail::class);
                $email
                    ->to($this->mailAddress)
                    ->from(new Address($senderEmail, $senderName))
                    ->subject($subject)
                    ->assign('content', $body)
                    ->assign('normalizedParams', ['siteUrl' => $siteUrl])
                    ->attachFromPath($csvFile, 'broken-redirects.csv');
                GeneralUtility::makeInstance(Mailer::class)->send($email);
            } else {
                $email = GeneralUtility::makeInstance(MailMessage::class);
                $email
                    ->setSubject($subject)
                    ->setFrom([$senderEmail => $senderName])
                    ->setTo($this->mailAddress)
                    ->setBody($body)
                    ->attach(\Swift_Attachment::fromPath($csvFile))
                    ->send();
            }
        } catch (\Throwable $e) {
            unlink($csvFile);
            throw $e;
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

    protected function getLanguageService(): LanguageService
    {
        return $GLOBALS['LANG'];
    }
}
