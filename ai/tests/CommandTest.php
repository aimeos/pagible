<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Tests;

use Aimeos\Cms\Models\Page;
use Aimeos\Cms\Models\File;
use Aimeos\Prisma\Prisma;
use Aimeos\Prisma\Responses\TextResponse;
use Database\Seeders\CmsSeeder;
use Prism\Prism\Facades\Prism;
use Prism\Prism\Testing\TextResponseFake;


class AiCommandTest extends AiTestAbstract
{
    use \Illuminate\Foundation\Testing\RefreshDatabase;

    public function testDescription(): void
    {
        $this->seed( CmsSeeder::class );

        Prism::fake( [
            TextResponseFake::make()->withText( 'Generated meta description' ),
            TextResponseFake::make()->withText( 'Generated meta description' ),
            TextResponseFake::make()->withText( 'Generated meta description' ),
            TextResponseFake::make()->withText( 'Generated meta description' ),
            TextResponseFake::make()->withText( 'Generated meta description' ),
            TextResponseFake::make()->withText( 'Generated meta description' ),
        ] );

        File::forceCreate( [
            'mime' => 'image/png',
            'name' => 'No description',
            'path' => 'https://picsum.photos/id/1/100/100',
            'editor' => 'test',
        ] );

        Prisma::fake( [TextResponse::fromText( 'Generated file description' )] );

        $this->artisan( 'cms:description' )->assertExitCode( 0 );

        $page = Page::where( 'path', '' )->firstOrFail();
        $this->assertEquals( 'Generated meta description', $page->meta->{'meta-tags'}->data->description ?? '' );

        $file = File::where( 'name', 'No description' )->firstOrFail();
        $this->assertEquals( 'Generated file description', $file->description->en ?? '' );
    }
}
