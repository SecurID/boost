<?php

declare(strict_types=1);

namespace Laravel\Boost\Install;

class GitignoreWriter
{
    protected const HEADER = '# Laravel Boost - generated AI agent skill directories';

    public function __construct(protected string $basePath)
    {
        //
    }

    /**
     * Ensure the given project-relative directories are ignored by git.
     *
     * Entries are anchored to the repository root and added only once. Paths that
     * resolve outside the project (e.g. "../skills") are skipped, since they cannot
     * be expressed as a repo-root .gitignore rule.
     *
     * @param  array<int, string>  $paths
     * @return array<int, string> The entries that were newly added.
     */
    public function ignore(array $paths): array
    {
        $entries = collect($paths)
            ->map(fn (string $path): string => $this->normalizeEntry($path))
            ->filter(fn (string $entry): bool => $entry !== '')
            ->unique()
            ->values();

        if ($entries->isEmpty()) {
            return [];
        }

        $gitignorePath = $this->basePath.DIRECTORY_SEPARATOR.'.gitignore';
        $existing = is_file($gitignorePath) ? (string) file_get_contents($gitignorePath) : '';
        $existingLines = $this->existingLines($existing);

        $missing = $entries
            ->reject(fn (string $entry): bool => in_array($this->comparable($entry), $existingLines, true))
            ->values();

        if ($missing->isEmpty()) {
            return [];
        }

        $block = self::HEADER."\n".$missing->implode("\n")."\n";

        $contents = $existing === ''
            ? $block
            : rtrim($existing, "\n")."\n\n".$block;

        if (file_put_contents($gitignorePath, $contents) === false) {
            return [];
        }

        return $missing->all();
    }

    protected function normalizeEntry(string $path): string
    {
        $path = str_replace('\\', '/', trim($path));
        $path = trim($path, '/');

        if ($path === '' || str_starts_with($path, '..')) {
            return '';
        }

        return '/'.$path.'/';
    }

    /**
     * @return array<int, string>
     */
    protected function existingLines(string $contents): array
    {
        return collect(preg_split('/\R/', $contents) ?: [])
            ->map(fn (string $line): string => $this->comparable($line))
            ->filter(fn (string $line): bool => $line !== '')
            ->values()
            ->all();
    }

    protected function comparable(string $entry): string
    {
        return trim(str_replace('\\', '/', $entry), "/ \t");
    }
}
