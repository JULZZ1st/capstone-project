<?php

namespace App\Console\Commands;

use Illuminate\Foundation\Console\ViewCacheCommand;

class WarmViews extends ViewCacheCommand
{
    protected $signature = 'view:warm';

    protected $description = 'Compile missing or changed Blade templates without clearing the existing view cache';

    public function handle(): int
    {
        $compiler = $this->laravel['blade.compiler'];
        $compiled = 0;

        foreach ($this->paths() as $path) {
            foreach ($this->bladeFilesIn([$path]) as $file) {
                $source = $file->getRealPath();

                if ($compiler->isExpired($source)) {
                    $compiler->compile($source);
                    $compiled++;
                }
            }
        }

        $this->components->info("Prepared {$compiled} Blade templates. Existing cached views were preserved.");

        return self::SUCCESS;
    }
}
