<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests;

use Aimeos\Cms\Models\Element;
use Aimeos\Cms\Models\File;
use Aimeos\Cms\Models\Page;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;


class WpImportTest extends ImportTestAbstract
{
    use CmsWithMigrations;
    use RefreshDatabase;


    protected function defineEnvironment( $app )
    {
        parent::defineEnvironment( $app );

        $app['config']->set( 'database.connections.wordpress', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'foreign_key_constraints' => true,
        ] );
    }


    protected function setUp(): void
    {
        parent::setUp();

        Schema::connection( 'wordpress' )->create( 'wp_posts', function( Blueprint $table ) {
            $table->id( 'ID' );
            $table->string( 'post_type' );
            $table->string( 'post_status' );
            $table->dateTime( 'post_date' );
            $table->dateTime( 'post_date_gmt' )->nullable();
            $table->string( 'post_name' );
            $table->string( 'post_title' );
            $table->text( 'post_excerpt' );
            $table->text( 'post_content' );
            $table->string( 'post_mime_type' )->default( '' );
            $table->string( 'guid' )->default( '' );
        } );

        Schema::connection( 'wordpress' )->create( 'wp_postmeta', function( Blueprint $table ) {
            $table->unsignedBigInteger( 'post_id' );
            $table->string( 'meta_key' );
            $table->text( 'meta_value' )->nullable();
        } );
    }


    public function testUpdatesExistingArticleBelowDomainBlog(): void
    {
        $oldRoot = $this->page( 'Old root', '', 'old.example', 'root' );
        $oldBlog = $this->page( 'Tips', 'tips', 'old.example', 'page', $oldRoot );
        $root = $this->page( 'New root', '', 'new.example', 'root' );
        $blog = $this->page( 'Tips', 'tips', 'new.example', 'page', $root );
        $footer = Element::forceCreate( [
            'lang' => 'en',
            'type' => 'footer',
            'name' => 'Shared footer',
            'data' => ['text' => 'Shared footer'],
            'editor' => 'test',
        ] );
        $footerFile = File::forceCreate( [
            'mime' => 'image/png',
            'name' => 'Footer image',
            'path' => 'footer.png',
            'editor' => 'test',
        ] );
        $footer->files()->attach( $footerFile->id );
        $blog->forceFill( ['content' => [[
            'type' => 'reference',
            'refid' => $footer->id,
            'group' => 'footer',
        ]]] )->saveQuietly();
        $blog->elements()->attach( $footer->id );

        DB::connection( 'wordpress' )->table( 'wp_posts' )->insert( [
            'ID' => 1,
            'post_type' => 'post',
            'post_status' => 'publish',
            'post_date' => '2025-01-02 03:04:05',
            'post_date_gmt' => '2025-01-02 02:04:05',
            'post_name' => 'example-post',
            'post_title' => 'Original title',
            'post_excerpt' => 'Original introduction',
            'post_content' => '<p>Original body</p>',
            'post_mime_type' => '',
            'guid' => '',
        ] );

        $result = Artisan::call( 'cms:wp-import', [
            '--connection' => 'wordpress',
            '--domain' => 'new.example',
            '--blog-path' => 'tips',
            '--theme' => 'pagible',
        ] );

        $this->assertSame( 0, $result );

        $article = Page::where( 'domain', 'new.example' )->where( 'path', 'example-post' )->firstOrFail();

        $this->assertSame( $blog->id, $article->parent_id );
        $this->assertSame( 'pagible', $article->theme );
        $this->assertSame( 'pagible', Page::whereKey( $blog->id )->firstOrFail()->theme );
        $this->assertSame( 0, Page::where( 'parent_id', $oldBlog->id )->count() );
        $this->assertSame( 1, $article->versions()->count() );

        $article->appendToNode( Page::whereKey( $root->id )->firstOrFail() )->save();

        DB::connection( 'wordpress' )->table( 'wp_posts' )->where( 'ID', 1 )->update( [
            'post_date' => '2026-02-03 04:05:06',
            'post_date_gmt' => '2026-02-03 03:05:06',
            'post_title' => 'Updated title',
            'post_excerpt' => 'Updated introduction',
            'post_content' => '<p>Updated body</p>',
        ] );

        $result = Artisan::call( 'cms:wp-import', [
            '--connection' => 'wordpress',
            '--domain' => 'new.example',
            '--blog-path' => 'tips',
            '--theme' => 'pagible',
        ] );

        $this->assertSame( 0, $result );

        $updated = Page::where( 'domain', 'new.example' )->where( 'path', 'example-post' )->firstOrFail();
        $content = (array) $updated->content;

        $this->assertSame( $article->id, $updated->id );
        $this->assertSame( $blog->id, $updated->parent_id );
        $this->assertSame( 'Updated title', $updated->title );
        $this->assertSame( '2026-02-03 03:05:06', $updated->getRawOriginal( 'created_at' ) );
        $this->assertSame( 'Updated introduction', $content[0]->data->text ?? null );
        $this->assertSame( 'Updated body', $content[1]->data->text ?? null );
        $this->assertSame( $footer->id, $content[2]->refid ?? null );
        $this->assertSame( 'footer', $content[2]->group ?? null );
        $this->assertSame( [$footer->id], $updated->elements()->pluck( 'cms_elements.id' )->all() );
        $this->assertSame( [$footerFile->id], $updated->files()->pluck( 'cms_files.id' )->all() );
        $this->assertSame( 1, Page::where( 'domain', 'new.example' )->where( 'path', 'example-post' )->count() );
        $this->assertSame( 2, $updated->versions()->count() );
    }


    public function testDownloadsWordpressUploadsAndKeepsExternalMediaUrls(): void
    {
        config( [
            'cms.allow-internal' => true,
            'cms.disks.public.name' => 'wp-import-media',
            'cms.image.preview-sizes' => [],
        ] );
        Storage::fake( 'wp-import-media' );

        $png = (string) base64_decode( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=' );
        Http::fake( fn() => Http::response( $png, 200, [
                'Content-Length' => (string) strlen( $png ),
                'Content-Type' => 'image/png',
            ] ) );

        $root = $this->page( 'Root', '', 'new.example', 'root' );

        DB::connection( 'wordpress' )->table( 'wp_posts' )->insert( [
            [
                'ID' => 10,
                'post_type' => 'post',
                'post_status' => 'publish',
                'post_date' => '2026-08-22 12:00:00',
                'post_name' => 'media-post',
                'post_title' => 'Media post',
                'post_excerpt' => 'Media introduction',
                'post_content' => '<!-- wp:image --><figure><img src="https://old.example/wp-content/uploads/2026/inside.png" alt="Inside"></figure><!-- /wp:image -->'
                    . '<!-- wp:image --><figure><img src="http://127.0.0.1/assets/direct.png" alt="Configured base"></figure><!-- /wp:image -->'
                    . '<!-- wp:image --><figure><img src="https://cdn.example/external.png" alt="External"></figure><!-- /wp:image -->',
                'post_mime_type' => '',
                'guid' => '',
            ],
            [
                'ID' => 11,
                'post_type' => 'attachment',
                'post_status' => 'inherit',
                'post_date' => '2026-08-22 12:00:00',
                'post_name' => 'cover',
                'post_title' => 'Cover',
                'post_excerpt' => '',
                'post_content' => '',
                'post_mime_type' => 'image/png',
                'guid' => 'https://old.example/wp-content/uploads/2026/cover.png',
            ],
        ] );
        DB::connection( 'wordpress' )->table( 'wp_postmeta' )->insert( [
            'post_id' => 10,
            'meta_key' => '_thumbnail_id',
            'meta_value' => '11',
        ] );

        $result = Artisan::call( 'cms:wp-import', [
            '--connection' => 'wordpress',
            '--domain' => 'new.example',
            '--blog-path' => 'tips',
            '--blog-name' => 'Tips',
            '--theme' => 'pagible',
            '--media-url' => ['http://127.0.0.1/media', 'http://127.0.0.1/assets'],
        ] );

        $this->assertSame( 0, $result );

        $article = Page::where( 'domain', 'new.example' )->where( 'path', 'media-post' )->first();
        $this->assertInstanceOf( Page::class, $article, Artisan::output() );
        $blog = Page::where( 'domain', 'new.example' )->where( 'path', 'tips' )->firstOrFail();
        $local = File::where( 'path', 'not like', 'http%' )->get();
        $external = File::where( 'path', 'https://cdn.example/external.png' )->firstOrFail();
        $cover = File::where( 'name', 'Cover' )->firstOrFail();
        $lead = collect( (array) $article->content )->first( fn( $item ) => $item->type === 'article' );

        $this->assertSame( $root->id, $blog->parent_id );
        $this->assertSame( 'pagible', $blog->theme );
        $this->assertSame( 'pagible', $article->theme );
        $this->assertCount( 4, $article->files );
        $this->assertCount( 3, $local );
        $this->assertSame( 'https://cdn.example/external.png', $external->path );
        $this->assertSame( $cover->id, $lead->data->file->id ?? null );
        $this->assertSame( [$cover->id], (array) ( $lead->files ?? [] ) );

        foreach( $local as $file )
        {
            if( !is_string( $file->path ) ) {
                $this->fail( 'The imported upload has no managed storage path.' );
            }

            Storage::disk( 'wp-import-media' )->assertExists( $file->path );
        }

        Http::assertSentCount( 3 );
        Http::assertSent( fn( $request ) => $request->url() === 'http://127.0.0.1/media/2026/inside.png' );
        Http::assertSent( fn( $request ) => $request->url() === 'http://127.0.0.1/assets/direct.png' );
        Http::assertSent( fn( $request ) => $request->url() === 'http://127.0.0.1/media/2026/cover.png' );
    }


    public function testUsesFirstInlineImageAsListCover(): void
    {
        $this->page( 'Root', '', 'new.example', 'root' );

        DB::connection( 'wordpress' )->table( 'wp_posts' )->insert( [
            'ID' => 20,
            'post_type' => 'post',
            'post_status' => 'publish',
            'post_date' => '2026-08-23 12:00:00',
            'post_name' => 'inline-cover',
            'post_title' => 'Inline cover',
            'post_excerpt' => 'Inline introduction',
            'post_content' => '<!-- wp:image --><figure><img src="https://cdn.example/first.png" alt="First"></figure><!-- /wp:image -->'
                . '<!-- wp:image --><figure><img src="https://cdn.example/second.png" alt="Second"></figure><!-- /wp:image -->',
            'post_mime_type' => '',
            'guid' => '',
        ] );

        $result = Artisan::call( 'cms:wp-import', [
            '--connection' => 'wordpress',
            '--domain' => 'new.example',
            '--blog-path' => 'tips',
            '--theme' => 'pagible',
        ] );

        $this->assertSame( 0, $result );

        $article = Page::where( 'domain', 'new.example' )->where( 'path', 'inline-cover' )->firstOrFail();
        $content = array_values( (array) $article->content );
        $lead = $content[0];
        $firstImage = $content[1];

        $this->assertNull( $lead->data->file ?? null );
        $this->assertSame( [$firstImage->data->file->id], (array) ($lead->files ?? []) );
        $this->assertSame( 'https://cdn.example/first.png', $article->files->get( $firstImage->data->file->id )?->path );
    }


    protected function page( string $name, string $path, string $domain, string $tag, ?Page $parent = null ): Page
    {
        $page = Page::forceCreate( [
            'name' => $name,
            'title' => $name,
            'path' => $path,
            'domain' => $domain,
            'lang' => 'en',
            'tag' => $tag,
            'status' => 1,
            'editor' => 'test',
        ] );

        if( $parent ) {
            $page->appendToNode( $parent )->save();
        }

        return $page;
    }
}
