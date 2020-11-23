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
                'mailaddress',
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
        $healthcheckService->setDisableBrokenRedirects($input->getOption('disable'));
        if ($input->getOption('mailaddress')) {
            $healthcheckService->setMailAddress($input->getOption('mailaddress'));
        }
        if ($output->isVerbose()) {
            $healthcheckService->setOutput($output);
        }
        $healthcheckService->runHealthcheck();

        return 0;
    }
}
