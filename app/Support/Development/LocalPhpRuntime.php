<?php

namespace App\Support\Development;

class LocalPhpRuntime
{
    /** @return list<string> */
    public static function command(string $binary, string $cacheDirectory): array
    {
        $command = [$binary];
        if (! extension_loaded('Zend OPcache')) {
            $directory = ini_get('extension_dir');
            if (! preg_match('~^(?:[A-Za-z]:[\\\\/]|/)~', $directory)) {
                $directory = dirname($binary).'/'.$directory;
            }
            $extension = $directory.'/'.(PHP_OS_FAMILY === 'Windows' ? 'php_opcache.dll' : 'opcache.so');
            if (! is_file($extension)) return $command;
            array_push($command, '-d', 'zend_extension='.$extension);
        }

        array_push($command,
            '-d', 'opcache.enable=1',
            '-d', 'opcache.enable_cli=1',
            '-d', 'opcache.validate_timestamps=1',
            '-d', 'opcache.revalidate_freq=2',
        );
        if ((is_dir($cacheDirectory) || mkdir($cacheDirectory, 0775, true)) && is_writable($cacheDirectory)) {
            array_push($command, '-d', 'opcache.file_cache='.$cacheDirectory);
        }

        return $command;
    }
}
