<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Tests;

use Aimeos\Cms\Models\Page;
use Aimeos\Cms\Models\File;
use Aimeos\Cms\Models\Element;
use Database\Seeders\TestSeeder;
use App\Models\User;


class CoreCommandTest extends CoreTestAbstract
{
    use CmsWithMigrations;
    use \Illuminate\Foundation\Testing\RefreshDatabase;

    public function testPublish(): void
    {
        $this->seed( TestSeeder::class );

        $this->artisan('cms:publish')->assertExitCode( 0 );

        $this->assertEquals( 1, Page::where( 'path', 'hidden' )->firstOrFail()?->status );
        $this->assertEquals( 'Powered by Laravel CMS!', Element::where( 'name', 'Shared footer' )->firstOrFail()?->data->text );
        $this->assertEquals( (object) [
            'en' => 'Test file description',
            'de' => 'Beschreibung der Testdatei',
        ], File::where( 'mime', 'image/jpeg' )->firstOrFail()?->description );
    }


    public function testUser(): void
    {
        $allPerms = \Aimeos\Cms\Permission::all();
        $imagePerms = array_values( array_filter( $allPerms, fn( $p ) => str_starts_with( $p, 'image:' ) ) );
        $viewPerms = array_values( array_filter( $allPerms, fn( $p ) => str_ends_with( $p, ':view' ) ) );
        $viewPublishPerms = array_values( array_filter( $allPerms, fn( $p ) => str_ends_with( $p, ':view' ) || str_ends_with( $p, ':publish' ) ) );

        $perms = fn() => User::where('email', 'test@example.com')->first()?->cmsperms ?? [];

        $this->artisan('cms:user', ['-p' => 'test', 'email' => 'test@example.com'])->assertExitCode( 0 );
        $this->assertEmpty( $perms() );

        $this->artisan('cms:user', ['-e' => true, 'email' => 'test@example.com'])->assertExitCode( 0 );
        $this->assertEqualsCanonicalizing( $allPerms, $perms() );

        $this->artisan('cms:user', ['-d' => true, 'email' => 'test@example.com'])->assertExitCode( 0 );
        $this->assertEmpty( $perms() );

        $this->artisan('cms:user', ['-a' => '*', 'email' => 'test@example.com'])->assertExitCode( 0 );
        $this->assertEqualsCanonicalizing( $allPerms, $perms() );

        $this->artisan('cms:user', ['-r' => '*', 'email' => 'test@example.com'])->assertExitCode( 0 );
        $this->assertEmpty( $perms() );

        $this->artisan('cms:user', ['-a' => 'image:*', 'email' => 'test@example.com'])->assertExitCode( 0 );
        $this->assertEqualsCanonicalizing( $imagePerms, $perms() );

        $this->artisan('cms:user', ['-r' => 'image:*', 'email' => 'test@example.com'])->assertExitCode( 0 );
        $this->assertEmpty( $perms() );

        $this->artisan('cms:user', ['-a' => '*:view', 'email' => 'test@example.com'])->assertExitCode( 0 );
        $this->assertEqualsCanonicalizing( $viewPerms, $perms() );

        $this->artisan('cms:user', ['-a' => ['*:view', '*:publish'], 'email' => 'test@example.com'])->assertExitCode( 0 );
        $this->assertEqualsCanonicalizing( $viewPublishPerms, $perms() );

        $this->artisan('cms:user', ['-r' => ['*:view', '*:publish'], 'email' => 'test@example.com'])->assertExitCode( 0 );
        $this->assertEmpty( $perms() );

        $this->artisan('cms:user', ['-r' => '*:view', 'email' => 'test@example.com'])->assertExitCode( 0 );
        $this->assertEmpty( $perms() );

        $this->artisan('cms:user', ['-l' => true, 'email' => 'test@example.com'])->assertExitCode( 0 );
    }
}
