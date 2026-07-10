<?php

declare(strict_types=1);

use Illuminate\Foundation\Application;

/**
 * Load skill content from markdown files.
 *
 * @param  string|array<string>  $skills
 */
function skill(string|array $skills): string
{
    $skills = is_array($skills) ? $skills : [$skills];
    $basePath = Application::getInstance()->basePath();
    $parts = [];

    foreach ($skills as $name) {
        $path = $basePath.'/skills/'.$name.'.md';

        if (! file_exists($path)) {
            continue;
        }

        $content = file_get_contents($path);

        if ($content !== false) {
            $parts[] = $content;
        }
    }

    return implode("\n\n", $parts);
}
