<?php

namespace ipl\Composer;

use Composer\Plugin\Capability\CommandProvider as CommandProviderCapability;
use ipl\Composer\Commands\AssetLoaderCommand;

class IplComposerCommandProvider implements CommandProviderCapability
{
    public function getCommands(): array
    {
        return [
            new AssetLoaderCommand(),
        ];
    }
}
