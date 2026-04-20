<?php

namespace ipl\Composer\Commands;

use Composer\Command\BaseCommand;
use ipl\Composer\Service\AssetMirror;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class AssetCommand extends BaseCommand
{
    protected function configure(): void
    {
        $this->setName('assets')
            ->setDescription('Collect and load assets from various asset directories')
            ->addOption("copy", "c", null, "Copy assets instead of creating symlinks");
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        AssetMirror::mirror($this->requireComposer(), $input->getOption('copy'));

        return 0;
    }
}
