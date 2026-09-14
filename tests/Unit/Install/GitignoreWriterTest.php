<?php

declare(strict_types=1);

use Laravel\Boost\Install\GitignoreWriter;

beforeEach(function (): void {
    $this->tempBase = sys_get_temp_dir().DIRECTORY_SEPARATOR.'boost-gitignore-'.uniqid();
    mkdir($this->tempBase, 0755, true);
    $this->gitignore = $this->tempBase.DIRECTORY_SEPARATOR.'.gitignore';
});

afterEach(function (): void {
    if (is_file($this->gitignore)) {
        @unlink($this->gitignore);
    }

    if (is_dir($this->tempBase)) {
        @rmdir($this->tempBase);
    }
});

it('creates a .gitignore with normalized, anchored entries', function (): void {
    $added = (new GitignoreWriter($this->tempBase))->ignore(['.claude/skills', '.cursor/skills/']);

    expect($added)->toBe(['/.claude/skills/', '/.cursor/skills/'])
        ->and($this->gitignore)->toBeFile();

    $contents = file_get_contents($this->gitignore);

    expect($contents)
        ->toContain('# Laravel Boost - generated AI agent skill directories')
        ->toContain('/.claude/skills/')
        ->toContain('/.cursor/skills/');
});

it('is idempotent and only appends missing entries', function (): void {
    $writer = new GitignoreWriter($this->tempBase);

    $writer->ignore(['.claude/skills']);
    $added = $writer->ignore(['.claude/skills', '.cursor/skills']);

    expect($added)->toBe(['/.cursor/skills/']);

    // Running again with everything present adds nothing.
    expect($writer->ignore(['.claude/skills', '.cursor/skills']))->toBe([]);

    $contents = file_get_contents($this->gitignore);

    expect(substr_count($contents, '/.claude/skills/'))->toBe(1)
        ->and(substr_count($contents, '/.cursor/skills/'))->toBe(1);
});

it('treats an existing entry as present regardless of leading or trailing slashes', function (): void {
    file_put_contents($this->gitignore, ".claude/skills\n");

    $added = (new GitignoreWriter($this->tempBase))->ignore(['.claude/skills']);

    expect($added)->toBe([]);
});

it('preserves existing content when appending', function (): void {
    file_put_contents($this->gitignore, "/vendor\n/node_modules\n");

    (new GitignoreWriter($this->tempBase))->ignore(['.claude/skills']);

    $contents = file_get_contents($this->gitignore);

    expect($contents)
        ->toContain('/vendor')
        ->toContain('/node_modules')
        ->toContain('/.claude/skills/');
});

it('deduplicates repeated paths', function (): void {
    $added = (new GitignoreWriter($this->tempBase))->ignore(['.agents/skills', '.agents/skills/']);

    expect($added)->toBe(['/.agents/skills/']);
});

it('skips paths that resolve outside the project root', function (): void {
    $added = (new GitignoreWriter($this->tempBase))->ignore(['../shared/skills', '.claude/skills']);

    expect($added)->toBe(['/.claude/skills/']);
});

it('returns an empty array when there is nothing to ignore', function (): void {
    $added = (new GitignoreWriter($this->tempBase))->ignore([]);

    expect($added)->toBe([])
        ->and($this->gitignore)->not->toBeFile();
});
