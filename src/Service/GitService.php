<?php

namespace ipl\Composer\Service;

use Composer\Config;
use Composer\IO\IOInterface;
use Composer\Util\Filesystem;
use Composer\Util\Git;
use Composer\Util\ProcessExecutor;
use Exception;
use RuntimeException;

class GitService
{
    protected ?Git $git = null;

    static public function isGitInstalled(): bool
    {
        $executor = new ProcessExecutor();
        return Git::getVersion($executor) !== null;
    }

    public function __construct(IOInterface $io, Config $config)
    {
        if (! self::isGitInstalled()) {
            throw new RuntimeException('Git is not installed');
        }

        $this->git = new Git($io, $config, new ProcessExecutor(), new Filesystem());
    }

    public function getTags(): array
    {
        $this->git->runCommands(
            [
                ['git', 'tag'],
            ],
            '',
            null,
            false,
            $gitOutput,
        );

        return explode("\n", trim($gitOutput));
    }

    public function getCurrentBranch(): string
    {
        $this->git->runCommands(
            [
                ['git', 'rev-parse', '--abbrev-ref', 'HEAD'],
            ],
            '',
            null,
            false,
            $gitOutput,
        );

        return trim($gitOutput);
    }

    public function remove(string $path): void
    {
        $this->git->runCommands(
            [
                ['git', 'rm', '-rf', $path],
            ],
            '',
            null,
            false,
            $gitOutput,
        );
    }

    public function add(string $path): void
    {
        $this->git->runCommands(
            [
                ['git', 'add', '-f', $path],
            ],
            '',
            null,
            false,
            $gitOutput,
        );
    }

    public function commit(string $message): void
    {
        $this->git->runCommands(
            [
                ['git', 'commit', '-m', $message],
            ],
            '',
            null,
            false,
            $gitOutput,
        );
    }

    public function tag(string $name, string $message): void
    {
        $this->git->runCommands(
            [
                ['git', 'tag', '-a', $name, '-m', $message],
            ],
            '',
            null,
            false,
            $gitOutput,
        );
    }

    public function createBranchAndSwitch(string $branchName): bool
    {
        try {
            $this->git->runCommands(
                [
                    ['git', 'checkout', '-b', $branchName],
                ],
                '',
                null,
                false,
                $gitOutput,
            );
        } catch (Exception $e) {
            // TODO: Find a way to handle this better
            return false;
        }

        return true;
    }
}
