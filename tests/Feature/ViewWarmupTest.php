<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Illuminate\View\Compilers\BladeCompiler;
use Illuminate\View\FileViewFinder;
use Mockery;
use Tests\TestCase;

class ViewWarmupTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->directory = storage_path('framework/testing/view-warm-'.bin2hex(random_bytes(6)));
        $files = $this->app['files'];
        $files->makeDirectory($this->directory.'/views', 0755, true);
        $files->makeDirectory($this->directory.'/compiled', 0755, true);
        $this->app['view']->setFinder(new FileViewFinder($files, [$this->directory.'/views']));
        $this->app->instance('blade.compiler', new BladeCompiler($files, $this->directory.'/compiled'));
        $this->app['view.engine.resolver']->forget('blade');
    }

    protected function tearDown(): void
    {
        $this->app['files']->deleteDirectory($this->directory);

        parent::tearDown();
    }

    public function test_warmup_prepares_missing_views_without_recompiling_unchanged_views_or_deleting_cache(): void
    {
        $source = $this->directory.'/views/page.blade.php';
        file_put_contents($source, 'Hello {{ $name }}');
        touch($source, time() - 10);
        $existing = $this->directory.'/compiled/existing.php';
        file_put_contents($existing, 'Preserved');

        $compiler = Mockery::mock(BladeCompiler::class, [$this->app['files'], $this->directory.'/compiled'])->makePartial();
        $compiler->shouldReceive('compile')->once()->with(realpath($source))->passthru();
        $this->app->instance('blade.compiler', $compiler);

        $this->assertSame(0, Artisan::call('view:warm'));
        $this->assertFileExists($compiler->getCompiledPath(realpath($source)));
        $this->assertSame('Hello &lt;Donor&gt;', view('page', ['name' => '<Donor>'])->render());
        $this->assertSame(0, Artisan::call('view:warm'));
        $this->assertSame('Preserved', file_get_contents($existing));
    }

    public function test_changed_and_namespaced_templates_are_refreshed_before_rendering(): void
    {
        $source = $this->directory.'/views/page.blade.php';
        file_put_contents($source, 'Original {{ $name }}');
        touch($source, time() - 10);
        $this->app['files']->makeDirectory($this->directory.'/package');
        $package = $this->directory.'/package/notice.blade.php';
        file_put_contents($package, 'Package {{ $name }}');
        touch($package, time() - 10);
        $this->app['view']->addNamespace('warm-test', $this->directory.'/package');

        $this->assertSame(0, Artisan::call('view:warm'));
        $this->assertSame('Package Donor', view('warm-test::notice', ['name' => 'Donor'])->render());

        $compiled = $this->app['blade.compiler']->getCompiledPath(realpath($source));
        file_put_contents($source, 'Updated {{ $name }}');
        touch($compiled, time() - 20);
        clearstatcache();

        $this->assertSame(0, Artisan::call('view:warm'));
        $this->assertSame('Updated Donor', view('page', ['name' => 'Donor'])->render());
    }
}
