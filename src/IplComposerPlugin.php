<?php

namespace ipl\Composer;

use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\Plugin\Capability\CommandProvider;
use Composer\Plugin\Capable;
use Composer\Plugin\PluginInterface;
use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\Script\Event;
use ipl\Composer\Service\AssetMirror;

class IplComposerPlugin implements PluginInterface, Capable, EventSubscriberInterface
{
    public function activate(Composer $composer, IOInterface $io)
    {

    }

    public function deactivate(Composer $composer, IOInterface $io)
    {

    }

    public function uninstall(Composer $composer, IOInterface $io)
    {

    }

    public function getCapabilities(): array
    {
        return [
            CommandProvider::class => IplComposerCommandProvider::class,
        ];
    }

    public static function getSubscribedEvents(): array
    {
        return [
            'post-install-cmd' => 'onPostInstall',
            'post-update-cmd' => 'onPostUpdate',
        ];
    }

    public function onPostInstall(Event $event): void
    {
        $event->getIO()->write('Mirroring asset directory');
        AssetMirror::mirror($event->getComposer());
    }

    public function onPostUpdate(Event $event): void
    {
        $event->getIO()->write('Mirroring asset directory');
        AssetMirror::mirror($event->getComposer());
    }
}
