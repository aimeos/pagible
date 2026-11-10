<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests;

use Aimeos\Cms\Models\PageView;
use Aimeos\Cms\Models\File;
use Aimeos\Prisma\Exceptions\UnauthorizedException;
use Aimeos\Prisma\Prisma;
use Aimeos\Prisma\Responses\TextResponse;
use Database\Seeders\TestSeeder;
class AiCommandTest extends AiTestAbstract
{
    use CmsWithMigrations;
    use \Illuminate\Foundation\Testing\RefreshDatabase;

    protected $seeder = TestSeeder::class;

    public function testDescription(): void
    {
        File::forceCreate( [
            'mime' => 'image/png',
            'name' => 'No description',
            'path' => 'https://picsum.photos/id/1/100/100',
            'editor' => 'test',
        ] );

        Prisma::fake( [
            TextResponse::fromText( 'Generated meta description' ),
            TextResponse::fromText( 'Generated meta description' ),
            TextResponse::fromText( 'Generated meta description' ),
            TextResponse::fromText( 'Generated meta description' ),
            TextResponse::fromText( 'Generated meta description' ),
            TextResponse::fromText( 'Generated meta description' ),
            TextResponse::fromText( 'Generated file description' ),
        ] );

        $this->artisan( 'cms:description' )->assertExitCode( 0 );

        // descriptions are saved as drafts
        $page = PageView::with( 'latest' )->where( 'path', '' )->firstOrFail();
        $this->assertEquals( 'Generated meta description', $page->latest?->aux->meta->{'meta-tags'}->data->description ?? '' );
        $this->assertEmpty( $page->meta->{'meta-tags'}->data->description ?? '' );

        $file = File::with( 'latest' )->where( 'name', 'No description' )->firstOrFail();
        $this->assertEquals( 'Generated file description', $file->latest?->aux->description->en ?? '' );
        $this->assertEmpty( (array) $file->description );
    }


    public function testDescriptionFileLang(): void
    {
        File::forceCreate( [
            'lang' => 'de',
            'mime' => 'image/png',
            'name' => 'German file',
            'path' => 'https://picsum.photos/id/1/100/100',
            'editor' => 'test',
        ] );

        $fake = Prisma::fake( $this->responses( TextResponse::fromText( 'Generated description' ) ) );

        $this->artisan( 'cms:description' )->assertExitCode( 0 );

        $file = File::with( 'latest' )->where( 'name', 'German file' )->firstOrFail();
        $this->assertEquals( 'Generated description', $file->latest?->aux->description->de ?? '' );
        $fake->assertCalled( 'describe', fn( $args ) => $args[1] === 'de' );
    }


    public function testDescriptionVariant(): void
    {
        $page = PageView::where( 'path', '' )->firstOrFail();

        \Aimeos\Cms\Permission::system( function() use ( $page ) {
            \Aimeos\Cms\Facades\Variants::add( $page->id, 'de' );
            \Aimeos\Cms\Facades\Variants::save( $page->id, 'de', ['path' => 'de'] );
        } );

        // new variants are disabled until they are published
        \Aimeos\Cms\Models\PageVariant::where( 'lang', 'de' )->update( ['status' => 1] );

        Prisma::fake( $this->responses( TextResponse::fromText( 'Generated meta description' ) ) );

        $this->artisan( 'cms:description' )->assertExitCode( 0 );

        // each language variant gets its own description
        $variant = PageView::language( 'de' )->with( 'latest' )->where( 'path', 'de' )->firstOrFail();
        $this->assertEquals( 'Generated meta description', $variant->latest?->aux->meta->{'meta-tags'}->data->description ?? '' );
    }


    public function testDescriptionFailed(): void
    {
        Prisma::fake( [] );

        $this->artisan( 'cms:description' )->assertExitCode( 1 );
    }


    public function testDescriptionUnsupportedDraft(): void
    {
        $file = File::forceCreate( [
            'mime' => 'image/png',
            'name' => 'Replaced image',
            'path' => 'https://picsum.photos/id/1/100/100',
            'editor' => 'test',
        ] );
        $version = $file->versions()->forceCreate( [
            'editor' => 'test',
            'data' => ['mime' => 'application/pdf', 'path' => 'https://example.com/document.pdf'],
        ] );
        $file->forceFill( ['latest_id' => $version->id] )->saveQuietly();

        Prisma::fake( $this->responses( TextResponse::fromText( 'Generated description' ) ) );

        $this->artisan( 'cms:description' )->assertExitCode( 0 );

        $file = File::with( 'latest' )->where( 'name', 'Replaced image' )->firstOrFail();
        $this->assertEmpty( (array) ( $file->latest?->aux->description ?? [] ) );
    }


    public function testDescriptionNoProvider(): void
    {
        config( ['cms.ai.write.provider' => null, 'cms.ai.describe.provider' => null] );

        $this->artisan( 'cms:description' )->assertExitCode( 1 );
    }


    public function testDescriptionShorten(): void
    {
        config( ['cms.ai.describe.provider' => null] );
        Prisma::fake( $this->responses( TextResponse::fromText( ' "' . str_repeat( 'Page "words" ', 20 ) . '"' ) ) );

        $this->artisan( 'cms:description' )->assertExitCode( 0 );

        $page = PageView::with( 'latest' )->where( 'path', '' )->firstOrFail();
        $desc = $page->latest?->aux->meta->{'meta-tags'}->data->description ?? '';

        // enclosing quotes are only removed if the text contains no other ones
        $this->assertStringStartsWith( '"Page "words"', $desc );
        $this->assertLessThanOrEqual( 160, mb_strlen( $desc ) );
        $this->assertStringEndsWith( '"words"', $desc );
    }


    public function testDescriptionShortenQuoted(): void
    {
        config( ['cms.ai.describe.provider' => null] );
        Prisma::fake( $this->responses( TextResponse::fromText( "\n\"Our customers' favorite page\"\n" ) ) );

        $this->artisan( 'cms:description' )->assertExitCode( 0 );

        $page = PageView::with( 'latest' )->where( 'path', '' )->firstOrFail();
        $this->assertEquals( "Our customers' favorite page", $page->latest?->aux->meta->{'meta-tags'}->data->description ?? '' );
    }


    public function testDescriptionStop(): void
    {
        config( ['cms.ai.describe.provider' => null] );
        $fake = Prisma::fake( [new UnauthorizedException( 'Invalid API key' )] );

        $this->artisan( 'cms:description' )
            ->expectsOutputToContain( 'denied the access' )
            ->assertExitCode( 1 );

        // no further pages are sent to the provider after the access was denied
        $this->assertCount( 1, $fake->calls() );
    }


    public function testDescriptionTenant(): void
    {
        Prisma::fake( [] );

        // no pages and files exist for the tenant, so no provider is called
        $this->artisan( 'cms:description', ['--tenant' => 'other'] )->assertExitCode( 0 );
    }


    /**
     * Returns the response for each page variant and file which may be described.
     *
     * @param TextResponse $response Response of the provider
     * @return array<int, TextResponse> One response per variant and file
     */
    protected function responses( TextResponse $response ) : array
    {
        return array_fill( 0, PageView::allVariants()->count() + File::count(), $response );
    }
}
