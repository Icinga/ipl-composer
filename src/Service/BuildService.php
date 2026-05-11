<?php

namespace ipl\Composer\Service;

use FilesystemIterator;
use RecursiveCallbackFilterIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Symfony\Component\Console\Output\OutputInterface;

class BuildService
{
    public function __construct(
        protected ?GitService $git,
        protected ComposerService $composerService,
        protected OutputInterface $output,
    ) {
    }

    /**
     * @param string[] $includeFiles
     * @param string[] $excludeFiles
     */
    public function release(
        string $version,
        bool $checkout,
        bool $tag,
        array $includeFiles,
        array $excludeFiles,
    ): bool {
        $tags = $this->git->getTags();
        if (in_array($version, $tags)) {
            $this->output->writeln("Version $version has already been tagged!");
            return false;
        }

        if ($this->composerService->run(['validate', '--no-check-all', '--strict']) !== 0) {
            $this->output->writeln("Composer validate failed");
            return false;
        }

        if ($checkout) {
            $branchName = "stable/$version";
            if (! $this->git->createBranchAndSwitch($branchName)) {
                $this->output->writeln("Version branch $branchName already exists");
                return false;
            }
        } else {
            $branchName = $this->git->getCurrentBranch();
            $this->output->writeln("Selected version branch: $branchName");
        }

        if (! is_dir('vendor')) {
            $this->output->writeln('No vendor directory found.');
            return false;
        }

        foreach ($this->collectFilesToAdd('.', $includeFiles, $excludeFiles) as $file) {
            $this->git->add($file);
        }

        if (! file_put_contents('VERSION', "v$version")) {
            $this->output->writeln("Could not write version file");
            return false;
        }

        $this->git->add('VERSION');

        $this->git->commit("Version v$version");

        if ($tag) {
            $this->git->tag("v$version", "Version v$version");

            $this->output->writeln("Finished and tagged v$version");
        } else {
            $this->output->writeln('Not tagging the release');
            $this->output->writeln('Finished, but not tagged yet');

            $this->output->writeln(
                "Please run: git tag -s v$version -m \"Version v$version\"",
            );
        }

        $this->output->writeln(
            "Please run: git push origin \"$branchName:$branchName\" && git push --tags",
        );

        return true;
    }

    /**
     * @param string[] $includeFiles
     * @param string[] $excludeFiles
     * @return string[]
     */
    private function collectFilesToAdd(string $relDir, array $includeFiles, array $excludeFiles): array
    {
        $toAdd = [];
        $cwd = getcwd();
        $absDir = $relDir === '.' ? $cwd : "$cwd/$relDir";

        $filter = new RecursiveCallbackFilterIterator(
            new RecursiveDirectoryIterator($absDir, FilesystemIterator::SKIP_DOTS | FilesystemIterator::FOLLOW_SYMLINKS),
            function (SplFileInfo $entry) use ($cwd, $includeFiles, $excludeFiles): bool {
                if (str_starts_with($entry->getFilename(), '.')) {
                    return false;
                }

                if (! $entry->isDir()) {
                    return true;
                }

                if ($entry->isLink()) {
                    return false;
                }

                $relPath = ltrim(substr($entry->getPathname(), strlen($cwd)), '/');

                if ($this->shouldInclude("$relPath/", $includeFiles, $excludeFiles)) {
                    return true;
                }

                foreach ($excludeFiles as $pattern) {
                    if ($this->matchesPattern("$relPath/", $pattern)) {
                        return false;
                    }
                }

                return true;
            },
        );

        /** @var SplFileInfo $entry */
        foreach (new RecursiveIteratorIterator($filter, RecursiveIteratorIterator::LEAVES_ONLY) as $entry) {
            $relPath = ltrim(substr($entry->getPathname(), strlen($cwd)), '/');
            if ($this->shouldInclude($relPath, $includeFiles, $excludeFiles)) {
                $toAdd[] = $relPath;
            }
        }

        return $toAdd;
    }

    /**
     * @param string[] $includeFiles
     * @param string[] $excludeFiles
     */
    public function shouldInclude(string $path, array $includeFiles, array $excludeFiles): bool
    {
        $included = false;
        foreach ($includeFiles as $pattern) {
            if ($this->matchesPattern($path, $pattern)) {
                $included = true;
                break;
            }
        }

        if (! $included) {
            return false;
        }

        foreach ($excludeFiles as $pattern) {
            if ($this->matchesPattern($path, $pattern)) {
                return false;
            }
        }

        return true;
    }

    private function matchesPattern(string $path, string $pattern): bool
    {
        if (str_ends_with($pattern, '/')) {
            $dir = ltrim($pattern, '/');
            if (str_starts_with($pattern, '/')) {
                return $path === rtrim($dir, '/') || str_starts_with($path, $dir);
            }
            return str_contains('/' . $path . '/', '/' . $dir);
        }

        if (str_starts_with($pattern, '/')) {
            return fnmatch(ltrim($pattern, '/'), $path);
        }

        if (! str_contains($pattern, '/')) {
            return fnmatch($pattern, basename($path));
        }

        return fnmatch($pattern, $path);
    }
}
