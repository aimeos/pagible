<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;


class InstallTest extends \Orchestra\Testbench\TestCase
{
    private string $dir;


    protected function setUp(): void
    {
        parent::setUp();

        // the install command changes application files, so use a temporary application directory
        $this->dir = sys_get_temp_dir().'/cms-install-'.uniqid();
        File::ensureDirectoryExists($this->dir.'/public');
        $this->app->setBasePath($this->dir);
    }


    protected function tearDown(): void
    {
        File::deleteDirectory($this->dir);

        parent::tearDown();
    }


    public function testComposer(): void
    {
        $this->commands($calls);
        file_put_contents($this->dir.'/composer.json', json_encode(['name' => 'app', 'extra' => (object) [], 'scripts' => [
            'post-update-cmd' => ['@php artisan vendor:publish --tag=laravel-assets --ansi --force', '@php artisan migrate'],
        ]]));

        $this->artisan('cms:install')->assertExitCode(0);
        $this->artisan('cms:install')->assertExitCode(0);

        $content = (string) file_get_contents($this->dir.'/composer.json');
        $json = json_decode($content, true);

        $this->assertStringContainsString('"extra": {}', $content);
        $this->assertEquals([
            '@php artisan vendor:publish --tag=laravel-assets --ansi --force',
            '@php artisan migrate',
            '@php artisan vendor:publish --force --tag=cms-admin --tag=cms-graphql',
            '@php artisan vendor:publish --tag=cms-theme',
        ], $json['scripts']['post-update-cmd']);
    }


    public function testComposerInvalid(): void
    {
        $this->commands($calls);
        file_put_contents($this->dir.'/composer.json', '{');

        $this->artisan('cms:install')->assertExitCode(1);
    }


    public function testComposerScripts(): void
    {
        $this->commands($calls);
        file_put_contents($this->dir.'/composer.json', '{"name": "app"}');

        $this->artisan('cms:install')->assertExitCode(0);

        $json = json_decode((string) file_get_contents($this->dir.'/composer.json'), true);
        $this->assertContains('@php artisan migrate', $json['scripts']['post-update-cmd']);
    }


    public function testRobots(): void
    {
        $this->commands($calls);
        file_put_contents($this->dir.'/public/robots.txt', "User-agent: *\r\nDisallow:\r\n");

        $this->artisan('cms:install')->assertExitCode(0);

        $this->assertFileDoesNotExist($this->dir.'/public/robots.txt');
    }


    public function testRobotsCustom(): void
    {
        $this->commands($calls);
        file_put_contents($this->dir.'/public/robots.txt', "User-agent: *\nDisallow: /admin\n");

        $this->artisan('cms:install')
            ->expectsOutputToContain('contains custom rules')
            ->assertExitCode(0);

        $this->assertFileExists($this->dir.'/public/robots.txt');
    }


    public function testUrl(): void
    {
        $this->commands($calls);
        config(['app.url' => 'http://localhost']);

        $this->artisan('cms:install')->expectsOutputToContain('has no port')->assertExitCode(0);

        config(['app.url' => 'http://localhost:8000']);

        $this->artisan('cms:install')->doesntExpectOutputToContain('has no port')->assertExitCode(0);
    }


    public function testFailure(): void
    {
        $this->commands($calls, 1);

        $this->artisan('cms:install')->assertExitCode(1);
    }


    public function testSeed(): void
    {
        $this->commands($calls);

        $this->artisan('cms:install', ['--seed' => true])->assertExitCode(0);

        $this->assertEquals(['cms:install:core' => true, 'cms:install:graphql' => false, 'cms:install:ai' => false], $calls);
    }


    protected function getPackageProviders($app): array
    {
        return [\Aimeos\Cms\ServiceProvider::class];
    }


    private function commands(?array &$calls, int $code = 0): void
    {
        $calls = [];

        Artisan::command('cms:install:core {--seed}', function () use (&$calls) {
            $calls['cms:install:core'] = $this->option('seed');
        });

        Artisan::command('cms:install:graphql', function () use (&$calls) {
            $calls['cms:install:graphql'] = false;
        });

        Artisan::command('cms:install:ai', function () use (&$calls, $code) {
            $calls['cms:install:ai'] = false;

            return $code;
        });
    }
}
