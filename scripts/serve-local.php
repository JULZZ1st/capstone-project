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
    fwrite(STDERR, "Run php artisan config:clear before starting the local server.\n");
    exit(1);
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

$command = [PHP_BINARY];
$opcacheAvailable = extension_loaded('Zend OPcache');
if (! $opcacheAvailable) {
    $extensionDirectory = ini_get('extension_dir');
    if (! preg_match('~^(?:[A-Za-z]:[\\\\/]|/)~', $extensionDirectory)) {
        $extensionDirectory = dirname(PHP_BINARY).'/'.$extensionDirectory;
    }
    $extension = $extensionDirectory.'/'.(PHP_OS_FAMILY === 'Windows' ? 'php_opcache.dll' : 'opcache.so');
    if (is_file($extension)) {
        array_push($command, '-d', 'zend_extension='.$extension);
        $opcacheAvailable = true;
    }
}

if ($opcacheAvailable) {
    array_push($command,
        '-d', 'opcache.enable=1',
        '-d', 'opcache.enable_cli=1',
        '-d', 'opcache.validate_timestamps=1',
        '-d', 'opcache.revalidate_freq=2',
    );
}

array_push($command, '-S', '127.0.0.1:'.$port, $root.'/vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php');

fwrite(STDOUT, 'Local server: http://127.0.0.1:'.$port.PHP_EOL);
fwrite(STDOUT, 'Built assets; OPcache '.($opcacheAvailable ? 'enabled' : 'unavailable in this PHP installation').'.'.PHP_EOL);
if ($opcacheAvailable) {
    fwrite(STDOUT, 'PHP edits are checked every 2 seconds.'.PHP_EOL);
}

$environment = ['LOCAL_BUILT_ASSETS' => 'true'];
if (PHP_OS_FAMILY === 'Windows') {
    $environment['PHP_CLI_SERVER_WORKERS'] = false;
}

$server = new Process($command, $root.'/public', $environment);
$server->setTimeout(null);
exit($server->run(static function (string $type, string $output): void {
    fwrite($type === Process::ERR ? STDERR : STDOUT, $output);
}));
