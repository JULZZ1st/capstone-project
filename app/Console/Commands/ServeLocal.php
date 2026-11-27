<?php

namespace App\Console\Commands;

use App\Support\Development\LocalPhpRuntime;
use Illuminate\Foundation\Console\ServeCommand;
use Symfony\Component\Console\Input\InputOption;

class ServeLocal extends ServeCommand
{
    public function handle()
    {
        if ($this->laravel->environment('local')) {
            if (PHP_OS_FAMILY === 'Windows') {
                $this->phpServerWorkers = false;
            }
            if ($this->laravel->configurationIsCached()) {
                $this->call('config:clear');
            }
            if (! $this->option('hot') && ! is_file(public_path('build/manifest.json'))) {
                $this->components->error('Run npm run build first, or use composer dev:hot for Vite development.');
                return self::FAILURE;
            }

            $values = [
                'LOCAL_BUILT_ASSETS' => $this->option('hot') ? 'false' : 'true',
                'LOCAL_SERVER_HOST' => in_array($this->host(), ['127.0.0.1', 'localhost'], true) ? '127.0.0.1' : '',
            ];
            foreach ($values as $key => $value) {
                $_ENV[$key] = $value;
                putenv($key.'='.$value);
            }
            static::$passthroughVariables = array_unique([...static::$passthroughVariables, ...array_keys($values)]);
        }

        return parent::handle();
    }

    protected function serverCommand()
    {
        $command = parent::serverCommand();
        if (! $this->laravel->environment('local')) return $command;

        return [...LocalPhpRuntime::command($command[0], storage_path('framework/cache/opcache')), ...array_slice($command, 1)];
    }

    protected function host()
    {
        $host = parent::host();
        return $this->laravel->environment('local') && $host === 'localhost' ? '127.0.0.1' : $host;
    }

    protected function getOptions()
    {
        return [...parent::getOptions(), ['hot', null, InputOption::VALUE_NONE, 'Use the running Vite development server instead of built assets']];
    }
}
