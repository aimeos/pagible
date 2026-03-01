<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Tests;

use Aimeos\Cms\Models\Page;
use Aimeos\Cms\Models\File;
use Aimeos\Cms\Models\Element;
use Aimeos\Cms\Models\Content;
use Database\Seeders\CmsSeeder;
use Illuminate\Foundation\Auth\User;


class CommandTest extends TestAbstract
{
    public function testIndex(): void
    {
        $this->seed( CmsSeeder::class );

        $this->artisan('cms:index')->assertExitCode( 0 );

        $this->assertEquals( 2, Content::get()->count() );
    }


    public function testPublish(): void
    {
        $this->seed( CmsSeeder::class );

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
        $this->seed( CmsSeeder::class );

        $this->artisan('cms:user', ['-p' => 'test', 'email' => 'test@example.com'])->assertExitCode( 0 );
        $this->assertEquals( 0, User::where('email', 'test@example.com')->get()->first()?->cmseditor );

        $this->artisan('cms:user', ['-e' => true, 'email' => 'test@example.com'])->assertExitCode( 0 );
        $this->assertEquals( 0b00000000_01111111_00000001_11000111_00000001_11111111_01111111_11111111, User::where('email', 'test@example.com')->get()->first()?->cmseditor );

        $this->artisan('cms:user', ['-d' => true, 'email' => 'test@example.com'])->assertExitCode( 0 );
        $this->assertEquals( 0, User::where('email', 'test@example.com')->get()->first()?->cmseditor );

        $this->artisan('cms:user', ['-a' => '*', 'email' => 'test@example.com'])->assertExitCode( 0 );
        $this->assertEquals( 0b00000000_01111111_00000001_11000111_00000001_11111111_01111111_11111111, User::where('email', 'test@example.com')->get()->first()?->cmseditor );

        $this->artisan('cms:user', ['-r' => '*', 'email' => 'test@example.com'])->assertExitCode( 0 );
        $this->assertEquals( 0, User::where('email', 'test@example.com')->get()->first()?->cmseditor );

        $this->artisan('cms:user', ['-a' => 'image:*', 'email' => 'test@example.com'])->assertExitCode( 0 );
        $this->assertEquals( 0b00000000_01111111_00000000_00000000_00000000_00000000_00000000_00000000, User::where('email', 'test@example.com')->get()->first()?->cmseditor );

        $this->artisan('cms:user', ['-r' => 'image:*', 'email' => 'test@example.com'])->assertExitCode( 0 );
        $this->assertEquals( 0, User::where('email', 'test@example.com')->get()->first()?->cmseditor );

        $this->artisan('cms:user', ['-a' => '*:view', 'email' => 'test@example.com'])->assertExitCode( 0 );
        $this->assertEquals( 0b00000000_00000000_00000000_00000000_00000000_00000001_00000001_00000001, User::where('email', 'test@example.com')->get()->first()?->cmseditor );

        $this->artisan('cms:user', ['-a' => ['*:view', '*:publish'], 'email' => 'test@example.com'])->assertExitCode( 0 );
        $this->assertEquals( 0b00000000_00000000_00000000_00000000_00000000_01000001_01000001_01000001, User::where('email', 'test@example.com')->get()->first()?->cmseditor );

        $this->artisan('cms:user', ['-r' => ['*:view', '*:publish'], 'email' => 'test@example.com'])->assertExitCode( 0 );
        $this->assertEquals( 0, User::where('email', 'test@example.com')->get()->first()?->cmseditor );

        $this->artisan('cms:user', ['-r' => '*:view', 'email' => 'test@example.com'])->assertExitCode( 0 );
        $this->assertEquals( 0, User::where('email', 'test@example.com')->get()->first()?->cmseditor );

        $this->artisan('cms:user', ['-l' => true, 'email' => 'test@example.com'])->assertExitCode( 0 );
    }
}