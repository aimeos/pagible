<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests\WordPress;

use Aimeos\Cms\Access;
use Aimeos\Cms\Models\Page;
use Aimeos\Cms\Models\PageAccess;
use Aimeos\Cms\Permission;
use Aimeos\Cms\Import\Files;
use Aimeos\Cms\Import\Elements;
use Aimeos\Cms\Import\Pages;
use Aimeos\Cms\Import\WordPress;
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
use Tests\CmsWithMigrations;
use Tests\ImportTestAbstract;

class ImportTest extends ImportTestAbstract
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

        $reader = new WordPress\Reader( DB::connection( 'wordpress' ) );
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
        $this->assertSame( [4 => ['guid' => 'https://wp.org/a.png', 'title' => 'A', 'mime' => 'image/png', 'url' => 'https://wp.org/a.png', 'alt' => '']], $reader->attachments( [4, 5, 9, 4] ) );
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

        $reader = new WordPress\Reader( $db );
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
            // the attached files are joined to the attachments, the former slugs are fetched once per run
            $metaQueries += (int) ( str_contains( $query->sql, 'from "wp_postmeta"' ) && !array_intersect( ['_wp_old_slug', '_yoast_wpseo_title'], $query->bindings ) );
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

        $footer = WordPress\Blog::footer( $page );

        $this->assertSame( ['ref1', 'ref1'], array_column( $footer['elements'], 'refid' ) );
        $this->assertSame( ['ref1'], $footer['elementIds'] );
    }


    public function testMediaPreview(): void
    {
        $media = new WordPress\Media( [], fn() => null );
        $pdf = Files::reference( 'application/pdf', 'Doc', 'https://cdn.example/doc.pdf', 'test' )->id ?? '';
        $image = Files::reference( 'image/png', 'Image', 'https://cdn.example/image.png', 'test' )->id ?? '';

        $db = \Aimeos\Cms\DB::connection();
        $db->enableQueryLog();
        $this->assertSame( $image, $media->preview( [$pdf, 'missing', $image, $pdf] ) );
        $this->assertNull( $media->preview( [$pdf] ) );
        $this->assertNull( $media->preview( [] ) );
        // the MIME types of the files are fetched once
        $this->assertSame( 1, count( $db->getQueryLog() ) );
        $this->assertSame( [$pdf, $image, 'test'], $db->getQueryLog()[0]['bindings'] );

        $other = Files::reference( 'image/png', 'Other', 'https://cdn.example/other.png', 'test' )->id ?? '';
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

        $db = \Aimeos\Cms\DB::connection();
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

        $cms = \Aimeos\Cms\DB::connection();
        $cms->enableQueryLog();
        $db->enableQueryLog();
        $this->assertSame( 0, Artisan::call( 'cms:wp-import', ['--connection' => 'wordpress', '--domain' => 'new.example'] ) );
        $log = Collection::make( $db->getQueryLog() );
        $mimes = Collection::make( $cms->getQueryLog() )->filter( fn( $query ) => preg_match( '/select "mime", "id" from "cms_files"/', $this->sql( $query['query'] ) ) === 1 );
        $db->disableQueryLog();
        $cms->disableQueryLog();
        // the former slugs and the values of the SEO plugins are fetched by their own queries
        $queries = $log->filter( fn( $query ) => str_contains( $query['query'], 'from "wp_postmeta"' ) && !array_intersect( ['_wp_old_slug', '_yoast_wpseo_title'], $query['bindings'] ) );
        $attachments = $log->filter( fn( $query ) => str_starts_with( $query['query'], 'select "wp_posts"."ID", "guid", "post_title", "post_mime_type", "attached"."meta_value" as "attached_file", "alt"."meta_value" as "alt" from "wp_posts"' ) );

        // the featured images are fetched for the posts of each chunk
        $this->assertSame( [100, 1], $queries->map( fn( $query ) => count( $query['bindings'] ) - 1 )->values()->all() );
        // only the used attachments are fetched once per chunk
        $this->assertSame( [[500, 501], [500, 502]], $attachments->map( fn( $query ) => array_map( 'intval', array_slice( $query['bindings'], 9 ) ) )->values()->all() );
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

        $db = \Aimeos\Cms\DB::connection();
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
        \Aimeos\Cms\DB::connection()->listen( function( $query ) use ( &$updates ) {
            $updates[] = str_contains( $this->sql( $query->sql ), 'set "created_at"' ) ? $query->sql : null;
        } );

        $this->assertSame( 0, Artisan::call( 'cms:wp-import', ['--connection' => 'wordpress', '--domain' => 'new.example'] ) );
        $this->assertStringContainsString( '2 imported, 0 updated, 0 unchanged', Artisan::output() );
        // the creation dates of new posts are part of the insert
        $this->assertSame( [], array_filter( $updates ) );
        $this->assertSame( '2026-08-20 12:00:00', PageView::where( 'path', 'first' )->firstOrFail()->created_at?->format( 'Y-m-d H:i:s' ) );

        DB::connection( 'wordpress' )->table( 'wp_posts' )->where( 'ID', 51 )->update( ['post_title' => 'Second changed'] );

        $queries = [];
        \Aimeos\Cms\DB::connection()->listen( function( $query ) use ( &$queries ) {
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
        $reader = new WordPress\Reader( $db );
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
        $this->assertMatchesRegularExpression( '/^select "ID", "post_name", "post_title", "post_excerpt", "post_content", "post_date_gmt", "post_date", "post_modified_gmt", "post_password", "post_status" from/', ( $chunks->first()['query'] ?? '' ) );

        $db->enableQueryLog();
        $this->assertCount( 3, $reader->cursor()->all() );
        $cursor = $db->getQueryLog()[0]['query'] ?? '';
        $db->disableQueryLog();

        $this->assertMatchesRegularExpression( '/^select "ID", "post_name", "post_title", "post_excerpt", "post_content", "post_date_gmt", "post_date", "post_modified_gmt", "post_password", "post_status" from/', $cursor );
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
                $queries->filter( fn( $query ) => str_contains( $query['query'], '"post_status" in' ) && str_contains( $query['query'], ' limit ' ) ),
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
        \Aimeos\Cms\DB::connection()->listen( function( $query ) use ( &$queries ) {
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
        $wp = new WordPress\Html( fn( string $tag ) => preg_match( '/src="([^"]+)"/', $tag, $m ) ? 'img:' . $m[1] : null, fn( string $url ) => null );
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
        $db = \Aimeos\Cms\DB::connection();
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
        $db = \Aimeos\Cms\DB::connection();
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
        $db = \Aimeos\Cms\DB::connection();
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
        $media = new WordPress\Media( ['https://wp.example/media'], function( string $mime, string $name, string $url ) use ( &$created ) {
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
        // the HTML entities of the sources and alternative texts are decoded like by the browser
        $names = [];
        $media = new WordPress\Media( [], function( string $mime, string $name, string $url ) use ( &$names ) {
            $names[$url] = $name;
            return 'id';
        } );
        $media->prefetch( [], [10 => '<img src="https://cdn.example/i.php?a=1&amp;b=2" alt="Tom &amp; Jerry">'] );

        $this->assertSame( ['https://cdn.example/i.php?a=1&b=2'], $media->paths() );
        $this->assertSame( 'id', $media->image( '<img src="https://cdn.example/i.php?a=1&amp;b=2" alt="Tom &amp; Jerry">' ) );
        $this->assertSame( ['https://cdn.example/i.php?a=1&b=2' => 'Tom & Jerry'], $names );
    }


    public function testSinceImportsUnchangedPostsWithoutPage(): void
    {
        $config = ['cms.allow-internal' => config( 'cms.allow-internal' ), 'cms.image.preview-sizes' => config( 'cms.image.preview-sizes' )];
        $this->beforeApplicationDestroyed( fn() => config( $config ) );
        config( ['cms.allow-internal' => true, 'cms.image.preview-sizes' => []] );
        Storage::fake( (string) config( 'cms.disks.public.name', 'public' ) );

        $png = (string) base64_decode( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=' );
        $cms = \Aimeos\Cms\DB::connection();
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
        $db = \Aimeos\Cms\DB::connection();
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
        $file = Files::download( 'image/jpeg', 'Photo', 'http://127.0.0.1/media/a.png', 'test', $fetched );

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
            $media = new WordPress\Media( ['https://wp.example/wp-content/uploads'], fn() => null, $basenames, function( array $ids ) use ( &$requested ) {
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

        $media = new class( [], fn() => null, ['a.b' => 1, 'photo.jpg' => 2, 'image.jpg' => 3, 'my.png' => 4] ) extends WordPress\Media {
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


    public function testReaderUsesTablePrefix(): void
    {
        $db = DB::connection( 'wordpress' );
        $db->getSchemaBuilder()->create( 'blog2_posts', function( Blueprint $table ) {
            $table->id( 'ID' );
            $table->string( 'post_type' )->default( 'post' );
            $table->string( 'post_status' )->default( 'publish' );
            $table->dateTime( 'post_date' );
            $table->string( 'post_name' );
            $table->string( 'post_title' );
        } );
        $db->getSchemaBuilder()->create( 'blog2_postmeta', function( Blueprint $table ) {
            $table->unsignedBigInteger( 'post_id' );
            $table->string( 'meta_key' );
            $table->text( 'meta_value' )->nullable();
        } );
        $db->table( 'blog2_posts' )->insert( ['ID' => 1, 'post_date' => '2026-01-01 00:00:00', 'post_name' => 'prefixed', 'post_title' => 'Prefixed'] );
        $db->table( 'blog2_postmeta' )->insert( ['post_id' => 1, 'meta_key' => '_thumbnail_id', 'meta_value' => '4'] );

        $reader = new WordPress\Reader( $db, 'blog2_' );

        $this->assertSame( 1, $reader->count() );
        $this->assertSame( 0, ( new WordPress\Reader( $db ) )->count() );
        $this->assertEquals( [1 => '4'], $reader->thumbnails( [1] ) );

        // WordPress allows letters, digits and underscores in $table_prefix only
        Artisan::call( 'cms:wp-import', ['--connection' => 'wordpress', '--prefix' => 'wp-1'] );
        $this->assertStringContainsString( 'Invalid WordPress table prefix: wp-1', Artisan::output() );

        $this->expectException( \InvalidArgumentException::class );
        new WordPress\Reader( $db, 'x;drop' );
    }


    public function testParsesShortcodes(): void
    {
        $warnings = [];
        $wp = new WordPress\Html(
            fn( string $tag ) => preg_match( '/src="([^"]+)"/', $tag, $m ) ? 'img:' . $m[1] : ( preg_match( '/wp-image-(\d+)/', $tag, $m ) ? 'id:' . $m[1] : null ),
            fn( string $url ) => 'file:' . $url,
            function( string $msg ) use ( &$warnings ) { $warnings[] = $msg; }
        );
        $data = fn( array $result ) => array_map( fn( $el ) => [$el['type'], $el['data']], $result['elements'] );

        $result = $wp->elements( "Intro\n\n[caption id=\"attachment_1\" align=\"alignleft\" width=\"300\"]<a href=\"a.png\"><img src=\"a.png\" /></a> Caption <em>A</em>[/caption]\n\n"
            . "[caption align=\"alignright\" caption=\"Caption B\"]<img src=\"b.png\" />[/caption]\n\n[caption]<img src=\"c.png\" />[/caption]\n\n"
            . "[gallery ids=\"1, 2\" columns=\"2\"]\n\n[gallery include='3']\n\n[gallery]\n\n[[gallery ids=\"4\"]]\n\n"
            . "[embed width=\"500\"]https://www.youtube.com/watch?v=abcdefghijk[/embed]\n\n[embed]https://example.com/post?a=1&amp;b=2[/embed]\n\n"
            . "[video width=\"640\" webm=\"v.webm\" mp4=\"v.mp4\"][/video]\n\n[audio src=\"a.mp3\"]\n\n[video src=\"https://vimeo.com/42\"]\n\n[unknown x=\"1\"]" );

        $this->assertSame( [
            ['text', ['text' => 'Intro']],
            ['image-text', ['text' => 'Caption *A*', 'file' => Elements::file( 'img:a.png' ), 'position' => 'start']],
            ['image-text', ['text' => 'Caption B', 'file' => Elements::file( 'img:b.png' ), 'position' => 'end']],
            ['image', ['file' => Elements::file( 'img:c.png' )]],
            ['slideshow', ['title' => '', 'files' => [Elements::file( 'id:1' ), Elements::file( 'id:2' )]]],
            ['image', ['file' => Elements::file( 'id:3' )]],
            // escaped shortcodes are text without the outer brackets
            ['text', ['text' => '[gallery ids="4"]']],
            // YouTube and Vimeo videos are embedded by their players
            ['html', ['text' => '<iframe src="https://www.youtube-nocookie.com/embed/abcdefghijk" loading="lazy" allow="fullscreen; picture-in-picture" allowfullscreen></iframe>']],
            ['text', ['text' => "[https://example.com/post?a=1&b=2](https://example.com/post?a=1&b=2)"]],
            ['video', ['file' => Elements::file( 'file:v.mp4' )]],
            ['audio', ['file' => Elements::file( 'file:a.mp3' )]],
            ['html', ['text' => '<iframe src="https://player.vimeo.com/video/42" loading="lazy" allow="fullscreen; picture-in-picture" allowfullscreen></iframe>']],
            // shortcodes of plugins are kept as text
            ['text', ['text' => '[unknown x="1"]']],
        ], $data( $result ) );
        $this->assertSame( ['img:a.png', 'img:b.png', 'img:c.png', 'id:1', 'id:2', 'id:3', 'file:v.mp4', 'file:a.mp3'], $result['fileIds'] );
        $this->assertSame( ['  Shortcodes kept as text: unknown', '  Gallery shortcode without images skipped'], $warnings );

        // shortcode blocks and classic content between Gutenberg blocks
        $result = $wp->elements( '<!-- wp:paragraph --><p>A</p><!-- /wp:paragraph --><!-- wp:shortcode -->[gallery ids="5,6"]<!-- /wp:shortcode -->'
            . '<p>Classic [audio mp3="b.mp3"]</p><!-- wp:nextpage --><!--nextpage--><!-- /wp:nextpage -->' );

        $this->assertSame( [
            ['text', ['text' => 'A']],
            ['slideshow', ['title' => '', 'files' => [Elements::file( 'id:5' ), Elements::file( 'id:6' )]]],
            ['text', ['text' => 'Classic']],
            ['audio', ['file' => Elements::file( 'file:b.mp3' )]],
        ], $data( $result ) );
    }


    public function testShortcodeHelpers(): void
    {
        $this->assertSame( ['ids' => '1,2', 'columns' => '3', 'link' => 'file', 'size' => "a\tb"], WordPress\Shortcode::attributes( " IDS=\"1,2\" columns=3 link='file' positional size=\"a\\tb\"" ) );
        $this->assertSame( [1, 2, 7], WordPress\Shortcode::galleryIds( 'A [gallery ids="1, 2,x,0"] [[gallery ids="5"]] [gallery include="7"] [gallery]' ) );
        $this->assertSame( [1, 2], WordPress\Shortcode::ids( ['ids' => '1,2', 'include' => '3'] ) );
        $this->assertSame( 'A  B [gallery] [x]', WordPress\Shortcode::strip( 'A [caption]<img src="a.png"> Cap[/caption] B [[gallery]] [x]' ) );

        // the excerpt doesn't contain the shortcodes like the excerpts of WordPress
        $this->assertSame( 'Intro text', WordPress\Article::intro( 'Intro [caption]<img src="a.png"> Caption[/caption]text' ) );
    }


    public function testMediaImportsOriginalImagesOfAttachments(): void
    {
        $attachments = [
            1 => ['guid' => 'https://wp.example/wp-content/uploads/2026/photo.jpg', 'title' => 'Photo', 'mime' => 'image/jpeg'],
            2 => ['guid' => 'https://wp.example/wp-content/uploads/2025/photo.jpg', 'title' => 'Old photo', 'mime' => 'image/jpeg'],
            3 => ['guid' => 'https://wp.example/wp-content/uploads/2026/big.jpg', 'title' => 'Big', 'mime' => 'image/jpeg'],
        ];
        $requested = [];
        $media = new WordPress\Media( [], fn( string $mime, string $name, string $path ) => "{$name}|{$path}", ['photo.jpg' => 2, 'big.jpg' => 3],
            function( array $ids ) use ( $attachments, &$requested ) {
                $requested[] = $ids;
                return array_intersect_key( $attachments, array_flip( $ids ) );
            } );

        // the attachment IDs of the image classes and gallery shortcodes are fetched at once
        $media->prefetch( [], [5 => '<img class="alignnone size-medium wp-image-1" src="https://wp.example/wp-content/uploads/2026/photo-300x200.jpg"> [gallery ids="3"]'] );
        $this->assertSame( [[1, 3, 2]], $requested );

        // the attachment of the class is used instead of the first attachment with the same basename
        $this->assertSame( 'Photo|https://wp.example/wp-content/uploads/2026/photo.jpg',
            $media->image( '<img class="wp-image-1" src="https://wp.example/wp-content/uploads/2026/photo-300x200.jpg">' ) );
        // tags without source are imported by the attachment ID
        $this->assertSame( 'Big|https://wp.example/wp-content/uploads/2026/big.jpg', $media->image( '<img class="wp-image-3" alt="" />' ) );
        $this->assertNull( $media->image( '<img class="wp-image-9" alt="" />' ) );
        // resized and scaled copies are the original image of the attachment
        $this->assertSame( 'Big|https://wp.example/wp-content/uploads/2026/big.jpg', $media->image( '<img src="https://wp.example/wp-content/uploads/2026/big-scaled.jpg?ver=2">' ) );
        $this->assertSame( 'Big|https://wp.example/wp-content/uploads/2026/big.jpg', $media->image( '<img src="https://wp.example/wp-content/uploads/2026/big-1024x768.jpg">' ) );
        // IDs of other attachments in the class of copied content aren't used
        $this->assertSame( 'Old photo|https://wp.example/wp-content/uploads/2025/photo.jpg',
            $media->image( '<img class="wp-image-3" src="https://wp.example/wp-content/uploads/2025/photo-150x150.jpg">' ) );
        // images without attachment are imported from their source
        $this->assertSame( 'other-300x200.jpg|https://wp.example/wp-content/uploads/other-300x200.jpg',
            $media->image( '<img class="wp-image-x" src="https://wp.example/wp-content/uploads/other-300x200.jpg">' ) );
    }


    public function testConvertsBlockEditorContent(): void
    {
        $warnings = [];
        $wp = new WordPress\Html(
            fn( string $tag ) => preg_match( '/src="([^"]+)"/', $tag, $m ) ? 'img:' . $m[1] : null,
            fn( string $url ) => str_contains( $url, 'youtube' ) || str_ends_with( $url, '.mp4' ) ? 'file:' . $url : null,
            function( string $msg ) use ( &$warnings ) { $warnings[] = $msg; }
        );
        $data = fn( array $result ) => array_map( fn( $el ) => [$el['type'], $el['data']], $result['elements'] );

        $result = $wp->elements( '<!-- wp:paragraph --><p>Text<sup data-fn="a1" class="fn"><a href="#a1" id="a1-link">1</a></sup> and'
            . '<sup data-fn="b2" class="fn"><a href="#b2" id="b2-link">2</a></sup></p><!-- /wp:paragraph -->'
            . '<!-- wp:more {"customText":"Read on"} --><!--more Read on--><!-- /wp:more -->'
            . '<!-- wp:list --><ul><!-- wp:list-item --><li>One<!-- wp:list --><ul><!-- wp:list-item --><li>Sub</li><!-- /wp:list-item --></ul><!-- /wp:list --></li><!-- /wp:list-item -->'
            . '<!-- wp:list-item --><li>Two</li><!-- /wp:list-item --></ul><!-- /wp:list -->'
            . '<!-- wp:table --><figure class="wp-block-table"><table><tbody><tr><td>A</td></tr></tbody></table><figcaption class="wp-element-caption">Prices &amp; more</figcaption></figure><!-- /wp:table -->'
            . '<!-- wp:details --><details class="wp-block-details"><summary>Question</summary><!-- wp:paragraph --><p>Answer</p><!-- /wp:paragraph --></details><!-- /wp:details -->'
            . '<!-- wp:preformatted --><pre class="wp-block-preformatted">Line <strong>bold</strong></pre><!-- /wp:preformatted -->'
            . "<!-- wp:preformatted --><pre class=\"wp-block-preformatted\">  a<br>    b</pre><!-- /wp:preformatted -->"
            . '<!-- wp:video --><figure class="wp-block-video"><video controls src="https://wp.example/v.mp4"></video><figcaption class="wp-element-caption">Clip</figcaption></figure><!-- /wp:video -->'
            . '<!-- wp:embed {"url":"https://social.example/a/1","providerNameSlug":"social"} --><figure class="wp-block-embed"><div class="wp-block-embed__wrapper">' . "\n"
            . 'https://social.example/a/1' . "\n" . '</div></figure><!-- /wp:embed -->'
            . '<!-- wp:block {"ref":99} /-->'
            . '<!-- wp:my_plugin/notice /--><!-- wp:footnotes /-->',
            ['b2' => 'Second <em>note</em>', 'a1' => 'First', 'c3' => 'Unused'] );

        $this->assertSame( [
            // footnote references are numbered in the order of their use
            ['text', ['text' => "Text[1] and[2]\n\n- One\n  - Sub\n- Two"]],
            ['table', ['title' => 'Prices & more', 'header' => '', 'table' => [['A']]]],
            ['text', ['text' => "**Question**\n\nAnswer"]],
            // preformatted texts with formatting are kept as HTML, plain ones keep their spacing
            ['html', ['text' => '<pre class="wp-block-preformatted">Line <strong>bold</strong></pre>']],
            ['code', ['language' => '', 'text' => "  a\n    b"]],
            ['video', ['file' => Elements::file( 'file:https://wp.example/v.mp4' )]],
            ['text', ['text' => "Clip\n\n[https://social.example/a/1](https://social.example/a/1)\n\n1. First\n2. Second *note*\n3. Unused"]],
        ], $data( $result ) );
        // self-closing blocks other than footnotes are rendered dynamically by WordPress
        $this->assertSame( ['  Reusable block 99 not found, unpublished or password protected, skipped', '  Dynamic block my_plugin/notice skipped'], $warnings );

        // query loops and comments blocks show posts and comments dynamically, also inside groups, so their content is skipped
        $warnings = [];
        $result = $wp->elements( '<!-- wp:group --><div class="wp-block-group"><!-- wp:query {"queryId":1} --><div class="wp-block-query"><!-- wp:post-template -->'
            . '<!-- wp:heading --><h2 class="wp-block-heading">Static</h2><!-- /wp:heading --><!-- wp:post-title /--><!-- /wp:post-template -->'
            . '<!-- wp:query-no-results --><!-- wp:paragraph --><p>None</p><!-- /wp:paragraph --><!-- /wp:query-no-results --></div><!-- /wp:query --></div><!-- /wp:group -->'
            . '<!-- wp:comments --><div class="wp-block-comments"><!-- wp:comments-title /--><!-- wp:comment-template --><!-- wp:comment-content /--><!-- /wp:comment-template --></div><!-- /wp:comments -->'
            . '<!-- wp:paragraph --><p>Kept</p><!-- /wp:paragraph -->' );
        $this->assertSame( [['text', ['text' => 'Kept']]], $data( $result ) );
        $this->assertSame( ['  Dynamic block query skipped', '  Dynamic block comments skipped'], $warnings );

        // the inner blocks of details are converted like the other blocks, classic details aren't split at blank lines
        $warnings = [];
        $result = $wp->elements( '<!-- wp:details --><details class="wp-block-details"><summary>Sum</summary><!-- wp:paragraph --><p>Hidden</p><!-- /wp:paragraph -->'
            . '<!-- wp:image {"id":3} --><figure class="wp-block-image"><img src="a.jpg"/></figure><!-- /wp:image -->'
            . '<!-- wp:embed {"url":"https://vimeo.com/123"} --><figure class="wp-block-embed"><div class="wp-block-embed__wrapper">' . "\n"
            . 'https://vimeo.com/123' . "\n" . '</div></figure><!-- /wp:embed --><!-- wp:latest-posts /--></details><!-- /wp:details -->' );
        $this->assertSame( ['text', 'image', 'html'], array_column( $result['elements'], 'type' ) );
        $this->assertStringContainsString( 'src="https://player.vimeo.com/video/123"', $result['elements'][2]['data']['text'] );
        $this->assertSame( "**Sum**\n\nHidden", $result['elements'][0]['data']['text'] );
        $this->assertSame( ['  Dynamic block latest-posts skipped'], $warnings );
        $result = $wp->elements( "<details><summary>Classic</summary>\nHidden text\nline 2\n\nMore</details>\n\nAfter" );
        $this->assertSame( [['text', ['text' => "**Classic**\n\nHidden text\\\nline 2\n\nMore\n\nAfter"]]], $data( $result ) );

        // tab panels become their bold label from the panel or the tab list followed by their inner blocks, terms queries are dynamic
        $warnings = [];
        $result = $wp->elements( '<!-- wp:image {"id":3,"align":"left"} --><figure class="wp-block-image alignleft"><img src="x.jpg"/></figure><!-- /wp:image -->'
            . '<!-- wp:tabs --><div class="wp-block-tabs"><!-- wp:tab-list --><div class="wp-block-tab-list" role="tablist"><button type="button" role="tab">First</button>'
            . '<button type="button" role="tab">Second &amp; <em>more</em></button></div><!-- /wp:tab-list --><!-- wp:tab-panels --><div class="wp-block-tab-panels">'
            . '<!-- wp:tab-panel {"label":"One"} --><section class="wp-block-tab-panel" role="tabpanel" tabindex="0"><!-- wp:paragraph --><p>One text</p><!-- /wp:paragraph -->'
            . '<!-- wp:image {"id":4} --><figure class="wp-block-image"><img src="a.jpg"/></figure><!-- /wp:image --></section><!-- /wp:tab-panel -->'
            . '<!-- wp:tab-panel --><section class="wp-block-tab-panel" role="tabpanel" tabindex="0"><!-- wp:paragraph --><p>Two text</p><!-- /wp:paragraph -->'
            . '</section><!-- /wp:tab-panel --></div><!-- /wp:tab-panels --></div><!-- /wp:tabs -->'
            . '<!-- wp:terms-query --><div class="wp-block-terms-query"><!-- wp:term-template --><!-- wp:term-name /--><!-- /wp:term-template --></div><!-- /wp:terms-query -->' );
        $this->assertSame( [
            ['image', ['file' => Elements::file( 'img:x.jpg' )]],
            ['text', ['text' => "**One**\n\nOne text"]],
            ['image', ['file' => Elements::file( 'img:a.jpg' )]],
            ['text', ['text' => "**Second & *more***\n\nTwo text"]],
        ], $data( $result ) );
        $this->assertSame( ['  Dynamic block terms-query skipped'], $warnings );

        // playlist tracks with source become audio elements named by title and artist, followed by the caption
        $names = [];
        $playlist = new WordPress\Html( fn( string $tag ) => null, function( string $url, string $name = '' ) use ( &$names ) {
            $names[] = $name;
            return 'file:' . $url;
        } );
        $result = $playlist->elements( '<!-- wp:playlist --><figure class="wp-block-playlist"><ol class="wp-block-playlist__tracklist">'
            . '<!-- wp:playlist-track {"id":5,"src":"https://wp.example/a.mp3","title":"Song","artist":"Me","length":"3:20"} /-->'
            . '<!-- wp:playlist-track {"id":7,"title":"No source"} /--><!-- wp:playlist-track {"id":6,"src":"https://wp.example/b.mp3"} /-->'
            . '</ol><figcaption class="wp-element-caption">My list</figcaption></figure><!-- /wp:playlist -->' );
        $this->assertSame( [
            ['audio', ['file' => Elements::file( 'file:https://wp.example/a.mp3' )]],
            ['audio', ['file' => Elements::file( 'file:https://wp.example/b.mp3' )]],
            ['text', ['text' => 'My list']],
        ], $data( $result ) );
        $this->assertSame( ['Song - Me', ''], $names );

        // layout blocks of plugins only wrap other blocks which keep their types
        $result = $wp->elements( '<!-- wp:kadence/rowlayout {"uniqueID":"1"} --><div class="kb-row-layout-wrap"><div class="kt-row-column-wrap"><!-- wp:kadence/column -->'
            . '<div class="wp-block-kadence-column"><div class="kt-inside-inner-col"><!-- wp:heading --><h2 class="wp-block-heading">H</h2><!-- /wp:heading -->'
            . '<!-- wp:paragraph --><p>Text</p><!-- /wp:paragraph --><!-- wp:image {"id":3} --><figure class="wp-block-image"><img src="a.jpg"/></figure><!-- /wp:image -->'
            . '</div></div><!-- /wp:kadence/column --></div></div><!-- /wp:kadence/rowlayout -->' );
        $this->assertSame( [
            ['heading', ['level' => 2, 'title' => 'H']],
            ['text', ['text' => 'Text']],
            ['image', ['file' => Elements::file( 'img:a.jpg' )]],
        ], $data( $result ) );

        // accordions with other answers than text become the bold titles without icon followed by their panels
        $item = '<!-- wp:accordion-item --><div class="wp-block-accordion-item"><!-- wp:accordion-heading --><h3 class="wp-block-accordion-heading">'
            . '<button class="wp-block-accordion-heading__toggle"><span class="wp-block-accordion-heading__toggle-title">Q &amp; %d</span>'
            . '<span class="wp-block-accordion-heading__toggle-icon" aria-hidden="true">+</span></button></h3><!-- /wp:accordion-heading -->'
            . '<!-- wp:accordion-panel --><div role="region" class="wp-block-accordion-panel"><!-- wp:paragraph --><p>A%1$d</p><!-- /wp:paragraph -->%s</div>'
            . '<!-- /wp:accordion-panel --></div><!-- /wp:accordion-item -->';
        $result = $wp->elements( '<!-- wp:accordion --><div class="wp-block-accordion">' . sprintf( $item, 1, '' )
            . sprintf( $item, 2, '<!-- wp:image {"id":3} --><figure class="wp-block-image"><img src="a.jpg"/></figure><!-- /wp:image -->' ) . '</div><!-- /wp:accordion -->' );
        $this->assertSame( [
            ['text', ['text' => "**Q & 1**\n\nA1\n\n**Q & 2**\n\nA2"]],
            ['image', ['file' => Elements::file( 'img:a.jpg' )]],
        ], $data( $result ) );

        // the columns of deprecated text columns blocks contain HTML only
        $result = $wp->elements( '<!-- wp:text-columns {"columns":2} --><div class="wp-block-text-columns alignundefined columns-2"><div class="wp-block-column">'
            . '<p>Left <strong>b</strong></p></div><div class="wp-block-column"><p>Right</p></div></div><!-- /wp:text-columns -->' );
        $this->assertSame( [['text', ['text' => "Left **b**\n\nRight"]]], $data( $result ) );

        // block names with the "core/" namespace are the same blocks
        $result = $wp->elements( '<!-- wp:core/quote --><blockquote class="wp-block-quote"><!-- wp:core/paragraph --><p>Quoted</p><!-- /wp:core/paragraph -->'
            . '<cite>Me</cite></blockquote><!-- /wp:core/quote --><!-- wp:core/image {"id":3,"align":"left"} --><figure class="wp-block-image alignleft"><img src="x.jpg"/>'
            . '</figure><!-- /wp:core/image --><!-- wp:core/paragraph --><p>Next</p><!-- /wp:core/paragraph -->' );
        $this->assertSame( [
            ['text', ['text' => "> Quoted\n>\n> — Me"]],
            ['image-text', ['text' => 'Next', 'file' => Elements::file( 'img:x.jpg' ), 'position' => 'start']],
        ], $data( $result ) );

        // reversed lists and citations next to images are converted like the other texts
        $reversed = '<!-- wp:list {"ordered":true,"reversed":true} --><ol reversed class="wp-block-list"><!-- wp:list-item --><li>%sA</li><!-- /wp:list-item -->'
            . '<!-- wp:list-item --><li>B</li><!-- /wp:list-item --></ol><!-- /wp:list -->';
        $result = $wp->elements( '<!-- wp:media-text --><div class="wp-block-media-text"><figure class="wp-block-media-text__media"><img src="m.jpg" alt=""/></figure>'
            . '<div class="wp-block-media-text__content">' . sprintf( $reversed, '' ) . '</div></div><!-- /wp:media-text -->'
            . sprintf( $reversed, '<img src="i.png" alt="" width="16" height="16"/> ' )
            . '<!-- wp:quote --><blockquote class="wp-block-quote"><!-- wp:paragraph --><p>Q</p><!-- /wp:paragraph --><!-- wp:image {"id":3} --><figure class="wp-block-image">'
            . '<img src="q.jpg" alt=""/></figure><!-- /wp:image --><cite>Cit</cite></blockquote><!-- /wp:quote -->' );
        $this->assertSame( [
            ['image', ['file' => Elements::file( 'img:m.jpg' )]],
            ['html', ['text' => '<ol class="wp-block-list"><li value="2">A</li><li value="1">B</li></ol>'
                . '<ol class="wp-block-list"><li value="2"><img src="i.png" alt="" width="16" height="16" /> A</li><li value="1">B</li></ol>']],
            ['image-text', ['text' => "> Q\n>\n> — Cit", 'file' => Elements::file( 'img:q.jpg' )]],
        ], $data( $result ) );

        // footnotes are added at the end if there's no footnotes block
        $result = $wp->elements( '<!-- wp:paragraph --><p>A<sup data-fn="x" class="fn"><a href="#x" id="x-link">1</a></sup></p><!-- /wp:paragraph -->', ['x' => 'Note'] );
        $this->assertSame( [['text', ['text' => "A[1]\n\n1. Note"]]], $data( $result ) );

        // URLs on their own line in classic content are embedded
        $result = $wp->elements( "Intro<!--more-->\n\nhttps://www.youtube.com/watch?v=abcdefghijk\n\n<p>https://example.com/page</p>\n\nEnd" );
        $this->assertSame( [
            ['text', ['text' => 'Intro']],
            ['html', ['text' => '<iframe src="https://www.youtube-nocookie.com/embed/abcdefghijk" loading="lazy" allow="fullscreen; picture-in-picture" allowfullscreen></iframe>']],
            ['text', ['text' => "[https://example.com/page](https://example.com/page)\n\nEnd"]],
        ], $data( $result ) );

        // URLs in their own paragraph and shortcodes in paragraph blocks are embedded too
        $result = $wp->elements( '<p>Text</p><p>https://www.youtube.com/watch?v=defghijklmn</p>'
            . '<!-- wp:paragraph --><p>https://www.youtube.com/watch?v=ghijklmnopq</p><!-- /wp:paragraph -->'
            . '<!-- wp:paragraph --><p>[video src="https://wp.example/b.mp4"]</p><!-- /wp:paragraph -->' );
        $this->assertSame( [
            ['text', ['text' => 'Text']],
            // adjacent HTML elements are merged
            ['html', ['text' => '<iframe src="https://www.youtube-nocookie.com/embed/defghijklmn" loading="lazy" allow="fullscreen; picture-in-picture" allowfullscreen></iframe><iframe src="https://www.youtube-nocookie.com/embed/ghijklmnopq" loading="lazy" allow="fullscreen; picture-in-picture" allowfullscreen></iframe>']],
            ['video', ['file' => Elements::file( 'file:https://wp.example/b.mp4' )]],
        ], $data( $result ) );

        // covers without image and media-text blocks with video keep their inner blocks, media on the right is at the end
        $result = $wp->elements( '<!-- wp:cover {"overlayColor":"black"} --><div class="wp-block-cover"><span aria-hidden="true" class="wp-block-cover__background"></span>'
            . '<div class="wp-block-cover__inner-container"><!-- wp:heading --><h2 class="wp-block-heading">Big</h2><!-- /wp:heading -->'
            . '<!-- wp:paragraph --><p>Cover</p><!-- /wp:paragraph --></div></div><!-- /wp:cover -->'
            . '<!-- wp:media-text {"mediaType":"video"} --><div class="wp-block-media-text"><figure class="wp-block-media-text__media"><video controls src="https://wp.example/c.mp4"></video></figure>'
            . '<div class="wp-block-media-text__content"><!-- wp:paragraph --><p>Clip text</p><!-- /wp:paragraph --></div></div><!-- /wp:media-text -->'
            . '<!-- wp:media-text {"mediaPosition":"right"} --><div class="wp-block-media-text has-media-on-the-right"><div class="wp-block-media-text__content">'
            . '<!-- wp:paragraph --><p>Side</p><!-- /wp:paragraph --></div><figure class="wp-block-media-text__media"><img src="https://wp.example/m.jpg"/></figure></div><!-- /wp:media-text -->'
            . '<!-- wp:cover --><div class="wp-block-cover" style="background-image:url(https://wp.example/old.jpg)"><div class="wp-block-cover__inner-container">'
            . '<!-- wp:paragraph --><p>Old cover</p><!-- /wp:paragraph --></div></div><!-- /wp:cover -->' );
        $this->assertSame( [
            ['heading', ['level' => 2, 'title' => 'Big']],
            ['text', ['text' => 'Cover']],
            ['video', ['file' => Elements::file( 'file:https://wp.example/c.mp4' )]],
            ['text', ['text' => 'Clip text']],
            ['image-text', ['text' => 'Side', 'file' => Elements::file( 'img:https://wp.example/m.jpg' ), 'position' => 'end']],
            ['image-text', ['text' => 'Old cover', 'file' => Elements::file( 'img:https://wp.example/old.jpg' )]],
        ], $data( $result ) );

        // like WordPress, buttons without content are left out
        $this->assertSame( ['elements' => [], 'fileIds' => []], $wp->elements( '<!-- wp:buttons --><div class="wp-block-buttons"><!-- wp:button --><div class="wp-block-button"><a class="wp-block-button__link" href="https://x.org/empty"><!-- note --></a></div><!-- /wp:button --></div><!-- /wp:buttons -->' ) );

        // buttons become links, custom image links are kept, table cells are decoded and footers kept, reversed lists count down, empty buttons are left out
        $result = $wp->elements( '<!-- wp:buttons --><div class="wp-block-buttons"><!-- wp:button --><div class="wp-block-button"><a class="wp-block-button__link" href="https://x.org/signup">Sign up</a></div><!-- /wp:button -->'
            . '<!-- wp:button --><div class="wp-block-button"><a class="wp-block-button__link" href="https://x.org/empty"></a></div><!-- /wp:button -->'
            . '<!-- wp:button --><div class="wp-block-button"><a class="wp-block-button__link" href="https://x.org/more">More</a></div><!-- /wp:button --></div><!-- /wp:buttons -->'
            . '<!-- wp:image {"linkDestination":"custom"} --><figure class="wp-block-image"><a href="https://shop.example/p"><img src="https://wp.example/a.jpg" alt="Shoe"/></a><figcaption>Buy</figcaption></figure><!-- /wp:image -->'
            . '<!-- wp:image {"linkDestination":"custom"} --><figure class="wp-block-image"><a href="https://shop.example/q"><img src="https://wp.example/b.jpg" alt="Hat"/></a></figure><!-- /wp:image -->'
            . '<!-- wp:image {"linkDestination":"media"} --><figure class="wp-block-image"><a href="https://wp.example/c.jpg"><img src="https://wp.example/c.jpg" alt=""/></a></figure><!-- /wp:image -->'
            . '<!-- wp:table --><figure class="wp-block-table"><table><thead><tr><th>Name &amp; Co</th></tr></thead><tbody><tr><td>A &lt;b&gt;</td></tr></tbody>'
            . '<tfoot><tr><td>Total</td></tr></tfoot></table></figure><!-- /wp:table -->'
            . '<!-- wp:list {"ordered":true,"reversed":true} --><ol reversed><!-- wp:list-item --><li>A</li><!-- /wp:list-item --><!-- wp:list-item --><li>B</li><!-- /wp:list-item --></ol><!-- /wp:list -->' );
        $this->assertSame( [
            ['text', ['text' => "[Sign up](https://x.org/signup)\n\n[More](https://x.org/more)"]],
            ['image-text', ['text' => '[Buy](https://shop.example/p)', 'file' => Elements::file( 'img:https://wp.example/a.jpg' )]],
            ['image', ['file' => Elements::file( 'img:https://wp.example/b.jpg' )]],
            ['text', ['text' => '[Hat](https://shop.example/q)']],
            ['image', ['file' => Elements::file( 'img:https://wp.example/c.jpg' )]],
            ['table', ['title' => '', 'header' => 'row', 'table' => [['Name & Co'], ['A \\<b>'], ['Total']]]],
            ['html', ['text' => '<ol><li value="2">A</li><li value="1">B</li></ol>']],
        ], $data( $result ) );

        // links to the anchors of blocks refer to the IDs of their elements, which aren't merged into the previous ones
        $result = $wp->elements( '<!-- wp:paragraph --><p><a href="#intro">Intro</a> <a href="#b">B</a></p><!-- /wp:paragraph -->'
            . '<!-- wp:heading {"anchor":"intro"} --><h2 class="wp-block-heading" id="intro">Intro</h2><!-- /wp:heading -->'
            . '<!-- wp:paragraph --><p>A</p><!-- /wp:paragraph --><!-- wp:paragraph {"anchor":"b"} --><p id="b">B</p><!-- /wp:paragraph -->' );
        $ids = array_column( $result['elements'], 'id' );
        $this->assertSame( "[Intro](#{$ids[1]}) [B](#{$ids[3]})", $result['elements'][0]['data']['text'] );
        $this->assertSame( ['A', 'B'], [$result['elements'][2]['data']['text'], $result['elements'][3]['data']['text']] );

        // with the post ID, the elements with anchors have the same IDs in each import, which links from other posts use
        $result = $wp->elements( '<!-- wp:paragraph --><p><a href="#intro">Intro</a></p><!-- /wp:paragraph -->'
            . '<!-- wp:paragraph --><p>A</p><!-- /wp:paragraph -->'
            . '<!-- wp:heading {"anchor":"intro"} --><h2 class="wp-block-heading" id="intro">Intro</h2><!-- /wp:heading -->'
            . '<!-- wp:paragraph --><p>B</p><!-- /wp:paragraph --><!-- wp:heading --><h2 id="intro">Again</h2><!-- /wp:heading -->', [], 5 );
        $id = WordPress\Html::anchorId( 5, 'intro' );
        $this->assertMatchesRegularExpression( '/^[A-Za-z][A-Za-z0-9_-]{5}$/', $id );
        $this->assertNotSame( $id, WordPress\Html::anchorId( 6, 'intro' ) );
        $this->assertSame( "[Intro](#{$id})\n\nA", $result['elements'][0]['data']['text'] );
        // only the first block with the same anchor gets its ID
        $this->assertSame( [$id, false], [$result['elements'][1]['id'], in_array( $id, array_column( array_slice( $result['elements'], 2 ), 'id' ), true )] );

        // the "more" tag with link text separates the introduction
        $this->assertSame( 'Teaser', WordPress\Article::intro( '<p>Teaser</p><!--more Read on--><p>Rest</p>' ) );
        // blocks which aren't text and footnote references aren't part of the introduction like in WordPress excerpts
        $this->assertSame( 'Hello world Item', WordPress\Article::intro( '<!-- wp:embed {"url":"https://youtu.be/x"} --><figure>https://youtu.be/x</figure><!-- /wp:embed -->'
            . '<!-- wp:paragraph --><p>Hello<sup data-fn="a" class="fn"><a href="#a" id="a-link">1</a></sup> world</p><!-- /wp:paragraph -->'
            . '<!-- wp:image {"id":3} --><figure><img src="x"><figcaption>Caption</figcaption></figure><!-- /wp:image -->'
            . '<!-- wp:list --><ul><!-- wp:list-item --><li>Item</li><!-- /wp:list-item --></ul><!-- /wp:list --><!-- wp:spacer /-->'
            . '<!-- wp:code --><pre>code</pre><!-- /wp:code -->' ) );
        $this->assertSame( 'A link & text', WordPress\Article::excerpt( "<p>A <a href=\"x\">link</a> &amp;\n text</p>" ) );
    }


    public function testMarkdownKeepsNestedLists(): void
    {
        $this->assertSame( "- One\n- Two\n  - Sub\n  - Sub 2\n    1. Deep\n- Three",
            \Aimeos\Cms\Import\Markdown::fromHtml( '<ul><li>One</li><li>Two<ul><li>Sub</li><li>Sub 2<ol><li>Deep</li></ol></li></ul></li><li>Three</li></ul>' ) );
        $this->assertSame( "- One\n- Two\n  - Sub",
            \Aimeos\Cms\Import\Markdown::fromHtml( "<ul>\n  <li>One</li>\n  <li>Two\n    <ul>\n      <li>Sub</li>\n    </ul>\n  </li>\n</ul>" ) );
        $this->assertSame( "Text\n\n3. **A**\n4. [B](x)\n\nAfter",
            \Aimeos\Cms\Import\Markdown::fromHtml( '<p>Text</p><ol start="3"><li><strong>A</strong></li><li><a href="x">B</a></li></ol><p>After</p>' ) );
    }


    public function testReaderResolvesBlocksFootnotesAndAttachedFiles(): void
    {
        $db = DB::connection( 'wordpress' );
        $post = ['post_status' => 'publish', 'post_date' => '2026-01-01 00:00:00', 'post_excerpt' => '', 'post_mime_type' => '', 'guid' => ''];
        $image = ['post_type' => 'attachment', 'post_status' => 'inherit', 'post_date' => '2026-01-01 00:00:00', 'post_excerpt' => '', 'post_content' => '', 'post_mime_type' => 'image/jpeg'];
        $db->table( 'wp_posts' )->insert( [
            ['ID' => 10, 'post_type' => 'wp_block', 'post_name' => 'outer', 'post_title' => 'Outer', 'post_content' => '<p>Outer</p><!-- wp:block {"ref":11} /-->'] + $post,
            ['ID' => 11, 'post_type' => 'wp_block', 'post_name' => 'inner', 'post_title' => 'Inner', 'post_content' => '<p>Inner</p>'] + $post,
            ['ID' => 12, 'post_type' => 'wp_block', 'post_name' => 'draft', 'post_title' => 'Draft', 'post_content' => '<p>Draft</p>', 'post_status' => 'draft'] + $post,
            ['ID' => 20, 'post_name' => 'same', 'post_title' => 'Same', 'guid' => 'https://wp.example/wp-content/uploads/2026/01/a.jpg'] + $image,
            ['ID' => 21, 'post_name' => 'scaled', 'post_title' => 'Scaled', 'guid' => 'https://old.example/wp-content/uploads/sites/2/2026/01/big.jpg'] + $image,
            ['ID' => 22, 'post_name' => 'other', 'post_title' => 'Other', 'guid' => 'https://wp.example/?attachment_id=22'] + $image,
            ['ID' => 23, 'post_name' => 'none', 'post_title' => 'None', 'guid' => 'https://wp.example/c.jpg'] + $image,
        ] );
        $db->table( 'wp_postmeta' )->insert( [
            ['post_id' => 1, 'meta_key' => 'footnotes', 'meta_value' => '[{"content":"Note <em>A</em>","id":"a"},{"id":"b"},"x"]'],
            ['post_id' => 2, 'meta_key' => 'footnotes', 'meta_value' => '[{"content":"Unused","id":"c"}]'],
            ['post_id' => 20, 'meta_key' => '_wp_attached_file', 'meta_value' => '2026/01/a.jpg'],
            ['post_id' => 21, 'meta_key' => '_wp_attached_file', 'meta_value' => '2026/01/big-scaled.jpg'],
            ['post_id' => 22, 'meta_key' => '_wp_attached_file', 'meta_value' => '2026/02/b.jpg'],
        ] );
        $reader = new WordPress\Reader( $db );

        // nested reusable blocks are replaced, missing and unpublished ones are kept
        $this->assertSame( [1 => '<p>A</p><p>Outer</p><p>Inner</p><!-- wp:block {"ref":12} /--><!-- wp:block {"ref":13} /-->', 2 => '<p>B</p>'],
            $reader->reusable( [1 => '<p>A</p><!-- wp:block {"ref":10} /--><!-- wp:block {"ref":12} /--><!-- wp:block {"ref":13} /-->', 2 => '<p>B</p>'] ) );
        // the values of synced pattern overrides replace the texts, links and images of the named blocks, only for the attributes bound to the overrides
        $db->table( 'wp_posts' )->insert( ['ID' => 13, 'post_type' => 'wp_block', 'post_name' => 'synced', 'post_title' => 'Synced', 'post_content' =>
            '<!-- wp:heading {"metadata":{"name":"Title","bindings":{"__default":{"source":"core/pattern-overrides"}}}} --><h2 class="wp-block-heading">Default</h2><!-- /wp:heading -->'
            . '<!-- wp:button {"metadata":{"name":"Action","bindings":{"text":{"source":"core/pattern-overrides"}}}} --><div class="wp-block-button"><a class="wp-block-button__link">Go</a></div><!-- /wp:button -->'
            . '<!-- wp:image {"id":5,"metadata":{"name":"Photo","bindings":{"__default":{"source":"core/pattern-overrides"}}}} --><figure class="wp-block-image"><img src="https://wp.example/a.jpg" alt="" class="wp-image-5"/></figure><!-- /wp:image -->'
            . '<!-- wp:paragraph {"metadata":{"name":"Fixed"}} --><p>Fixed</p><!-- /wp:paragraph -->'] + $post );
        $this->assertSame( [1 => '<!-- wp:heading {"metadata":{"name":"Title","bindings":{"__default":{"source":"core/pattern-overrides"}}}} --><h2 class="wp-block-heading">Mine &amp; yours</h2><!-- /wp:heading -->'
            . '<!-- wp:button {"metadata":{"name":"Action","bindings":{"text":{"source":"core/pattern-overrides"}}}} --><div class="wp-block-button"><a class="wp-block-button__link">Buy</a></div><!-- /wp:button -->'
            . '<!-- wp:image {"id":5,"metadata":{"name":"Photo","bindings":{"__default":{"source":"core/pattern-overrides"}}}} --><figure class="wp-block-image"><img src="https://wp.example/b.jpg" alt="B" class="wp-image-6"/></figure><!-- /wp:image -->'
            . '<!-- wp:paragraph {"metadata":{"name":"Fixed"}} --><p>Fixed</p><!-- /wp:paragraph -->'],
            $reader->reusable( [1 => '<!-- wp:block {"ref":13,"content":{"Title":{"content":"Mine &amp; yours"},"Action":{"text":"Buy","url":"https://x.org/?a=1&b=2"},'
                . '"Photo":{"values":{"url":"https://wp.example/b.jpg","alt":"B","id":6}},"Fixed":{"content":"Changed"}}} /-->'] ) );
        // overrides stored in the "overrides" attribute by older versions of the block editor are used too
        $this->assertSame( [1 => '<!-- wp:heading {"metadata":{"name":"Title","bindings":{"__default":{"source":"core/pattern-overrides"}}}} --><h2 class="wp-block-heading">Old</h2><!-- /wp:heading -->'],
            array_map( fn( $html ) => strstr( $html, '<!-- wp:button', true ), $reader->reusable( [1 => '<!-- wp:block {"ref":13,"overrides":{"Title":{"content":"Old"}}} /-->'] ) ) );

        // footnotes are only fetched for posts which use them
        $this->assertSame( [1 => ['a' => 'Note <em>A</em>']], $reader->footnotes( [1 => '<!-- wp:footnotes /-->', 2 => '<p>B</p>'] ) );

        $urls = fn( WordPress\Reader $reader ) => array_map( fn( $attachment ) => $attachment['url'], $reader->attachments( [20, 21, 22, 23, 24] ) );
        $db->table( 'wp_posts' )->insert( ['ID' => 24, 'post_name' => 'avif', 'post_title' => 'Avif', 'guid' => 'https://wp.example/wp-content/uploads/2026/03/d.avif', 'post_mime_type' => 'image/avif'] + $image );
        // without options, the upload URL of the guid is used and attachments without uploads URL keep their guid
        $this->assertSame( [
            20 => 'https://wp.example/wp-content/uploads/2026/01/a.jpg',
            21 => 'https://old.example/wp-content/uploads/sites/2/2026/01/big-scaled.jpg',
            22 => 'https://wp.example/?attachment_id=22',
            23 => 'https://wp.example/c.jpg',
            24 => 'https://wp.example/wp-content/uploads/2026/03/d.avif',
        ], $urls( $reader ) );

        // the uploads URL of the options is used instead of the guid which keeps the domain of the upload
        $db->getSchemaBuilder()->create( 'wp_options', function( Blueprint $table ) {
            $table->string( 'option_name' );
            $table->text( 'option_value' );
        } );
        $db->table( 'wp_options' )->insert( [['option_name' => 'siteurl', 'option_value' => 'https://wp.example/'], ['option_name' => 'upload_path', 'option_value' => '']] );
        $this->assertSame( [
            20 => 'https://wp.example/wp-content/uploads/2026/01/a.jpg',
            21 => 'https://wp.example/wp-content/uploads/2026/01/big-scaled.jpg',
            22 => 'https://wp.example/wp-content/uploads/2026/02/b.jpg',
            23 => 'https://wp.example/c.jpg',
            24 => 'https://wp.example/wp-content/uploads/2026/03/d.avif',
        ], $urls( new WordPress\Reader( $db ) ) );

        // absolute upload paths are only used if they are the default uploads directory
        $db->table( 'wp_options' )->where( 'option_name', 'upload_path' )->update( ['option_value' => '/var/www/html/wp-content/uploads'] );
        $this->assertSame( 'https://wp.example/wp-content/uploads/2026/02/b.jpg', $urls( new WordPress\Reader( $db ) )[22] );
        $db->table( 'wp_options' )->where( 'option_name', 'upload_path' )->update( ['option_value' => '/srv/media'] );
        $this->assertSame( 'https://wp.example/?attachment_id=22', $urls( new WordPress\Reader( $db ) )[22] );
        $db->table( 'wp_options' )->where( 'option_name', 'upload_path' )->update( ['option_value' => ''] );

        $db->table( 'wp_options' )->insert( ['option_name' => 'upload_url_path', 'option_value' => 'https://cdn.example/files/'] );
        $this->assertSame( 'https://cdn.example/files/2026/02/b.jpg', $urls( new WordPress\Reader( $db ) )[22] );

        // sites of a multisite network use their own uploads directory or the "files" URL of networks with ms-files rewriting
        $schema = $db->getSchemaBuilder();
        $schema->create( 'wp_3_options', function( Blueprint $table ) {
            $table->string( 'option_name' );
            $table->text( 'option_value' );
        } );
        $schema->create( 'wp_blogs', fn( Blueprint $table ) => $table->integer( 'blog_id' ) );
        $db->table( 'wp_3_options' )->insert( [['option_name' => 'siteurl', 'option_value' => 'https://sub.example'], ['option_name' => 'upload_path', 'option_value' => '']] );
        $db->table( 'wp_blogs' )->insert( [['blog_id' => 1], ['blog_id' => 3]] );
        $site = fn( string $prefix ) => new class( $db, $prefix ) extends WordPress\Reader {
            public function url( string $guid, string $file ): string { return $this->attachedUrl( $guid, $file ); }
        };
        $this->assertSame( 'https://sub.example/wp-content/uploads/sites/3/2026/01/a.jpg', $site( 'wp_3_' )->url( 'https://old.example/a.jpg', '2026/01/a.jpg' ) );
        $schema->create( 'wp_sitemeta', function( Blueprint $table ) {
            $table->string( 'meta_key' );
            $table->text( 'meta_value' );
        } );
        $db->table( 'wp_sitemeta' )->insert( ['meta_key' => 'ms_files_rewriting', 'meta_value' => '1'] );
        $this->assertSame( 'https://sub.example/files/2026/01/a.jpg', $site( 'wp_3_' )->url( 'https://old.example/a.jpg', '2026/01/a.jpg' ) );
        $db->table( 'wp_blogs' )->where( 'blog_id', 3 )->delete();
        $this->assertSame( 'https://sub.example/wp-content/uploads/2026/01/a.jpg', $site( 'wp_3_' )->url( 'https://old.example/a.jpg', '2026/01/a.jpg' ) );
    }


    public function testImportsReusableBlocksFootnotesAndEncodedSlugs(): void
    {
        $this->page( 'Root', '', 'new.example', 'root' );

        $db = DB::connection( 'wordpress' );
        $post = ['post_type' => 'post', 'post_status' => 'publish', 'post_date' => '2026-01-01 00:00:00', 'post_excerpt' => '', 'post_mime_type' => '', 'guid' => ''];
        $db->table( 'wp_posts' )->insert( [
            ['ID' => 1, 'post_name' => '%e4%bd%a0%e5%a5%bd', 'post_title' => 'Hello', 'post_excerpt' => '<p>Short <strong>excerpt</strong></p>',
                'post_content' => '<!-- wp:paragraph --><p>A<sup data-fn="n" class="fn"><a href="#n" id="n-link">1</a></sup></p><!-- /wp:paragraph -->'
                    . '<!-- wp:block {"ref":5} /--><!-- wp:footnotes /-->'] + $post,
            ['ID' => 5, 'post_type' => 'wp_block', 'post_name' => 'shared', 'post_title' => 'Shared',
                'post_content' => '<!-- wp:paragraph --><p>Shared</p><!-- /wp:paragraph -->'] + $post,
        ] );
        $db->table( 'wp_postmeta' )->insert( ['post_id' => 1, 'meta_key' => 'footnotes', 'meta_value' => '[{"content":"Note","id":"n"}]'] );

        $this->assertSame( 0, Artisan::call( 'cms:wp-import', ['--connection' => 'wordpress', '--domain' => 'new.example'] ) );

        // URL encoded slugs are decoded and reusable blocks aren't imported as posts
        $this->assertSame( 1, PageView::where( 'domain', 'new.example' )->where( 'type', 'blog' )->where( 'tag', 'article' )->count() );
        $content = collect( (array) PageView::where( 'domain', 'new.example' )->where( 'path', '你好' )->firstOrFail()->content );

        $this->assertSame( 'Short excerpt', $content->first( fn( $item ) => $item->type === 'article' )->data->text ?? null );
        $this->assertSame( "A[1]\n\nShared\n\n1. Note", $content->first( fn( $item ) => $item->type === 'text' )->data->text ?? null );
    }


    public function testImportsBlocksBoundToCustomFields(): void
    {
        $this->page( 'Root', '', 'new.example', 'root' );

        $bind = fn( string $attrs ) => '{"metadata":{"bindings":{' . $attrs . '}}}';
        $meta = fn( string $attr, string $key ) => '"' . $attr . '":{"source":"core/post-meta","args":{"key":"' . $key . '"}}';

        $db = DB::connection( 'wordpress' );
        $post = ['post_type' => 'post', 'post_status' => 'publish', 'post_date' => '2026-01-01 00:00:00', 'post_excerpt' => '', 'post_mime_type' => '', 'guid' => ''];
        $db->table( 'wp_posts' )->insert( [
            ['ID' => 1, 'post_name' => 'bound', 'post_title' => 'Bound',
                'post_content' => '<!-- wp:core/heading ' . $bind( $meta( 'content', 'headline' ) ) . ' --><h2 class="wp-block-heading">Old</h2><!-- /wp:core/heading -->'
                    . '<!-- wp:paragraph ' . $bind( $meta( 'content', 'price' ) ) . ' --><p>0 EUR</p><!-- /wp:paragraph -->'
                    . '<!-- wp:paragraph ' . $bind( $meta( 'content', '_secret' ) ) . ' --><p>Public</p><!-- /wp:paragraph -->'
                    . '<!-- wp:paragraph ' . $bind( $meta( 'content', 'missing' ) ) . ' --><p>Saved</p><!-- /wp:paragraph -->'
                    . '<!-- wp:paragraph ' . $bind( $meta( 'content', 'empty' ) ) . ' --><p>Emptied</p><!-- /wp:paragraph -->'
                    . '<!-- wp:paragraph ' . $bind( $meta( 'content', 'list' ) ) . ' --><p>Kept</p><!-- /wp:paragraph -->'
                    . '<!-- wp:core/block {"ref":5} /-->'
                    . '<!-- wp:list --><ul class="wp-block-list"><!-- wp:list-item ' . $bind( $meta( 'content', 'item' ) ) . ' --><li>Old item<!-- wp:list --><ul class="wp-block-list">'
                    . '<!-- wp:list-item ' . $bind( $meta( 'content', 'sub' ) ) . ' --><li>Old sub</li><!-- /wp:list-item --></ul><!-- /wp:list --></li><!-- /wp:list-item --></ul><!-- /wp:list -->'
                    . '<!-- wp:image {"id":3,' . substr( $bind( $meta( 'caption', 'caption' ) ), 1 ) . ' --><figure class="wp-block-image"><img src="https://wp.example/a.jpg" alt=""/><figcaption class="wp-element-caption"></figcaption></figure><!-- /wp:image -->'
                    . '<!-- wp:image {"id":4,' . substr( $bind( $meta( 'caption', 'caption' ) ), 1 ) . ' --><figure class="wp-block-image"><img src="https://wp.example/c.jpg" alt=""/></figure><!-- /wp:image -->'
                    . '<!-- wp:image {"id":7,' . substr( $bind( $meta( 'url', 'empty' ) ), 1 ) . ' --><figure class="wp-block-image"><img src="https://wp.example/d.jpg" alt="" class="wp-image-7"/></figure><!-- /wp:image -->'
                    . '<!-- wp:image {"id":8,' . substr( $bind( $meta( 'caption', 'empty' ) ), 1 ) . ' --><figure class="wp-block-image"><img src="https://wp.example/e.jpg" alt=""/>'
                    . '<figcaption class="wp-element-caption">Removed</figcaption></figure><!-- /wp:image -->'
                    . '<!-- wp:block {"ref":6,"content":{"Item":{"content":"Overridden item"},"Photo":{"caption":"Overridden caption"}}} /-->'] + $post,
            ['ID' => 5, 'post_type' => 'wp_block', 'post_name' => 'shared', 'post_title' => 'Shared',
                'post_content' => '<!-- wp:buttons --><div class="wp-block-buttons"><!-- wp:button ' . $bind( $meta( 'text', 'cta' ) . ',' . $meta( 'url', 'link' ) ) . ' -->'
                    . '<div class="wp-block-button"><a class="wp-block-button__link" href="https://old.example">Old</a></div><!-- /wp:button -->'
                    . '<!-- wp:button ' . $bind( $meta( 'text', 'empty' ) ) . ' --><div class="wp-block-button"><a class="wp-block-button__link" href="https://empty.example">Empty</a></div><!-- /wp:button -->'
                    . '</div><!-- /wp:buttons -->'] + $post,
            ['ID' => 6, 'post_type' => 'wp_block', 'post_name' => 'pattern', 'post_title' => 'Pattern',
                'post_content' => '<!-- wp:list --><ul class="wp-block-list"><!-- wp:list-item {"metadata":{"name":"Item","bindings":{"__default":{"source":"core/pattern-overrides"}}}} --><li>Pattern item</li><!-- /wp:list-item --></ul><!-- /wp:list -->'
                    . '<!-- wp:image {"metadata":{"name":"Photo","bindings":{"__default":{"source":"core/pattern-overrides"}}}} --><figure class="wp-block-image"><img src="https://wp.example/b.jpg" alt=""/>'
                    . '<figcaption class="wp-element-caption">Pattern caption</figcaption></figure><!-- /wp:image -->'] + $post,
        ] );
        $db->table( 'wp_postmeta' )->insert( [
            ['post_id' => 1, 'meta_key' => 'headline', 'meta_value' => 'New & better'],
            ['post_id' => 1, 'meta_key' => 'headline', 'meta_value' => 'Second'],
            ['post_id' => 1, 'meta_key' => 'price', 'meta_value' => '10 EUR'],
            ['post_id' => 1, 'meta_key' => '_secret', 'meta_value' => 'Secret'],
            ['post_id' => 1, 'meta_key' => 'empty', 'meta_value' => ''],
            ['post_id' => 1, 'meta_key' => 'list', 'meta_value' => 'a:1:{i:0;s:1:"x";}'],
            ['post_id' => 1, 'meta_key' => 'cta', 'meta_value' => 'Buy now'],
            ['post_id' => 1, 'meta_key' => 'link', 'meta_value' => 'https://shop.example/?a=1&b=2'],
            ['post_id' => 1, 'meta_key' => 'item', 'meta_value' => 'New item'],
            ['post_id' => 1, 'meta_key' => 'sub', 'meta_value' => 'New sub'],
            ['post_id' => 1, 'meta_key' => 'caption', 'meta_value' => 'New caption'],
        ] );

        $this->assertSame( 0, Artisan::call( 'cms:wp-import', ['--connection' => 'wordpress', '--domain' => 'new.example'] ) );

        // the first values of not protected and not serialized custom fields replace the saved content, also in reusable blocks, empty values too but not missing ones
        $content = collect( (array) PageView::where( 'domain', 'new.example' )->where( 'path', 'bound' )->firstOrFail()->content );
        $this->assertSame( 'New & better', $content->first( fn( $item ) => $item->type === 'heading' )->data->title ?? null );
        $this->assertSame( "10 EUR\n\nPublic\n\nSaved\n\nKept\n\n[Buy now](https://shop.example/?a=1&b=2)\n\n- New item\n  - New sub", $content->first( fn( $item ) => $item->type === 'text' )->data->text ?? null );
        // list items and image captions can be bound to custom fields and overridden in synced patterns too, images without caption keep having none,
        // images with an empty URL and buttons with an empty text are removed and empty captions are removed
        $this->assertSame( ['New caption', null, null, '- Overridden item', 'Overridden caption'], $content->slice( 3 )->map( fn( $item ) => $item->data->text ?? null )->values()->all() );
    }


    public function testConvertsGalleryCaptionsFileBlocksAndAlignedImages(): void
    {
        $warnings = [];
        $wp = new WordPress\Html(
            fn( string $tag ) => preg_match( '/src="([^"]+)"/', $tag, $m ) ? 'img:' . $m[1] : null,
            fn( string $url, string $name = '' ) => 'file:' . $url . '|' . $name,
            function( string $msg ) use ( &$warnings ) { $warnings[] = $msg; }
        );
        $data = fn( array $result ) => array_map( fn( $el ) => [$el['type'], $el['data']], $result['elements'] );
        $image = fn( string $src ) => '<!-- wp:image --><figure class="wp-block-image"><img src="' . $src . '" alt=""/></figure><!-- /wp:image -->';
        $caption = '<figcaption class="blocks-gallery-caption wp-element-caption">My trip</figcaption>';

        $result = $wp->elements( '<!-- wp:gallery --><figure class="wp-block-gallery has-nested-images">' . $image( 'a.jpg' ) . $caption . '</figure><!-- /wp:gallery -->'
            . '<!-- wp:gallery --><figure class="wp-block-gallery has-nested-images">' . $image( 'b.jpg' ) . $image( 'c.jpg' ) . $caption . '</figure><!-- /wp:gallery -->'
            . '<!-- wp:file {"id":9,"href":"https://wp.example/doc.pdf"} --><div class="wp-block-file"><a id="wp-block-file--media-1" href="https://wp.example/doc.pdf">Doc</a>'
            . '<a href="https://wp.example/doc.pdf" class="wp-block-file__button wp-element-button" download>Download</a></div><!-- /wp:file -->'
            . '<!-- wp:file {"id":10,"href":"https://wp.example/x.zip"} --><div class="wp-block-file"><a href="https://wp.example/x.zip" class="wp-block-file__button" download>Download</a></div><!-- /wp:file -->' );

        $this->assertSame( [
            // galleries with one image keep their caption and the caption of larger galleries is the slideshow title
            ['image-text', ['text' => 'My trip', 'file' => Elements::file( 'img:a.jpg' )]],
            ['slideshow', ['title' => 'My trip', 'files' => [Elements::file( 'img:b.jpg' ), Elements::file( 'img:c.jpg' )]]],
            // file blocks are downloadable files named like the link, the download button isn't a name
            ['file', ['file' => Elements::file( 'file:https://wp.example/doc.pdf|Doc' )]],
            ['file', ['file' => Elements::file( 'file:https://wp.example/x.zip|' )]],
        ], $data( $result ) );

        // classic images aligned left or right are shown next to the following paragraphs
        $result = $wp->elements( "<a href=\"a.jpg\"><img class=\"alignleft size-medium wp-image-1\" src=\"a.jpg\" alt=\"\" /></a>\n\nBeside\n\nMore\n\n<h2>Title</h2>\n\n"
            . "<img class=\"alignright\" src=\"b.jpg\" />\n\nRight\n\n<img class=\"aligncenter\" src=\"c.jpg\" />\n\nBelow\n\n<img class=\"alignleft\" src=\"d.jpg\" />" );

        $this->assertSame( [
            ['image-text', ['text' => "Beside\n\nMore", 'file' => Elements::file( 'img:a.jpg' ), 'position' => 'start']],
            ['heading', ['level' => 2, 'title' => 'Title']],
            ['image-text', ['text' => 'Right', 'file' => Elements::file( 'img:b.jpg' ), 'position' => 'end']],
            ['image', ['file' => Elements::file( 'img:c.jpg' )]],
            ['text', ['text' => 'Below']],
            ['image', ['file' => Elements::file( 'img:d.jpg' )]],
        ], $data( $result ) );

        // shortcodes of plugins are reported once per post, including the ones without attributes but with closing tag
        $wp->elements( '[su_box title="x"]Box[/su_box] [note]Text[/note] [su_box title="y"]Box[/su_box] [[esc a="1"]] [caption]<img src="a.jpg"> A[/caption] [b]' );
        $this->assertSame( ['  Shortcodes kept as text: su_box, note'], $warnings );
    }


    public function testMediaMatchesEditedImagesAndCustomUploads(): void
    {
        $media = new WordPress\Media( ['https://new.example/media/'], fn( string $mime, string $name, string $path, bool $managed ) => "{$name}|{$path}|" . (int) $managed,
            ['photo.jpg' => 1], fn( array $ids ) => array_intersect_key( [1 => ['guid' => 'https://wp.example/wp-content/uploads/photo.jpg', 'title' => 'Photo', 'mime' => 'image/jpeg']], array_flip( $ids ) ) );

        // rotated and edited copies are the original image of the attachment
        $this->assertSame( 'Photo|https://new.example/media/photo.jpg|1', $media->image( '<img src="https://wp.example/wp-content/uploads/photo-rotated.jpg">' ) );
        $this->assertSame( 'Photo|https://new.example/media/photo.jpg|1', $media->image( '<img src="https://wp.example/wp-content/uploads/photo-e1700000000000-300x200.jpg">' ) );
        $this->assertSame( 'photo-e123.jpg|https://cdn.example/photo-e123.jpg|0', $media->image( '<img src="https://cdn.example/photo-e123.jpg">' ) );

        // files below the uploads URL of the options are downloaded and replaced by the media URL
        $this->assertFalse( $media->managed( 'https://cdn.example/files/2026/doc.pdf' ) );
        $media->uploads( 'https://cdn.example/files/' )->uploads( null );
        $this->assertTrue( $media->managed( 'https://cdn.example/files/2026/doc.pdf' ) );
        $this->assertFalse( $media->managed( 'https://cdn.example/other/doc.pdf' ) );
        $this->assertSame( 'https://new.example/media/2026/doc.pdf', $media->rewrite( 'https://cdn.example/files/2026/doc.pdf' ) );
        $this->assertSame( 'https://cdn.example/filesx/doc.pdf', $media->rewrite( 'https://cdn.example/filesx/doc.pdf' ) );
        $this->assertSame( 'Doc|https://new.example/media/2026/doc.pdf|1', $media->url( 'https://cdn.example/files/2026/doc.pdf', 'Doc' ) );

        // the files of file blocks are downloaded before their post
        $media->prefetch( [], [5 => '<!-- wp:file {"id":9,"href":"https://cdn.example/files/2026/doc.pdf"} --><div class="wp-block-file"></div><!-- /wp:file -->'] );
        $this->assertSame( ['https://new.example/media/2026/doc.pdf'], $media->paths() );
    }


    public function testReaderAddsAttachedImagesToGalleries(): void
    {
        Schema::connection( 'wordpress' )->table( 'wp_posts', function( Blueprint $table ) {
            $table->unsignedBigInteger( 'post_parent' )->default( 0 );
            $table->integer( 'menu_order' )->default( 0 );
            $table->dateTime( 'post_modified' )->nullable();
        } );

        $db = DB::connection( 'wordpress' );
        $image = ['post_type' => 'attachment', 'post_status' => 'inherit', 'post_date' => '2026-01-01 00:00:00', 'post_mime_type' => 'image/jpeg'];
        $db->table( 'wp_posts' )->insert( [
            ['ID' => 20, 'post_parent' => 1, 'menu_order' => 2, 'post_name' => 'c', 'post_title' => 'A'] + $image,
            ['ID' => 21, 'post_parent' => 1, 'menu_order' => 1, 'post_name' => 'b', 'post_title' => 'C'] + $image,
            ['ID' => 22, 'post_parent' => 1, 'menu_order' => 1, 'post_name' => 'a', 'post_title' => 'B'] + $image,
            ['ID' => 23, 'post_parent' => 1, 'menu_order' => 0, 'post_name' => 'pdf', 'post_title' => 'PDF', 'post_mime_type' => 'application/pdf'] + $image,
            ['ID' => 30, 'post_parent' => 7, 'menu_order' => 0, 'post_name' => 'other', 'post_title' => 'Other'] + $image,
        ] );
        $reader = new WordPress\Reader( $db );

        $this->assertSame( [
            // images attached to the post ordered by menu order and ID, other attachments aren't used
            1 => 'A [gallery columns="2" ids="21,22,20"] [gallery orderby="title" order="DESC" ids="21,22,20"] [gallery orderby="post_name" exclude="22" ids="21,20"]'
                // the "id" attribute uses the images of another post, existing IDs and escaped shortcodes are kept
                . ' [gallery id="7" ids="30"] [gallery ids="5"] [[gallery]] [gallery orderby="rand" ids="21,22,20"]',
            // posts without attached images keep their shortcode
            2 => '[gallery]',
            3 => '<p>No gallery</p>',
        ], $reader->galleries( [
            1 => 'A [gallery columns="2"] [gallery orderby="title" order="DESC"] [gallery orderby="post_name" exclude="22"] [gallery id="7"] [gallery ids="5"] [[gallery]] [gallery orderby="rand"]',
            2 => '[gallery]',
            3 => '<p>No gallery</p>',
        ] ) );
    }


    public function testReaderSortsGalleryImagesByTheirIds(): void
    {
        Schema::connection( 'wordpress' )->table( 'wp_posts', function( Blueprint $table ) {
            $table->unsignedBigInteger( 'post_parent' )->default( 0 );
            $table->integer( 'menu_order' )->default( 0 );
            $table->dateTime( 'post_modified' )->nullable();
        } );

        $db = DB::connection( 'wordpress' );
        $image = ['post_type' => 'attachment', 'post_status' => 'inherit', 'post_date' => '2026-01-01 00:00:00', 'post_mime_type' => 'image/jpeg'];
        $db->table( 'wp_posts' )->insert( [
            ['ID' => 20, 'post_parent' => 7, 'menu_order' => 2, 'post_name' => 'c', 'post_title' => 'A'] + $image,
            ['ID' => 21, 'post_parent' => 7, 'menu_order' => 1, 'post_name' => 'b', 'post_title' => 'C'] + $image,
            ['ID' => 22, 'post_parent' => 0, 'menu_order' => 1, 'post_name' => 'a', 'post_title' => 'B'] + $image,
            ['ID' => 23, 'post_parent' => 0, 'menu_order' => 0, 'post_name' => 'pdf', 'post_title' => 'PDF', 'post_mime_type' => 'application/pdf'] + $image,
        ] );
        $reader = new WordPress\Reader( $db );

        $this->assertSame( [
            // the images in the "ids" attribute are sorted by "orderby" only, the ones of the old "include" attribute by "menu_order ID" by default
            1 => '[gallery ids="20,21,22"] [gallery ids="20,21,22" orderby="title" ids="20,22,21"] [gallery include="20,21,22" ids="21,22,20"]'
                // the "exclude" attribute isn't used and images which don't exist are left out
                . ' [gallery ids="20,23,99,22" orderby="ID" order="DESC" exclude="22" ids="22,20"]'
                // random order keeps the order of the IDs like galleries without images
                . ' [gallery ids="22,20" orderby="rand"] [gallery ids="23" orderby="title"]',
        ], $reader->galleries( [
            1 => '[gallery ids="20,21,22"] [gallery ids="20,21,22" orderby="title"] [gallery include="20,21,22"]'
                . ' [gallery ids="20,23,99,22" orderby="ID" order="DESC" exclude="22"]'
                . ' [gallery ids="22,20" orderby="rand"] [gallery ids="23" orderby="title"]',
        ] ) );
    }


    public function testFeaturedAndGalleryImagesUseAltTextOfAttachments(): void
    {
        $db = DB::connection( 'wordpress' );
        $image = ['post_type' => 'attachment', 'post_status' => 'inherit', 'post_date' => '2026-01-01 00:00:00', 'post_mime_type' => 'image/jpeg', 'post_name' => 'photo'];
        $db->table( 'wp_posts' )->insert( [
            ['ID' => 20, 'post_title' => 'IMG_1234', 'guid' => 'https://wp.example/wp-content/uploads/a.jpg'] + $image,
            ['ID' => 21, 'post_title' => 'IMG_5678', 'guid' => 'https://wp.example/wp-content/uploads/b.jpg'] + $image,
        ] );
        $db->table( 'wp_postmeta' )->insert( [
            ['post_id' => 20, 'meta_key' => '_wp_attachment_image_alt', 'meta_value' => ' Sunset at the beach '],
            ['post_id' => 21, 'meta_key' => '_wp_attachment_image_alt', 'meta_value' => ''],
        ] );

        // WordPress shows the alternative text of the attachment, the title is used if there's none
        $attachments = ( new WordPress\Reader( $db ) )->attachments( [20, 21] );
        $this->assertSame( ['Sunset at the beach', ''], [$attachments[20]['alt'], $attachments[21]['alt']] );

        $media = new WordPress\Media( [], fn( string $mime, string $name, string $path ) => $name, [], fn( array $ids ) => array_intersect_key( $attachments, array_flip( $ids ) ) );
        $media->prefetch( [1 => 20, 2 => 21], [1 => '[gallery ids="20,21"] <img class="wp-image-20" src="https://wp.example/wp-content/uploads/a-300x200.jpg" alt="Own text">'] );

        $this->assertSame( 'Sunset at the beach', $media->featured( 1 ) );
        $this->assertSame( 'IMG_5678', $media->featured( 2 ) );
        // images of gallery shortcodes have no alternative text, the one in the content is used otherwise
        $this->assertSame( 'Sunset at the beach', $media->image( '<img class="wp-image-20" alt="" />' ) );
        $this->assertSame( 'Own text', $media->image( '<img class="wp-image-20" src="https://wp.example/wp-content/uploads/a-300x200.jpg" alt="Own text">' ) );
    }


    public function testImportsPagesInTheLanguageOfTheSite(): void
    {
        $this->page( 'Root', '', 'new.example', 'root' );

        $db = DB::connection( 'wordpress' );
        $db->getSchemaBuilder()->create( 'wp_options', function( Blueprint $table ) {
            $table->string( 'option_name' );
            $table->longText( 'option_value' );
        } );
        $db->table( 'wp_posts' )->insert( ['ID' => 1, 'post_type' => 'post', 'post_date' => '2026-01-01 00:00:00', 'post_name' => 'hallo', 'post_title' => 'Hallo', 'post_content' => '<p>Text</p>'] );
        $reader = new WordPress\Reader( $db );

        // the default locale "en_US" is stored as empty value
        $this->assertNull( $reader->language() );
        $db->table( 'wp_options' )->insert( ['option_name' => 'WPLANG', 'option_value' => ''] );
        $this->assertSame( 'en', $reader->language() );
        $db->table( 'wp_options' )->where( 'option_name', 'WPLANG' )->update( ['option_value' => 'de_DE_formal'] );
        $this->assertSame( 'de', $reader->language() );

        // sites of a multisite network without own language use the one of the network
        $db->getSchemaBuilder()->create( 'wp_3_options', function( Blueprint $table ) {
            $table->string( 'option_name' );
            $table->longText( 'option_value' );
        } );
        $db->getSchemaBuilder()->create( 'wp_sitemeta', function( Blueprint $table ) {
            $table->string( 'meta_key' );
            $table->longText( 'meta_value' );
        } );
        $db->table( 'wp_sitemeta' )->insert( ['meta_key' => 'WPLANG', 'meta_value' => 'pt_BR'] );
        $this->assertSame( 'pt', ( new WordPress\Reader( $db, 'wp_3_' ) )->language() );

        $this->assertSame( 0, Artisan::call( 'cms:wp-import', ['--connection' => 'wordpress', '--domain' => 'new.example'] ) );
        $this->assertStringContainsString( 'Importing the pages in the language "de" of the WordPress site ("WPLANG" option), use --lang to change it.', Artisan::output() );
        $this->assertSame( 'de', PageView::where( 'domain', 'new.example' )->where( 'path', 'hallo' )->firstOrFail()->lang );

        // the --lang option overrides the language of the site
        $this->assertSame( 0, Artisan::call( 'cms:wp-import', ['--connection' => 'wordpress', '--domain' => 'new.example', '--lang' => 'de'] ) );
        $this->assertStringNotContainsString( 'WPLANG', Artisan::output() );
    }


    public function testStripsTagsFromPostTitles(): void
    {
        $this->page( 'Root', '', 'new.example', 'root' );

        DB::connection( 'wordpress' )->table( 'wp_posts' )->insert( [
            ['ID' => 1, 'post_type' => 'post', 'post_date' => '2026-01-01 00:00:00', 'post_name' => 'tagged', 'post_title' => 'A <em>B</em> &amp; C', 'post_content' => '<p>Text</p>'],
            ['ID' => 2, 'post_type' => 'post', 'post_date' => '2026-01-01 00:00:00', 'post_name' => 'only-tag', 'post_title' => '<br>', 'post_content' => '<p>Text</p>'],
        ] );

        $this->assertSame( 0, Artisan::call( 'cms:wp-import', ['--connection' => 'wordpress', '--domain' => 'new.example'] ) );
        $this->assertSame( 'A B & C', PageView::where( 'domain', 'new.example' )->where( 'path', 'tagged' )->firstOrFail()->name );
        $this->assertSame( '<br>', PageView::where( 'domain', 'new.example' )->where( 'path', 'only-tag' )->firstOrFail()->name );
    }


    public function testConvertsQuotesVersesSocialLinksAndPageBreaks(): void
    {
        $warnings = [];
        $wp = new WordPress\Html(
            fn( string $tag ) => preg_match( '/src="([^"]+)"/', $tag, $m ) ? 'img:' . $m[1] : null,
            fn( string $url ) => 'file:' . $url,
            function( string $msg ) use ( &$warnings ) { $warnings[] = $msg; }
        );
        $data = fn( array $result ) => array_map( fn( $el ) => [$el['type'], $el['data']], $result['elements'] );

        $result = $wp->elements( '<!-- wp:quote --><blockquote class="wp-block-quote"><!-- wp:paragraph --><p>Q</p><!-- /wp:paragraph --><cite>Author <a href="https://x.org">site</a></cite></blockquote><!-- /wp:quote -->'
            . '<!-- wp:pullquote --><figure class="wp-block-pullquote"><blockquote><p>PQ</p><cite></cite></blockquote></figure><!-- /wp:pullquote -->'
            . '<!-- wp:paragraph --><p>Read <cite>Dune</cite></p><!-- /wp:paragraph -->'
            . '<!-- wp:verse --><pre class="wp-block-verse">Roses are <em>red</em><br>violets blue' . "\n" . 'too</pre><!-- /wp:verse -->'
            . '<!-- wp:social-links --><ul class="wp-block-social-links"><!-- wp:social-link {"url":"https://x.com/me","service":"x"} /-->'
            . '<!-- wp:social-link {"url":"https://github.com/me","service":"github","label":"My code"} /--><!-- wp:social-link {"service":"mail"} /--></ul><!-- /wp:social-links -->'
            . '<!-- wp:columns --><div class="wp-block-columns"><!-- wp:column --><div class="wp-block-column"><!-- wp:search /--><!-- wp:paragraph --><p>P</p><!-- /wp:paragraph --></div><!-- /wp:column --></div><!-- /wp:columns -->'
            . '<!-- wp:audio --><figure class="wp-block-audio"><audio controls src="a.mp3"></audio><figcaption class="wp-element-caption">Episode</figcaption></figure><!-- /wp:audio -->' );

        $this->assertSame( [
            // citations of quotes are the last line of the quote, empty ones are removed and other citations are italic
            // verses keep their line breaks as hard breaks, social links are a list of links
            ['text', ['text' => "> Q\n>\n> — Author [site](https://x.org)\n\n> PQ\n\nRead *Dune*\n\nRoses are *red*\\\nviolets blue\\\ntoo\n\n- [X](https://x.com/me)\n- [My code](https://github.com/me)\n\nP"]],
            // captions of audio blocks are added as text like the ones of videos
            ['audio', ['file' => Elements::file( 'file:a.mp3' )]],
            ['text', ['text' => 'Episode']],
        ], $data( $result ) );
        // dynamic blocks in columns are reported too
        $this->assertSame( ['  Dynamic block search skipped'], $warnings );

        // like WordPress, emails get "mailto:", URLs without scheme "https://" and links without label the name of the service
        $result = $wp->elements( '<!-- wp:social-links --><ul class="wp-block-social-links"><!-- wp:social-link {"url":"me@example.org","service":"mail"} /-->'
            . '<!-- wp:social-link {"url":"linkedin.com/in/me","service":"linkedin"} /--><!-- wp:social-link {"url":"//feed.example/rss","service":"feed"} /-->'
            . '<!-- wp:social-link {"url":"#contact","service":"chain"} /--><!-- wp:social-link {"url":"https://new.example/me","service":"newnet"} /--></ul><!-- /wp:social-links -->' );
        $this->assertSame( "- [Mail](mailto:me@example.org)\n- [LinkedIn](https://linkedin.com/in/me)\n- [RSS Feed](//feed.example/rss)\n- [Link](#contact)\n- [Share Icon](https://new.example/me)",
            $result['elements'][0]['data']['text'] );

        // notices keep their paragraphs, line breaks, links and lists, the label starts the first paragraph
        $result = $wp->elements( "<div class=\"note\">Inside <a href=\"https://x.org\">div</a>\nline 2\n\nPara 2</div>\n\n<div class=\"caution\"><p>Careful</p><ul><li>A</li><li>B</li></ul></div>" );
        $this->assertSame( "**Note:** Inside [div](https://x.org)\\\nline 2\n\nPara 2\n\n**Warning:** Careful\n\n- A\n- B", $result['elements'][0]['data']['text'] );
        $result = $wp->elements( '<!-- wp:html --><div class="note"><p>One</p><p>Two <em>x</em></p></div><!-- /wp:html -->' );
        $this->assertSame( [['text', ['text' => "**Note:** One\n\nTwo *x*"]]], $data( $result ) );

        // lists, quotes and tables of classic content aren't split by blank lines and the parts of paginated posts are separated
        $result = $wp->elements( "Intro<!--nextpage-->Page\n\n<ul>\n\n<li>A</li>\n\n<li>B<ul>\n\n<li>B1</li>\n\n</ul></li>\n\n</ul>\n\n"
            . "<blockquote>Quoted\n\n<p>Second</p>\n\n<cite>Me</cite></blockquote>\n\n<table>\n\n<tr><td>A</td></tr>\n\n<tr><td>B</td></tr>\n\n</table>\n\n[playlist ids=\"1\"]" );

        $this->assertSame( [
            ['text', ['text' => "Intro\n\nPage\n\n- A\n- B\n  - B1\n\n> Quoted\n>\n> Second\n>\n> — Me"]],
            ['table', ['title' => '', 'header' => '', 'table' => [['A'], ['B']]]],
        ], $data( $result ) );
        // playlists are replaced by WordPress\Reader::playlists() before, the remaining ones have no files
        $this->assertSame( ['  Dynamic block search skipped', '  Playlist shortcode without files skipped'], $warnings );
    }


    public function testReaderReplacesPlaylistsByTheirFiles(): void
    {
        Schema::connection( 'wordpress' )->table( 'wp_posts', function( Blueprint $table ) {
            $table->unsignedBigInteger( 'post_parent' )->default( 0 );
            $table->integer( 'menu_order' )->default( 0 );
            $table->dateTime( 'post_modified' )->nullable();
        } );

        $db = DB::connection( 'wordpress' );
        $file = ['post_type' => 'attachment', 'post_status' => 'inherit', 'post_date' => '2026-01-01 00:00:00', 'menu_order' => 0];
        $db->table( 'wp_posts' )->insert( [
            ['ID' => 20, 'post_parent' => 1, 'post_name' => 'b', 'post_title' => 'B', 'post_mime_type' => 'audio/mpeg', 'guid' => 'https://wp.example/b.mp3'] + $file,
            ['ID' => 21, 'post_parent' => 1, 'post_name' => 'a', 'post_title' => 'A', 'post_mime_type' => 'audio/mpeg', 'guid' => 'https://wp.example/a[1].mp3'] + $file,
            ['ID' => 22, 'post_parent' => 1, 'post_name' => 'v', 'post_title' => 'V', 'post_mime_type' => 'video/mp4', 'guid' => 'https://wp.example/v.mp4'] + $file,
            ['ID' => 23, 'post_parent' => 1, 'post_name' => 'i', 'post_title' => 'I', 'post_mime_type' => 'image/jpeg', 'guid' => 'https://wp.example/i.jpg'] + $file,
            ['ID' => 30, 'post_parent' => 7, 'post_name' => 'c', 'post_title' => 'C', 'post_mime_type' => 'audio/ogg', 'guid' => 'https://wp.example/c.ogg'] + $file,
        ] );

        $this->assertSame( [
            // audio files attached to the post or the post of the "id" attribute, video files for the "video" type
            1 => "[audio src=\"https://wp.example/b.mp3\" attached=\"1\"]\n\n[audio src=\"https://wp.example/a%5B1%5D.mp3\" attached=\"1\"] [audio src=\"https://wp.example/a%5B1%5D.mp3\" attached=\"1\"]\n\n[audio src=\"https://wp.example/b.mp3\" attached=\"1\"]"
                . ' [video src="https://wp.example/v.mp4" attached="1"] [audio src="https://wp.example/c.ogg" attached="1"]'
                // IDs keep their order, other types and missing files are left out, playlists without files and escaped ones are kept
                . " [audio src=\"https://wp.example/c.ogg\" attached=\"1\"]\n\n[audio src=\"https://wp.example/b.mp3\" attached=\"1\"] [playlist ids=\"22,9\"] [[playlist]]",
            2 => '[playlist]',
            3 => '<p>No playlist</p>',
        ], ( new WordPress\Reader( $db ) )->playlists( [
            1 => '[playlist] [playlist orderby="title"] [playlist type="video"] [playlist id="7"] [playlist ids="30,23,20"] [playlist ids="22,9"] [[playlist]]',
            2 => '[playlist]',
            3 => '<p>No playlist</p>',
        ] ) );

        // video and audio shortcodes without URL show the first attached file of their type, others and escaped ones are kept
        $this->assertSame( [
            // attributes of other extensions, unknown ones and URLs with another extension are ignored like by WordPress
            1 => '[video src="https://wp.example/v.mp4" attached="1"] [audio src="https://wp.example/b.mp3" attached="1"] [audio mp3="https://x.org/a.mp3"] [[video]]'
                . ' [audio src="https://wp.example/b.mp3" attached="1"] [video src="https://wp.example/v.mp4" attached="1"]',
            2 => '[video width="640"]',
        ], ( new WordPress\Reader( $db ) )->playlists( [
            1 => '[video width="640"][/video] [audio] [audio mp3="https://x.org/a.mp3"] [[video]] [audio wma="https://x.org/a.wma"] [video mp4="https://x.org/v.webm"]',
            2 => '[video width="640"]',
        ] ) );
    }


    public function testConvertsLineBreaksIframesVideosTablesAndCovers(): void
    {
        $warnings = [];
        $wp = new WordPress\Html(
            fn( string $tag ) => preg_match( '/src="([^"]+)"/', $tag, $m ) ? 'img:' . $m[1] : null,
            fn( string $url ) => 'file:' . $url,
            function( string $msg ) use ( &$warnings ) { $warnings[] = $msg; }
        );
        $data = fn( array $result ) => array_map( fn( $el ) => [$el['type'], $el['data']], $result['elements'] );

        // single newlines of classic content are hard breaks like by wpautop() except next to block tags, URLs on their own line are still embedded
        $result = $wp->elements( "Line one\nline <strong>two\nthree</strong><br />\nfour\n\n<ul>\n<li>A\nB</li>\n<li>C</li>\n</ul>\n\nText\nhttps://m.youtube.com/watch?v=abcdefghijk\nEnd" );
        $this->assertSame( [
            ['text', ['text' => "Line one\\\nline **two\\\nthree**\\\nfour\n\n- A\\\n  B\n- C\n\nText"]],
            ['html', ['text' => '<iframe src="https://www.youtube-nocookie.com/embed/abcdefghijk" loading="lazy" allow="fullscreen; picture-in-picture" allowfullscreen></iframe>']],
            ['text', ['text' => 'End']],
        ], $data( $result ) );

        // single newlines of block content aren't line breaks, several line breaks in a row separate paragraphs
        $result = $wp->elements( "<!-- wp:paragraph --><p>A\nB<br><br>C<br></p><!-- /wp:paragraph -->" );
        $this->assertSame( [['text', ['text' => "A\nB\n\nC"]]], $data( $result ) );

        // iframes are HTML elements and iframes without web URL are removed
        $result = $wp->elements( '<!-- wp:html --><p>Before</p><iframe src="//www.youtube.com/embed/abcdefghijk" width="560"></iframe>'
            . '<iframe src="https://maps.example/x?a=1&amp;b=2" title="Map"></iframe><iframe src="javascript:alert(1)"></iframe><!-- /wp:html -->'
            . '<!-- wp:html --><form action="/send"><input name="a"></form><!-- /wp:html -->' );
        $this->assertSame( [
            ['text', ['text' => 'Before']],
            ['html', ['text' => '<iframe src="https://www.youtube.com/embed/abcdefghijk" width="560" loading="lazy" allow="fullscreen; picture-in-picture" allowfullscreen></iframe><iframe src="https://maps.example/x?a=1&amp;b=2" title="Map" loading="lazy" allow="fullscreen; picture-in-picture" allowfullscreen></iframe>']],
        ], $data( $result ) );
        $this->assertSame( ['  Iframe removed: javascript:alert(1)', '  HTML removed: form, input'], $warnings );

        $result = $wp->elements( 'Classic <iframe src="https://player.vimeo.com/video/123"></iframe> text' );
        $this->assertSame( [
            ['text', ['text' => 'Classic']],
            ['html', ['text' => '<iframe src="https://player.vimeo.com/video/123" loading="lazy" allow="fullscreen; picture-in-picture" allowfullscreen></iframe>']],
            ['text', ['text' => 'text']],
        ], $data( $result ) );

        // short and live URLs of embeds are embedded by their players too, unknown video URLs become links
        $result = $wp->elements( '<!-- wp:embed {"url":"https://www.youtube.com/shorts/abcdefghijk"} --><figure class="wp-block-embed"><div class="wp-block-embed__wrapper">https://www.youtube.com/shorts/abcdefghijk</div></figure><!-- /wp:embed -->'
            . '<!-- wp:embed {"url":"https://youtube.com/live/defghijklmn"} --><figure class="wp-block-embed"><div class="wp-block-embed__wrapper">https://youtube.com/live/defghijklmn</div></figure><!-- /wp:embed -->'
            . '<!-- wp:embed {"url":"https://vimeo.com/channels/x"} --><figure class="wp-block-embed"><div class="wp-block-embed__wrapper">https://vimeo.com/channels/x</div></figure><!-- /wp:embed -->' );
        $this->assertSame( [
            ['html', ['text' => '<iframe src="https://www.youtube-nocookie.com/embed/abcdefghijk" loading="lazy" allow="fullscreen; picture-in-picture" allowfullscreen></iframe><iframe src="https://www.youtube-nocookie.com/embed/defghijklmn" loading="lazy" allow="fullscreen; picture-in-picture" allowfullscreen></iframe>']],
            ['text', ['text' => '[https://vimeo.com/channels/x](https://vimeo.com/channels/x)']],
        ], $data( $result ) );

        // table cells keep links and formatting, cells spanning columns are followed by empty cells
        $result = $wp->elements( '<!-- wp:table --><figure class="wp-block-table"><table><tbody><tr><td colspan="2"><a href="https://x.org">Link</a> <strong>b</strong></td><td>c<br>d</td></tr>'
            . '<tr><td>1</td><td>2</td><td>3</td></tr></tbody></table><figcaption><em>Cap</em></figcaption></figure><!-- /wp:table -->' );
        $this->assertSame( [['table', ['title' => 'Cap', 'header' => '', 'table' => [['[Link](https://x.org) **b**', '', 'c d'], ['1', '2', '3']]]]], $data( $result ) );

        // lists with letters or roman numbers are kept as HTML
        $result = $wp->elements( '<!-- wp:list {"ordered":true} --><ol type="a"><li>x</li></ol><!-- /wp:list -->'
            . '<!-- wp:list {"ordered":true} --><ol style="list-style-type:upper-roman"><li>y</li></ol><!-- /wp:list -->' );
        $this->assertSame( [['html', ['text' => '<ol type="a"><li>x</li></ol><ol style="list-style-type:upper-roman;"><li>y</li></ol>']]], $data( $result ) );

        // headings of covers are bold paragraphs
        $result = $wp->elements( '<!-- wp:cover {"url":"https://e.com/c.jpg"} --><div class="wp-block-cover"><img class="wp-block-cover__image-background" src="https://e.com/c.jpg"/>'
            . '<div class="wp-block-cover__inner-container"><!-- wp:heading --><h2>Big</h2><!-- /wp:heading --><!-- wp:paragraph --><p>Sub</p><!-- /wp:paragraph --></div></div><!-- /wp:cover -->' );
        $this->assertSame( [['image-text', ['text' => "**Big**\n\nSub", 'file' => Elements::file( 'img:https://e.com/c.jpg' )]]], $data( $result ) );
    }


    public function testConvertsAccordionsMathAndRowSpans(): void
    {
        $warnings = [];
        $wp = new WordPress\Html(
            fn( string $tag ) => preg_match( '/src="([^"]+)"/', $tag, $m ) ? 'img:' . $m[1] : null,
            fn( string $url ) => 'file:' . $url,
            function( string $msg ) use ( &$warnings ) { $warnings[] = $msg; }
        );
        $data = fn( array $result ) => array_map( fn( $el ) => [$el['type'], $el['data']], $result['elements'] );
        $item = fn( string $title, string $panel ) => '<!-- wp:accordion-item --><div class="wp-block-accordion-item"><!-- wp:accordion-heading {"title":"x"} -->'
            . '<h3 class="wp-block-accordion-heading has-icon has-icon-right"><button type="button" class="wp-block-accordion-heading__toggle">'
            . '<span class="wp-block-accordion-heading__toggle-title">' . $title . '</span><span class="wp-block-accordion-heading__toggle-icon" aria-hidden="true">+</span>'
            . '</button></h3><!-- /wp:accordion-heading --><!-- wp:accordion-panel --><div role="region" class="wp-block-accordion-panel">' . $panel
            . '</div><!-- /wp:accordion-panel --></div><!-- /wp:accordion-item -->';

        // accordions become questions with the texts of their panels
        $result = $wp->elements( '<!-- wp:accordion --><div role="group" class="wp-block-accordion">'
            . $item( 'What &amp; <em>why</em>?', '<!-- wp:paragraph --><p>Because <strong>yes</strong>.</p><!-- /wp:paragraph --><!-- wp:list --><ul><!-- wp:list-item --><li>A</li><!-- /wp:list-item --></ul><!-- /wp:list -->' )
            . $item( 'How?', '<!-- wp:paragraph --><p>Like this.</p><!-- /wp:paragraph -->' )
            . '</div><!-- /wp:accordion -->' );
        $this->assertSame( [['questions', ['title' => '', 'items' => [
            ['title' => 'What & why?', 'text' => "Because **yes**.\n\n- A"],
            ['title' => 'How?', 'text' => 'Like this.'],
        ]]]], $data( $result ) );

        // accordions with images in their panels become the bold titles followed by the converted panels
        $result = $wp->elements( '<!-- wp:accordion --><div role="group" class="wp-block-accordion">'
            . $item( 'Image', '<!-- wp:image --><figure class="wp-block-image"><img src="https://e.com/a.jpg"/></figure><!-- /wp:image -->' )
            . '</div><!-- /wp:accordion -->' );
        $this->assertSame( [['text', ['text' => '**Image**']], ['image', ['file' => Elements::file( 'img:https://e.com/a.jpg' )]]], $data( $result ) );
        $this->assertSame( ['img:https://e.com/a.jpg'], $result['fileIds'] );

        // math formulas keep their LaTeX source as code
        $warnings = [];
        $result = $wp->elements( '<!-- wp:math --><div class="wp-block-math"><math display="block"><semantics><msup><mi>x</mi><mn>2</mn></msup>'
            . '<annotation encoding="application/x-tex">x^2 &lt; y</annotation></semantics></math></div><!-- /wp:math -->' );
        $this->assertSame( [['code', ['language' => 'latex', 'text' => 'x^2 < y']]], $data( $result ) );
        $this->assertSame( ['  Math formula kept as LaTeX code: x^2 < y'], $warnings );

        // cells spanning several rows are followed by empty cells in the rows below, also after the last cell and in empty rows
        $result = $wp->elements( '<!-- wp:table --><figure class="wp-block-table"><table><thead><tr><th colspan="2" rowspan="2">H</th><th>X</th></tr><tr><th>Y</th></tr></thead>'
            . '<tbody><tr><td>1</td><td rowspan="0">2</td><td>3</td></tr><tr><td>4</td></tr><tr></tr></tbody><tfoot><tr><td>F</td><td>G</td></tr></tfoot></table></figure><!-- /wp:table -->' );
        $this->assertSame( [['table', ['title' => '', 'header' => 'row', 'table' => [
            ['H', '', 'X'], ['', '', 'Y'], ['1', '2', '3'], ['4', ''], ['', ''], ['F', 'G'],
        ]]]], $data( $result ) );
    }


    public function testConvertsSuperscriptsSeparatorsHeadingLinksAndMediaShortcodes(): void
    {
        $warnings = [];
        $wp = new WordPress\Html(
            fn( string $tag ) => null,
            fn( string $url ) => 'file:' . $url,
            function( string $msg ) use ( &$warnings ) { $warnings[] = $msg; }
        );
        $data = fn( array $result ) => array_map( fn( $el ) => [$el['type'], $el['data']], $result['elements'] );

        // superscript and subscript are kept as HTML, the numbers of footnotes are text
        $result = $wp->elements( '<!-- wp:paragraph --><p>H<sub>2</sub>O and 10<sup>3</sup> m</p><!-- /wp:paragraph -->'
            . '<!-- wp:paragraph --><p>Note<sup data-fn="a" class="fn"><a href="#a" id="a-link">1</a></sup></p><!-- /wp:paragraph -->', ['a' => 'Source'] );
        $this->assertSame( [
            ['html', ['text' => '<p>H<sub>2</sub>O and 10<sup>3</sup> m</p>']],
            ['text', ['text' => "Note[1]\n\n1. Source"]],
        ], $data( $result ) );

        // separators are horizontal lines
        $result = $wp->elements( '<!-- wp:paragraph --><p>A</p><!-- /wp:paragraph --><!-- wp:separator --><hr class="wp-block-separator is-style-dots"/><!-- /wp:separator -->'
            . '<!-- wp:paragraph --><p>B</p><!-- /wp:paragraph -->' );
        $this->assertSame( [['text', ['text' => "A\n\n---\n\nB"]]], $data( $result ) );

        // links in headings are reported because headings can't contain links
        $warnings = [];
        $result = $wp->elements( '<!-- wp:heading --><h2 class="wp-block-heading">Read <a href="https://x.org/?a=1&amp;b=2">this</a></h2><!-- /wp:heading -->' );
        $this->assertSame( [['heading', ['level' => 2, 'title' => 'Read this']]], $data( $result ) );
        $this->assertSame( ['  Links removed from heading "Read this": https://x.org/?a=1&b=2'], $warnings );

        // video and audio shortcodes without URL and attached file are reported
        $warnings = [];
        $result = $wp->elements( "Intro\n\n[video width=\"640\"]\n\n[audio]\n\n[audio src=\"https://x.org/a.mp3\"]" );
        $this->assertSame( [['text', ['text' => 'Intro']], ['audio', ['file' => Elements::file( 'file:https://x.org/a.mp3' )]]], $data( $result ) );
        $this->assertSame( ['  Video shortcode without file skipped', '  Audio shortcode without file skipped'], $warnings );

        // files of other types are links like in WordPress except attached files, the extension attributes must match the URL
        $warnings = [];
        $result = $wp->elements( "[video src=\"https://x.org/v.mov\"]\n\n[audio src=\"https://x.org/a.wma\"]\n\n[video src=\"https://x.org/v.mp4?v=1\"]\n\n"
            . "[video src=\"https://www.youtube.com/shorts/abcdefghijk\"]\n\n[video src=\"https://x.org/v.mov\" attached=\"1\"]\n\n"
            . "[audio src=\"https://x.org/b.OGG\"]\n\n[video wmv=\"https://x.org/v.wmv\"]\n\n[video mp4=\"https://x.org/v.webm\"]\n\n[video m4v=\"https://x.org/v.m4v\"]" );
        $this->assertSame( [
            ['text', ['text' => "[https://x.org/v.mov](https://x.org/v.mov)\n\n[https://x.org/a.wma](https://x.org/a.wma)\n\n"
                . "[https://x.org/v.mp4?v=1](https://x.org/v.mp4?v=1)\n\n[https://www.youtube.com/shorts/abcdefghijk](https://www.youtube.com/shorts/abcdefghijk)"]],
            ['video', ['file' => Elements::file( 'file:https://x.org/v.mov' )]],
            ['audio', ['file' => Elements::file( 'file:https://x.org/b.OGG' )]],
            ['video', ['file' => Elements::file( 'file:https://x.org/v.m4v' )]],
        ], $data( $result ) );
        $this->assertSame( ['  Video shortcode without file skipped', '  Video shortcode without file skipped'], $warnings );

        // embeds of audio and video file URLs are played, the "src" attribute is used without content and old YouTube URLs are embedded
        $warnings = [];
        $result = $wp->elements( "Intro\nhttps://x.org/song.mp3\nhttps://x.org/a.mp3?x=1\n\n[embed]https://x.org/clip.MP4[/embed]\n\n[embed src=\"https://vimeo.com/42\"]\n\n"
            . "https://www.youtube.com/v/abcdefghijk\n\n<!-- wp:embed {\"url\":\"https://x.org/c.ogg\"} --><figure class=\"wp-block-embed\"><div class=\"wp-block-embed__wrapper\">\n"
            . "https://x.org/c.ogg\n</div></figure><!-- /wp:embed -->" );
        $this->assertSame( [
            ['text', ['text' => 'Intro']],
            ['audio', ['file' => Elements::file( 'file:https://x.org/song.mp3' )]],
            ['text', ['text' => '[https://x.org/a.mp3?x=1](https://x.org/a.mp3?x=1)']],
            ['video', ['file' => Elements::file( 'file:https://x.org/clip.MP4' )]],
            ['html', ['text' => '<iframe src="https://player.vimeo.com/video/42" loading="lazy" allow="fullscreen; picture-in-picture" allowfullscreen></iframe>'
                . '<iframe src="https://www.youtube-nocookie.com/embed/abcdefghijk" loading="lazy" allow="fullscreen; picture-in-picture" allowfullscreen></iframe>']],
            ['audio', ['file' => Elements::file( 'file:https://x.org/c.ogg' )]],
        ], $data( $result ) );
        $this->assertSame( [], $warnings );

        // the HTML entities of the sources of audio and video blocks and shortcodes are decoded like by the browser
        $result = $wp->elements( '<!-- wp:audio --><figure class="wp-block-audio"><audio controls src="https://x.org/a.php?a=1&amp;b=2"></audio></figure><!-- /wp:audio -->'
            . '<!-- wp:video --><figure class="wp-block-video"><video controls src="https://x.org/v.php?a=1&amp;b=2"></video></figure><!-- /wp:video -->'
            . '<!-- wp:paragraph --><p>[video src="https://x.org/R&amp;D.mp4"]</p><!-- /wp:paragraph -->' );
        $this->assertSame( [
            ['audio', ['file' => Elements::file( 'file:https://x.org/a.php?a=1&b=2' )]],
            ['video', ['file' => Elements::file( 'file:https://x.org/v.php?a=1&b=2' )]],
            ['video', ['file' => Elements::file( 'file:https://x.org/R&D.mp4' )]],
        ], $data( $result ) );

        // the teaser before the "more" tag is hidden on the page of the post by "noteaser" if the tag is on the first page like by get_the_content()
        $result = $wp->elements( '<!-- wp:paragraph --><p>Teaser</p><!-- /wp:paragraph --><!-- wp:more {"noTeaser":true} --><!--more--><!--noteaser--><!-- /wp:more -->'
            . '<!-- wp:paragraph --><p>Rest</p><!-- /wp:paragraph -->' );
        $this->assertSame( [['text', ['text' => 'Rest']]], $data( $result ) );

        $result = $wp->elements( "Teaser\n<!--more Read on--><!--noteaser-->\n\nRest" );
        $this->assertSame( [['text', ['text' => 'Rest']]], $data( $result ) );

        $result = $wp->elements( "First<!--nextpage-->Teaser<!--more--><!--noteaser-->Rest" );
        $this->assertSame( [['text', ['text' => "First\n\nTeaserRest"]]], $data( $result ) );

        $result = $wp->elements( "Text<!--noteaser-->\n\nRest" );
        $this->assertSame( [['text', ['text' => "Text\n\nRest"]]], $data( $result ) );
    }


    public function testRewritesLinksToPosts(): void
    {
        $warnings = [];
        $links = new WordPress\Links( ['https://www.wp.example/blog', 'https://wp.example/wp'], [5 => 'first', 7 => 'zweiter-beitrag'], function( string $msg ) use ( &$warnings ) {
            $warnings[] = $msg;
        } );

        $html = '<a href="https://wp.example/blog/?p=5">ID</a> <a href="http://www.wp.example/blog/2024/05/first/#part">Permalink</a>'
            . ' <a href="/blog/Zweiter-Beitrag/2/">Page 2</a> <a href="https://wp.example/wp/index.php?p=7&amp;preview=true">Preview</a>'
            . ' <a href="https://wp.example/blog/?page_id=9">WP page</a> <a href="https://wp.example/blog/missing/">Missing</a>'
            . ' <a href="https://wp.example/blog/category/news/">Category</a> <a href="https://wp.example/blog/2024/05/">Archive</a>'
            . ' <a href="https://wp.example/blog/wp-content/uploads/a.pdf">File</a> <a href="https://wp.example/blog/">Home</a>'
            . ' <a href="https://other.example/first/">Other</a> <a href="#intro">Anchor</a> <a href="first/">Relative</a>';

        $this->assertSame( '<a href="/first">ID</a> <a href="/first#' . WordPress\Html::anchorId( 5, 'part' ) . '">Permalink</a>'
            . ' <a href="/zweiter-beitrag">Page 2</a> <a href="/zweiter-beitrag">Preview</a>'
            . ' <a href="https://wp.example/blog/?page_id=9">WP page</a> <a href="https://wp.example/blog/missing/">Missing</a>'
            . ' <a href="https://wp.example/blog/category/news/">Category</a> <a href="https://wp.example/blog/2024/05/">Archive</a>'
            . ' <a href="https://wp.example/blog/wp-content/uploads/a.pdf">File</a> <a href="https://wp.example/blog/">Home</a>'
            . ' <a href="https://other.example/first/">Other</a> <a href="#intro">Anchor</a> <a href="first/">Relative</a>', $links->rewrite( $html ) );
        $this->assertSame( ["  Links to posts which aren't imported kept: https://wp.example/blog/?page_id=9, https://wp.example/blog/missing/"], $warnings );

        // fragments added by WordPress itself stay unchanged, encoded anchors point to the element IDs too
        $this->assertSame( '<a href="/first#comments">C</a> <a href="/first#respond">R</a> <a href="/first#comment-3">3</a> <a href="/first#more-5">M</a>'
            . ' <a href="/zweiter-beitrag#' . WordPress\Html::anchorId( 7, 'ü' ) . '">Ü</a>', $links->rewrite( '<a href="https://wp.example/blog/first/#comments">C</a>'
            . ' <a href="https://wp.example/blog/first/#respond">R</a> <a href="https://wp.example/blog/first/#comment-3">3</a>'
            . ' <a href="https://wp.example/blog/?p=5#more-5">M</a> <a href="https://wp.example/blog/zweiter-beitrag/#%C3%BC">Ü</a>' ) );
    }


    public function testRewritesLinksByPermalinkSettings(): void
    {
        $warnings = [];
        $links = new WordPress\Links( ['https://wp.example'], [123 => 'my-post', 5 => 'news'], function( string $msg ) use ( &$warnings ) {
            $warnings[] = $msg;
        }, '/archives/%post_id%', ['/topics', ''] );

        // numeric permalinks contain the post ID, archives below the category and tag bases aren't posts
        $html = '<a href="https://wp.example/archives/123">ID</a> <a href="/archives/123/2/#c">Page 2</a> <a href="/archives/9">Missing</a>'
            . ' <a href="https://wp.example/topics/news/">Category</a> <a href="/tag/news/">Tag</a> <a href="/2024/news/">Slug</a>';

        $this->assertSame( '<a href="/my-post">ID</a> <a href="/my-post#' . WordPress\Html::anchorId( 123, 'c' ) . '">Page 2</a> <a href="/archives/9">Missing</a>'
            . ' <a href="https://wp.example/topics/news/">Category</a> <a href="/tag/news/">Tag</a> <a href="/news">Slug</a>', $links->rewrite( $html ) );
        $this->assertSame( ["  Links to posts which aren't imported kept: /archives/9"], $warnings );

        // the other tags of the permalinks are matched too and the archives without bases are below the static part of the permalinks
        $links = new WordPress\Links( ['https://wp.example'], [123 => 'my-post'], null, '/blog/%category%/%post_id%-%postname%/' );

        $this->assertSame( '<a href="/my-post">Post</a> <a href="/blog/category/x/">Category</a> <a href="/blog/tag/y/">Tag</a>',
            $links->rewrite( '<a href="/blog/a/b/123-my-post/">Post</a> <a href="/blog/category/x/">Category</a> <a href="/blog/tag/y/">Tag</a>' ) );
    }


    public function testImportRewritesLinksToImportedPosts(): void
    {
        $this->page( 'Root', '', 'new.example', 'root' );
        $db = DB::connection( 'wordpress' );
        $db->getSchemaBuilder()->create( 'wp_options', function( Blueprint $table ) {
            $table->string( 'option_name' );
            $table->text( 'option_value' );
        } );
        $db->table( 'wp_options' )->insert( [['option_name' => 'home', 'option_value' => 'https://wp.example/'], ['option_name' => 'siteurl', 'option_value' => 'https://wp.example/wp'],
            ['option_name' => 'permalink_structure', 'option_value' => '/archives/%post_id%'], ['option_name' => 'category_base', 'option_value' => '']] );
        $db->table( 'wp_posts' )->insert( [
            ['ID' => 1, 'post_date' => '2026-01-01 00:00:00', 'post_name' => 'first', 'post_title' => 'First', 'post_excerpt' => '',
                'post_content' => '<p>See <a href="https://wp.example/?p=2">the second</a> and <a href="https://wp.example/about/">about</a></p>'],
            ['ID' => 2, 'post_date' => '2026-01-02 00:00:00', 'post_name' => 'second', 'post_title' => 'Second', 'post_excerpt' => '',
                'post_content' => '<p><a href="/2026/01/first/">Back</a> <a href="/archives/1">ID</a> <a href="/archives/category/news/">News</a></p>'],
        ] );

        $this->assertSame( 0, Artisan::call( 'cms:wp-import', ['--connection' => 'wordpress', '--domain' => 'new.example'] ) );

        $first = PageView::where( 'domain', 'new.example' )->where( 'path', 'first' )->firstOrFail();
        $second = PageView::where( 'domain', 'new.example' )->where( 'path', 'second' )->firstOrFail();
        $text = collect( (array) $first->content )->first( fn( $item ) => $item->type === 'text' );
        $back = collect( (array) $second->content )->first( fn( $item ) => $item->type === 'text' );

        $this->assertSame( 'See [the second](/second) and [about](https://wp.example/about/)', $text->data->text ?? null );
        $this->assertSame( '[Back](/first) [ID](/first) [News](/archives/category/news/)', $back->data->text ?? null );
        $this->assertStringContainsString( "Links to posts which aren't imported kept: https://wp.example/about/", Artisan::output() );
    }


    public function testImportsScheduledAndPrivatePosts(): void
    {
        $this->page( 'Root', '', 'new.example', 'root' );

        $post = ['post_date' => '2026-08-01 12:00:00', 'post_content' => '<!-- wp:paragraph --><p>Text</p><!-- /wp:paragraph -->'];
        DB::connection( 'wordpress' )->table( 'wp_posts' )->insert( [
            ['ID' => 95, 'post_name' => 'later', 'post_title' => 'Later', 'post_status' => 'future', 'post_date_gmt' => '2030-01-01 10:00:00'] + $post,
            ['ID' => 96, 'post_name' => 'internal', 'post_title' => 'Internal', 'post_status' => 'private', 'post_date_gmt' => null] + $post,
            ['ID' => 97, 'post_name' => 'draft', 'post_title' => 'Draft', 'post_status' => 'draft', 'post_date_gmt' => null] + $post,
        ] );

        $this->assertSame( 0, Artisan::call( 'cms:wp-import', ['--connection' => 'wordpress', '--domain' => 'new.example'] ) );
        $output = Artisan::output();

        $this->assertStringContainsString( 'Disabled [95] Later because it is scheduled for 2030-01-01 10:00:00 UTC', $output );
        $this->assertStringContainsString( 'Disabled [96] Internal because it is private', $output );
        $this->assertSame( 0, PageView::where( 'domain', 'new.example' )->where( 'path', 'internal' )->firstOrFail()->status );
        $this->assertNull( PageView::where( 'domain', 'new.example' )->where( 'path', 'draft' )->first() );

        // scheduled posts are published by a pending version at their date
        $later = PageView::where( 'domain', 'new.example' )->where( 'path', 'later' )->firstOrFail();
        $pending = $later->versions()->where( 'published', false )->get();

        $this->assertSame( 0, $later->status );
        $this->assertCount( 1, $pending );
        // SQL Server returns the date with milliseconds
        $this->assertSame( '2030-01-01 10:00:00', substr( (string) $pending->first()?->publish_at, 0, 19 ) );
        $this->assertSame( 1, $pending->first()->data->status ?? null );

        // the pending version is kept if nothing changed
        $this->assertSame( 0, Artisan::call( 'cms:wp-import', ['--connection' => 'wordpress', '--domain' => 'new.example'] ) );
        $this->assertSame( [$pending->first()?->id], $later->versions()->where( 'published', false )->pluck( 'id' )->all() );

        // the pending version is removed if the post was published since
        DB::connection( 'wordpress' )->table( 'wp_posts' )->where( 'ID', 95 )->update( ['post_status' => 'publish'] );

        $this->assertSame( 0, Artisan::call( 'cms:wp-import', ['--connection' => 'wordpress', '--domain' => 'new.example'] ) );
        $this->assertSame( 1, PageView::where( 'domain', 'new.example' )->where( 'path', 'later' )->firstOrFail()->status );
        $this->assertSame( 0, $later->versions()->where( 'published', false )->count() );
    }


    public function testDisablesPagesOfPostsNotImportedAnyMore(): void
    {
        $root = $this->page( 'Root', '', 'new.example', 'root' );

        $post = ['post_date' => '2026-08-01 12:00:00', 'post_content' => '<!-- wp:paragraph --><p>Text</p><!-- /wp:paragraph -->'];
        DB::connection( 'wordpress' )->table( 'wp_posts' )->insert( [
            ['ID' => 1, 'post_name' => 'kept', 'post_title' => 'Kept'] + $post,
            ['ID' => 2, 'post_name' => 'trashed', 'post_title' => 'Trashed'] + $post,
            ['ID' => 3, 'post_name' => 'deleted', 'post_title' => 'Deleted'] + $post,
        ] );

        $this->assertSame( 0, Artisan::call( 'cms:wp-import', ['--connection' => 'wordpress', '--domain' => 'new.example'] ) );
        $this->assertSame( 0, Artisan::call( 'cms:wp-import', ['--connection' => 'wordpress', '--domain' => 'new.example', '--blog-path' => 'news'] ) );

        DB::connection( 'wordpress' )->table( 'wp_posts' )->where( 'ID', 2 )->update( ['post_status' => 'trash'] );
        DB::connection( 'wordpress' )->table( 'wp_posts' )->where( 'ID', 3 )->delete();
        $status = fn( string $path ) => PageView::where( 'domain', 'new.example' )->where( 'path', $path )->firstOrFail()->status;

        $this->assertSame( 0, Artisan::call( 'cms:wp-import', ['--connection' => 'wordpress', '--domain' => 'new.example', '--blog-path' => 'news', '--dry-run' => true] ) );
        $this->assertStringContainsString( 'Disable /trashed [en]', Artisan::output() );
        $this->assertSame( 1, $status( 'trashed' ) );

        $this->assertSame( 0, Artisan::call( 'cms:wp-import', ['--connection' => 'wordpress', '--domain' => 'new.example', '--blog-path' => 'news'] ) );
        $output = Artisan::output();

        $this->assertStringContainsString( 'Disabled 2 pages of previous imports whose WordPress posts aren\'t imported any more.', $output );
        $this->assertSame( 1, $status( 'kept' ) );
        $this->assertSame( 0, $status( 'trashed' ) );
        $this->assertSame( 0, $status( 'deleted' ) );
        // the blog pages and the pages which aren't articles below the blog page aren't disabled
        $this->assertSame( 1, $status( 'news' ) );
        $this->assertSame( 1, $status( 'blog' ) );
        $this->assertSame( 1, $status( '' ) );
        $this->assertSame( $root->id, PageView::where( 'domain', 'new.example' )->where( 'path', 'blog' )->firstOrFail()->parent_id );
    }


    public function testRedirectsFormerSlugsOfPosts(): void
    {
        $this->page( 'Root', '', 'new.example', 'root' );
        $this->page( 'Other', 'taken', 'new.example', 'page' );
        $import = fn() => Artisan::call( 'cms:wp-import', ['--connection' => 'wordpress', '--domain' => 'new.example'] );
        $page = fn( string $path ) => PageView::where( 'domain', 'new.example' )->where( 'path', $path )->firstOrFail();

        $db = DB::connection( 'wordpress' );
        $db->table( 'wp_posts' )->insert( [
            ['ID' => 1, 'post_date' => '2026-01-01 00:00:00', 'post_name' => 'old-name', 'post_title' => 'First', 'post_content' => '<p>Text</p>'],
            ['ID' => 2, 'post_date' => '2026-01-02 00:00:00', 'post_name' => 'second', 'post_title' => 'Second', 'post_content' => '<p>Text</p>'],
        ] );

        $this->assertSame( 0, $import() );
        $this->assertSame( '', $page( 'old-name' )->to );

        // the post was renamed twice and the former slug of another post is the current slug of the second post
        $db->table( 'wp_posts' )->where( 'ID', 1 )->update( ['post_name' => 'new-name'] );
        $db->table( 'wp_posts' )->where( 'ID', 2 )->update( ['post_content' => '<p><a href="/2026/01/old-name/">Old</a> <a href="/caf%C3%A9/">Cafe</a></p>'] );
        $db->table( 'wp_postmeta' )->insert( [
            ['post_id' => 1, 'meta_key' => '_wp_old_slug', 'meta_value' => 'old-name'],
            ['post_id' => 1, 'meta_key' => '_wp_old_slug', 'meta_value' => 'caf%c3%a9'],
            ['post_id' => 1, 'meta_key' => '_wp_old_slug', 'meta_value' => 'second'],
        ] );
        $db->table( 'wp_postmeta' )->insert( ['post_id' => 2, 'meta_key' => '_wp_old_slug', 'meta_value' => 'taken'] );

        $this->assertSame( 0, $import() );
        $output = Artisan::output();

        // the page imported before the post was renamed becomes the redirect
        $this->assertSame( '/new-name', $page( 'old-name' )->to );
        $this->assertSame( 2, $page( 'old-name' )->status );
        $this->assertSame( '/new-name', $page( 'café' )->to );
        $this->assertSame( '', $page( 'second' )->to );
        $this->assertSame( '', $page( 'taken' )->to );
        $this->assertStringContainsString( 'Skipped redirect: /taken (URL is used by another page)', $output );
        $this->assertStringContainsString( 'Redirect /café to /new-name', $output );
        $this->assertStringNotContainsString( 'Disabled', $output );
        $this->assertSame( '[Old](/new-name) [Cafe](/new-name)', collect( (array) $page( 'second' )->content )
            ->first( fn( $item ) => $item->type === 'text' )->data->text ?? null );

        // the post renamed back replaces the redirect page
        $db->table( 'wp_posts' )->where( 'ID', 1 )->update( ['post_name' => 'old-name'] );
        $db->table( 'wp_postmeta' )->where( 'meta_value', 'old-name' )->update( ['meta_value' => 'new-name'] );

        $this->assertSame( 0, $import() );

        $this->assertSame( '', $page( 'old-name' )->to );
        $this->assertSame( 1, $page( 'old-name' )->status );
        $this->assertSame( '/old-name', $page( 'new-name' )->to );
        $this->assertSame( '/old-name', $page( 'café' )->to );
    }


    public function testSinceImportsPostsUsingChangedReusableBlocks(): void
    {
        Schema::connection( 'wordpress' )->table( 'wp_posts', fn( Blueprint $table ) => $table->dateTime( 'post_modified_gmt' )->nullable() );
        $this->page( 'Root', '', 'new.example', 'root' );

        $db = DB::connection( 'wordpress' );
        $post = ['post_date' => '2026-08-01 12:00:00', 'post_modified_gmt' => '2026-08-01 12:00:00'];
        $db->table( 'wp_posts' )->insert( [
            ['ID' => 200, 'post_type' => 'wp_block', 'post_name' => 'inner', 'post_title' => 'Inner', 'post_content' => '<!-- wp:paragraph --><p>Old</p><!-- /wp:paragraph -->'] + $post,
            ['ID' => 201, 'post_type' => 'wp_block', 'post_name' => 'outer', 'post_title' => 'Outer', 'post_content' => '<!-- wp:block {"ref":200} /-->'] + $post,
            ['ID' => 1, 'post_type' => 'post', 'post_name' => 'direct', 'post_title' => 'Direct', 'post_content' => '<!-- wp:block {"ref":200} /-->'] + $post,
            ['ID' => 2, 'post_type' => 'post', 'post_name' => 'nested', 'post_title' => 'Nested', 'post_content' => '<!-- wp:block {"ref":201} /-->'] + $post,
            ['ID' => 3, 'post_type' => 'post', 'post_name' => 'other', 'post_title' => 'Other', 'post_content' => '<!-- wp:block {"ref":2000} /-->'] + $post,
        ] );

        $this->assertSame( 0, Artisan::call( 'cms:wp-import', ['--connection' => 'wordpress', '--domain' => 'new.example'] ) );

        $db->table( 'wp_posts' )->where( 'ID', 200 )->update( ['post_content' => '<!-- wp:paragraph --><p>New</p><!-- /wp:paragraph -->', 'post_modified_gmt' => '2026-09-02 12:00:00'] );

        $this->assertSame( 0, Artisan::call( 'cms:wp-import', ['--connection' => 'wordpress', '--domain' => 'new.example', '--since' => '2026-09-01 12:00:00 UTC'] ) );
        $output = Artisan::output();

        $this->assertStringContainsString( 'Updated: Direct', $output );
        $this->assertStringContainsString( 'Updated: Nested', $output );
        $this->assertStringContainsString( 'Unchanged: Other', $output );
        $this->assertStringContainsString( 'New', (string) json_encode( PageView::where( 'path', 'nested' )->firstOrFail()->content ) );
    }


    public function testImportsInlineImagesAsFiles(): void
    {
        $config = ['cms.allow-internal' => config( 'cms.allow-internal' ), 'cms.image.preview-sizes' => config( 'cms.image.preview-sizes' )];
        $this->beforeApplicationDestroyed( fn() => config( $config ) );
        config( ['cms.allow-internal' => true, 'cms.image.preview-sizes' => []] );
        Storage::fake( (string) config( 'cms.disks.public.name', 'public' ) );

        $png = (string) base64_decode( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=' );
        Http::fake( fn() => Http::response( $png, 200, ['Content-Type' => 'image/png'] ) );
        $this->page( 'Root', '', 'new.example', 'root' );

        DB::connection( 'wordpress' )->table( 'wp_posts' )->insert( ['ID' => 1, 'post_date' => '2026-08-01 12:00:00', 'post_name' => 'icons', 'post_title' => 'Icons',
            'post_content' => '<!-- wp:paragraph --><p>Logo <img class="wp-image-5" style="width: 20px;" src="http://127.0.0.1/wp-content/uploads/logo.png" srcset="http://127.0.0.1/wp-content/uploads/logo-2x.png 2x" alt="Logo"> here</p><!-- /wp:paragraph -->'] );

        $this->assertSame( 0, Artisan::call( 'cms:wp-import', ['--connection' => 'wordpress', '--domain' => 'new.example'] ) );
        $page = PageView::where( 'path', 'icons' )->firstOrFail();
        $html = collect( (array) $page->content )->first( fn( $item ) => $item->type === 'html' );
        $file = File::where( 'path', 'not like', 'http%' )->firstOrFail();

        // the image is shown by the URL of the stored file and attached to the page
        $this->assertStringContainsString( 'Logo <img', $html->data->text ?? '' );
        $this->assertStringContainsString( 'src="' . Files::stored( $file ) . '"', $html->data->text ?? '' );
        $this->assertStringNotContainsString( '127.0.0.1', $html->data->text ?? '' );
        $this->assertStringNotContainsString( 'srcset', $html->data->text ?? '' );
        $this->assertContains( $file->id, $page->files()->pluck( 'id' )->all() );
        $this->assertStringContainsString( 'Inline images kept as HTML: ' . Files::stored( $file ), Artisan::output() );
    }


    public function testStoredFileUrls(): void
    {
        $disk = (string) config( 'cms.disks.public.name', 'public' );
        Storage::fake( $disk );

        $this->assertSame( 'https://cdn.example/a.png', Files::stored( ( new File )->forceFill( ['path' => 'https://cdn.example/a.png', 'disk' => 'public'] ) ) );
        // local disks use the URL path only, so the HTML works on all hosts
        $this->assertSame( (string) parse_url( Storage::disk( $disk )->url( 'cms/a.png' ), PHP_URL_PATH ), Files::stored( ( new File )->forceFill( ['path' => 'cms/a.png', 'disk' => 'public'] ) ) );
    }


    public function testSkipsPasswordProtectedReusableBlocks(): void
    {
        $this->page( 'Root', '', 'new.example', 'root' );

        DB::connection( 'wordpress' )->table( 'wp_posts' )->insert( [
            ['ID' => 200, 'post_type' => 'wp_block', 'post_date' => '2026-08-01 12:00:00', 'post_name' => 'secret', 'post_title' => 'Secret',
                'post_password' => 'pass', 'post_content' => '<!-- wp:paragraph --><p>Hidden text</p><!-- /wp:paragraph -->'],
            ['ID' => 1, 'post_type' => 'post', 'post_date' => '2026-08-01 12:00:00', 'post_name' => 'post', 'post_title' => 'Post', 'post_password' => '',
                'post_content' => '<!-- wp:paragraph --><p>Visible</p><!-- /wp:paragraph --><!-- wp:block {"ref":200} /-->'],
        ] );

        $this->assertSame( 0, Artisan::call( 'cms:wp-import', ['--connection' => 'wordpress', '--domain' => 'new.example'] ) );
        $content = (string) json_encode( PageView::where( 'path', 'post' )->firstOrFail()->content );

        $this->assertStringContainsString( 'Visible', $content );
        $this->assertStringNotContainsString( 'Hidden text', $content );
        $this->assertStringContainsString( 'Reusable block 200 not found, unpublished or password protected, skipped', Artisan::output() );
    }


    public function testDisablesRedirectsOfRemovedPosts(): void
    {
        $this->page( 'Root', '', 'new.example', 'root' );
        $args = ['--connection' => 'wordpress', '--domain' => 'new.example'];
        $page = fn( string $path ) => PageView::where( 'domain', 'new.example' )->where( 'path', $path )->firstOrFail();

        $db = DB::connection( 'wordpress' );
        $db->table( 'wp_posts' )->insert( [
            ['ID' => 1, 'post_date' => '2026-01-01 00:00:00', 'post_name' => 'renamed', 'post_title' => 'Renamed', 'post_content' => '<p>Text</p>'],
            ['ID' => 2, 'post_date' => '2026-01-02 00:00:00', 'post_name' => 'kept', 'post_title' => 'Kept', 'post_content' => '<p>Text</p>'],
        ] );
        $db->table( 'wp_postmeta' )->insert( [
            ['post_id' => 1, 'meta_key' => '_wp_old_slug', 'meta_value' => 'first-name'],
            ['post_id' => 2, 'meta_key' => '_wp_old_slug', 'meta_value' => 'old-kept'],
        ] );

        $this->assertSame( 0, Artisan::call( 'cms:wp-import', $args ) );
        $this->assertSame( '/renamed', $page( 'first-name' )->to );

        // valid redirects aren't listed by the dry run
        $this->assertSame( 0, Artisan::call( 'cms:wp-import', $args + ['--dry-run' => true] ) );
        $this->assertStringNotContainsString( 'Disable /', Artisan::output() );

        $db->table( 'wp_posts' )->where( 'ID', 1 )->delete();
        $db->table( 'wp_postmeta' )->where( 'post_id', 1 )->delete();

        $this->assertSame( 0, Artisan::call( 'cms:wp-import', $args + ['--dry-run' => true] ) );
        $this->assertStringContainsString( 'Disable /first-name [en]', Artisan::output() );
        $this->assertSame( 2, $page( 'first-name' )->status );

        $this->assertSame( 0, Artisan::call( 'cms:wp-import', $args ) );

        $this->assertSame( 0, $page( 'first-name' )->status );
        $this->assertSame( 0, $page( 'renamed' )->status );
        $this->assertSame( 2, $page( 'old-kept' )->status );
        $this->assertSame( 1, $page( 'kept' )->status );
    }


    public function testRestrictsPrivatePostsToRoles(): void
    {
        $this->beforeApplicationDestroyed( fn() => Access::using( null ) );
        $this->page( 'Root', '', 'new.example', 'root' );
        $args = ['--connection' => 'wordpress', '--domain' => 'new.example'];
        $access = fn( string $path ) => PageView::where( 'domain', 'new.example' )->where( 'path', $path )->firstOrFail()->accessValues();
        $status = fn( string $path ) => PageView::where( 'domain', 'new.example' )->where( 'path', $path )->firstOrFail()->status;

        $db = DB::connection( 'wordpress' );
        $db->getSchemaBuilder()->create( 'wp_options', function( Blueprint $table ) {
            $table->string( 'option_name' );
            $table->longText( 'option_value' );
        } );
        $db->table( 'wp_options' )->insert( ['option_name' => 'wp_user_roles', 'option_value' => serialize( [
            'administrator' => ['name' => 'Administrator', 'capabilities' => ['read_private_posts' => true]],
            'editor' => ['name' => 'Editor', 'capabilities' => ['read_private_posts' => true]],
            'author' => ['name' => 'Author', 'capabilities' => ['read' => true]],
            'shop_manager' => ['name' => 'Shop manager', 'capabilities' => ['read_private_posts' => true]],
        ] )] );

        $post = ['post_date' => '2026-08-01 12:00:00', 'post_content' => '<!-- wp:paragraph --><p>Text</p><!-- /wp:paragraph -->'];
        $db->table( 'wp_posts' )->insert( [
            ['ID' => 1, 'post_name' => 'internal', 'post_title' => 'Internal', 'post_status' => 'private'] + $post,
            ['ID' => 2, 'post_name' => 'public', 'post_title' => 'Public', 'post_status' => 'publish'] + $post,
        ] );

        Access::using( fn() => ['administrator', 'staff', 'gold'] );

        // invalid roles stop the import
        $this->assertSame( 0, Artisan::call( 'cms:wp-import', $args + ['--role' => ['editor']] ) );
        $this->assertStringContainsString( 'Invalid WordPress role: editor', Artisan::output() );
        $this->assertFalse( PageView::where( 'path', 'internal' )->exists() );

        // roles which can read private posts and exist in Pagible
        $this->assertSame( 0, Artisan::call( 'cms:wp-import', $args + ['--role' => ['editor:staff']] ) );
        $this->assertStringContainsString( 'Restricted [1] Internal to administrator, staff because it is private', Artisan::output() );
        $this->assertSame( ['administrator', 'staff'], $access( 'internal' ) );
        $this->assertSame( 1, $status( 'internal' ) );
        $this->assertNull( $access( 'public' ) );

        // access restrictions set in Pagible are kept
        Permission::system( fn() => PageAccess::set( [(string) PageView::where( 'path', 'public' )->firstOrFail()->id], ['gold'] ) );

        // pages of posts which aren't private any more become public again
        $db->table( 'wp_posts' )->where( 'ID', 1 )->update( ['post_status' => 'publish'] );
        $this->assertSame( 0, Artisan::call( 'cms:wp-import', $args + ['--role' => ['editor:staff']] ) );
        $this->assertStringNotContainsString( 'Restricted', Artisan::output() );
        $this->assertNull( $access( 'internal' ) );
        $this->assertSame( ['gold'], $access( 'public' ) );

        // private posts are disabled if no role exists in Pagible
        $db->table( 'wp_posts' )->where( 'ID', 1 )->update( ['post_status' => 'private'] );
        Access::using( fn() => ['gold'] );

        $this->assertSame( 0, Artisan::call( 'cms:wp-import', $args ) );
        $this->assertStringContainsString( 'Disabled [1] Internal because it is private and the roles "administrator", "editor", "shop_manager" don\'t exist in Pagible', Artisan::output() );
        $this->assertSame( 0, $status( 'internal' ) );
    }


    public function testRedirectsPermalinksOfPosts(): void
    {
        $this->page( 'Root', '', 'new.example', 'root' );
        $args = ['--connection' => 'wordpress', '--domain' => 'new.example'];
        $page = fn( string $path ) => PageView::where( 'domain', 'new.example' )->where( 'path', $path )->firstOrFail();

        $db = DB::connection( 'wordpress' );
        $db->getSchemaBuilder()->create( 'wp_options', function( Blueprint $table ) {
            $table->string( 'option_name' );
            $table->longText( 'option_value' );
        } );
        $db->table( 'wp_options' )->insert( ['option_name' => 'permalink_structure', 'option_value' => '/%year%/%monthnum%/%day%/%postname%/'] );
        $db->table( 'wp_posts' )->insert( [
            ['ID' => 1, 'post_date' => '2026-01-15 08:30:00', 'post_name' => 'first', 'post_title' => 'First', 'post_content' => '<p>Text</p>'],
            ['ID' => 2, 'post_date' => '2026-02-01 00:00:00', 'post_name' => 'second', 'post_title' => 'Second', 'post_content' => '<p>Text</p>'],
        ] );
        $db->table( 'wp_postmeta' )->insert( ['post_id' => 1, 'meta_key' => '_wp_old_slug', 'meta_value' => 'former'] );

        $this->assertSame( 0, Artisan::call( 'cms:wp-import', $args ) );
        $output = Artisan::output();

        // the permalinks of the current and former slugs redirect to the page of the post
        $this->assertSame( '/first', $page( '2026/01/15/first' )->to );
        $this->assertSame( '/first', $page( '2026/01/15/former' )->to );
        $this->assertSame( '/first', $page( 'former' )->to );
        $this->assertSame( '/second', $page( '2026/02/01/second' )->to );
        $this->assertSame( 2, $page( '2026/02/01/second' )->status );
        $this->assertStringContainsString( 'Redirect /2026/01/15/first to /first', $output );

        // redirects of removed posts are disabled
        $db->table( 'wp_posts' )->where( 'ID', 2 )->delete();

        $this->assertSame( 0, Artisan::call( 'cms:wp-import', $args + ['--dry-run' => true] ) );
        $output = Artisan::output();
        $this->assertStringContainsString( 'Disable /2026/02/01/second [en]', $output );
        $this->assertStringNotContainsString( 'Disable /2026/01/15/first', $output );

        $this->assertSame( 0, Artisan::call( 'cms:wp-import', $args ) );
        $this->assertSame( 0, $page( '2026/02/01/second' )->status );
        $this->assertSame( 2, $page( '2026/01/15/first' )->status );

        // permalinks with categories aren't redirected without the taxonomy tables
        $db->table( 'wp_options' )->update( ['option_value' => '/%category%/%postname%/'] );
        $db->table( 'wp_posts' )->insert( ['ID' => 3, 'post_date' => '2026-03-01 00:00:00', 'post_name' => 'third', 'post_title' => 'Third', 'post_content' => '<p>Text</p>'] );

        $this->assertSame( 0, Artisan::call( 'cms:wp-import', $args ) );
        $this->assertStringContainsString( "Permalinks /%category%/%postname%/ aren't redirected because the category of the posts isn't available", Artisan::output() );
        $this->assertFalse( PageView::where( 'domain', 'new.example' )->where( 'to', '/third' )->exists() );
    }


    public function testRedirectsPermalinksWithCategoriesAuthorsAndFormerDates(): void
    {
        $this->page( 'Root', '', 'new.example', 'root' );
        $args = ['--connection' => 'wordpress', '--domain' => 'new.example'];
        $redirects = fn( string $to ) => PageView::where( 'domain', 'new.example' )->where( 'to', $to )->orderBy( 'path' )->pluck( 'path' )->all();

        $db = DB::connection( 'wordpress' );
        $schema = $db->getSchemaBuilder();
        $schema->table( 'wp_posts', fn( Blueprint $table ) => $table->unsignedBigInteger( 'post_author' )->default( 0 ) );
        $this->taxonomyTables( $db );
        $db->table( 'wp_options' )->insert( [
            ['option_name' => 'permalink_structure', 'option_value' => '/%category%/%postname%/'],
            ['option_name' => 'default_category', 'option_value' => '1'],
        ] );
        $db->table( 'wp_terms' )->insert( [
            ['term_id' => 1, 'name' => 'Uncategorized', 'slug' => 'uncategorized'],
            ['term_id' => 2, 'name' => 'News', 'slug' => 'news'],
            ['term_id' => 3, 'name' => 'Local', 'slug' => 'lok%c3%a4l'],
            ['term_id' => 4, 'name' => 'Sports', 'slug' => 'sports'],
        ] );
        $db->table( 'wp_term_taxonomy' )->insert( [
            ['term_taxonomy_id' => 11, 'term_id' => 1, 'taxonomy' => 'category', 'parent' => 0],
            ['term_taxonomy_id' => 12, 'term_id' => 2, 'taxonomy' => 'category', 'parent' => 0],
            ['term_taxonomy_id' => 13, 'term_id' => 3, 'taxonomy' => 'category', 'parent' => 2],
            ['term_taxonomy_id' => 14, 'term_id' => 4, 'taxonomy' => 'category', 'parent' => 0],
        ] );
        $post = ['post_content' => '<p>Text</p>', 'post_author' => 7];
        $db->table( 'wp_posts' )->insert( [
            ['ID' => 1, 'post_date' => '2026-01-15 08:30:00', 'post_name' => 'first', 'post_title' => 'First'] + $post,
            ['ID' => 2, 'post_date' => '2026-02-01 00:00:00', 'post_name' => 'second', 'post_title' => 'Second'] + $post,
            ['ID' => 3, 'post_date' => '2026-03-01 00:00:00', 'post_name' => 'third', 'post_title' => 'Third'] + $post,
        ] );
        // the category with the lowest ID is used, the primary category of the SEO plugins wins, posts without category use the default one
        $db->table( 'wp_term_relationships' )->insert( [
            ['object_id' => 1, 'term_taxonomy_id' => 14],
            ['object_id' => 1, 'term_taxonomy_id' => 13],
            ['object_id' => 2, 'term_taxonomy_id' => 12],
            ['object_id' => 2, 'term_taxonomy_id' => 14],
        ] );
        $db->table( 'wp_postmeta' )->insert( ['post_id' => 2, 'meta_key' => '_yoast_wpseo_primary_category', 'meta_value' => '4'] );

        $this->assertSame( 0, Artisan::call( 'cms:wp-import', $args ) );

        $this->assertSame( ['news/lokäl/first'], $redirects( '/first' ) );
        $this->assertSame( ['sports/second'], $redirects( '/second' ) );
        $this->assertSame( ['uncategorized/third'], $redirects( '/third' ) );

        // permalinks with authors need the users table
        $db->table( 'wp_options' )->where( 'option_name', 'permalink_structure' )->update( ['option_value' => '/%author%/%year%/%monthnum%/%postname%/'] );

        $this->assertSame( 0, Artisan::call( 'cms:wp-import', $args ) );
        $this->assertStringContainsString( "aren't redirected because the author of the posts isn't available", Artisan::output() );

        $schema->create( 'wp_users', function( Blueprint $table ) {
            $table->id( 'ID' );
            $table->string( 'user_nicename' );
        } );
        $db->table( 'wp_users' )->insert( ['ID' => 7, 'user_nicename' => 'jane'] );
        // the permalinks of former dates use the current slug
        $db->table( 'wp_postmeta' )->insert( [
            ['post_id' => 1, 'meta_key' => '_wp_old_date', 'meta_value' => '2025-12-24'],
            ['post_id' => 1, 'meta_key' => '_wp_old_slug', 'meta_value' => 'former'],
        ] );

        $this->assertSame( 0, Artisan::call( 'cms:wp-import', $args ) );
        $this->assertSame( ['former', 'jane/2025/12/first', 'jane/2026/01/first', 'jane/2026/01/former', 'news/lokäl/first'], $redirects( '/first' ) );
        $this->assertSame( ['jane/2026/02/second', 'sports/second'], $redirects( '/second' ) );
    }


    /**
     * Creates the options and taxonomy tables of WordPress.
     */
    protected function taxonomyTables( \Illuminate\Database\Connection $db ): void
    {
        $schema = $db->getSchemaBuilder();
        $schema->create( 'wp_options', function( Blueprint $table ) {
            $table->string( 'option_name' );
            $table->longText( 'option_value' );
        } );
        $schema->create( 'wp_terms', function( Blueprint $table ) {
            $table->bigInteger( 'term_id' );
            $table->string( 'name' );
            $table->string( 'slug' )->default( '' );
        } );
        $schema->create( 'wp_term_taxonomy', function( Blueprint $table ) {
            $table->bigInteger( 'term_taxonomy_id' );
            $table->bigInteger( 'term_id' );
            $table->string( 'taxonomy' );
            $table->bigInteger( 'parent' )->default( 0 );
        } );
        $schema->create( 'wp_term_relationships', function( Blueprint $table ) {
            $table->bigInteger( 'object_id' );
            $table->bigInteger( 'term_taxonomy_id' );
            $table->integer( 'term_order' )->default( 0 );
        } );
    }


    public function testPermalinkOfPost(): void
    {
        $command = new class extends WpImport {
            public function link( string $structure, int|string $id, string $slug, string $date ): ?string
            {
                return $this->permalink( $structure, $id, $slug, $date );
            }
        };

        $this->assertSame( '2026/01/15/08/30/05/my-post', $command->link( '%year%/%monthnum%/%day%/%hour%/%minute%/%second%/%postname%', 1, 'my-post', '2026-01-15 08:30:05' ) );
        $this->assertSame( 'archives/12', $command->link( 'archives/%post_id%', 12, 'my-post', '2026-01-15 08:30:05' ) );
        $this->assertSame( 'blog/my-post.html', $command->link( '/blog/%postname%.html', 1, 'my-post', '2026-01-15 08:30:05' ) );
        $this->assertNull( $command->link( '%postname%', 1, 'café', '' ) );
        $this->assertNull( $command->link( '%postname%', 1, 'my-post', '2026-01-15 08:30:05' ) );
        $this->assertNull( $command->link( '%year%/%postname%', 1, 'my-post', '0000-00-00 00:00:00' ) );
        $this->assertNull( $command->link( '%custom_tag%/%postname%', 1, 'my-post', '2026-01-15 08:30:05' ) );
    }


    public function testImportsMetaOfSeoPlugins(): void
    {
        $this->page( 'Root', '', 'new.example', 'root' );
        $args = ['--connection' => 'wordpress', '--domain' => 'new.example'];
        $page = fn( string $path ) => PageView::where( 'domain', 'new.example' )->where( 'path', $path )->firstOrFail();

        $db = DB::connection( 'wordpress' );
        $post = ['post_date' => '2026-01-01 00:00:00', 'post_content' => '<p>Long text of the post</p>'];
        $db->table( 'wp_posts' )->insert( [
            ['ID' => 1, 'post_name' => 'yoast', 'post_title' => 'Yoast', 'post_excerpt' => ''] + $post,
            ['ID' => 2, 'post_name' => 'rank-math', 'post_title' => 'Rank Math', 'post_excerpt' => ''] + $post,
            ['ID' => 3, 'post_name' => 'templates', 'post_title' => 'Templates', 'post_excerpt' => 'The <b>excerpt</b>'] + $post,
        ] );
        $db->table( 'wp_postmeta' )->insert( [
            ['post_id' => 1, 'meta_key' => '_yoast_wpseo_title', 'meta_value' => 'Yoast &amp; SEO title'],
            ['post_id' => 1, 'meta_key' => '_yoast_wpseo_metadesc', 'meta_value' => 'Yoast description'],
            ['post_id' => 1, 'meta_key' => '_yoast_wpseo_canonical', 'meta_value' => 'https://other.example/yoast'],
            ['post_id' => 1, 'meta_key' => '_yoast_wpseo_meta-robots-noindex', 'meta_value' => '1'],
            ['post_id' => 1, 'meta_key' => '_yoast_wpseo_meta-robots-nofollow', 'meta_value' => '1'],
            ['post_id' => 1, 'meta_key' => '_yoast_wpseo_opengraph-title', 'meta_value' => 'Social title'],
            ['post_id' => 2, 'meta_key' => 'rank_math_title', 'meta_value' => 'Rank Math title'],
            ['post_id' => 2, 'meta_key' => 'rank_math_description', 'meta_value' => 'Rank Math description'],
            ['post_id' => 2, 'meta_key' => 'rank_math_robots', 'meta_value' => serialize( ['index', 'nofollow'] )],
            ['post_id' => 2, 'meta_key' => 'rank_math_canonical_url', 'meta_value' => 'not a URL'],
            ['post_id' => 3, 'meta_key' => '_yoast_wpseo_title', 'meta_value' => '%%title%% %%sep%% %%sitename%%'],
            ['post_id' => 3, 'meta_key' => 'rank_math_description', 'meta_value' => '%excerpt%'],
        ] );

        $this->assertSame( 0, Artisan::call( 'cms:wp-import', $args ) );

        $yoast = $page( 'yoast' );
        $this->assertSame( 'Yoast', $yoast->name );
        $this->assertSame( 'Yoast & SEO title', $yoast->title );
        $this->assertSame( 'Yoast description', $yoast->meta->{'meta-tags'}->data->description ?? null );
        $this->assertSame( 'https://other.example/yoast', $yoast->meta->canonical->data->url ?? null );
        $this->assertEquals( (object) ['index' => 'noindex', 'follow' => 'nofollow'], $yoast->meta->robots->data ?? null );
        $this->assertSame( 'Social title', $yoast->meta->{'social-media'}->data->title ?? null );
        $this->assertSame( 'Yoast description', $yoast->meta->{'social-media'}->data->description ?? null );

        $rankMath = $page( 'rank-math' );
        $this->assertSame( 'Rank Math title', $rankMath->title );
        $this->assertSame( 'Rank Math description', $rankMath->meta->{'meta-tags'}->data->description ?? null );
        $this->assertEquals( (object) ['index' => 'index', 'follow' => 'nofollow'], $rankMath->meta->robots->data ?? null );
        $this->assertNull( $rankMath->meta->canonical ?? null );
        $this->assertNull( $rankMath->meta->{'social-media'} ?? null );

        // templates of the SEO plugins aren't used, the excerpt is the description
        $templates = $page( 'templates' );
        $this->assertSame( 'Templates', $templates->title );
        $this->assertSame( 'The excerpt', $templates->meta->{'meta-tags'}->data->description ?? null );
        $this->assertNull( $templates->meta->robots ?? null );

        // search engines are discouraged from indexing the whole site
        $db->getSchemaBuilder()->create( 'wp_options', function( Blueprint $table ) {
            $table->string( 'option_name' );
            $table->longText( 'option_value' );
        } );
        $db->table( 'wp_options' )->insert( ['option_name' => 'blog_public', 'option_value' => '0'] );
        $db->table( 'wp_posts' )->update( ['post_content' => '<p>Changed text</p>'] );

        $this->assertSame( 0, Artisan::call( 'cms:wp-import', $args ) );
        $this->assertStringContainsString( 'Search engines are discouraged from indexing the WordPress site ("blog_public" option)', Artisan::output() );
        $this->assertEquals( (object) ['index' => 'noindex', 'follow' => 'nofollow'], $page( 'rank-math' )->meta->robots->data ?? null );
        $this->assertEquals( (object) ['index' => 'noindex', 'follow' => 'nofollow'], $page( 'templates' )->meta->robots->data ?? null );

        // the setting is ignored on request
        $db->table( 'wp_posts' )->update( ['post_content' => '<p>Text changed again</p>'] );

        $this->assertSame( 0, Artisan::call( 'cms:wp-import', $args + ['--index' => true] ) );
        $this->assertStringNotContainsString( 'Search engines are discouraged', Artisan::output() );
        $this->assertEquals( (object) ['index' => 'index', 'follow' => 'nofollow'], $page( 'rank-math' )->meta->robots->data ?? null );
        $this->assertNull( $page( 'templates' )->meta->robots ?? null );
    }


    public function testImportsTemplatesRobotsDefaultsAndCanonicalUrlsOfSeoPlugins(): void
    {
        $this->page( 'Root', '', 'new.example', 'root' );
        $args = ['--connection' => 'wordpress', '--domain' => 'new.example'];
        $page = fn( string $path ) => PageView::where( 'domain', 'new.example' )->where( 'path', $path )->firstOrFail();

        $db = DB::connection( 'wordpress' );
        $db->getSchemaBuilder()->create( 'wp_options', function( Blueprint $table ) {
            $table->string( 'option_name' );
            $table->longText( 'option_value' );
        } );
        $db->table( 'wp_options' )->insert( [
            ['option_name' => 'home', 'option_value' => 'https://wp.example'],
            ['option_name' => 'blogname', 'option_value' => 'My &amp; Blog'],
            ['option_name' => 'wpseo_titles', 'option_value' => serialize( ['separator' => 'sc-pipe', 'noindex-post' => true] )],
        ] );
        $post = ['post_date' => '2026-01-01 00:00:00', 'post_content' => '<p>Text of the post</p>'];
        $db->table( 'wp_posts' )->insert( [
            ['ID' => 1, 'post_name' => 'first', 'post_title' => 'First'] + $post,
            ['ID' => 2, 'post_name' => 'second', 'post_title' => 'Second'] + $post,
            ['ID' => 3, 'post_name' => 'third', 'post_title' => 'Third'] + $post,
        ] );
        $db->table( 'wp_postmeta' )->insert( [
            ['post_id' => 1, 'meta_key' => '_yoast_wpseo_title', 'meta_value' => 'Custom %%title%% %%page%% %%sep%% %%sitename%%'],
            ['post_id' => 1, 'meta_key' => '_yoast_wpseo_metadesc', 'meta_value' => '%%excerpt%%'],
            ['post_id' => 1, 'meta_key' => '_yoast_wpseo_canonical', 'meta_value' => 'https://www.wp.example/2026/01/second/'],
            ['post_id' => 2, 'meta_key' => '_yoast_wpseo_title', 'meta_value' => '%%cf_custom%% %%sep%% %%sitename%%'],
            ['post_id' => 2, 'meta_key' => '_yoast_wpseo_meta-robots-noindex', 'meta_value' => '2'],
            ['post_id' => 2, 'meta_key' => '_yoast_wpseo_canonical', 'meta_value' => 'https://wp.example/category/news/'],
            ['post_id' => 3, 'meta_key' => '_yoast_wpseo_canonical', 'meta_value' => 'https://other.example/third'],
        ] );

        $this->assertSame( 0, Artisan::call( 'cms:wp-import', $args ) );
        $output = Artisan::output();

        // the variables are replaced and the separator of the settings is used
        $first = $page( 'first' );
        $this->assertSame( 'Custom First | My & Blog', $first->title );
        $this->assertSame( 'Text of the post', $first->meta->{'meta-tags'}->data->description ?? null );
        // canonical URLs to posts point to their pages, others to the WordPress site are removed
        $this->assertSame( '/second', $first->meta->canonical->data->url ?? null );
        $this->assertNull( $page( 'second' )->meta->canonical ?? null );
        $this->assertStringContainsString( "Removed canonical URL https://wp.example/category/news/ because it isn't an imported post", $output );
        $this->assertSame( 'https://other.example/third', $page( 'third' )->meta->canonical->data->url ?? null );

        // values with unknown variables are skipped
        $this->assertSame( 'Second', $page( 'second' )->title );

        // posts without own settings use the default robots settings for posts
        $this->assertEquals( (object) ['index' => 'noindex'], $first->meta->robots->data ?? null );
        $this->assertEquals( (object) ['index' => 'index'], $page( 'second' )->meta->robots->data ?? null );
    }


    public function testImportsCategoriesAndTagsAsKeywords(): void
    {
        $this->page( 'Root', '', 'new.example', 'root' );
        $args = ['--connection' => 'wordpress', '--domain' => 'new.example'];
        $keywords = fn( string $path ) => PageView::where( 'domain', 'new.example' )->where( 'path', $path )->firstOrFail()->meta->{'meta-tags'}->data->keywords ?? null;

        $db = DB::connection( 'wordpress' );
        $post = ['post_date' => '2026-01-01 00:00:00', 'post_content' => '<p>Text</p>'];
        $db->table( 'wp_posts' )->insert( [
            ['ID' => 1, 'post_name' => 'tagged', 'post_title' => 'Tagged'] + $post,
            ['ID' => 2, 'post_name' => 'plain', 'post_title' => 'Plain'] + $post,
        ] );

        // missing taxonomy tables are tolerated
        $this->assertSame( 0, Artisan::call( 'cms:wp-import', $args ) );
        $this->assertNull( $keywords( 'tagged' ) );

        $this->taxonomyTables( $db );

        $db->table( 'wp_options' )->insert( ['option_name' => 'default_category', 'option_value' => '1'] );
        $db->table( 'wp_terms' )->insert( [
            ['term_id' => 1, 'name' => 'Uncategorized'],
            ['term_id' => 2, 'name' => 'News &amp; Events'],
            ['term_id' => 3, 'name' => 'laravel'],
            ['term_id' => 4, 'name' => 'Menu'],
        ] );
        $db->table( 'wp_term_taxonomy' )->insert( [
            ['term_taxonomy_id' => 11, 'term_id' => 1, 'taxonomy' => 'category'],
            ['term_taxonomy_id' => 12, 'term_id' => 2, 'taxonomy' => 'category'],
            ['term_taxonomy_id' => 13, 'term_id' => 3, 'taxonomy' => 'post_tag'],
            ['term_taxonomy_id' => 14, 'term_id' => 4, 'taxonomy' => 'nav_menu'],
        ] );
        $db->table( 'wp_term_relationships' )->insert( [
            ['object_id' => 1, 'term_taxonomy_id' => 11],
            ['object_id' => 1, 'term_taxonomy_id' => 12],
            ['object_id' => 1, 'term_taxonomy_id' => 13],
            ['object_id' => 1, 'term_taxonomy_id' => 14],
            ['object_id' => 2, 'term_taxonomy_id' => 11],
        ] );
        $db->table( 'wp_posts' )->update( ['post_content' => '<p>Changed text</p>'] );
        $db->table( 'wp_postmeta' )->insert( [
            ['post_id' => 1, 'meta_key' => '_yoast_wpseo_focuskw', 'meta_value' => 'cms migration'],
            ['post_id' => 1, 'meta_key' => 'rank_math_focus_keyword', 'meta_value' => 'Laravel,wordpress import'],
        ] );

        // the focus keyphrases come first, the default category and other taxonomies aren't keywords
        $this->assertSame( 0, Artisan::call( 'cms:wp-import', $args ) );
        $this->assertSame( 'cms migration, Laravel, wordpress import, News & Events', $keywords( 'tagged' ) );
        $this->assertNull( $keywords( 'plain' ) );
    }


    public function testMetaOfPosts(): void
    {
        $meta = new WordPress\Meta( ['name' => 'Site', 'description' => 'Tagline', 'rankMath' => ['title_separator' => '&raquo;']] );

        $this->assertSame( 'Title', $meta->title( ['_yoast_wpseo_title' => '%%cf_field%%', 'rank_math_title' => '<b>Title</b>'], 'Post' ) );
        $this->assertSame( 'Post » Site', $meta->title( ['rank_math_title' => '%title% %page% %sep% %sitename%'], 'Post' ) );
        $this->assertSame( 'Post - Tagline', $meta->title( ['_yoast_wpseo_title' => '%%sep%% %%title%% %%sep%% %%primary_category%% %%sep%% %%sitedesc%% %%sep%%'], 'Post' ) );
        $this->assertNull( $meta->title( ['_yoast_wpseo_title' => '%%cf_custom%% text'], 'Post' ) );
        $this->assertNull( $meta->title( ['rank_math_title' => '%customterm(genre)%'], 'Post' ) );
        $this->assertNull( $meta->title( ['_yoast_wpseo_title' => '%%page%% %%sep%%'], 'Post' ) );

        $this->assertSame( [
            'meta-tags' => ['type' => 'meta-tags', 'data' => ['description' => 'Intro', 'keywords' => 'a, b'], 'files' => []],
            'social-media' => ['type' => 'social-media', 'data' => ['title' => 'Post', 'description' => 'Intro', 'file' => ['id' => 'F1', 'type' => 'file']], 'files' => ['F1']],
            'canonical' => ['type' => 'canonical', 'data' => ['url' => '/other'], 'files' => []],
        ], $meta->build( ['_yoast_wpseo_canonical' => '/other', '_yoast_wpseo_meta-robots-noindex' => '0'], ['a', 'b', 'A'], 'Intro', 'Post', 'F1' ) );

        $this->assertSame( ['type' => 'robots', 'data' => ['index' => 'index'], 'files' => []],
            $meta->build( ['_yoast_wpseo_meta-robots-noindex' => '2', 'rank_math_robots' => 'invalid'], [], '', 'Post', null )['robots'] ?? null );
        $this->assertSame( [], $meta->build( [], [], '', 'Post', null ) );

        // the canonical URL is mapped, the robots defaults of Rank Math are used for posts without own settings
        $meta = new WordPress\Meta( ['rankMath' => ['pt_post_custom_robots' => 'on', 'pt_post_robots' => ['noindex', 'nofollow'], 'robots_global' => ['index']]], false, fn( $url ) => null );

        $this->assertNull( $meta->build( ['rank_math_canonical_url' => 'https://wp.example/x'], [], '', 'Post', null )['canonical'] ?? null );
        $this->assertSame( ['index' => 'noindex', 'follow' => 'nofollow'], $meta->build( [], [], '', 'Post', null )['robots']['data'] ?? null );
        $this->assertSame( ['index' => 'index'], $meta->build( ['rank_math_robots' => serialize( ['index'] )], [], '', 'Post', null )['robots']['data'] ?? null );
        $this->assertSame( ['index' => 'index'], ( new WordPress\Meta( ['rankMath' => ['robots_global' => ['index']]] ) )->build( [], [], '', 'Post', null )['robots']['data'] ?? null );
        $this->assertSame( ['index' => 'noindex', 'follow' => 'nofollow'], ( new WordPress\Meta( [], true ) )->build( [], [], '', 'Post', null )['robots']['data'] ?? null );
    }


    public function testConvertsTheLanguagesOfCodeAndCodeShortcodes(): void
    {
        $warnings = [];
        $wp = new WordPress\Html(
            fn( string $tag ) => null,
            fn( string $url ) => 'file:' . $url,
            function( string $msg ) use ( &$warnings ) { $warnings[] = $msg; }
        );
        $data = fn( array $result ) => array_map( fn( $el ) => [$el['type'], $el['data']], $result['elements'] );

        // SyntaxHighlighter blocks have the language only in the block attributes
        $result = $wp->elements( '<!-- wp:syntaxhighlighter/code {"language":"PHP"} --><pre class="wp-block-syntaxhighlighter-code">echo $a &lt; 1;</pre><!-- /wp:syntaxhighlighter/code -->'
            . '<!-- wp:syntaxhighlighter/code --><pre class="wp-block-syntaxhighlighter-code">plain</pre><!-- /wp:syntaxhighlighter/code -->' );
        $this->assertSame( [
            ['code', ['language' => 'php', 'text' => 'echo $a < 1;']],
            ['code', ['language' => '', 'text' => 'plain']],
        ], $data( $result ) );

        // languages of Prism and highlight.js, SyntaxHighlighter and WP-Syntax, text languages aren't code languages
        $result = $wp->elements( '<!-- wp:code --><pre class="wp-block-code"><code class="language-js">a()</code></pre><!-- /wp:code -->'
            . '<!-- wp:code --><pre class="brush: python; gutter: false">b()</pre><!-- /wp:code -->'
            . '<!-- wp:code --><pre lang="ruby">c()</pre><!-- /wp:code -->'
            . '<!-- wp:code --><pre xml:lang="ruby" lang="en-US" data-lang="go">d()</pre><!-- /wp:code -->' );
        $this->assertSame( [
            ['code', ['language' => 'js', 'text' => 'a()']],
            ['code', ['language' => 'python', 'text' => 'b()']],
            ['code', ['language' => 'ruby', 'text' => 'c()']],
            ['code', ['language' => '', 'text' => 'd()']],
        ], $data( $result ) );

        // code shortcodes of WordPress.com keep the code as it is without line breaks added by wpautop()
        $result = $wp->elements( "Intro\n\n[code language=\"css\"]\n.a {\n  color: red;\n}\n\n.b > p { }\n[/code]\n\n"
            . "[sourcecode lang=\"HTML\"]&lt;p&gt;[x]&lt;/p&gt;[/sourcecode]\n\n[code]\n[/code]\n\nOutro" );
        $this->assertSame( [
            ['text', ['text' => 'Intro']],
            ['code', ['language' => 'css', 'text' => ".a {\n  color: red;\n}\n\n.b > p { }"]],
            ['code', ['language' => 'html', 'text' => '<p>[x]</p>']],
            ['text', ['text' => 'Outro']],
        ], $data( $result ) );

        // code shortcodes in shortcode blocks
        $result = $wp->elements( "<!-- wp:shortcode -->\n[code lang=sql]SELECT * FROM t WHERE a < 1[/code]\n<!-- /wp:shortcode -->" );
        $this->assertSame( [['code', ['language' => 'sql', 'text' => 'SELECT * FROM t WHERE a < 1']]], $data( $result ) );
        $this->assertSame( [], $warnings );
    }


    public function testReplacesVariablesOfPostsAndUsesTemplatesOfSeoPlugins(): void
    {
        $post = ['id' => 5, 'date' => '2026-03-05 10:00:00', 'modified' => '2026-04-01 08:00:00', 'author' => 'Jane Doe',
            'categories' => [3 => 'Events', 7 => 'News'], 'tags' => ['cms', 'laravel']];
        $meta = new WordPress\Meta( ['name' => 'Site', 'dateFormat' => 'j. F Y'], false, null, 'de' );

        // Yoast lists all categories and tags, the primary category is empty if none is chosen
        $this->assertSame( 'Post - Events, News - cms, laravel - Jane Doe - 5 - 5. März 2026 - 1. April 2026 - 2026 März 05 - kw',
            $meta->title( ['_yoast_wpseo_title' => '%%title%% %%sep%% %%category%% %%sep%% %%tag%% %%sep%% %%primary_category%% %%sep%% %%name%% %%sep%% %%id%%'
                . ' %%sep%% %%date%% %%sep%% %%modified%% %%sep%% %%post_year%% %%post_month%% %%post_day%% %%sep%% %%focuskw%%', '_yoast_wpseo_focuskw' => 'kw'], 'Post', '', $post ) );
        $this->assertSame( 'News', $meta->title( ['_yoast_wpseo_title' => '%%primary_category%%', '_yoast_wpseo_primary_category' => '7'], 'Post', '', $post ) );
        // primary categories which aren't categories of the post are ignored by Yoast
        $this->assertSame( 'Post', $meta->title( ['_yoast_wpseo_title' => '%%title%% %%primary_category%%', '_yoast_wpseo_primary_category' => '9'], 'Post', '', $post ) );

        // Rank Math has the first category and tag by name in "category" and "tag" and ignores the primary category, dates can have a format
        $this->assertSame( 'Events - Events, News - cms - cms, laravel - 03/2026 - a - a, b',
            $meta->title( ['rank_math_title' => '%category% %sep% %categories% %sep% %tag% %sep% %tags% %sep% %date(m/Y)% %sep% %focuskw% %sep% %keywords%',
                'rank_math_primary_category' => '7', 'rank_math_focus_keyword' => 'a, b'], 'Post', '', $post ) );
        // Rank Math has no primary category variable
        $this->assertNull( $meta->title( ['rank_math_title' => '%title% %primary_category%'], 'Post', '', $post ) );
        // the own SEO title and description of Rank Math or the title and excerpt
        $this->assertSame( 'Own Post | Intro', $meta->build( ['rank_math_title' => 'Own %title%', 'rank_math_facebook_title' => '%seo_title% | %seo_description%'],
            [], 'Intro', 'Post', null, $post )['social-media']['data']['title'] ?? null );
        $this->assertSame( 'Post', $meta->title( ['rank_math_title' => '%seo_title%'], 'Post', '', $post ) );

        // values which aren't available and the current date are unknown
        $this->assertNull( $meta->title( ['_yoast_wpseo_title' => '%%name%% %%category%%'], 'Post', '', ['id' => 5] ) );
        $this->assertNull( $meta->title( ['_yoast_wpseo_title' => '%%currentyear%%'], 'Post', '', $post ) );
        $this->assertNull( $meta->title( ['_yoast_wpseo_title' => '%%date%%'], 'Post', '', ['date' => '0000-00-00 00:00:00'] ) );
        // posts without categories have empty category variables
        $this->assertSame( 'Post', $meta->title( ['_yoast_wpseo_title' => '%%title%% %%sep%% %%category%% %%primary_category%%'], 'Post', '', ['categories' => []] ) );

        // the templates for posts of the active plugin are used for posts without own values
        $site = ['name' => 'Site', 'yoast' => ['title-post' => '%%title%% %%page%% %%sep%% %%sitename%%', 'metadesc-post' => '%%category%%: %%excerpt%%'],
            'rankMath' => ['pt_post_title' => '%title% | %sitename%', 'pt_post_description' => '%excerpt%']];

        $meta = new WordPress\Meta( $site + ['plugins' => ['wordpress-seo/wp-seo.php']] );
        $this->assertSame( 'Post - Site', $meta->title( [], 'Post', '', $post ) );
        $this->assertSame( 'Own', $meta->title( ['_yoast_wpseo_title' => 'Own'], 'Post', '', $post ) );
        $this->assertSame( 'Events, News: Intro', $meta->build( [], [], 'Intro', 'Post', null, $post )['meta-tags']['data']['description'] ?? null );
        // templates with unknown variables are skipped
        $this->assertSame( 'Intro', $meta->build( [], [], 'Intro', 'Post', null, ['id' => 5] )['meta-tags']['data']['description'] ?? null );

        $this->assertSame( 'Post | Site', ( new WordPress\Meta( $site + ['plugins' => ['seo-by-rank-math/rank-math.php']] ) )->title( [], 'Post', '', $post ) );
        // the options of deactivated plugins stay, so their templates aren't used
        $this->assertNull( ( new WordPress\Meta( $site + ['plugins' => ['akismet/akismet.php']] ) )->title( [], 'Post', '', $post ) );
        // the templates of the plugins with options are used if the active plugins aren't known
        $this->assertSame( 'Post - Site', ( new WordPress\Meta( $site ) )->title( [], 'Post', '', $post ) );

        $this->assertTrue( ( new WordPress\Meta( $site ) )->variables( [] ) );
        $this->assertTrue( ( new WordPress\Meta() )->variables( [1 => ['_yoast_wpseo_title' => '%%title%%']] ) );
        $this->assertFalse( ( new WordPress\Meta() )->variables( [1 => ['_yoast_wpseo_title' => 'Title']] ) );
    }


    public function testImportsValuesOfPostsForVariablesOfSeoPlugins(): void
    {
        $this->page( 'Root', '', 'new.example', 'root' );
        $args = ['--connection' => 'wordpress', '--domain' => 'new.example', '--lang' => 'en'];
        $page = fn( string $path ) => PageView::where( 'domain', 'new.example' )->where( 'path', $path )->firstOrFail();

        $db = DB::connection( 'wordpress' );
        $schema = $db->getSchemaBuilder();
        $schema->table( 'wp_posts', function( Blueprint $table ) {
            $table->unsignedBigInteger( 'post_author' )->default( 0 );
            $table->dateTime( 'post_modified' )->nullable();
        } );
        $schema->create( 'wp_users', function( Blueprint $table ) {
            $table->id( 'ID' );
            $table->string( 'display_name' );
        } );
        $this->taxonomyTables( $db );

        $db->table( 'wp_users' )->insert( ['ID' => 7, 'display_name' => 'Jane &amp; John'] );
        $db->table( 'wp_options' )->insert( [
            ['option_name' => 'blogname', 'option_value' => 'Blog'],
            ['option_name' => 'date_format', 'option_value' => 'Y-m-d'],
            ['option_name' => 'default_category', 'option_value' => '1'],
            ['option_name' => 'wpseo_titles', 'option_value' => serialize( ['title-post' => '%%title%% %%sep%% %%primary_category%% %%sep%% %%sitename%%'] )],
            ['option_name' => 'rank-math-options-titles', 'option_value' => serialize( ['pt_post_title' => '%title% from Rank Math'] )],
            ['option_name' => 'active_plugins', 'option_value' => serialize( ['wordpress-seo/wp-seo.php'] )],
        ] );
        $db->table( 'wp_terms' )->insert( [
            ['term_id' => 1, 'name' => 'Uncategorized'],
            ['term_id' => 2, 'name' => 'Releases'],
            ['term_id' => 3, 'name' => 'News &amp; Events'],
            ['term_id' => 4, 'name' => 'laravel'],
        ] );
        $db->table( 'wp_term_taxonomy' )->insert( [
            ['term_taxonomy_id' => 11, 'term_id' => 1, 'taxonomy' => 'category'],
            ['term_taxonomy_id' => 12, 'term_id' => 2, 'taxonomy' => 'category'],
            ['term_taxonomy_id' => 13, 'term_id' => 3, 'taxonomy' => 'category'],
            ['term_taxonomy_id' => 14, 'term_id' => 4, 'taxonomy' => 'post_tag'],
        ] );
        $db->table( 'wp_term_relationships' )->insert( [
            ['object_id' => 1, 'term_taxonomy_id' => 12],
            ['object_id' => 1, 'term_taxonomy_id' => 13],
            ['object_id' => 1, 'term_taxonomy_id' => 14],
            ['object_id' => 2, 'term_taxonomy_id' => 12],
            ['object_id' => 2, 'term_taxonomy_id' => 13],
            ['object_id' => 3, 'term_taxonomy_id' => 11],
        ] );
        $post = ['post_date' => '2026-01-02 10:00:00', 'post_modified' => '2026-02-03 11:00:00', 'post_author' => 7, 'post_content' => '<p>Text</p>'];
        $db->table( 'wp_posts' )->insert( [
            ['ID' => 1, 'post_name' => 'first', 'post_title' => 'First'] + $post,
            ['ID' => 2, 'post_name' => 'second', 'post_title' => 'Second'] + $post,
            ['ID' => 3, 'post_name' => 'third', 'post_title' => 'Third'] + $post,
        ] );
        $db->table( 'wp_postmeta' )->insert( [
            ['post_id' => 1, 'meta_key' => '_yoast_wpseo_metadesc', 'meta_value' => 'By %%name%% on %%date%%, updated %%modified%%: %%tag%%'],
            ['post_id' => 2, 'meta_key' => '_yoast_wpseo_primary_category', 'meta_value' => '3'],
            ['post_id' => 3, 'meta_key' => '_yoast_wpseo_metadesc', 'meta_value' => 'In %%category%%'],
        ] );

        $this->assertSame( 0, Artisan::call( 'cms:wp-import', $args ) );

        // the template of the active plugin is used and the primary category is empty if none is chosen
        $this->assertSame( 'First - Blog', $page( 'first' )->title );
        $this->assertSame( 'By Jane & John on 2026-01-02, updated 2026-02-03: laravel', $page( 'first' )->meta->{'meta-tags'}->data->description ?? null );
        $this->assertSame( 'Second - News & Events - Blog', $page( 'second' )->title );
        // the default category is a category of the post like in WordPress
        $this->assertSame( 'In Uncategorized', $page( 'third' )->meta->{'meta-tags'}->data->description ?? null );
    }


    public function testUsesTitlesOrIdsForPostsWithoutSlug(): void
    {
        $this->page( 'Root', '', 'new.example', 'root' );

        DB::connection( 'wordpress' )->table( 'wp_posts' )->insert( [
            ['ID' => 7, 'post_date' => '2026-01-01 00:00:00', 'post_name' => '', 'post_title' => 'Hello World', 'post_content' => '<p>Text</p>'],
            ['ID' => 8, 'post_date' => '2026-01-02 00:00:00', 'post_name' => '', 'post_title' => '???', 'post_content' => '<p>Text</p>'],
            ['ID' => 9, 'post_date' => '2026-01-03 00:00:00', 'post_name' => '', 'post_title' => '', 'post_content' => '<p><a href="/?p=8">Link</a></p>'],
        ] );

        $this->assertSame( 0, Artisan::call( 'cms:wp-import', ['--connection' => 'wordpress', '--domain' => 'new.example'] ) );

        // WordPress uses the post ID as slug if the title contains no characters for the slug
        $this->assertSame( 'Hello World', PageView::where( 'domain', 'new.example' )->where( 'path', 'hello-world' )->firstOrFail()->name );
        $this->assertSame( '???', PageView::where( 'domain', 'new.example' )->where( 'path', '8' )->firstOrFail()->name );
        $this->assertSame( 'Root', PageView::where( 'domain', 'new.example' )->where( 'path', '' )->firstOrFail()->name );

        $texts = collect( (array) PageView::where( 'domain', 'new.example' )->where( 'path', '9' )->firstOrFail()->content )
            ->map( fn( $item ) => $item->data->text ?? null )->filter()->implode( ' ' );
        $this->assertStringContainsString( '[Link](/8)', $texts );
    }


    public function testRewritesLinksToEndpointsOfPosts(): void
    {
        $warnings = [];
        $links = new WordPress\Links( ['https://wp.example'], [5 => 'my-post'], function( string $msg ) use ( &$warnings ) {
            $warnings[] = $msg;
        }, '/%year%/%monthnum%/%postname%/' );

        // comment pages, embeds and trackbacks show the post, the comment feeds aren't available
        $html = '<a href="https://wp.example/2024/05/my-post/comment-page-2/#comments">A</a> <a href="/2024/05/my-post/embed/">B</a>'
            . ' <a href="/2024/05/my-post/trackback/">C</a> <a href="/2024/05/my-post/feed/">D</a> <a href="/2024/05/my-post/feed/atom/">E</a>'
            . ' <a href="/?name=my-post">F</a> <a href="/index.php?name=other">G</a>';

        $this->assertSame( '<a href="/my-post#comments">A</a> <a href="/my-post">B</a>'
            . ' <a href="/my-post">C</a> <a href="/2024/05/my-post/feed/">D</a> <a href="/2024/05/my-post/feed/atom/">E</a>'
            . ' <a href="/my-post">F</a> <a href="/index.php?name=other">G</a>', $links->rewrite( $html ) );
        $this->assertSame( ["  Links to posts which aren't imported kept: /index.php?name=other"], $warnings );

        // the endpoints follow the post ID in numeric permalinks too
        $links = new WordPress\Links( ['https://wp.example'], [123 => 'my-post'], null, '/archives/%post_id%' );

        $this->assertSame( '<a href="/my-post#' . WordPress\Html::anchorId( 123, 'c' ) . '">A</a> <a href="/my-post">B</a> <a href="/archives/123/feed/">C</a>',
            $links->rewrite( '<a href="/archives/123/comment-page-3/#c">A</a> <a href="/archives/123/embed">B</a> <a href="/archives/123/feed/">C</a>' ) );
    }


    public function testUsesOnlySettingsOfActiveSeoPlugins(): void
    {
        $this->page( 'Root', '', 'new.example', 'root' );
        $page = fn( string $path ) => PageView::where( 'domain', 'new.example' )->where( 'path', $path )->firstOrFail();

        $db = DB::connection( 'wordpress' );
        $this->taxonomyTables( $db );
        // the settings of Yoast SEO stay after switching to Rank Math
        $db->table( 'wp_options' )->insert( [
            ['option_name' => 'active_plugins', 'option_value' => serialize( ['seo-by-rank-math/rank-math.php'] )],
            ['option_name' => 'wpseo_titles', 'option_value' => serialize( ['noindex-post' => true, 'title-post' => '%%title%% Yoast'] )],
            ['option_name' => 'default_category', 'option_value' => '1'],
        ] );
        $db->table( 'wp_terms' )->insert( [
            ['term_id' => 1, 'name' => 'Uncategorized', 'slug' => 'uncategorized'],
            ['term_id' => 2, 'name' => 'News', 'slug' => 'news'],
            ['term_id' => 4, 'name' => 'Sports', 'slug' => 'sports'],
        ] );
        $db->table( 'wp_term_taxonomy' )->insert( [
            ['term_taxonomy_id' => 11, 'term_id' => 1, 'taxonomy' => 'category', 'parent' => 0],
            ['term_taxonomy_id' => 12, 'term_id' => 2, 'taxonomy' => 'category', 'parent' => 0],
            ['term_taxonomy_id' => 14, 'term_id' => 4, 'taxonomy' => 'category', 'parent' => 0],
        ] );
        $db->table( 'wp_posts' )->insert( [
            ['ID' => 1, 'post_date' => '2026-01-01 00:00:00', 'post_name' => 'first', 'post_title' => 'First', 'post_content' => '<p>Text</p>'],
            ['ID' => 2, 'post_date' => '2026-01-02 00:00:00', 'post_name' => 'second', 'post_title' => 'Second', 'post_content' => '<p>Text</p>'],
        ] );
        $db->table( 'wp_term_relationships' )->insert( [
            ['object_id' => 1, 'term_taxonomy_id' => 12],
            ['object_id' => 1, 'term_taxonomy_id' => 14],
            ['object_id' => 2, 'term_taxonomy_id' => 12],
            ['object_id' => 2, 'term_taxonomy_id' => 14],
        ] );
        $db->table( 'wp_postmeta' )->insert( [
            ['post_id' => 1, 'meta_key' => '_yoast_wpseo_title', 'meta_value' => 'Old title'],
            ['post_id' => 1, 'meta_key' => '_yoast_wpseo_metadesc', 'meta_value' => 'Old description'],
            ['post_id' => 1, 'meta_key' => '_yoast_wpseo_canonical', 'meta_value' => 'https://other.example/old'],
            ['post_id' => 1, 'meta_key' => '_yoast_wpseo_primary_category', 'meta_value' => '4'],
            ['post_id' => 1, 'meta_key' => 'rank_math_title', 'meta_value' => 'New title'],
            ['post_id' => 1, 'meta_key' => 'rank_math_description', 'meta_value' => 'New description'],
            ['post_id' => 2, 'meta_key' => 'rank_math_primary_category', 'meta_value' => '4'],
            ['post_id' => 2, 'meta_key' => 'rank_math_description', 'meta_value' => '%excerpt_only% %excerpt%'],
        ] );

        $this->assertSame( 0, Artisan::call( 'cms:wp-import', ['--connection' => 'wordpress', '--domain' => 'new.example'] ) );

        $first = $page( 'first' );
        $this->assertSame( 'New title', $first->title );
        $this->assertSame( 'New description', $first->meta->{'meta-tags'}->data->description ?? null );
        $this->assertNull( $first->meta->canonical ?? null );
        $this->assertNull( $first->meta->robots ?? null );
        // the template of the deactivated plugin isn't used
        $this->assertSame( 'Second', $page( 'second' )->title );
        // posts without manual excerpt have no "excerpt_only" and Rank Math generates the excerpt from the first paragraph
        $this->assertSame( 'Text', $page( 'second' )->meta->{'meta-tags'}->data->description ?? null );

        // the primary categories of active plugins are used by the permalinks, otherwise the category with the lowest ID
        $this->assertSame( [1 => 'news', 2 => 'sports'], ( new WordPress\Reader( $db ) )->categoryPaths() );
        $this->assertSame( [1 => ['rank_math_title' => 'New title', 'rank_math_description' => 'New description']], ( new WordPress\Reader( $db ) )->seo( [1] ) );

        // the settings of all plugins are used if the active plugins are unknown
        $db->table( 'wp_options' )->where( 'option_name', 'active_plugins' )->delete();

        $this->assertSame( [1 => 'sports', 2 => 'sports'], ( new WordPress\Reader( $db ) )->categoryPaths() );
        $reader = new WordPress\Reader( $db );
        $this->assertSame( ['_yoast_wpseo_canonical', '_yoast_wpseo_metadesc', '_yoast_wpseo_primary_category', '_yoast_wpseo_title', 'rank_math_description', 'rank_math_title'],
            collect( $reader->seo( [1] )[1] ?? [] )->keys()->sort()->values()->all() );
        $this->assertSame( ['yoast', 'rankMath'], WordPress\Reader::seoPlugins( null ) );
        $this->assertSame( ['yoast'], WordPress\Reader::seoPlugins( ['akismet/akismet.php', 'wordpress-seo-premium/wp-seo-premium.php'] ) );
        $this->assertSame( [], WordPress\Reader::seoPlugins( [] ) );
    }


    public function testReplacesExcerptsLikeSeoPlugins(): void
    {
        $meta = new WordPress\Meta( ['name' => 'Site'] );
        $desc = fn( array $seo, array $post, string $intro = 'Intro…' ) => $meta->build( $seo, [], $intro, 'Post', null, $post )['meta-tags']['data']['description'] ?? null;
        $long = str_repeat( 'word ', 40 ) . 'end';
        $content = '<!-- wp:paragraph --><p>First <b>paragraph</b> [gallery ids="1"]</p><!-- /wp:paragraph -->' . "\n\n"
            . '<!-- wp:heading --><h2>Heading with apple</h2><!-- /wp:heading -->' . "\n\n"
            . '<!-- wp:paragraph --><p>Green Apples are tasty</p><!-- /wp:paragraph -->';

        // "excerpt_only" is only the manual excerpt
        $this->assertSame( 'Manual', $desc( ['_yoast_wpseo_metadesc' => '%%excerpt_only%%'], ['excerpt' => 'Manual', 'content' => $content] ) );
        $this->assertSame( 'Post - Site', $meta->title( ['_yoast_wpseo_title' => '%%title%% %%sep%% %%excerpt_only%% %%sep%% %%sitename%%'], 'Post', 'Intro', ['excerpt' => '', 'content' => $content] ) );
        $this->assertSame( 'Post', $meta->title( ['rank_math_title' => '%title% %excerpt_only%'], 'Post', 'Intro', ['excerpt' => '', 'content' => $content] ) );

        // the manual excerpt is used by both plugins
        $this->assertSame( 'Manual', $desc( ['rank_math_description' => '%excerpt%'], ['excerpt' => 'Manual', 'content' => $content] ) );

        // Yoast SEO shortens the whole content to 156 characters and the last space without ellipsis
        $this->assertSame( 'First paragraph Heading with apple Green Apples are tasty', $desc( ['_yoast_wpseo_metadesc' => '%%excerpt%%'], ['excerpt' => '', 'content' => $content] ) );
        $this->assertSame( rtrim( str_repeat( 'word ', 31 ) ), $desc( ['_yoast_wpseo_metadesc' => '%%excerpt%%'], ['excerpt' => '', 'content' => '<p>' . $long . '</p><!--more--><p>More</p>'] ) );
        $this->assertSame( rtrim( str_repeat( 'word ', 16 ) ), ( new WordPress\Meta( [], false, null, 'ja' ) )->build( ['_yoast_wpseo_metadesc' => '%%excerpt%%'], [], '', 'Post', null,
            ['excerpt' => '', 'content' => $long] )['meta-tags']['data']['description'] ?? null );

        // Rank Math uses the paragraph with the focus keyword or the first paragraph without shortening it
        $this->assertSame( 'Green Apples are tasty', $desc( ['rank_math_description' => '%excerpt%', 'rank_math_focus_keyword' => 'green apples, pears'], ['excerpt' => '', 'content' => $content] ) );
        $this->assertSame( 'First paragraph', $desc( ['rank_math_description' => '%excerpt%'], ['excerpt' => '', 'content' => $content] ) );
        $this->assertSame( 'Classic text', $desc( ['rank_math_description' => '%excerpt%'], ['excerpt' => '', 'content' => "Classic text\n\n<h2>Head</h2>\n\nMore text"] ) );
        $this->assertSame( $long, $meta->title( ['rank_math_title' => '%excerpt%'], 'Post', 'Intro', ['excerpt' => '', 'content' => $long] ) );

        // without the values of the post, the introduction is used
        $this->assertSame( 'Intro…', $desc( ['_yoast_wpseo_metadesc' => '%%excerpt_only%%'], [] ) );
    }


    public function testUsesTitleSettingsAndVariablesOfRankMath(): void
    {
        $site = ['name' => 'Site', 'rankMath' => ['capitalize_titles' => 'on', 'pt_post_title' => '%title% %sep% %sitename%']];
        $meta = new WordPress\Meta( $site + ['plugins' => ['seo-by-rank-math/rank-math.php']] );
        $post = ['date' => '2026-03-05 10:00:00', 'modified' => '2026-03-01 08:00:00', 'author' => 'jane doe'];

        // Rank Math capitalizes the words of its titles but not the ones starting with other characters than ASCII letters
        $this->assertSame( 'How To Use 2nd élan (beta) - Site', $meta->title( [], 'how to use 2nd élan (beta)', '', $post ) );
        $this->assertSame( 'Own Title', $meta->title( ['rank_math_title' => 'own title'], 'Post', '', $post ) );
        $social = fn( array $seo ) => $meta->build( $seo, [], 'Intro', 'Post', null, $post )['social-media']['data']['title'] ?? null;
        $this->assertSame( 'Social Title', $social( ['rank_math_facebook_title' => 'social title'] ) );
        // Yoast titles and descriptions aren't capitalized
        $this->assertSame( 'yoast title', $meta->title( ['_yoast_wpseo_title' => 'yoast title'], 'Post', '', $post ) );
        $this->assertSame( 'own description', $meta->build( ['rank_math_description' => 'own description'], [], 'Intro', 'Post', null, $post )['meta-tags']['data']['description'] ?? null );
        // only if the setting is enabled and Rank Math is active
        $this->assertSame( 'own title', ( new WordPress\Meta( ['rankMath' => ['capitalize_titles' => 'off']] ) )->title( ['rank_math_title' => 'own title'], 'Post' ) );
        $this->assertSame( 'own title', ( new WordPress\Meta( $site + ['plugins' => []] ) )->title( ['rank_math_title' => 'own title'], 'Post' ) );

        // Rank Math uses the later date as modification date, Yoast the modification date
        $meta = new WordPress\Meta( ['dateFormat' => 'Y-m-d'] );
        $this->assertSame( '2026-03-05', $meta->title( ['rank_math_title' => '%modified%'], 'Post', '', $post ) );
        $this->assertSame( '2026-03-01', $meta->title( ['_yoast_wpseo_title' => '%%modified%%'], 'Post', '', $post ) );
        $this->assertSame( '2026-04-01', $meta->title( ['rank_math_title' => '%modified%'], 'Post', '', ['modified' => '2026-04-01 08:00:00'] + $post ) );

        // "post_author" of Rank Math is the display name of the author like "name"
        $this->assertSame( 'jane doe jane doe', $meta->title( ['rank_math_title' => '%post_author% %name%'], 'Post', '', $post ) );
        $this->assertNull( $meta->title( ['rank_math_title' => '%post_author%'], 'Post' ) );
    }


    public function testBuildsSeoTitlesAndDescriptionsLikeRankMath(): void
    {
        $site = ['name' => 'Site', 'plugins' => ['seo-by-rank-math/rank-math.php'],
            'rankMath' => ['pt_post_title' => '%seo_title% %sep% %sitename%', 'pt_post_description' => '%title%: %seo_description%']];
        $meta = new WordPress\Meta( $site );
        $desc = fn( array $seo, array $post ) => $meta->build( $seo, [], 'Intro', 'Post', null, $post )['meta-tags']['data']['description'] ?? null;
        $social = fn( array $seo, array $post ) => $meta->build( $seo, [], 'Intro', 'Post', 'file1', $post )['social-media']['data'] ?? [];
        $manual = ['excerpt' => 'Manual excerpt', 'content' => '<p>First paragraph</p>'];
        $generated = ['excerpt' => '', 'content' => '<p>First paragraph</p>'];

        // Rank Math uses the manual excerpt before the description template
        $this->assertSame( 'Manual excerpt', $desc( [], $manual ) );
        $this->assertSame( 'Own', $desc( ['rank_math_description' => 'Own'], $manual ) );
        // "%seo_title%" and "%seo_description%" in templates are the title and the excerpt
        $this->assertSame( 'Post: First paragraph', $desc( ['rank_math_title' => 'Own title'], $generated ) );
        $this->assertSame( 'Post - Site', $meta->title( ['rank_math_description' => 'Own'], 'Post', 'Intro', $generated ) );

        // "%seo_title%" and "%seo_description%" in own values are the SEO title and description of the page
        $this->assertSame( 'Post - Site | Manual excerpt', $social( ['rank_math_facebook_title' => '%seo_title% | %seo_description%'], $manual )['title'] ?? null );
        $this->assertSame( 'Own title | Post: First paragraph', $social( ['rank_math_title' => 'Own title', 'rank_math_facebook_title' => '%seo_title% | %seo_description%'], $generated )['title'] ?? null );
        $this->assertSame( 'Post - Site', $social( ['rank_math_facebook_description' => '%seo_title%'], $generated )['description'] ?? null );
        // without templates, the title of the post and no description are used
        $this->assertSame( 'Post', ( new WordPress\Meta( ['plugins' => ['seo-by-rank-math/rank-math.php']] ) )->title( ['rank_math_title' => '%seo_title% %seo_description%'], 'Post', 'Intro', $generated ) );
    }
}
