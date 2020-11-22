<?php

namespace Networkteam\RedirectsHealthcheck\Command;

use Networkteam\RedirectsHealthcheck\Service\HealthcheckService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Object\ObjectManager;

class CheckCommand extends Command
{
    protected function configure()
    {
        $this->setDescription('Check health of redirects')
            ->addOption(
                'siteIdentifier',
                's',
                InputOption::VALUE_REQUIRED,
                'Site is used for wildcard source hosts. It defaults to first site found.')
            ->addOption(
                'mailAddress',
                'm',
                InputOption::VALUE_REQUIRED,
                'Recipient address for mail report')
            ->addOption(
                'disable',
                'd',
                InputOption::VALUE_NONE,
                'Disable broken redirects',
                null);
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $healthcheckService = GeneralUtility::makeInstance(ObjectManager::class)->get(HealthcheckService::class);
        if ($input->getOption('siteIdentifier')) {
            $healthcheckService->setDefaultSite($input->getOption('siteIdentifier'));
        }
        $healthcheckService->setDisableBrokenRedirects($input->getOption('disable'));
        if ($input->getOption('mailAddress')) {
            $healthcheckService->setMailAddress($input->getOption('mailAddress'));
        }
        if ($output->isVerbose()) {
            $healthcheckService->setOutput($output);
        }
        $healthcheckService->runHealthcheck();

        return 0;
    }
}
