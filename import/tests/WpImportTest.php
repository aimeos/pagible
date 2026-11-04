<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests;

use Aimeos\Cms\Models\Page;
use Aimeos\Cms\Import\Files;
use Aimeos\Cms\Import\Elements;
use Aimeos\Cms\Import\Pages;
use Aimeos\Cms\Import\WordPress;
use Aimeos\Cms\Import\WpBlog;
use Aimeos\Cms\Import\WpMedia;
use Aimeos\Cms\Import\WpReader;
use Aimeos\Cms\Models\Element;
use Aimeos\Cms\Models\File;
use Aimeos\Cms\Models\PageVariant;
use Aimeos\Cms\Models\PageView;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

use Aimeos\Cms\Commands\WpImport;
use Illuminate\Database\Events\TransactionCommitted;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

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
            $table->string( 'post_type' )->default( 'post' );
            $table->string( 'post_status' )->default( 'publish' );
            $table->dateTime( 'post_date' );
            $table->dateTime( 'post_date_gmt' )->nullable();
            $table->string( 'post_name' );
            $table->string( 'post_title' );
            $table->text( 'post_excerpt' )->default( '' );
            $table->text( 'post_content' )->default( '' );
            $table->string( 'post_mime_type' )->default( '' );
            $table->string( 'guid' )->default( '' );
            $table->string( 'post_password' )->default( '' );
        } );

        Schema::connection( 'wordpress' )->create( 'wp_postmeta', function( Blueprint $table ) {
            $table->unsignedBigInteger( 'post_id' );
            $table->string( 'meta_key' );
            $table->text( 'meta_value' )->nullable();
        } );
    }


    public function testReaderReadsPublishedPostsAndImages(): void
    {
        $post = ['post_type' => 'post', 'post_status' => 'publish', 'post_excerpt' => '', 'post_content' => '', 'post_mime_type' => '', 'guid' => ''];
        DB::connection( 'wordpress' )->table( 'wp_posts' )->insert( [
            ['ID' => 1, 'post_date' => '2026-01-02 00:00:00', 'post_name' => 'later', 'post_title' => 'Later'] + $post,
            ['ID' => 2, 'post_date' => '2026-01-01 00:00:00', 'post_name' => 'draft', 'post_title' => 'Draft', 'post_status' => 'draft'] + $post,
            ['ID' => 3, 'post_date' => '2026-01-01 00:00:00', 'post_name' => 'first', 'post_title' => 'First'] + $post,
            ['ID' => 4, 'post_date' => '2026-01-01 00:00:00', 'post_name' => 'a', 'post_title' => 'A', 'post_type' => 'attachment',
                'post_status' => 'inherit', 'post_mime_type' => 'image/png', 'guid' => 'https://wp.org/a.png'] + $post,
            ['ID' => 5, 'post_date' => '2026-01-01 00:00:00', 'post_name' => 'b', 'post_title' => 'B', 'post_type' => 'attachment',
                'post_status' => 'inherit', 'post_mime_type' => 'application/pdf', 'guid' => 'https://wp.org/b.pdf'] + $post,
        ] );
        DB::connection( 'wordpress' )->table( 'wp_postmeta' )->insert( [
            ['post_id' => 1, 'meta_key' => '_thumbnail_id', 'meta_value' => '4'],
            ['post_id' => 1, 'meta_key' => '_edit_lock', 'meta_value' => '5'],
            ['post_id' => 2, 'meta_key' => '_thumbnail_id', 'meta_value' => '4'],
        ] );

        $reader = new WpReader( DB::connection( 'wordpress' ) );
        $ids = [];
        $reader->chunk( function( $rows ) use ( $reader, &$ids ) {
            foreach( $rows as $row ) {
                $ids[] = $reader->post( $row )->ID;
            }
        } );

        $this->assertSame( 2, $reader->count() );
        $this->assertSame( [3, 1], $ids );
        $this->assertSame( [3, 1], $reader->cursor()->map( fn( $row ) => $reader->post( $row )->ID )->all() );
        $this->assertSame( ['a.png' => 4], iterator_to_array( $reader->basenames() ) );
        $this->assertSame( [4 => ['guid' => 'https://wp.org/a.png', 'title' => 'A', 'mime' => 'image/png']], $reader->attachments( [4, 5, 9, 4] ) );
        $this->assertSame( [], $reader->attachments( [] ) );
        $this->assertEquals( [1 => '4', 2 => '4'], $reader->thumbnails( [1, 2, 3] ) );
        $this->assertEquals( [1 => '4'], $reader->thumbnails( [1] ) );
        $this->assertSame( [], $reader->thumbnails( [] ) );

        $this->expectException( \UnexpectedValueException::class );
        $reader->post( (object) ['ID' => 'x'] );
    }


    public function testReaderChunksManyPostsAndImages(): void
    {
        $db = DB::connection( 'wordpress' );
        $post = ['post_type' => 'post', 'post_status' => 'publish', 'post_excerpt' => '', 'post_content' => '', 'post_mime_type' => '', 'guid' => ''];
        // posts of the same date are inserted in reverse ID order
        $db->table( 'wp_posts' )->insert( array_map( fn( $id ) => ['ID' => $id, 'post_date' => '2026-01-0' . ( $id % 3 + 1 ) . ' 00:00:00', 'post_name' => "p{$id}", 'post_title' => "P{$id}"] + $post, range( 250, 1 ) ) );
        $db->table( 'wp_posts' )->insert( array_map( fn( $id ) => ['ID' => $id, 'post_date' => '2026-01-01 00:00:00', 'post_name' => "i{$id}", 'post_title' => "I{$id}",
            'post_type' => 'attachment', 'post_status' => 'inherit', 'post_mime_type' => 'image/png', 'guid' => "https://wp.org/{$id}.png"] + $post, range( 1001, 1150 ) ) );

        $reader = new WpReader( $db );
        $ids = $sizes = [];

        $db->enableQueryLog();
        $reader->chunk( function( $rows ) use ( $reader, &$ids, &$sizes ) {
            $sizes[] = iterator_count( $rows );

            foreach( $rows as $row ) {
                $ids[] = $reader->post( $row )->ID;
            }
        } );
        $basenames = iterator_to_array( $reader->basenames() );
        $attachments = $reader->attachments( range( 1, 1200 ) );
        $queries = Collection::make( $db->getQueryLog() )->pluck( 'query' );
        $db->disableQueryLog();

        $expected = Collection::range( 1, 250 )->sortBy( fn( $id ) => [$id % 3, $id] )->values()->all();

        // all posts are passed once in the order of their dates and IDs without skipping rows by offset
        $this->assertSame( [100, 100, 50], $sizes );
        $this->assertSame( $expected, $ids );
        $this->assertSame( $expected, $reader->cursor()->map( fn( $row ) => $reader->post( $row )->ID )->all() );
        $this->assertSame( range( 1001, 1150 ), array_values( $basenames ) );
        $this->assertSame( '1001.png', array_key_first( $basenames ) );
        $this->assertSame( range( 1001, 1150 ), array_keys( $attachments ) );
        $this->assertSame( 3, $queries->filter( fn( $query ) => str_contains( $query, '"ID" in' ) )->count() );
        // the basenames of all attachments are fetched by one query
        $this->assertSame( 1, $queries->filter( fn( $query ) => str_starts_with( $query, 'select "ID", "guid" from' ) )->count() );
        $this->assertFalse( $queries->contains( fn( $query ) => str_contains( $query, 'offset' ) ) );

        $calls = 0;
        $reader->chunk( function() use ( &$calls ) {
            $calls++;
            return false;
        } );
        $this->assertSame( 1, $calls );
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
        $blog->variant->update( ['content' => [[
            'type' => 'reference',
            'refid' => $footer->id,
            'group' => 'footer',
        ]]] );
        $blog->elements()->attach( $footer->id );

        DB::connection( 'wordpress' )->table( 'wp_posts' )->insert( [
            'ID' => 1,
            'post_date' => '2025-01-02 03:04:05',
            'post_date_gmt' => '2025-01-02 02:04:05',
            'post_name' => 'example-post',
            'post_title' => 'Original title',
            'post_excerpt' => 'Original introduction',
            'post_content' => '<p>Original body</p>',
        ] );

        $result = Artisan::call( 'cms:wp-import', [
            '--connection' => 'wordpress',
            '--domain' => 'new.example',
            '--blog-path' => 'tips',
            '--theme' => 'pagible',
        ] );

        $this->assertSame( 0, $result );

        $article = PageView::where( 'domain', 'new.example' )->where( 'path', 'example-post' )->firstOrFail();

        $this->assertSame( $blog->id, $article->parent_id );
        $this->assertSame( 'pagible', $article->theme );
        // blog pages which weren't imported get no new version
        $this->assertStringContainsString( "Theme of the blog page /tips isn't changed because it wasn't imported or was changed since", Artisan::output() );
        $this->assertSame( '', PageView::whereKey( $blog->id )->firstOrFail()->theme );
        $this->assertSame( 0, PageView::whereKey( $blog->id )->firstOrFail()->versions()->count() );
        $this->assertSame( 0, PageView::where( 'parent_id', $oldBlog->id )->count() );
        $this->assertSame( 1, $article->versions()->count() );

        $article->page->appendToNode( Page::findOrFail( $root->id ) )->save();

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

        $updated = PageView::where( 'domain', 'new.example' )->where( 'path', 'example-post' )->firstOrFail();
        $content = (array) $updated->content;

        $this->assertSame( $article->id, $updated->id );
        $this->assertSame( $blog->id, $updated->parent_id );
        $this->assertSame( 'Updated title', $updated->title );
        $this->assertSame( '2026-02-03 03:05:06', $updated->created_at->format( 'Y-m-d H:i:s' ) );
        $this->assertSame( 'Updated introduction', $content[0]->data->text ?? null );
        $this->assertSame( 'Updated body', $content[1]->data->text ?? null );
        $this->assertSame( $footer->id, $content[2]->refid ?? null );
        $this->assertSame( 'footer', $content[2]->group ?? null );
        $this->assertSame( [$footer->id], $updated->elements()->pluck( 'cms_elements.id' )->all() );
        $this->assertSame( 1, PageView::where( 'domain', 'new.example' )->where( 'path', 'example-post' )->count() );
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

        // the blog page is added to the root page of the imported domain
        $this->page( 'Other', '', 'other.example', 'root' );
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

        $article = PageView::where( 'domain', 'new.example' )->where( 'path', 'media-post' )->first();
        $this->assertInstanceOf( PageView::class, $article, Artisan::output() );
        $blog = PageView::where( 'domain', 'new.example' )->where( 'path', 'tips' )->firstOrFail();
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

        DB::connection( 'wordpress' )->table( 'wp_posts' )->insert( [
            'ID' => 12,
            'post_date' => '2026-08-23 12:00:00',
            'post_name' => 'second-post',
            'post_title' => 'Second post',
            'post_content' => '<!-- wp:paragraph --><p>Text &amp; more</p><!-- /wp:paragraph -->',
        ] );

        $metaQueries = 0;
        DB::connection( 'wordpress' )->listen( function( $query ) use ( &$metaQueries ) {
            $metaQueries += (int) str_contains( $query->sql, '"wp_postmeta"' );
        } );

        $options = [
            '--connection' => 'wordpress',
            '--domain' => 'new.example',
            '--blog-path' => 'tips',
            '--media-url' => ['http://127.0.0.1/media', 'http://127.0.0.1/assets'],
        ];

        $this->assertSame( 0, Artisan::call( 'cms:wp-import', $options ) );

        $files = PageView::where( 'domain', 'new.example' )->where( 'path', 'media-post' )->firstOrFail()->files;

        $this->assertSame( 1, $metaQueries );
        // the content is the introduction of posts without excerpt
        $this->assertSame( 'Text & more', collect( (array) PageView::where( 'domain', 'new.example' )->where( 'path', 'second-post' )->firstOrFail()->content )
            ->first( fn( $item ) => $item->type === 'article' )->data->text ?? null );
        // the downloaded files of previous imports aren't reused because they are stored under new paths
        $this->assertSame( 8, File::count() );
        $this->assertCount( $article->files->count(), $files );
        Http::assertSentCount( 6 );
    }


    public function testDoesNotReuseFilesOfFailedPosts(): void
    {
        $config = ['cms.allow-internal' => config( 'cms.allow-internal' ), 'cms.image.preview-sizes' => config( 'cms.image.preview-sizes' )];
        $this->beforeApplicationDestroyed( fn() => config( $config ) );
        config( ['cms.allow-internal' => true, 'cms.image.preview-sizes' => []] );
        Storage::fake( (string) config( 'cms.disks.public.name', 'public' ) );

        $png = (string) base64_decode( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=' );
        Http::fake( fn() => Http::response( $png, 200, ['Content-Type' => 'image/png'] ) );

        $this->page( 'Root', '', 'new.example', 'root' );
        $content = '<!-- wp:image --><figure><img src="http://127.0.0.1/media/shared.png" alt="Shared"></figure><!-- /wp:image -->';

        foreach( [20 => 'Broken', 21 => 'Good'] as $id => $title )
        {
            DB::connection( 'wordpress' )->table( 'wp_posts' )->insert( [
                'ID' => $id,
                'post_date' => '2026-08-' . $id . ' 12:00:00',
                'post_name' => strtolower( $title ),
                'post_title' => $title,
                'post_excerpt' => $title,
                'post_content' => $content,
            ] );
        }

        $fail = true;
        $this->beforeApplicationDestroyed( function() use ( &$fail ) { $fail = false; } );

        Event::listen( 'eloquent.creating: ' . PageVariant::class, function( PageVariant $variant ) use ( &$fail ) {
            if( $fail && $variant->name === 'Broken' ) {
                throw new \RuntimeException( 'Broken page' );
            }
        } );

        $this->assertSame( 0, Artisan::call( 'cms:wp-import', [
            '--connection' => 'wordpress',
            '--domain' => 'new.example',
            '--media-url' => ['http://127.0.0.1/media'],
        ] ) );

        $good = PageView::where( 'domain', 'new.example' )->where( 'path', 'good' )->first();

        $this->assertInstanceOf( PageView::class, $good, Artisan::output() );
        $this->assertNull( PageView::where( 'domain', 'new.example' )->where( 'path', 'broken' )->first() );
        $this->assertSame( 1, File::count() );
        $this->assertSame( File::value( 'id' ), $good->files->pluck( 'id' )->first() );
        Http::assertSentCount( 2 );
    }


    public function testDeadlockRollsBackBatchAndStopsImport(): void
    {
        $this->page( 'Root', '', 'new.example', 'root' );

        foreach( [30 => 'First', 31 => 'Locked', 32 => 'Last'] as $id => $title )
        {
            DB::connection( 'wordpress' )->table( 'wp_posts' )->insert( [
                'ID' => $id,
                'post_date' => '2026-09-' . $id . ' 12:00:00',
                'post_name' => strtolower( $title ),
                'post_title' => $title,
                'post_excerpt' => $title,
                'post_content' => '<!-- wp:paragraph --><p>' . $title . '</p><!-- /wp:paragraph -->',
            ] );
        }

        $fail = true;
        $this->beforeApplicationDestroyed( function() use ( &$fail ) { $fail = false; } );

        // the page of the post is already saved when the deadlock occurs
        Event::listen( 'eloquent.creating: ' . PageVariant::class, function( PageVariant $variant ) use ( &$fail ) {
            if( $fail && $variant->name === 'Locked' ) {
                throw new \RuntimeException( 'Deadlock found when trying to get lock; try restarting transaction' );
            }
        } );

        // the batch is complete after the failed post
        $command = new class extends WpImport {
            protected int $batchSize = 2;
        };
        $command->setLaravel( app() );

        $level = DB::connection()->transactionLevel();
        $pages = Page::count();
        $output = new BufferedOutput();

        try {
            $command->run( new ArrayInput( ['--connection' => 'wordpress', '--domain' => 'new.example'] ), $output );
            $this->fail( 'Deadlock not thrown' );
        } catch( \Throwable $e ) {
            $this->assertInstanceOf( \Illuminate\Database\DeadlockException::class, $e );
        }

        // the records of the batch are rolled back instead of being committed partially
        $this->assertStringContainsString( 'Import stopped by a concurrent change in the database', $output->fetch() );
        $this->assertSame( $level, DB::connection()->transactionLevel() );
        $this->assertSame( $pages, Page::count() );
        $this->assertSame( 0, PageVariant::whereIn( 'name', ['First', 'Locked', 'Last'] )->count() );
    }


    public function testDeadlockRolledBackByDatabaseStopsImport(): void
    {
        $this->page( 'Root', '', 'new.example', 'root' );

        foreach( [30 => 'First', 31 => 'Locked'] as $id => $title )
        {
            DB::connection( 'wordpress' )->table( 'wp_posts' )->insert( [
                'ID' => $id,
                'post_date' => '2026-09-' . $id . ' 12:00:00',
                'post_name' => strtolower( $title ),
                'post_title' => $title,
                'post_excerpt' => $title,
                'post_content' => '<!-- wp:paragraph --><p>' . $title . '</p><!-- /wp:paragraph -->',
            ] );
        }

        $fail = true;
        $this->beforeApplicationDestroyed( function() use ( &$fail ) { $fail = false; } );

        // MySQL rolls back the whole transaction including the savepoints on deadlocks
        Event::listen( 'eloquent.creating: ' . PageVariant::class, function( PageVariant $variant ) use ( &$fail ) {
            if( $fail && $variant->name === 'Locked' ) {
                DB::connection()->getPdo()->rollBack();
                throw new \RuntimeException( 'Deadlock found when trying to get lock; try restarting transaction' );
            }
        } );

        try {
            Artisan::call( 'cms:wp-import', ['--connection' => 'wordpress', '--domain' => 'new.example'] );
            $this->fail( 'Deadlock not thrown' );
        } catch( \Throwable $e ) {
            $this->assertInstanceOf( \Illuminate\Database\DeadlockException::class, $e );
        }

        // the transaction counter is reset because no savepoint exists any more
        $this->assertSame( 0, DB::connection()->transactionLevel() );
        $this->assertFalse( DB::connection()->getPdo()->inTransaction() );
        $this->assertSame( 0, PageVariant::whereIn( 'name', ['First', 'Locked'] )->count() );
    }


    public function testDownloadsFailedMediaOnce(): void
    {
        $config = ['cms.allow-internal' => config( 'cms.allow-internal' )];
        $this->beforeApplicationDestroyed( fn() => config( $config ) );
        config( ['cms.allow-internal' => true] );
        Storage::fake( (string) config( 'cms.disks.public.name', 'public' ) );
        Http::fake( fn() => Http::response( 'Not found', 404 ) );

        $this->page( 'Root', '', 'new.example', 'root' );
        $content = '<!-- wp:image --><figure><img src="http://127.0.0.1/media/missing.png" alt="Missing"></figure><!-- /wp:image -->';

        foreach( [22 => 'First', 23 => 'Second'] as $id => $title )
        {
            DB::connection( 'wordpress' )->table( 'wp_posts' )->insert( [
                'ID' => $id,
                'post_date' => '2026-08-' . $id . ' 12:00:00',
                'post_name' => strtolower( $title ),
                'post_title' => $title,
                'post_excerpt' => $title,
                'post_content' => $content,
            ] );
        }

        $this->assertSame( 0, Artisan::call( 'cms:wp-import', [
            '--connection' => 'wordpress',
            '--domain' => 'new.example',
            '--media-url' => ['http://127.0.0.1/media'],
        ] ) );
        $output = Artisan::output();

        // the failure is reported for each post but the file is only requested once
        Http::assertSentCount( 1 );
        $this->assertSame( 2, substr_count( $output, 'Failed to download "http://127.0.0.1/media/missing.png"' ), $output );
        $this->assertSame( 0, File::count() );
    }


    public function testKeepsInnerBlockTypesInColumns(): void
    {
        $this->page( 'Root', '', 'new.example', 'root' );

        DB::connection( 'wordpress' )->table( 'wp_posts' )->insert( [
            'ID' => 30,
            'post_date' => '2026-08-24 12:00:00',
            'post_name' => 'columns',
            'post_title' => 'Columns',
            'post_excerpt' => 'Columns introduction',
            'post_content' => '<!-- wp:columns --><div class="wp-block-columns"><!-- wp:column --><div class="wp-block-column">'
                . '<!-- wp:heading --><h2>Column heading</h2><!-- /wp:heading -->'
                . '<!-- wp:paragraph --><p>Column text</p><!-- /wp:paragraph -->'
                . '</div><!-- /wp:column --></div><!-- /wp:columns -->',
        ] );

        $result = Artisan::call( 'cms:wp-import', [
            '--connection' => 'wordpress',
            '--domain' => 'new.example',
            '--blog-path' => 'tips',
        ] );

        $this->assertSame( 0, $result );

        $article = PageView::where( 'domain', 'new.example' )->where( 'path', 'columns' )->firstOrFail();
        $content = array_values( (array) $article->content );

        $this->assertSame( 'heading', $content[1]->type ?? null );
        $this->assertSame( 'Column heading', $content[1]->data->title ?? null );
        $this->assertSame( 'text', $content[2]->type ?? null );
        $this->assertSame( 'Column text', $content[2]->data->text ?? null );
    }


    public function testUsesFirstInlineImageAsListCover(): void
    {
        $this->page( 'Root', '', 'new.example', 'root' );

        DB::connection( 'wordpress' )->table( 'wp_posts' )->insert( [
            'ID' => 20,
            'post_date' => '2026-08-23 12:00:00',
            'post_name' => 'inline-cover',
            'post_title' => 'Inline cover',
            'post_excerpt' => 'Inline introduction',
            'post_content' => '<!-- wp:image --><figure><img src="https://cdn.example/first.png" alt="First"></figure><!-- /wp:image -->'
                . '<!-- wp:image --><figure><img src="https://cdn.example/second.png" alt="Second"></figure><!-- /wp:image -->',
        ] );

        $result = Artisan::call( 'cms:wp-import', [
            '--connection' => 'wordpress',
            '--domain' => 'new.example',
            '--blog-path' => 'tips',
            '--theme' => 'pagible',
        ] );

        $this->assertSame( 0, $result );

        $article = PageView::where( 'domain', 'new.example' )->where( 'path', 'inline-cover' )->firstOrFail();
        $content = array_values( (array) $article->content );
        $lead = $content[0];
        $firstImage = $content[1];

        $this->assertNull( $lead->data->file ?? null );
        $this->assertSame( [$firstImage->data->file->id], (array) ($lead->files ?? []) );
        $this->assertSame( 'https://cdn.example/first.png', $article->files->get( $firstImage->data->file->id )?->path );
    }


    public function testBlogFooter(): void
    {
        $page = ( new PageView() )->setRawAttributes( ['content' => json_encode( [
            ['type' => 'reference', 'group' => 'footer', 'refid' => 'ref1'],
            ['type' => 'reference', 'group' => 'main', 'refid' => 'ref2'],
            ['type' => 'text', 'group' => 'footer', 'refid' => 'ref3'],
            ['type' => 'reference', 'group' => 'footer', 'refid' => ''],
            ['type' => 'reference', 'group' => 'footer', 'refid' => 'ref1', 'id' => 'second'],
        ] )] );

        $footer = WpBlog::footer( $page );

        $this->assertSame( ['ref1', 'ref1'], array_column( $footer['elements'], 'refid' ) );
        $this->assertSame( ['ref1'], $footer['elementIds'] );
    }


    public function testMediaPreview(): void
    {
        $media = new WpMedia( [], fn() => null );
        $pdf = Files::create( 'application/pdf', 'Doc', 'https://cdn.example/doc.pdf', 'test', false )->id ?? '';
        $image = Files::create( 'image/png', 'Image', 'https://cdn.example/image.png', 'test', false )->id ?? '';

        $db = DB::connection( config( 'cms.db', 'sqlite' ) );
        $db->enableQueryLog();
        $this->assertSame( $image, $media->preview( [$pdf, 'missing', $image, $pdf] ) );
        $this->assertNull( $media->preview( [$pdf] ) );
        $this->assertNull( $media->preview( [] ) );
        // the MIME types of the files are fetched once
        $this->assertSame( 1, count( $db->getQueryLog() ) );
        $this->assertSame( [$pdf, $image, 'test'], $db->getQueryLog()[0]['bindings'] );

        $other = Files::create( 'image/png', 'Other', 'https://cdn.example/other.png', 'test', false )->id ?? '';
        $db->flushQueryLog();
        $this->assertSame( 'remembered', $media->remember( 'remembered', 'image/webp' )->preview( [$pdf, 'remembered', $other] ) );
        $this->assertSame( $other, $media->remember( 'remembered', 'text/plain' )->preview( [$pdf, 'remembered', $other] ) );
        // the remembered MIME types aren't fetched
        $this->assertSame( [[$other, 'test']], array_column( $db->getQueryLog(), 'bindings' ) );
        $db->disableQueryLog();
    }


    /**
     * Returns the SQL statement with the identifiers quoted like in SQLite and PostgreSQL.
     *
     * MariaDB and MySQL quote identifiers with backticks, SQL Server with brackets and uses "top" instead of "limit".
     */
    protected function sql( string $sql ): string
    {
        return (string) preg_replace( '/^select top \d+ /', 'select ', strtr( $sql, ['`' => '"', '[' => '"', ']' => '"'] ) );
    }


    protected function article( string $path ): ?object
    {
        return collect( (array) PageView::where( 'domain', 'new.example' )->where( 'path', $path )->firstOrFail()->content )
            ->first( fn( $item ) => $item->type === 'article' );
    }


    protected function page( string $name, string $path, string $domain, string $tag, ?PageView $parent = null ): PageView
    {
        $node = new Page( ['source' => 'en'] );
        $parent ? $node->appendToNode( Page::findOrFail( $parent->id ) )->save() : $node->save();

        $page = $node->variants()->create( [
            'name' => $name,
            'title' => $name,
            'path' => $path,
            'domain' => $domain,
            'lang' => 'en',
            'tag' => $tag,
            'status' => 1,
            'editor' => 'test',
        ] )->view;

        return $page;
    }


    public function testCommitsPostsInBatches(): void
    {
        $this->page( 'Root', '', 'new.example', 'root' );

        foreach( [30 => 'First', 31 => 'Second'] as $id => $title )
        {
            DB::connection( 'wordpress' )->table( 'wp_posts' )->insert( [
                'ID' => $id,
                'post_date' => '2026-08-' . $id . ' 12:00:00',
                'post_name' => strtolower( $title ),
                'post_title' => $title,
                'post_excerpt' => $title,
                'post_content' => '<!-- wp:paragraph --><p>Text</p><!-- /wp:paragraph -->',
            ] );
        }

        $command = new class extends WpImport {
            protected int $batchSize = 1;

            public function kept(): int
            {
                return $this->createdPages->count();
            }
        };
        $command->setLaravel( app() );

        $db = DB::connection( config( 'cms.db', 'sqlite' ) );
        $level = $db->transactionLevel();
        $commits = 0;
        Event::listen( TransactionCommitted::class, function( $event ) use ( $level, &$commits ) {
            $commits += (int) ( $event->connection->transactionLevel() === $level );
        } );

        $this->assertSame( 0, $command->run( new ArrayInput( ['--connection' => 'wordpress', '--domain' => 'new.example'] ), new BufferedOutput() ) );
        // after each post and at the end
        $this->assertSame( 3, $commits );
        $this->assertSame( $level, $db->transactionLevel() );
        $this->assertSame( 2, PageView::where( 'domain', 'new.example' )->whereIn( 'path', ['first', 'second'] )->count() );
        // the pages aren't looked up by the ID of their source record
        $this->assertSame( 0, $command->kept() );
    }


    public function testFetchesFeaturedImagesPerChunk(): void
    {
        $this->page( 'Root', '', 'new.example', 'root' );

        $db = DB::connection( 'wordpress' );
        $db->table( 'wp_posts' )->insert( array_map( fn( $id ) => ['ID' => $id, 'post_date' => '2026-08-01 12:00:00', 'post_name' => "post-{$id}",
            'post_title' => "Post {$id}", 'post_excerpt' => '', 'post_content' => '<!-- wp:paragraph --><p>Text</p><!-- /wp:paragraph -->'], range( 1, 101 ) ) );
        $db->table( 'wp_posts' )->insert( ['ID' => 500, 'post_type' => 'attachment', 'post_status' => 'inherit', 'post_date' => '2026-08-01 12:00:00',
            'post_name' => 'cover', 'post_title' => 'Cover', 'post_excerpt' => '', 'post_content' => '', 'post_mime_type' => 'image/png', 'guid' => 'https://cdn.example/cover.png'] );
        $db->table( 'wp_postmeta' )->insert( [
            ['post_id' => 1, 'meta_key' => '_thumbnail_id', 'meta_value' => '500'],
            ['post_id' => 101, 'meta_key' => '_thumbnail_id', 'meta_value' => '500'],
        ] );
        // inline images of attachments in the first chunk and in the second one, the third attachment isn't used
        foreach( [501 => 'inline', 502 => 'later', 503 => 'unused'] as $id => $name ) {
            $db->table( 'wp_posts' )->insert( ['ID' => $id, 'post_type' => 'attachment', 'post_status' => 'inherit', 'post_date' => '2026-08-01 12:00:00',
                'post_name' => $name, 'post_title' => ucfirst( $name ), 'post_excerpt' => '', 'post_content' => '', 'post_mime_type' => 'image/png', 'guid' => "https://cdn.example/{$name}.png"] );
        }
        $image = fn( string $name ) => '<!-- wp:image --><figure class="wp-block-image"><img src="https://cdn.example/' . $name . '.png" alt=""/></figure><!-- /wp:image -->';
        $db->table( 'wp_posts' )->where( 'ID', 2 )->update( ['post_content' => '<!-- wp:paragraph --><p><a href="https://cdn.example/doc.pdf">Doc</a></p><!-- /wp:paragraph -->' . $image( 'inline' )] );
        $db->table( 'wp_posts' )->where( 'ID', 3 )->update( ['post_content' => $image( 'inline' )] );
        $db->table( 'wp_posts' )->where( 'ID', 101 )->update( ['post_content' => $image( 'later' )] );

        $cms = DB::connection( config( 'cms.db', 'sqlite' ) );
        $cms->enableQueryLog();
        $db->enableQueryLog();
        $this->assertSame( 0, Artisan::call( 'cms:wp-import', ['--connection' => 'wordpress', '--domain' => 'new.example'] ) );
        $log = Collection::make( $db->getQueryLog() );
        $mimes = Collection::make( $cms->getQueryLog() )->filter( fn( $query ) => preg_match( '/select "mime", "id" from "cms_files"/', $this->sql( $query['query'] ) ) === 1 );
        $db->disableQueryLog();
        $cms->disableQueryLog();
        $queries = $log->filter( fn( $query ) => str_contains( $query['query'], 'from "wp_postmeta"' ) );
        $attachments = $log->filter( fn( $query ) => str_starts_with( $query['query'], 'select "ID", "guid", "post_title", "post_mime_type" from "wp_posts"' ) );

        // the featured images are fetched for the posts of each chunk
        $this->assertSame( [100, 1], $queries->map( fn( $query ) => count( $query['bindings'] ) - 1 )->values()->all() );
        // only the used attachments are fetched once per chunk
        $this->assertSame( [[500, 501], [500, 502]], $attachments->map( fn( $query ) => array_map( 'intval', array_slice( $query['bindings'], 6 ) ) )->values()->all() );
        // the MIME types of the created files are known
        $this->assertSame( 0, $mimes->count() );
        $this->assertSame( File::where( 'name', 'Inline' )->value( 'id' ), $this->article( 'post-2' )->files[0] ?? null );

        $cover = File::where( 'name', 'Cover' )->firstOrFail();
        $lead = fn( string $path ) => collect( (array) PageView::where( 'domain', 'new.example' )->where( 'path', $path )->firstOrFail()->content )
            ->first( fn( $item ) => $item->type === 'article' )->data->file->id ?? null;

        $this->assertSame( $cover->id, $lead( 'post-1' ) );
        $this->assertSame( $cover->id, $lead( 'post-101' ) );
        $this->assertNull( $lead( 'post-2' ) );
    }


    public function testMovesPostsBelowBlogPageFetchingItOnce(): void
    {
        $root = $this->page( 'Root', '', 'new.example', 'root' );

        foreach( [40 => 'First', 41 => 'Second', 42 => 'Third'] as $id => $title )
        {
            DB::connection( 'wordpress' )->table( 'wp_posts' )->insert( [
                'ID' => $id,
                'post_date' => '2026-08-01 12:00:' . $id,
                'post_name' => strtolower( $title ),
                'post_title' => $title,
                'post_excerpt' => $title,
                'post_content' => '<!-- wp:paragraph --><p>Text</p><!-- /wp:paragraph -->',
            ] );
        }

        $args = ['--connection' => 'wordpress', '--domain' => 'new.example'];
        $this->assertSame( 0, Artisan::call( 'cms:wp-import', $args ) );
        $blog = PageView::where( 'domain', 'new.example' )->where( 'path', 'blog' )->firstOrFail();

        foreach( ['first', 'second', 'third'] as $path ) {
            PageView::where( 'domain', 'new.example' )->where( 'path', $path )->firstOrFail()->page->appendToNode( Page::findOrFail( $root->id ) )->save();
        }

        $db = DB::connection( config( 'cms.db', 'sqlite' ) );
        $db->enableQueryLog();
        $this->assertSame( 0, Artisan::call( 'cms:wp-import', $args ) );
        $parents = Collection::make( $db->getQueryLog() )->filter( fn( $query ) => preg_match( '/^select \* from "cms_pages" where "cms_pages"."id" = \?/', $this->sql( $query['query'] ) ) === 1
            && $query['bindings'][0] === $blog->id );
        $db->disableQueryLog();

        // the node of the blog page is fetched once for all moved posts
        $this->assertSame( 1, $parents->count() );
        $this->assertSame( 3, PageView::where( 'parent_id', $blog->id )->whereIn( 'path', ['first', 'second', 'third'] )->count() );
        $this->assertSame( 0, Page::whereKey( $root->id )->firstOrFail()->children()->whereKeyNot( $blog->id )->count() );
    }


    public function testShowsConnectionExampleIfDatabaseIsUnavailable(): void
    {
        $this->assertSame( 0, Artisan::call( 'cms:wp-import', ['--connection' => 'unknown'] ) );

        $output = Artisan::output();

        $this->assertStringContainsString( 'Cannot connect to WordPress database using connection "unknown": Database connection [unknown] not configured.', $output );
        $this->assertStringContainsString( "'host' => env('WP_DB_HOST', '127.0.0.1'),", $output );
        $this->assertStringContainsString( "'database' => env('WP_DB_DATABASE', 'wordpress'),", $output );
    }


    public function testSkipsUnchangedPostsAndFetchesPagesOncePerChunk(): void
    {
        $this->page( 'Root', '', 'new.example', 'root' );

        foreach( [50 => 'First', 51 => 'Second'] as $id => $title )
        {
            DB::connection( 'wordpress' )->table( 'wp_posts' )->insert( [
                'ID' => $id,
                'post_date' => '2026-08-' . $id % 30 . ' 12:00:00',
                'post_name' => strtolower( $title ),
                'post_title' => $title,
                'post_excerpt' => $title,
                'post_content' => '<!-- wp:paragraph --><p>Text</p><!-- /wp:paragraph -->',
            ] );
        }

        $updates = [];
        DB::connection( config( 'cms.db', 'sqlite' ) )->listen( function( $query ) use ( &$updates ) {
            $updates[] = str_contains( $this->sql( $query->sql ), 'set "created_at"' ) ? $query->sql : null;
        } );

        $this->assertSame( 0, Artisan::call( 'cms:wp-import', ['--connection' => 'wordpress', '--domain' => 'new.example'] ) );
        $this->assertStringContainsString( '2 imported, 0 updated, 0 unchanged', Artisan::output() );
        // the creation dates of new posts are part of the insert
        $this->assertSame( [], array_filter( $updates ) );
        $this->assertSame( '2026-08-20 12:00:00', PageView::where( 'path', 'first' )->firstOrFail()->created_at?->format( 'Y-m-d H:i:s' ) );

        DB::connection( 'wordpress' )->table( 'wp_posts' )->where( 'ID', 51 )->update( ['post_title' => 'Second changed'] );

        $queries = [];
        DB::connection( config( 'cms.db', 'sqlite' ) )->listen( function( $query ) use ( &$queries ) {
            $queries[] = [$this->sql( $query->sql ), $query->bindings];
        } );

        $this->assertSame( 0, Artisan::call( 'cms:wp-import', ['--connection' => 'wordpress', '--domain' => 'new.example'] ) );

        $output = Artisan::output();
        $first = PageView::where( 'domain', 'new.example' )->where( 'path', 'first' )->firstOrFail();
        $second = PageView::where( 'domain', 'new.example' )->where( 'path', 'second' )->firstOrFail();
        // queries of Pages::find() and Pages::findAll() for the posts
        $finds = array_filter( $queries, fn( $q ) => str_contains( $q[0], '("domain" = ?' ) && array_intersect( ['first', 'second'], $q[1] ) );

        $this->assertStringContainsString( 'Unchanged: First', $output );
        $this->assertStringContainsString( 'Updated: Second changed', $output );
        $this->assertStringContainsString( '0 imported, 1 updated, 1 unchanged (2/2 posts processed)', $output );
        $this->assertSame( 1, $first->versions()->count() );
        $this->assertSame( 2, $second->versions()->count() );
        // the posts contain no files, so no files are fetched for the preview
        $this->assertSame( [], array_filter( array_column( $queries, 0 ), fn( $sql ) => str_contains( $sql, 'from "cms_files"' ) ) );
        // the creation dates are unchanged, so the variants aren't loaded to update them
        $this->assertSame( [], array_filter( array_column( $queries, 0 ), fn( $sql ) => str_starts_with( $sql, 'select * from "cms_page_variants"' ) ) );
        $this->assertSame( 'Second changed', $second->title );
        // pages of all posts are fetched by a single query, also with the legacy paths with leading slash
        $this->assertSame( ['"path" in (?, ?, ?, ?)'], array_values( array_map( fn( $q ) => str_contains( $q[0], '"path" in (?, ?, ?, ?)' ) ? '"path" in (?, ?, ?, ?)' : $q[0], $finds ) ) );
    }


    public function testSinceKeepsUnchangedPosts(): void
    {
        Schema::connection( 'wordpress' )->table( 'wp_posts', fn( Blueprint $table ) => $table->dateTime( 'post_modified_gmt' )->nullable() );

        foreach( [50 => 'First', 51 => 'Second', 52 => 'Third'] as $id => $title )
        {
            DB::connection( 'wordpress' )->table( 'wp_posts' )->insert( [
                'ID' => $id,
                'post_date' => '2026-08-' . $id % 30 . ' 12:00:00',
                'post_modified_gmt' => $id === 52 ? '0000-00-00 00:00:00' : '2026-08-01 12:00:00',
                'post_name' => strtolower( $title ),
                'post_title' => $title,
                'post_content' => '<!-- wp:paragraph --><p>Text</p><!-- /wp:paragraph -->',
            ] );
        }

        $this->assertSame( 0, Artisan::call( 'cms:wp-import', ['--connection' => 'wordpress', '--domain' => 'new.example', '--since' => 'invalid'] ) );
        $this->assertStringContainsString( 'Invalid date for --since: invalid', Artisan::output() );
        $this->assertSame( 0, PageView::count() );
        $this->assertSame( 0, Artisan::call( 'cms:wp-import', ['--connection' => 'wordpress', '--domain' => 'new.example'] ) );

        $blog = PageView::where( 'path', 'blog' )->firstOrFail();
        $first = PageView::where( 'path', 'first' )->firstOrFail();
        $first->page?->appendToNode( Page::findOrFail( PageView::where( 'path', 'second' )->firstOrFail()->id ) )->save();
        DB::connection( 'wordpress' )->table( 'wp_posts' )->update( ['post_content' => '<!-- wp:paragraph --><p>New</p><!-- /wp:paragraph -->'] );
        DB::connection( 'wordpress' )->table( 'wp_posts' )->where( 'ID', 51 )->update( ['post_modified_gmt' => '2026-09-02 12:00:00'] );

        $thumbnails = $basenames = 0;
        DB::connection( 'wordpress' )->listen( function( $query ) use ( &$thumbnails, &$basenames ) {
            $thumbnails += str_contains( $query->sql, 'from "wp_postmeta"' ) && $query->bindings === ['_thumbnail_id', 51, 52] ? 1 : 0;
            $basenames += str_starts_with( $query->sql, 'select "ID", "guid" from' ) ? 1 : 0;
        } );

        $this->assertSame( 0, Artisan::call( 'cms:wp-import', ['--connection' => 'wordpress', '--domain' => 'new.example', '--since' => '2026-09-01 12:00:00 UTC'] ) );
        $output = Artisan::output();

        // only the featured images of the changed posts are fetched
        $this->assertSame( 1, $thumbnails );
        // the attachment basenames aren't fetched if no image URLs are looked up
        $this->assertSame( 0, $basenames );
        // unchanged posts are moved below the blog page, posts without modification time are changed
        $this->assertStringContainsString( 'Unchanged: First', $output );
        $this->assertStringContainsString( 'Updated: Second', $output );
        $this->assertStringContainsString( 'Updated: Third', $output );
        $first = PageView::findOrFail( $first->id );
        $this->assertSame( $blog->id, $first->parent_id );
        $this->assertStringNotContainsString( 'New', (string) json_encode( $first->content ) );
        $this->assertStringContainsString( 'New', (string) json_encode( PageView::where( 'path', 'second' )->firstOrFail()->content ) );

        // the time zone of the modification time is UTC
        $this->assertSame( 0, Artisan::call( 'cms:wp-import', ['--connection' => 'wordpress', '--domain' => 'new.example', '--since' => '2026-08-01 12:00:01 UTC'] ) );
        $this->assertStringContainsString( 'Unchanged: First', Artisan::output() );
        $this->assertSame( 0, Artisan::call( 'cms:wp-import', ['--connection' => 'wordpress', '--domain' => 'new.example', '--since' => '2026-08-01 12:00:00 UTC'] ) );
        $this->assertStringContainsString( 'Updated: First', Artisan::output() );
    }


    public function testUpdatesThemeOfImportedBlogPage(): void
    {
        $this->page( 'Root', '', 'new.example', 'root' );

        DB::connection( 'wordpress' )->table( 'wp_posts' )->insert( [
            'ID' => 40,
            'post_date' => '2026-08-01 12:00:00',
            'post_name' => 'blog',
            'post_title' => 'Blog post',
            'post_excerpt' => 'Blog post',
            'post_content' => '<!-- wp:paragraph --><p>Text</p><!-- /wp:paragraph -->',
        ] );

        $this->assertSame( 0, Artisan::call( 'cms:wp-import', ['--connection' => 'wordpress', '--domain' => 'new.example'] ) );

        $blog = PageView::where( 'domain', 'new.example' )->where( 'path', 'blog' )->firstOrFail();

        // posts don't overwrite the blog page
        $this->assertStringContainsString( 'Failed to import [40] Blog post: URL /blog is used by another page of this import', Artisan::output() );
        $this->assertSame( 'Blog', $blog->name );
        $this->assertSame( '', $blog->theme );

        $this->assertSame( 0, Artisan::call( 'cms:wp-import', ['--connection' => 'wordpress', '--domain' => 'new.example', '--theme' => 'pagible'] ) );

        $this->assertStringContainsString( 'Using existing blog page: Blog (/blog)', Artisan::output() );
        $this->assertSame( 'pagible', PageView::findOrFail( $blog->id )->theme );
        $this->assertSame( 2, PageView::findOrFail( $blog->id )->versions()->count() );
    }


    public function testSameSlugDoesntOverwritePages(): void
    {
        $this->page( 'Root', '', 'new.example', 'root' );

        foreach( [60 => 'Original', 61 => 'Duplicate'] as $id => $title )
        {
            DB::connection( 'wordpress' )->table( 'wp_posts' )->insert( [
                'ID' => $id,
                'post_date' => '2026-08-0' . $id % 10 + 1 . ' 12:00:00',
                'post_name' => 'same',
                'post_title' => $title,
                'post_excerpt' => $title,
                'post_content' => '<!-- wp:paragraph --><p>Text</p><!-- /wp:paragraph -->',
            ] );
        }

        $this->assertSame( 0, Artisan::call( 'cms:wp-import', ['--connection' => 'wordpress', '--domain' => 'new.example'] ) );

        $output = Artisan::output();

        $this->assertStringContainsString( 'Failed to import [61] Duplicate: URL /same is used by another page of this import', $output );
        $this->assertStringContainsString( '1 imported, 0 updated, 0 unchanged (1/2 posts processed)', $output );
        $this->assertSame( 'Original', PageView::where( 'domain', 'new.example' )->where( 'path', 'same' )->sole()->title );
    }


    public function testRefetchesPageOfFailedPost(): void
    {
        $this->page( 'Root', '', 'new.example', 'root' );

        $post = [
            'post_type' => 'post',
            'post_status' => 'publish',
            'post_name' => 'same',
            'post_content' => '<!-- wp:paragraph --><p>Text</p><!-- /wp:paragraph -->',
        ];
        DB::connection( 'wordpress' )->table( 'wp_posts' )->insert( ['ID' => 80, 'post_date' => '2026-08-01 12:00:00', 'post_title' => 'Original', 'post_excerpt' => 'Original'] + $post );

        $this->assertSame( 0, Artisan::call( 'cms:wp-import', ['--connection' => 'wordpress', '--domain' => 'new.example'] ) );

        DB::connection( 'wordpress' )->table( 'wp_posts' )->delete();
        foreach( [81, 82] as $id ) {
            DB::connection( 'wordpress' )->table( 'wp_posts' )->insert( ['ID' => $id, 'post_date' => '2026-08-02 12:00:00', 'post_title' => 'Changed', 'post_excerpt' => 'Changed'] + $post );
        }

        $fail = true;
        $this->beforeApplicationDestroyed( function() use ( &$fail ) { $fail = false; } );

        // the first post fails after its page has been published
        Event::listen( 'eloquent.saving: ' . PageVariant::class, function( PageVariant $variant ) use ( &$fail ) {
            if( $fail && $variant->isDirty( 'created_at' ) ) {
                $fail = false;
                throw new \RuntimeException( 'Broken date' );
            }
        } );

        $this->assertSame( 0, Artisan::call( 'cms:wp-import', ['--connection' => 'wordpress', '--domain' => 'new.example'] ) );

        $output = Artisan::output();
        $page = PageView::where( 'domain', 'new.example' )->where( 'path', 'same' )->firstOrFail();

        $this->assertStringContainsString( 'Failed to import [81] Changed: Broken date', $output );
        $this->assertStringContainsString( 'Updated: Changed', $output );
        $this->assertSame( 'Changed', $page->title );
        $this->assertSame( '2026-08-02 12:00:00', $page->created_at?->format( 'Y-m-d H:i:s' ) );
    }

    public function testReportsInvalidPostRows(): void
    {
        $this->page( 'Root', '', 'new.example', 'root' );

        $post = [
            'post_type' => 'post',
            'post_status' => 'publish',
            'post_excerpt' => '',
            'post_content' => '<!-- wp:paragraph --><p>Text</p><!-- /wp:paragraph -->',
        ];
        // numeric dates are stored as numbers by SQLite and aren't valid post dates
        DB::connection( 'wordpress' )->table( 'wp_posts' )->insert( ['ID' => 90, 'post_name' => 'broken', 'post_title' => 'Broken', 'post_date' => 20260801] + $post );
        DB::connection( 'wordpress' )->table( 'wp_posts' )->insert( ['ID' => 91, 'post_name' => 'valid', 'post_title' => 'Valid', 'post_date' => '2026-08-01 12:00:00'] + $post );
        // empty MySQL dates aren't used as creation date
        DB::connection( 'wordpress' )->table( 'wp_posts' )->insert( ['ID' => 92, 'post_name' => 'undated', 'post_title' => 'Undated', 'post_date' => '0000-00-00 00:00:00', 'post_date_gmt' => '0000-00-00 00:00:00'] + $post );

        // the dry run lists the valid posts too
        DB::connection( 'wordpress' )->enableQueryLog();
        $this->assertSame( 0, Artisan::call( 'cms:wp-import', ['--connection' => 'wordpress', '--domain' => 'new.example', '--dry-run' => true] ) );
        $queries = implode( "\n", array_column( DB::connection( 'wordpress' )->getQueryLog(), 'query' ) );
        DB::connection( 'wordpress' )->disableQueryLog();
        DB::connection( 'wordpress' )->flushQueryLog();

        $output = Artisan::output();

        // the texts of the posts aren't fetched for the dry run
        $this->assertStringContainsString( '"post_title"', $queries );
        $this->assertStringNotContainsString( 'post_content', $queries );

        $this->assertStringContainsString( 'Skipped [90] Broken: Invalid WordPress post row.', $output );
        $this->assertStringContainsString( '[91] Valid (valid)', $output );
        $this->assertStringContainsString( 'Dry run complete.', $output );

        $this->assertSame( 0, Artisan::call( 'cms:wp-import', ['--connection' => 'wordpress', '--domain' => 'new.example'] ) );

        $output = Artisan::output();

        $this->assertStringContainsString( 'Failed to import [90] Broken: Invalid WordPress post row.', $output );
        $this->assertFalse( PageView::where( 'domain', 'new.example' )->where( 'path', 'broken' )->exists() );
        $this->assertTrue( PageView::where( 'domain', 'new.example' )->where( 'path', 'valid' )->exists() );
        $this->assertGreaterThan( 2000, PageView::where( 'domain', 'new.example' )->where( 'path', 'undated' )->firstOrFail()->created_at?->year );
    }


    public function testDisablesPasswordProtectedPosts(): void
    {
        $this->page( 'Root', '', 'new.example', 'root' );

        $post = ['post_date' => '2026-08-01 12:00:00', 'post_content' => '<!-- wp:paragraph --><p>Text</p><!-- /wp:paragraph -->'];
        DB::connection( 'wordpress' )->table( 'wp_posts' )->insert( ['ID' => 93, 'post_name' => 'members', 'post_title' => 'Members', 'post_password' => 'secret'] + $post );
        DB::connection( 'wordpress' )->table( 'wp_posts' )->insert( ['ID' => 94, 'post_name' => 'public', 'post_title' => 'Public'] + $post );

        $this->assertSame( 0, Artisan::call( 'cms:wp-import', ['--connection' => 'wordpress', '--domain' => 'new.example'] ) );
        $output = Artisan::output();

        $this->assertStringContainsString( 'Disabled [93] Members because it is password protected', $output );
        $this->assertStringNotContainsString( 'Disabled [94]', $output );
        $this->assertSame( 0, PageView::where( 'domain', 'new.example' )->where( 'path', 'members' )->firstOrFail()->status );
        $this->assertSame( 1, PageView::where( 'domain', 'new.example' )->where( 'path', 'public' )->firstOrFail()->status );

        // posts whose password was removed are enabled again
        DB::connection( 'wordpress' )->table( 'wp_posts' )->where( 'ID', 93 )->update( ['post_password' => ''] );

        $this->assertSame( 0, Artisan::call( 'cms:wp-import', ['--connection' => 'wordpress', '--domain' => 'new.example'] ) );
        $this->assertSame( 1, PageView::where( 'domain', 'new.example' )->where( 'path', 'members' )->firstOrFail()->status );
    }


    public function testRestoresTrashedPageOfPost(): void
    {
        $root = $this->page( 'Root', '', 'new.example', 'root' );
        $this->page( 'Other', 'other', 'new.example', 'page', $root )->page->delete();

        DB::connection( 'wordpress' )->table( 'wp_posts' )->insert( [
            'ID' => 71,
            'post_date' => '2026-08-01 12:00:00',
            'post_name' => 'other',
            'post_title' => 'Other',
            'post_excerpt' => 'Other',
            'post_content' => '<!-- wp:paragraph --><p>Text</p><!-- /wp:paragraph -->',
        ] );
        DB::connection( 'wordpress' )->table( 'wp_posts' )->insert( [
            'ID' => 70,
            'post_date' => '2026-08-01 12:00:00',
            'post_name' => 'trashed',
            'post_title' => 'Trashed',
            'post_excerpt' => 'Trashed',
            'post_content' => '<!-- wp:paragraph --><p>Text</p><!-- /wp:paragraph -->',
        ] );

        $this->assertSame( 0, Artisan::call( 'cms:wp-import', ['--connection' => 'wordpress', '--domain' => 'new.example'] ) );

        // trashed pages which weren't imported aren't restored
        $this->assertStringContainsString( "Failed to import [71] Other: URL /other is used by a page which wasn't imported or was changed since", Artisan::output() );
        $this->assertSame( 0, PageView::where( 'domain', 'new.example' )->where( 'path', 'other' )->count() );

        PageView::where( 'domain', 'new.example' )->where( 'path', 'trashed' )->firstOrFail()->page->delete();
        DB::connection( 'wordpress' )->table( 'wp_posts' )->where( 'ID', 70 )->update( ['post_title' => 'Restored'] );

        $this->assertSame( 0, Artisan::call( 'cms:wp-import', ['--connection' => 'wordpress', '--domain' => 'new.example'] ) );

        $this->assertStringContainsString( '0 imported, 1 updated, 0 unchanged (1/2 posts processed)', Artisan::output() );
        $this->assertSame( 'Restored', PageView::where( 'domain', 'new.example' )->where( 'path', 'trashed' )->sole()->title );
        $this->assertSame( 1, PageView::withTrashed()->where( 'domain', 'new.example' )->where( 'path', 'trashed' )->count() );
    }


    public function testDoesntOverwritePagesOfOtherLanguages(): void
    {
        $this->page( 'Root', '', 'new.example', 'root' );
        Pages::create( ['name' => 'Andere', 'title' => 'Andere', 'path' => 'other', 'tag' => 'page', 'domain' => 'new.example',
            'lang' => 'de', 'status' => 1, 'editor' => 'wp-import'], [] );

        DB::connection( 'wordpress' )->table( 'wp_posts' )->insert( [
            'ID' => 72,
            'post_date' => '2026-08-01 12:00:00',
            'post_name' => 'other',
            'post_title' => 'Other',
            'post_excerpt' => 'Other',
            'post_content' => '<!-- wp:paragraph --><p>Text</p><!-- /wp:paragraph -->',
        ] );

        $this->assertSame( 0, Artisan::call( 'cms:wp-import', ['--connection' => 'wordpress', '--domain' => 'new.example', '--blog-path' => '/news'] ) );
        $output = Artisan::output();

        $this->assertStringContainsString( 'Created blog page: Blog (/news)', $output );
        $this->assertStringContainsString( "Failed to import [72] Other: URL /other is used by a page which wasn't imported or was changed since", $output );
        $this->assertSame( 1, PageView::where( 'domain', 'new.example' )->where( 'path', 'news' )->count() );
        $this->assertSame( 'Andere', PageView::where( 'domain', 'new.example' )->where( 'path', 'other' )->sole()->name );
    }


    public function testReaderFetchesTextsOfRequiredPostsOnly(): void
    {
        Schema::connection( 'wordpress' )->table( 'wp_posts', fn( Blueprint $table ) => $table->dateTime( 'post_modified_gmt' )->nullable() );

        foreach( [1, 2, 3] as $id )
        {
            DB::connection( 'wordpress' )->table( 'wp_posts' )->insert( [
                'ID' => $id,
                'post_date' => '2026-01-01 00:00:00',
                'post_name' => 'post-' . $id,
                'post_title' => 'Post ' . $id,
                'post_excerpt' => 'Intro ' . $id,
                'post_content' => 'Text ' . $id,
            ] );
        }

        $db = DB::connection( 'wordpress' );
        $reader = new WpReader( $db );
        $read = function( ?\Closure $required ) use ( $db, $reader ) {
            $texts = [];
            $db->enableQueryLog();
            $reader->chunk( function( $rows ) use ( $reader, &$texts ) {
                foreach( $rows as $row )
                {
                    $post = $reader->post( $row );
                    $texts[$post->ID] = [$post->post_excerpt, $post->post_content];
                }
            }, $required );
            $queries = Collection::make( $db->getQueryLog() );
            $db->disableQueryLog();
            $db->flushQueryLog();

            return [$texts, $queries->filter( fn( $query ) => str_contains( $query['query'], ' limit ' ) ), $queries->filter( fn( $query ) => str_contains( $query['query'], '"ID" in' ) )];
        };

        // the texts are only fetched for the required posts
        [$texts, $chunks, $fetched] = $read( fn( object $row ) => ( get_object_vars( $row )['ID'] ?? null ) !== 2 );

        $this->assertSame( [1 => ['Intro 1', 'Text 1'], 2 => ['', ''], 3 => ['Intro 3', 'Text 3']], $texts );
        $this->assertCount( 1, $chunks );
        $this->assertDoesNotMatchRegularExpression( '/\*|post_content|post_excerpt/', explode( ' from ', ( $chunks->first()['query'] ?? '' ) )[0] );
        $this->assertSame( [[1, 3]], $fetched->pluck( 'bindings' )->values()->all() );
        $this->assertStringContainsString( '"post_content"', $fetched->first()['query'] ?? '' );

        // no texts are fetched if no post requires them
        [$texts, , $fetched] = $read( fn() => false );

        $this->assertSame( [1 => ['', ''], 2 => ['', ''], 3 => ['', '']], $texts );
        $this->assertCount( 0, $fetched );

        // without callback, the used columns of the posts are fetched at once
        [$texts, $chunks, $fetched] = $read( null );

        $this->assertSame( ['Intro 2', 'Text 2'], $texts[2] ?? null );
        $this->assertCount( 0, $fetched );
        $this->assertMatchesRegularExpression( '/^select "ID", "post_name", "post_title", "post_excerpt", "post_content", "post_date_gmt", "post_date", "post_modified_gmt", "post_password" from/', ( $chunks->first()['query'] ?? '' ) );

        $db->enableQueryLog();
        $this->assertCount( 3, $reader->cursor()->all() );
        $cursor = $db->getQueryLog()[0]['query'] ?? '';
        $db->disableQueryLog();

        $this->assertMatchesRegularExpression( '/^select "ID", "post_name", "post_title", "post_excerpt", "post_content", "post_date_gmt", "post_date", "post_modified_gmt", "post_password" from/', $cursor );
    }


    public function testSinceFetchesTextsOfChangedPostsOnly(): void
    {
        Schema::connection( 'wordpress' )->table( 'wp_posts', fn( Blueprint $table ) => $table->dateTime( 'post_modified_gmt' )->nullable() );
        $this->page( 'Root', '', 'new.example', 'root' );

        foreach( [60 => '2026-08-01 12:00:00', 61 => '2026-09-02 12:00:00', 62 => '0000-00-00 00:00:00', 63 => null] as $id => $modified )
        {
            DB::connection( 'wordpress' )->table( 'wp_posts' )->insert( [
                'ID' => $id,
                'post_date' => '2026-08-' . $id % 30 . ' 12:00:00',
                'post_modified_gmt' => $modified,
                'post_name' => 'post-' . $id,
                'post_title' => 'Post ' . $id,
                'post_content' => '<!-- wp:paragraph --><p>Text</p><!-- /wp:paragraph -->',
            ] );
        }

        $db = DB::connection( 'wordpress' );
        $run = function( array $options ) use ( $db ) {
            $db->enableQueryLog();
            $this->assertSame( 0, Artisan::call( 'cms:wp-import', ['--connection' => 'wordpress', '--domain' => 'new.example'] + $options ) );
            $queries = Collection::make( $db->getQueryLog() );
            $db->disableQueryLog();
            $db->flushQueryLog();

            return [
                $queries->filter( fn( $query ) => str_contains( $query['query'], '"post_status" = ?' ) && str_contains( $query['query'], ' limit ' ) ),
                $queries->filter( fn( $query ) => str_contains( $query['query'], '"post_content"' ) && str_contains( $query['query'], '"ID" in' ) ),
            ];
        };

        // without --since, the texts are fetched together with the other columns
        [$chunks, $texts] = $run( [] );

        $this->assertCount( 1, $chunks );
        $this->assertStringContainsString( '"post_content"', explode( ' from ', ( $chunks->first()['query'] ?? '' ) )[0] );
        $this->assertStringNotContainsString( '*', explode( ' from ', ( $chunks->first()['query'] ?? '' ) )[0] );
        $this->assertCount( 0, $texts );

        $db->table( 'wp_posts' )->update( ['post_content' => '<!-- wp:paragraph --><p>New</p><!-- /wp:paragraph -->'] );
        [$chunks, $texts] = $run( ['--since' => '2026-09-01 12:00:00 UTC'] );
        $output = Artisan::output();

        // the texts are only fetched for changed posts and posts without modification time
        $this->assertDoesNotMatchRegularExpression( '/\*|post_content|post_excerpt/', explode( ' from ', ( $chunks->first()['query'] ?? '' ) )[0] );
        $this->assertSame( [[61, 62, 63]], $texts->pluck( 'bindings' )->values()->all() );
        $this->assertStringContainsString( 'Unchanged: Post 60', $output );
        $this->assertStringContainsString( 'Updated: Post 61', $output );
        $this->assertStringContainsString( 'Updated: Post 63', $output );
        $this->assertStringNotContainsString( 'New', json_encode( PageView::where( 'path', 'post-60' )->firstOrFail()->content ) ?: '' );
        $this->assertStringContainsString( 'New', json_encode( PageView::where( 'path', 'post-63' )->firstOrFail()->content ) ?: '' );
    }


    public function testSinceFetchesOnlyKeptColumnsOfUnchangedPosts(): void
    {
        Schema::connection( 'wordpress' )->table( 'wp_posts', fn( Blueprint $table ) => $table->dateTime( 'post_modified_gmt' )->nullable() );

        foreach( [53 => 'First', 54 => 'Second'] as $id => $title )
        {
            DB::connection( 'wordpress' )->table( 'wp_posts' )->insert( [
                'ID' => $id,
                'post_date' => '2026-08-01 12:00:00',
                'post_modified_gmt' => '2026-08-01 12:00:00',
                'post_name' => strtolower( $title ),
                'post_title' => $title,
                'post_content' => '<!-- wp:paragraph --><p>Text</p><!-- /wp:paragraph -->',
            ] );
        }

        $this->assertSame( 0, Artisan::call( 'cms:wp-import', ['--connection' => 'wordpress', '--domain' => 'new.example'] ) );

        $first = PageView::where( 'domain', 'new.example' )->where( 'path', 'first' )->firstOrFail();
        $first->page->delete();
        DB::connection( 'wordpress' )->table( 'wp_posts' )->where( 'ID', 54 )->update( ['post_title' => 'Second changed', 'post_modified_gmt' => '2026-09-02 12:00:00'] );

        $queries = [];
        DB::connection( config( 'cms.db', 'sqlite' ) )->listen( function( $query ) use ( &$queries ) {
            $queries[] = [$this->sql( $query->sql ), $query->bindings];
        } );

        $this->assertSame( 0, Artisan::call( 'cms:wp-import', ['--connection' => 'wordpress', '--domain' => 'new.example', '--since' => '2026-09-01 12:00:00 UTC'] ) );
        $output = Artisan::output();

        // the pages of unchanged posts are only moved, so their content isn't fetched
        $lookups = array_values( array_filter( $queries, fn( $q ) => str_contains( $q[0], 'from "cms_page_view" where ("domain" = ?) and "path" in' ) ) );
        $this->assertCount( 2, $lookups );
        $this->assertStringStartsWith( 'select * from', $lookups[0][0] );
        $this->assertSame( ['second', '/second'], array_values( array_intersect( $lookups[0][1], ['first', '/first', 'second', '/second'] ) ) );
        $this->assertStringStartsWith( 'select "id", "variant_id", "parent_id", "tenant_id", "lang", "domain", "path", "editor", "to", "deleted_at" from', $lookups[1][0] );
        $this->assertSame( ['first', '/first'], array_values( array_intersect( $lookups[1][1], ['first', '/first', 'second', '/second'] ) ) );

        // the trashed page of the unchanged post is restored and kept
        $this->assertStringContainsString( 'Unchanged: First', $output );
        $this->assertStringContainsString( 'Updated: Second changed', $output );
        $this->assertSame( 'First', PageView::where( 'domain', 'new.example' )->where( 'path', 'first' )->sole()->title );
        $this->assertSame( 1, PageView::withTrashed()->where( 'domain', 'new.example' )->where( 'path', 'first' )->count() );
    }


    public function testUpdatesPageOfPostWithLegacyPath(): void
    {
        DB::connection( 'wordpress' )->table( 'wp_posts' )->insert( [
            'ID' => 55,
            'post_date' => '2026-08-01 12:00:00',
            'post_name' => 'legacy',
            'post_title' => 'Legacy',
            'post_content' => '<!-- wp:paragraph --><p>Text</p><!-- /wp:paragraph -->',
        ] );

        $this->assertSame( 0, Artisan::call( 'cms:wp-import', ['--connection' => 'wordpress', '--domain' => 'new.example'] ) );

        // pages of previous imports may use URL paths with leading slash
        $page = PageView::where( 'domain', 'new.example' )->where( 'path', 'legacy' )->firstOrFail();
        PageVariant::whereKey( $page->variant_id )->update( ['path' => '/legacy'] );
        DB::connection( 'wordpress' )->table( 'wp_posts' )->where( 'ID', 55 )->update( ['post_title' => 'Legacy changed'] );

        $this->assertSame( 0, Artisan::call( 'cms:wp-import', ['--connection' => 'wordpress', '--domain' => 'new.example'] ) );

        $this->assertStringContainsString( '0 imported, 1 updated, 0 unchanged', Artisan::output() );
        $pages = PageView::withTrashed()->where( 'domain', 'new.example' )->whereIn( 'path', ['legacy', '/legacy'] )->get();
        $this->assertSame( [$page->id], $pages->pluck( 'id' )->all() );
        $this->assertSame( 'Legacy changed', PageView::findOrFail( $page->id )->title );

        // the page with the exact URL path is preferred over the one with leading slash
        PageVariant::whereKey( $page->variant_id )->update( ['path' => 'legacy'] );
        $old = $this->page( 'Old', '/legacy', 'new.example', '' );
        DB::connection( 'wordpress' )->table( 'wp_posts' )->where( 'ID', 55 )->update( ['post_title' => 'Legacy again'] );

        $this->assertSame( 0, Artisan::call( 'cms:wp-import', ['--connection' => 'wordpress', '--domain' => 'new.example'] ) );

        $this->assertSame( 'Legacy again', PageView::findOrFail( $page->id )->title );
        $this->assertSame( 'Old', PageView::findOrFail( $old->id )->title );
    }


    public function testParsesBlocksWithNestedAttributes(): void
    {
        $wp = new WordPress( fn( string $tag ) => preg_match( '/src="([^"]+)"/', $tag, $m ) ? 'img:' . $m[1] : null, fn( string $url ) => null );
        $data = fn( array $result ) => array_map( fn( $el ) => [$el['type'], $el['data']], $result['elements'] );

        $result = $wp->elements( '<!-- wp:group {"tagName":"section","layout":{"type":"constrained"}} --><section class="wp-block-group">'
            . '<!-- wp:group {"style":{"spacing":{"padding":"1em"}}} --><div class="wp-block-group"><div class="wp-block-group__inner-container">'
            . '<!-- wp:heading {"style":{"typography":{"fontSize":"2em"}}} --><h2>Inner</h2><!-- /wp:heading --></div></div><!-- /wp:group -->'
            . '<!-- wp:paragraph --><p>After</p><!-- /wp:paragraph --></section><!-- /wp:group -->'
            . '<!-- wp:image {"align":"left","style":{"border":{"radius":"4px"}}} --><figure><img src="c.png"></figure><!-- /wp:image -->'
            . '<!-- wp:paragraph --><p>Next</p><!-- /wp:paragraph --><!-- wp:spacer {"style":{"spacing":{"margin":"0"}}} /-->'
            . '<!-- wp:columns {"style":{"spacing":{"blockGap":"1em"}}} --><div><!-- wp:column {"style":{"color":{"text":"red"}}} --><div><p>Left</p></div><!-- /wp:column -->'
            . '<!-- wp:column --><div><!-- wp:image --><figure><img src="d.png"></figure><!-- /wp:image --></div><!-- /wp:column --></div><!-- /wp:columns -->'
            . '<!-- wp:group --><div class="wp-block-group"><figure><img src="e.png"></figure><!-- wp:paragraph --><p>Last</p><!-- /wp:paragraph --></div><!-- /wp:group -->' );

        // the blocks inside of groups are used and the attributes may contain nested objects
        $this->assertSame( [
            ['heading', ['level' => 2, 'title' => 'Inner']],
            ['text', ['text' => 'After']],
            ['image-text', ['text' => 'Next', 'file' => Elements::file( 'img:c.png' ), 'position' => 'start']],
            ['image-text', ['text' => 'Left', 'file' => Elements::file( 'img:d.png' ), 'position' => 'end']],
            ['image', ['file' => Elements::file( 'img:e.png' )]],
            ['text', ['text' => 'Last']],
        ], $data( $result ) );
    }


    public function testDownloadsAllFilesOfPostBeforeItsTransaction(): void
    {
        $config = ['cms.allow-internal' => config( 'cms.allow-internal' ), 'cms.image.preview-sizes' => config( 'cms.image.preview-sizes' )];
        $this->beforeApplicationDestroyed( fn() => config( $config ) );
        config( ['cms.allow-internal' => true, 'cms.image.preview-sizes' => []] );
        Storage::fake( (string) config( 'cms.disks.public.name', 'public' ) );

        $png = (string) base64_decode( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=' );
        $db = DB::connection( config( 'cms.db', 'sqlite' ) );
        $level = $db->transactionLevel();
        $requests = [];

        Http::fake( function( $request ) use ( $png, $db, &$requests ) {
            $requests[] = [basename( $request->url() ), $db->transactionLevel(), PageView::where( 'domain', 'new.example' )->where( 'path', 'like', 'post-%' )->count()];
            return Http::response( $png, 200, ['Content-Type' => 'image/png'] );
        } );

        $this->page( 'Root', '', 'new.example', 'root' );
        $img = fn( string $name ) => '<!-- wp:image --><figure><img src="http://127.0.0.1/media/' . $name . '" alt="Image"></figure><!-- /wp:image -->';

        foreach( [88 => ['a.png', 'b.png', 'c.png', 'd.png', 'e.png'], 89 => ['f.png', 'g.png', 'h.png']] as $id => $names )
        {
            DB::connection( 'wordpress' )->table( 'wp_posts' )->insert( [
                'ID' => $id,
                'post_date' => '2026-08-' . $id % 30 . ' 12:00:00',
                'post_name' => 'post-' . $id,
                'post_title' => 'Post ' . $id,
                'post_content' => implode( '', array_map( $img, $names ) ),
            ] );
        }

        $command = new class extends WpImport {
            protected const DOWNLOAD_SIZE = 2;
        };
        $command->setLaravel( app() );

        $this->assertSame( 0, $command->run( new ArrayInput( [
            '--connection' => 'wordpress',
            '--domain' => 'new.example',
            '--media-url' => ['http://127.0.0.1/media'],
        ] ), new BufferedOutput() ) );

        // all files of a post are downloaded outside of its transaction, also more than DOWNLOAD_SIZE
        $this->assertSame( [
            ['a.png', $level + 1, 0],
            ['b.png', $level + 1, 0],
            ['c.png', $level + 1, 0],
            ['d.png', $level + 1, 0],
            ['e.png', $level + 1, 0],
            ['f.png', $level + 1, 0],
            ['g.png', $level + 1, 0],
            ['h.png', $level + 1, 1],
        ], $requests );
        $this->assertSame( 8, File::where( 'path', 'not like', 'http%' )->count() );
    }


    public function testModifiedAtTreatsMissingDatesAsChanged(): void
    {
        $command = new class extends WpImport {
            public function at( mixed $gmt ): int
            {
                return $this->modifiedAt( $gmt );
            }
        };

        $this->assertSame( PHP_INT_MAX, $command->at( null ) );
        $this->assertSame( PHP_INT_MAX, $command->at( '' ) );
        $this->assertSame( PHP_INT_MAX, $command->at( '0000-00-00 00:00:00' ) );
        $this->assertSame( PHP_INT_MAX, $command->at( 'invalid' ) );
        $this->assertSame( PHP_INT_MAX, $command->at( 1785585600 ) );
        // the modification time is in UTC
        $this->assertSame( gmmktime( 12, 0, 0, 8, 1, 2026 ), $command->at( '2026-08-01 12:00:00' ) );
    }


    public function testDownloadsMediaBeforeImportingPosts(): void
    {
        $config = ['cms.allow-internal' => config( 'cms.allow-internal' ), 'cms.image.preview-sizes' => config( 'cms.image.preview-sizes' )];
        $this->beforeApplicationDestroyed( fn() => config( $config ) );
        config( ['cms.allow-internal' => true, 'cms.image.preview-sizes' => []] );
        Storage::fake( (string) config( 'cms.disks.public.name', 'public' ) );

        $png = (string) base64_decode( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=' );
        $db = DB::connection( config( 'cms.db', 'sqlite' ) );
        $level = $db->transactionLevel();
        $requests = [];

        Http::fake( function( $request ) use ( $png, $db, &$requests ) {
            $requests[] = [$request->url(), $db->transactionLevel(), PageView::where( 'domain', 'new.example' )->whereIn( 'path', ['first', 'second'] )->count()];
            return Http::response( $png, 200, ['Content-Type' => 'image/png'] );
        } );

        $this->page( 'Root', '', 'new.example', 'root' );
        $img = fn( string $url ) => '<!-- wp:image --><figure><img src="' . $url . '" alt="Image"></figure><!-- /wp:image -->';

        foreach( [
            82 => ['First', $img( 'http://127.0.0.1/media/a.png' ) . $img( 'http://127.0.0.1/media/shared.png' ) . $img( 'https://cdn.example/external.png' )],
            83 => ['Second', $img( 'http://127.0.0.1/media/photo.jpg' ) . $img( 'http://127.0.0.1/media/shared.png' )],
        ] as $id => [$title, $content] )
        {
            DB::connection( 'wordpress' )->table( 'wp_posts' )->insert( [
                'ID' => $id,
                'post_date' => '2026-08-' . $id % 30 . ' 12:00:00',
                'post_name' => strtolower( $title ),
                'post_title' => $title,
                'post_excerpt' => $title,
                'post_content' => $content,
            ] );
        }

        $this->assertSame( 0, Artisan::call( 'cms:wp-import', [
            '--connection' => 'wordpress',
            '--domain' => 'new.example',
            '--media-url' => ['http://127.0.0.1/media'],
        ] ) );
        $output = Artisan::output();

        // the uploads of all posts in the chunk are downloaded once before the posts are imported, external media isn't downloaded
        $this->assertEqualsCanonicalizing( ['http://127.0.0.1/media/a.png', 'http://127.0.0.1/media/shared.png', 'http://127.0.0.1/media/photo.jpg'], array_column( $requests, 0 ), $output );
        $this->assertSame( [$level + 1], array_values( array_unique( array_column( $requests, 1 ) ) ) );
        $this->assertSame( [0], array_values( array_unique( array_column( $requests, 2 ) ) ) );
        $this->assertSame( 2, PageView::where( 'domain', 'new.example' )->whereIn( 'path', ['first', 'second'] )->count() );

        // the downloaded files are used and their MIME type is detected from the content
        $local = File::where( 'path', 'not like', 'http%' )->get();
        $this->assertCount( 3, $local );
        $this->assertSame( ['image/png'], $local->pluck( 'mime' )->unique()->values()->all() );
        $this->assertSame( 1, File::where( 'path', 'https://cdn.example/external.png' )->count() );
    }


    public function testDownloadsMediaOfNextPostsBeforeEachPost(): void
    {
        $config = ['cms.allow-internal' => config( 'cms.allow-internal' ), 'cms.image.preview-sizes' => config( 'cms.image.preview-sizes' )];
        $this->beforeApplicationDestroyed( fn() => config( $config ) );
        config( ['cms.allow-internal' => true, 'cms.image.preview-sizes' => []] );
        Storage::fake( (string) config( 'cms.disks.public.name', 'public' ) );

        $png = (string) base64_decode( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=' );
        $db = DB::connection( config( 'cms.db', 'sqlite' ) );
        $level = $db->transactionLevel();
        $requests = [];

        Http::fake( function( $request ) use ( $png, $db, &$requests ) {
            $requests[] = [basename( $request->url() ), $db->transactionLevel(), PageView::where( 'domain', 'new.example' )->where( 'path', 'like', 'post-%' )->count()];
            return Http::response( $png, 200, ['Content-Type' => 'image/png'] );
        } );

        $this->page( 'Root', '', 'new.example', 'root' );
        $img = fn( string $name ) => '<!-- wp:image --><figure><img src="http://127.0.0.1/media/' . $name . '" alt="Image"></figure><!-- /wp:image -->';

        foreach( [84 => ['a.png', 'b.png'], 85 => ['c.png', 'd.png'], 86 => ['e.png', 'a.png']] as $id => $names )
        {
            DB::connection( 'wordpress' )->table( 'wp_posts' )->insert( [
                'ID' => $id,
                'post_date' => '2026-08-' . $id % 30 . ' 12:00:00',
                'post_name' => 'post-' . $id,
                'post_title' => 'Post ' . $id,
                'post_content' => implode( '', array_map( $img, $names ) ),
            ] );
        }

        $command = new class extends WpImport {
            protected const DOWNLOAD_SIZE = 2;
        };
        $command->setLaravel( app() );

        $this->assertSame( 0, $command->run( new ArrayInput( [
            '--connection' => 'wordpress',
            '--domain' => 'new.example',
            '--media-url' => ['http://127.0.0.1/media'],
        ] ), new BufferedOutput() ) );

        // the files of the next posts are downloaded outside of the post transactions when the post uses a missing file
        // all files of the post and up to DOWNLOAD_SIZE files of the next posts are downloaded together
        $this->assertSame( [
            ['a.png', $level + 1, 0],
            ['b.png', $level + 1, 0],
            ['c.png', $level + 1, 0],
            ['d.png', $level + 1, 0],
            ['e.png', $level + 1, 2],
        ], $requests );
        $this->assertSame( 5, File::where( 'path', 'not like', 'http%' )->count() );
    }


    public function testKeepsDownloadedMediaUntilItsPost(): void
    {
        $config = ['cms.allow-internal' => config( 'cms.allow-internal' ), 'cms.image.preview-sizes' => config( 'cms.image.preview-sizes' )];
        $this->beforeApplicationDestroyed( fn() => config( $config ) );
        config( ['cms.allow-internal' => true, 'cms.image.preview-sizes' => []] );
        Storage::fake( (string) config( 'cms.disks.public.name', 'public' ) );

        $png = (string) base64_decode( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=' );
        $requests = [];

        Http::fake( function( $request ) use ( $png, &$requests ) {
            $requests[] = [basename( $request->url() ), PageView::where( 'domain', 'new.example' )->where( 'path', 'like', 'post-%' )->count()];
            return Http::response( $png, 200, ['Content-Type' => 'image/png'] );
        } );

        $this->page( 'Root', '', 'new.example', 'root' );
        $img = fn( string $name ) => '<!-- wp:image --><figure><img src="http://127.0.0.1/media/' . $name . '" alt="Image"></figure><!-- /wp:image -->';

        foreach( [84 => ['a.png'], 85 => ['b.png'], 86 => ['c.png'], 87 => ['d.png', 'e.png']] as $id => $names )
        {
            DB::connection( 'wordpress' )->table( 'wp_posts' )->insert( [
                'ID' => $id,
                'post_date' => '2026-08-' . $id % 30 . ' 12:00:00',
                'post_name' => 'post-' . $id,
                'post_title' => 'Post ' . $id,
                'post_content' => implode( '', array_map( $img, $names ) ),
            ] );
        }

        $command = new class extends WpImport {
            protected const DOWNLOAD_SIZE = 2;
        };
        $command->setLaravel( app() );

        $this->assertSame( 0, $command->run( new ArrayInput( [
            '--connection' => 'wordpress',
            '--domain' => 'new.example',
            '--media-url' => ['http://127.0.0.1/media'],
        ] ), new BufferedOutput() ) );

        // files are downloaded again only when a post needs a missing file and held files are kept until they are used
        $this->assertSame( [['a.png', 0], ['b.png', 0], ['c.png', 0], ['d.png', 3], ['e.png', 3]], $requests );
        $this->assertSame( 5, File::where( 'path', 'not like', 'http%' )->count() );
    }


    public function testMediaSkipsInlineImagesAndListsDownloadsByPost(): void
    {
        $created = $iterated = [];
        $attachments = function() use ( &$iterated ) {
            $iterated[] = true;
            yield 'photo.jpg' => 7;
        };
        $media = new WpMedia( ['https://wp.example/media'], function( string $mime, string $name, string $url ) use ( &$created ) {
            $created[] = $url;
            return 'id';
        }, $attachments(), fn( array $ids ) => array_fill_keys( $ids, ['guid' => 'https://wp.example/wp-content/uploads/photo.jpg', 'title' => 'Photo', 'mime' => 'image/jpeg'] ) );
        $inline = '<img src="data:image/png;base64,' . str_repeat( 'iVBORw0KGgo', 100 ) . '">';

        // inline images aren't files, aren't looked up as attachments and aren't fetched by path
        $media->prefetch( [], [5 => $inline . '<img src=" DATA:image/gif;base64,R0lGOD">'] );

        $this->assertSame( [], $media->paths() );
        $this->assertSame( [], $iterated );
        $this->assertNull( $media->image( $inline ) );
        $this->assertSame( [], $created );
        $this->assertSame( [], $iterated );

        // the downloaded files are listed for each post including the featured images
        $media->prefetch( [6 => 7], [
            5 => '<img src="https://wp.example/wp-content/uploads/a.png"><img src="https://cdn.example/b.png">',
            6 => '<img src="https://wp.example/wp-content/uploads/c.png"><img src="https://wp.example/wp-content/uploads/a.png">',
            8 => '<img src="https://wp.example/wp-content/uploads/2026/photo.jpg">',
        ] );

        $this->assertSame( ['https://wp.example/media/a.png'], $media->downloads( [5] ) );
        $this->assertSame( ['https://wp.example/media/c.png', 'https://wp.example/media/a.png', 'https://wp.example/media/photo.jpg'], $media->downloads( [6] ) );
        $this->assertSame( ['https://wp.example/media/photo.jpg'], $media->downloads( [8] ) );
        $this->assertSame( [], $media->downloads( [9] ) );
        $this->assertSame( ['https://wp.example/media/photo.jpg', 'https://wp.example/media/a.png'], $media->downloads( [8, 5] ) );
        $this->assertSame( ['https://wp.example/media/a.png', 'https://wp.example/media/c.png', 'https://wp.example/media/photo.jpg'], $media->downloads( [5, 6] ) );
        $this->assertEqualsCanonicalizing( ['https://wp.example/media/a.png', 'https://wp.example/media/c.png', 'https://wp.example/media/photo.jpg'], $media->downloads() );

        // the downloads of the posts prefetched before aren't kept
        $media->prefetch( [], [9 => '<img src="https://wp.example/wp-content/uploads/d.png">'] );
        $this->assertSame( [], $media->downloads( [5, 6, 8] ) );
        $this->assertSame( ['https://wp.example/media/d.png'], $media->downloads( [9] ) );
    }


    public function testSinceImportsUnchangedPostsWithoutPage(): void
    {
        $config = ['cms.allow-internal' => config( 'cms.allow-internal' ), 'cms.image.preview-sizes' => config( 'cms.image.preview-sizes' )];
        $this->beforeApplicationDestroyed( fn() => config( $config ) );
        config( ['cms.allow-internal' => true, 'cms.image.preview-sizes' => []] );
        Storage::fake( (string) config( 'cms.disks.public.name', 'public' ) );

        $png = (string) base64_decode( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=' );
        $cms = DB::connection( config( 'cms.db', 'sqlite' ) );
        $level = $cms->transactionLevel();
        $requests = [];

        Http::fake( function( $request ) use ( $png, $cms, &$requests ) {
            $requests[] = [basename( $request->url() ), $cms->transactionLevel()];
            return Http::response( $png, 200, ['Content-Type' => 'image/png'] );
        } );

        Schema::connection( 'wordpress' )->table( 'wp_posts', fn( Blueprint $table ) => $table->dateTime( 'post_modified_gmt' )->nullable() );
        $this->page( 'Root', '', 'new.example', 'root' );

        foreach( [64 => 'Kept', 65 => 'Missing'] as $id => $title )
        {
            DB::connection( 'wordpress' )->table( 'wp_posts' )->insert( [
                'ID' => $id,
                'post_date' => '2026-08-' . $id % 30 . ' 12:00:00',
                'post_modified_gmt' => '2026-08-01 12:00:00',
                'post_name' => 'post-' . $id,
                'post_title' => $title,
                'post_excerpt' => "Intro {$title}",
                'post_content' => "<!-- wp:paragraph --><p>Text {$title}</p><!-- /wp:paragraph -->"
                    . '<!-- wp:image --><figure><img src="http://127.0.0.1/media/' . $id . '.png" alt="Image"></figure><!-- /wp:image -->',
            ] );
        }

        $options = ['--connection' => 'wordpress', '--domain' => 'new.example', '--media-url' => ['http://127.0.0.1/media']];
        $this->assertSame( 0, Artisan::call( 'cms:wp-import', $options ) );
        PageView::where( 'path', 'post-65' )->firstOrFail()->page?->forceDelete();
        $requests = [];

        $db = DB::connection( 'wordpress' );
        $db->enableQueryLog();
        $this->assertSame( 0, Artisan::call( 'cms:wp-import', $options + ['--since' => '2026-09-01 12:00:00 UTC'] ) );
        $texts = Collection::make( $db->getQueryLog() )->filter( fn( $query ) => str_contains( $query['query'], '"post_content"' ) && str_contains( $query['query'], '"ID" in' ) );
        $db->disableQueryLog();
        $db->flushQueryLog();
        $output = Artisan::output();

        // unchanged posts whose page doesn't exist any more are imported with their texts
        $this->assertSame( [[65]], $texts->pluck( 'bindings' )->values()->all() );
        $this->assertStringContainsString( 'Unchanged: Kept', $output );
        $content = json_encode( PageView::where( 'path', 'post-65' )->firstOrFail()->content ) ?: '';
        $this->assertStringContainsString( 'Text Missing', $content );
        $this->assertStringContainsString( 'Intro Missing', $content );

        // their media is downloaded outside of the post transactions like the one of changed posts
        $this->assertSame( [['65.png', $level + 1]], $requests );
    }


    public function testDownloadKeepsFetchedFilesUntilTheyAreUsed(): void
    {
        $config = ['cms.allow-internal' => config( 'cms.allow-internal' ), 'cms.image.preview-sizes' => config( 'cms.image.preview-sizes' )];
        $this->beforeApplicationDestroyed( fn() => config( $config ) );
        config( ['cms.allow-internal' => true, 'cms.image.preview-sizes' => []] );
        Storage::fake( (string) config( 'cms.disks.public.name', 'public' ) );

        $png = (string) base64_decode( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=' );
        $db = DB::connection( config( 'cms.db', 'sqlite' ) );
        $commits = 0;
        $requests = [];

        Event::listen( TransactionCommitted::class, function() use ( &$commits ) { $commits++; } );
        Http::fake( function( $request ) use ( $png, &$commits, &$requests ) {
            $requests[] = [$request->url(), $commits];
            return str_contains( $request->url(), 'missing' ) ? Http::response( 'Not found', 404 ) : Http::response( $png, 200, ['Content-Type' => 'text/plain'] );
        } );

        $command = new class extends WpImport {
            /** @param array<int, string> $urls */
            public function fetchAll( array $urls ): void
            {
                $this->download( $urls );
            }

            /** @return array{resource: resource, mime: string}|null */
            public function take( string $url ): ?array
            {
                return $this->downloaded( $url );
            }

            /** @return array<int, resource> */
            public function pending(): array
            {
                return array_column( $this->downloads, 'resource' );
            }

            /** @return array<string, string> */
            public function failed(): array
            {
                return $this->failedFiles;
            }

            public function batch( int $level ): void
            {
                $this->createdFiles = Collection::make();
                $this->batchLevel = $level;
                $this->batched = 1;
            }
        };

        $db->beginTransaction();
        $command->batch( $db->transactionLevel() );

        // the pending batch is committed before the missing files are downloaded once
        $command->fetchAll( ['http://127.0.0.1/media/a.png', 'http://127.0.0.1/media/a.png', 'http://127.0.0.1/media/b.png', 'http://127.0.0.1/media/missing.png'] );

        $this->assertSame( [
            ['http://127.0.0.1/media/a.png', 1],
            ['http://127.0.0.1/media/b.png', 1],
            ['http://127.0.0.1/media/missing.png', 1],
        ], $requests );
        $this->assertSame( ['http://127.0.0.1/media/missing.png'], array_keys( $command->failed() ) );

        // the MIME type is detected from the content and each downloaded file is used once
        $fetched = $command->take( 'http://127.0.0.1/media/a.png' );
        $this->assertSame( 'image/png', $fetched['mime'] ?? null );
        $this->assertNull( $command->take( 'http://127.0.0.1/media/a.png' ) );
        $unused = $command->pending();
        $this->assertCount( 1, $unused );

        // failed files aren't downloaded again, nothing is committed without downloads and unused files are closed
        $command->fetchAll( ['http://127.0.0.1/media/missing.png'] );

        $this->assertCount( 3, $requests );
        $this->assertSame( 1, $commits );
        $this->assertSame( [], $command->pending() );
        $this->assertFalse( is_resource( $unused[0] ?? null ) );
        $this->assertTrue( is_resource( $fetched['resource'] ?? null ) );

        // the downloaded file is stored without downloading it again
        $file = Files::create( 'image/jpeg', 'Photo', 'http://127.0.0.1/media/a.png', 'test', true, $fetched );

        $this->assertCount( 3, $requests );
        $this->assertSame( 'image/png', $file->mime );
        $this->assertFalse( is_resource( $fetched['resource'] ?? null ) );

        $db->rollBack();

        $this->expectException( \Exception::class );
        Files::fetch( 'http://127.0.0.1/media/missing.png' );
    }


    public function testReportsFailedDownloadOnce(): void
    {
        $config = ['cms.allow-internal' => config( 'cms.allow-internal' )];
        $this->beforeApplicationDestroyed( fn() => config( $config ) );
        config( ['cms.allow-internal' => true] );
        Storage::fake( (string) config( 'cms.disks.public.name', 'public' ) );
        Http::fake( fn() => Http::response( 'Not found', 404 ) );

        $this->page( 'Root', '', 'new.example', 'root' );
        DB::connection( 'wordpress' )->table( 'wp_posts' )->insert( [
            'ID' => 24,
            'post_date' => '2026-08-24 12:00:00',
            'post_name' => 'missing',
            'post_title' => 'Missing',
            'post_excerpt' => 'Missing',
            'post_content' => '<!-- wp:image --><figure><img src="http://127.0.0.1/media/missing.png" alt="Missing"></figure><!-- /wp:image -->',
        ] );

        $this->assertSame( 0, Artisan::call( 'cms:wp-import', [
            '--connection' => 'wordpress',
            '--domain' => 'new.example',
            '--media-url' => ['http://127.0.0.1/media'],
        ] ) );
        $output = Artisan::output();

        // the download failed before the post is imported isn't retried
        Http::assertSentCount( 1 );
        $this->assertSame( 1, substr_count( $output, 'Failed to download "http://127.0.0.1/media/missing.png"' ), $output );
        $this->assertSame( 0, File::count() );
    }


    public function testMediaFindsAttachmentsByBasenameSuffix(): void
    {
        $find = function( array $basenames, string $src ) {
            $requested = [];
            $media = new WpMedia( ['https://wp.example/wp-content/uploads'], fn() => null, $basenames, function( array $ids ) use ( &$requested ) {
                $requested = $ids;
                return [];
            } );
            $media->prefetch( [], ['<img src="' . $src . '">'] );

            return $requested;
        };

        // long basenames are found by their last characters, short ones by their length
        $this->assertSame( [7], $find( ['photo-long-name.jpg' => 7], 'https://wp.example/wp-content/uploads/2026/photo-long-name.jpg' ) );
        $this->assertSame( [7], $find( ['photo-long-name.jpg' => 7], 'https://wp.example/wp-content/uploads/2026/photo-long-name.jpg?ver=1' ) );
        $this->assertSame( [], $find( ['photo-long-name.jpg' => 7], 'https://wp.example/wp-content/uploads/2026/other-name.jpg' ) );
        $this->assertSame( [], $find( ['a-very-long-photo-name.jpg' => 8], 'name.jpg' ) );
        $this->assertSame( [3], $find( ['a.b' => 3], 'https://wp.example/wp-content/uploads/a.b' ) );
        $this->assertSame( [4], $find( ['abc' => 4, 'photo.jpg' => 5], 'https://wp.example/wp-content/uploads/xabcx.png' ) );

        // the attachment added first wins if several basenames match
        $this->assertSame( [5], $find( ['name.jpg' => 5, 'photo-name.jpg' => 6], 'https://wp.example/wp-content/uploads/photo-name.jpg' ) );
        $this->assertSame( [6], $find( ['photo-name.jpg' => 6, 'name.jpg' => 5], 'https://wp.example/wp-content/uploads/photo-name.jpg' ) );
        $this->assertSame( [2], $find( ['x.b' => 9, 'a.b' => 2, 'long-a.b' => 1], 'https://wp.example/wp-content/uploads/long-a.b' ) );

        $media = new class( [], fn() => null, ['a.b' => 1, 'photo.jpg' => 2, 'image.jpg' => 3, 'my.png' => 4] ) extends WpMedia {
            /** @return array{0: array<int, true>, 1: array<string, array<int, true>>} */
            public function index(): array
            {
                return [$this->basenameLengths, $this->suffixLengths];
            }
        };

        // the basenames are only indexed when the first image URL is looked up
        $this->assertSame( [[], []], $media->index() );
        $media->prefetch( [], ['<img src="https://wp.example/x.png">'] );

        // only the basenames shorter than the suffix are looked up by their length
        $this->assertSame( [[3 => true], ['.jpg' => [9 => true], '.png' => [6 => true]]], $media->index() );
    }
}
