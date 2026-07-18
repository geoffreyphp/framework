<?php

declare(strict_types=1);

namespace Geoffrey\Database\Console;

class MigrateCommand extends \Illuminate\Database\Console\Migrations\MigrateCommand
{
    /**
     * @param  string  $path
     */
    protected function createMissingSqliteDatabase($path): bool
    {
        $directory = dirname($path);

        if (! is_dir($directory)) {
            mkdir($directory, 0755, recursive: true);
        }

        return parent::createMissingSqliteDatabase($path);
    }
}
