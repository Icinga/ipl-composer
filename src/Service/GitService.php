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
    protected Git $git;

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

    public function getBranches(): array
    {
        $this->git->runCommands(
            [
                ['git', 'branch', '--list'],
            ],
            '',
            null,
            false,
            $gitOutput,
        );
        $branches = explode("\n", $gitOutput);
        $branches = array_map(fn($item) => trim($item, "* \n\r\t\v\0"), $branches);
        $branches = array_filter($branches, fn($item) => $item !== '');

        return $branches;
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

    public function commit(?string $message = null, bool $noEdit = false): void
    {
        $command = ['git', 'commit'];
        if ($message) {
            $command[] = '-m';
            $command[] = $message;
        }
        if ($noEdit) {
            $command[] = '--no-edit';
        }
        $this->git->runCommands(
            [
                $command,
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

    public function deleteBranch(string $branchName): void
    {
        if (! in_array($branchName, $this->getBranches(), true)) {
            return;
        }

        $this->git->runCommands(
            [
                ['git', 'branch', '-D', $branchName],
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

    public function merge(string $target, ?string $message, bool $fastForward = false, bool $commit = false): void
    {
        $command = ['git', 'merge'];
        if ($message) {
            $command[] = '-m';
            $command[] = $message;
        }
        if (! $fastForward) {
            $command[] = '--no-ff';
        }
        if (! $commit) {
            $command[] = '--no-commit';
        }
        $command[] = $target;

        $this->git->runCommands(
            [
                $command,
            ],
            '',
            null,
            false,
            $gitOutput,
        );
    }

    public function restore(string $string, bool $worktree = false, bool $staged = false): void
    {
        $command = ['git', 'restore'];
        if ($worktree) {
            $command[] = '--worktree';
        }
        if ($staged) {
            $command[] = '--staged';
        }
        $command[] = $string;
        $this->git->runCommands(
            [
                $command,
            ],
            '',
            null,
            false,
            $gitOutput,
        );
    }
}
