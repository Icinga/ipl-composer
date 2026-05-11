<?php

namespace ipl\Composer\Commands;

use Composer\Command\BaseCommand;
use ipl\Composer\Service\AssetMirror;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class AssetCommand extends BaseCommand
{
    protected function configure(): void
    {
        $this->setName('assets')
            ->setDescription(
                'Collect and load assets from various asset directories. '
                . 'By default the contents of the "asset" directory in each packages root directory are collected, '
                . 'merged and placed into the "asset" directory of the application root directory. '
                . 'This behavior can be modified by specifying additional data in the composer.json file.',
            )
            ->addOption(
                'copy',
                'c',
                InputOption::VALUE_NONE,
                'Copy assets instead of creating symlinks',
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $mirror = new AssetMirror($this->requireComposer());
        $mirror->mirror($input->getOption('copy'));

        return 0;
    }
}
