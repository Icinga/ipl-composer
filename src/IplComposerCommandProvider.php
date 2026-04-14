<?php

namespace ipl\Composer;

use Composer\Plugin\Capability\CommandProvider as CommandProviderCapability;
use ipl\Composer\Commands\AssetCommand;
use ipl\Composer\Commands\ReleaseCommand;
use ipl\Composer\Commands\SnapshotCommand;

class IplComposerCommandProvider implements CommandProviderCapability
{
    public function getCommands(): array
    {
        return [
            new AssetCommand(),
            new ReleaseCommand(),
            new SnapshotCommand(),
        ];
    }
}
