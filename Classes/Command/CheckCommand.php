<?php

namespace Networkteam\RedirectsHealthcheck\Command;

use Networkteam\RedirectsHealthcheck\Service\HealthcheckService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Object\ObjectManager;

class CheckCommand extends Command
{
    protected function configure()
    {
        $this->setDescription('Check health of redirects and disable them in case of unreachable destinations')
            ->addArgument('siteIdentifier', InputArgument::OPTIONAL, 'Site is used for wildcard source hosts. It defaults to first site found.');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $healthcheckService = GeneralUtility::makeInstance(ObjectManager::class)->get(HealthcheckService::class);
        if ($input->getArgument('siteIdentifier')) {
            $healthcheckService->setDefaultSite($input->getArgument('siteIdentifier'));
        }
        if ($output->isVerbose()) {
            $healthcheckService->setOutput($output);
        }
        $healthcheckService->runHealthcheck();

        return 0;
    }
}
