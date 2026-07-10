<?php

declare(strict_types=1);

use Illuminate\Foundation\Application;

beforeEach(function (): void {
    $this->basePath = sys_get_temp_dir().'/geoffrey-helpers-'.bin2hex(random_bytes(4));
    mkdir($this->basePath.'/skills', 0755, true);

    $app = new Application($this->basePath);
    Application::setInstance($app);
});

afterEach(function (): void {
    array_map(unlink(...), glob($this->basePath.'/skills/*.md') ?: []);
    @rmdir($this->basePath.'/skills');
    @rmdir($this->basePath);
    Application::setInstance();
});

it('loads a single skill by name', function (): void {
    file_put_contents($this->basePath.'/skills/summarize.md', '# Summarize Skill');

    expect(skill('summarize'))->toBe('# Summarize Skill');
});

it('loads multiple skills from an array', function (): void {
    file_put_contents($this->basePath.'/skills/summarize.md', '# Summarize');
    file_put_contents($this->basePath.'/skills/translate.md', '# Translate');

    $result = skill(['summarize', 'translate']);

    expect($result)->toContain('# Summarize');
    expect($result)->toContain('# Translate');
});

it('separates multiple skills with blank lines', function (): void {
    file_put_contents($this->basePath.'/skills/a.md', 'Skill A');
    file_put_contents($this->basePath.'/skills/b.md', 'Skill B');

    expect(skill(['a', 'b']))->toBe("Skill A\n\nSkill B");
});

it('skips missing skill files gracefully', function (): void {
    file_put_contents($this->basePath.'/skills/exists.md', 'I exist');

    $result = skill(['exists', 'does-not-exist']);

    expect($result)->toBe('I exist');
});

it('returns empty string when no skills are found', function (): void {
    expect(skill('nonexistent'))->toBe('');
    expect(skill(['also-missing']))->toBe('');
});
