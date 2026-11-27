<?php

use Illuminate\Contracts\Console\Kernel;
use Symfony\Component\Process\Process;

require dirname(__DIR__).'/vendor/autoload.php';

$root = dirname(__DIR__);
$app = require $root.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

if (! $app->environment('local')) {
    fwrite(STDERR, "This server is for APP_ENV=local only.\n");
    exit(1);
}

if ($app->configurationIsCached()) {
    $app->make(Kernel::class)->call('config:clear');
}

if (! is_file($root.'/public/build/manifest.json')) {
    fwrite(STDERR, "Run npm run build before starting the local server.\n");
    exit(1);
}

$port = $argv[1] ?? '8000';
if (! ctype_digit($port) || (int) $port < 1 || (int) $port > 65535) {
    fwrite(STDERR, "Port must be a number from 1 to 65535.\n");
    exit(1);
}

$command = \App\Support\Development\LocalPhpRuntime::command(PHP_BINARY, $root.'/storage/framework/cache/opcache');
$opcacheAvailable = count($command) > 1;

array_push($command, '-S', '127.0.0.1:'.$port, $root.'/vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php');

fwrite(STDOUT, 'Local server: http://127.0.0.1:'.$port.PHP_EOL);
fwrite(STDOUT, 'Built assets; OPcache '.($opcacheAvailable ? 'enabled' : 'unavailable in this PHP installation').'.'.PHP_EOL);
if ($opcacheAvailable) {
    fwrite(STDOUT, 'PHP edits are checked every 2 seconds.'.PHP_EOL);
}

$environment = ['LOCAL_BUILT_ASSETS' => 'true', 'LOCAL_SERVER_HOST' => '127.0.0.1'];
if (PHP_OS_FAMILY === 'Windows') {
    $environment['PHP_CLI_SERVER_WORKERS'] = false;
}

$server = new Process($command, $root.'/public', $environment);
$server->setTimeout(null);
exit($server->run(static function (string $type, string $output): void {
    fwrite($type === Process::ERR ? STDERR : STDOUT, $output);
}));
