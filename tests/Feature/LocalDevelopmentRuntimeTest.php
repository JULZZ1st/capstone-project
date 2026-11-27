<?php

namespace Tests\Feature;

use App\Console\Commands\ServeLocal;
use App\Http\Middleware\CanonicalizeLocalHost;
use App\Providers\AppServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class LocalDevelopmentRuntimeTest extends TestCase
{
    public function test_standard_serve_command_uses_the_project_runtime(): void
    {
        $this->assertInstanceOf(ServeLocal::class, Artisan::all()['serve']);
        $this->assertTrue(Artisan::all()['serve']->getDefinition()->hasOption('hot'));
    }

    public function test_local_runtime_keeps_php_change_detection_and_persistent_bytecode_cache(): void
    {
        $this->app['env'] = 'local';
        $command = new class extends ServeLocal {
            public function commandForTest(string $host = '127.0.0.1'): array
            {
                $this->input = new ArrayInput(['--host' => $host, '--port' => 8123], $this->getDefinition());
                return $this->serverCommand();
            }
        };
        $command->setLaravel($this->app);
        $arguments = $command->commandForTest();
        $this->assertContains('127.0.0.1:8123', $arguments);
        $this->assertContains('127.0.0.1:8123', $command->commandForTest('localhost'));
        if (! in_array('opcache.enable=1', $arguments, true)) {
            $this->markTestSkipped('OPcache is not installed for this PHP binary.');
        }
        $this->assertContains('opcache.validate_timestamps=1', $arguments);
        $this->assertContains('opcache.file_cache='.storage_path('framework/cache/opcache'), $arguments);
    }

    public function test_local_built_assets_ignore_a_stale_vite_marker(): void
    {
        $this->app['env'] = 'local';
        config(['app.local_built_assets' => true]);
        $marker = storage_path('framework/testing-vite.hot');
        file_put_contents($marker, 'http://localhost:59999');
        try {
            Vite::useHotFile($marker);
            $this->assertTrue(Vite::isRunningHot());
            (new AppServiceProvider($this->app))->boot();
            $this->assertFalse(Vite::isRunningHot());
            $this->assertStringContainsString('/build/', (string) Vite::asset('resources/js/app.js'));
        } finally {
            unlink($marker);
        }
    }

    public function test_explicit_hot_reload_is_preserved(): void
    {
        $this->app['env'] = 'local';
        config(['app.local_built_assets' => false]);
        $marker = storage_path('framework/testing-vite.hot');
        file_put_contents($marker, 'http://localhost:59999');
        try {
            Vite::useHotFile($marker);
            (new AppServiceProvider($this->app))->boot();
            $this->assertStringContainsString('localhost:59999', (string) Vite::asset('resources/js/app.js'));
        } finally {
            unlink($marker);
        }
    }

    public function test_loopback_redirect_retains_the_requested_page_and_filters(): void
    {
        $this->app['env'] = 'local';
        config(['app.local_server_host' => '127.0.0.1']);
        $response = (new CanonicalizeLocalHost())->handle(
            Request::create('http://localhost:8000/inventory?search=Rice&page=2'),
            fn () => new Response('should not render'),
        );
        $this->assertSame('http://127.0.0.1:8000/inventory?search=Rice&page=2', $response->headers->get('Location'));
    }

    public function test_local_redirect_does_not_rewrite_post_requests_or_production_hosts(): void
    {
        config(['app.local_server_host' => '127.0.0.1']);
        foreach ([['local', 'POST', 'localhost'], ['production', 'GET', 'localhost'], ['local', 'GET', 'barangay.test']] as [$environment, $method, $host]) {
            $this->app['env'] = $environment;
            $response = (new CanonicalizeLocalHost())->handle(
                Request::create('http://'.$host.':8000/donations', $method),
                fn () => new Response('unchanged'),
            );
            $this->assertSame('unchanged', $response->getContent());
        }
    }
}
