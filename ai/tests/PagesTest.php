<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests;

use Aimeos\Cms\Translation;
use Aimeos\Cms\Jobs\TranslatePage;
use Aimeos\Cms\Models\PageView;
use Aimeos\Cms\Models\PageVariant;
use Aimeos\Cms\Facades\Pages;
use Aimeos\Cms\Facades\Variants;
use Aimeos\Cms\Tenancy;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Queue;


class PagesTest extends TranslateTestAbstract
{
    public function testDispatchUser()
    {
        Queue::fake();

        $page = $this->page();
        $user = $this->user( ['page:save'] );
        \Aimeos\Cms\Permission::set( $user, ['page:save'], ['de'] );

        // users who can't add variants only update existing ones
        $this->assertSame( 0, Translation\Pages::dispatch( [$page->id], ['de'], $user )['total'] );
        $this->assertSame( 1, Translation\Pages::dispatch( [$page->id], ['de'], $this->user )['total'] );

        $this->expectException( \Aimeos\Cms\Exceptions\PermissionException::class );
        Translation\Pages::dispatch( [$page->id], ['de', 'fr'], $user );
    }


    public function testDispatchSkipsQueued()
    {
        Queue::fake();
        $page = $this->page();

        $this->assertSame( 1, Translation\Pages::dispatch( [$page->id], ['de'], $this->user )['total'] );
        $batch = Translation\Pages::dispatch( [$page->id], ['de', 'en'], $this->user );

        // "de" is already queued and "en" is the source language
        $this->assertSame( 0, $batch['total'] );
        Queue::assertPushed( TranslatePage::class, 1 );
    }


    public function testDispatchTreeOrder()
    {
        Queue::fake();

        $first = $this->page();
        $second = $this->page();

        config( ['cms.locales' => ['en', 'de', 'fr']] );
        Translation\Pages::dispatch( [$second->id, $first->id], ['de', 'fr'], $this->user );

        $pairs = Queue::pushed( TranslatePage::class )->map( fn( $job ) => [$job->id, $job->lang] )->values()->all();
        $this->assertSame( [[$first->id, 'de'], [$first->id, 'fr'], [$second->id, 'de'], [$second->id, 'fr']], $pairs );
    }


    public function testDispatchUniqueFor()
    {
        Queue::fake();

        $page = $this->page();
        $limit = config( 'cms.ai.ratelimit' );
        config( ['cms.locales' => ['en', 'de', 'fr'], 'cms.ai.ratelimit' => 1] );

        try {
            Translation\Pages::dispatch( [$page->id], ['de', 'fr'], $this->user );
        } finally {
            config( ['cms.locales' => ['en', 'de'], 'cms.ai.ratelimit' => $limit] );
        }

        // locks of lost jobs expire after the jobs would have been retried
        Queue::assertPushed( TranslatePage::class, fn( $job ) => $job->uniqueFor === $job->delay + TranslatePage::RETRY + 600 );
        Queue::assertPushed( TranslatePage::class, fn( $job ) => $job->lang === 'fr' && $job->delay === 60 );
    }


    public function testDispatchTooMany()
    {
        Queue::fake();

        $page = $this->page();
        $limit = config( 'cms.ai.ratelimit' );
        $langs = array_map( fn( $idx ) => sprintf( 'de-%03d', $idx ), range( 1, intdiv( TranslatePage::RETRY, 60 ) + 1 ) );
        config( ['cms.locales' => $langs, 'cms.ai.ratelimit' => 1] );

        try {
            $this->expectException( \Aimeos\Cms\Exception::class );
            Translation\Pages::dispatch( [$page->id], $langs, $this->user );
        } finally {
            config( ['cms.locales' => ['en', 'de'], 'cms.ai.ratelimit' => $limit] );
            Queue::assertNothingPushed();
        }
    }


    public function testDispatchChunks()
    {
        Queue::fake();

        $page = $this->page();
        $langs = array_map( fn( $idx ) => sprintf( 'de-%03d', $idx ), range( 1, 600 ) );
        config( ['cms.locales' => $langs] );

        try {
            $batch = Translation\Pages::dispatch( [$page->id], [...$langs, 'de-001'], $this->user );
        } finally {
            config( ['cms.locales' => ['en', 'de']] );
        }

        $this->assertSame( 600, $batch['total'] );
        $this->assertSame( ['total' => 600, 'done' => 0, 'failed' => 0], Translation\Pages::progress( $batch['id'] ) );
        Queue::assertPushed( TranslatePage::class, 600 );
    }


    public function testDispatchInvalidLang()
    {
        $this->expectException( \Aimeos\Cms\Exception::class );
        $this->expectExceptionMessage( 'Invalid language code "xx"' );

        Translation\Pages::dispatch( ['page-1'], ['de', 'xx'], $this->user );
    }


    public function testDispatchInvalidLangs()
    {
        $this->expectException( \Aimeos\Cms\Exception::class );
        $this->expectExceptionMessage( 'Invalid language codes "xx", "yy"' );

        Translation\Pages::dispatch( ['page-1'], ['xx', 'yy'], $this->user );
    }


    public function testDispatchVariantLangNotConfigured()
    {
        Queue::fake();

        $page = $this->page();
        Variants::translate( $page->id, 'de', $this->user );
        PageVariant::where( 'page_id', $page->id )->where( 'lang', 'de' )->update( ['lang' => 'fr', 'stale' => true] );

        $batch = Translation\Pages::dispatch( [$page->id], ['fr'], $this->user );
        $this->assertSame( 1, $batch['total'] );
    }


    public function testDispatchVariantLangNotConfiguredNoAdd()
    {
        Queue::fake();

        $page = $this->page();
        $other = PageView::where( 'id', '!=', $page->id )->whereNull( 'deleted_at' )->firstOrFail();
        Variants::translate( $page->id, 'de', $this->user );
        PageVariant::where( 'page_id', $page->id )->where( 'lang', 'de' )->update( ['lang' => 'fr', 'stale' => true] );

        // unconfigured languages of other pages mustn't become available for all pages
        $batch = Translation\Pages::dispatch( [$page->id, $other->id], ['fr'], $this->user );

        $this->assertSame( 1, $batch['total'] );
        Queue::assertPushed( TranslatePage::class, fn( $job ) => $job->id === $page->id );
        Queue::assertNotPushed( TranslatePage::class, fn( $job ) => $job->id === $other->id );
    }


    public function testDispatchNoLocales()
    {
        Queue::fake();
        config( ['cms.locales' => []] );

        try {
            $batch = Translation\Pages::dispatch( [$this->page()->id], ['es'], $this->user );
            $this->assertSame( 1, $batch['total'] );

            $this->expectException( \Aimeos\Cms\Exception::class );
            Translation\Pages::dispatch( ['page-1'], ['no language'], $this->user );
        } finally {
            config( ['cms.locales' => ['en', 'de']] );
        }
    }


    public function testDispatchPending()
    {
        Queue::fake();

        $pages = [$this->page(), $this->page(), $this->page(), $this->page()];
        $ids = array_map( fn( $page ) => (string) $page->id, $pages );

        foreach( array_slice( $ids, 1 ) as $id ) {
            Variants::translate( $id, 'de', $this->user );
        }

        // up to date, stale and trashed variants in "de"
        PageVariant::where( 'page_id', $ids[1] )->where( 'lang', 'de' )->update( ['stale' => false] );
        PageVariant::where( 'page_id', $ids[2] )->where( 'lang', 'de' )->update( ['stale' => true] );
        PageVariant::where( 'page_id', $ids[3] )->where( 'lang', 'de' )->update( ['stale' => true, 'deleted_at' => now()] );

        $batch = Translation\Pages::dispatch( $ids, ['de', 'en'], $this->user );

        $this->assertSame( 2, $batch['total'] );
        Queue::assertPushed( TranslatePage::class, fn( $job ) => $job->id === $ids[0] && $job->lang === 'de' );
        Queue::assertPushed( TranslatePage::class, fn( $job ) => $job->id === $ids[2] && $job->lang === 'de' );
        Queue::assertNotPushed( TranslatePage::class, fn( $job ) => $job->lang === 'en' );
    }


    public function testDispatchPendingNoAdd()
    {
        Queue::fake();

        $missing = $this->page();
        $stale = $this->page();

        Variants::translate( $stale->id, 'de', $this->user );
        PageVariant::where( 'page_id', $stale->id )->where( 'lang', 'de' )->update( ['stale' => true] );

        $batch = Translation\Pages::dispatch( [$missing->id, $stale->id], ['de'], $this->user( ['page:save'] ) );

        $this->assertSame( 1, $batch['total'] );
        Queue::assertPushed( TranslatePage::class, fn( $job ) => $job->id === $stale->id );
    }


    public function testDispatchPendingTrashedPage()
    {
        Queue::fake();

        $page = $this->page();
        Pages::dropMany( [$page->id], $this->user );

        $this->expectException( \Aimeos\Cms\Exception::class );
        $this->expectExceptionMessage( 'Page not found' );
        Translation\Pages::dispatch( [$page->id], ['de'], $this->user );
    }


    public function testProgress()
    {
        $page = $this->page();
        $this->fake( $page );

        config( ['cms.queue.connection' => 'database'] );

        try
        {
            $batch = Translation\Pages::dispatch( [$page->id], ['de'], $this->user );
            $this->assertSame( ['total' => 1, 'done' => 0, 'failed' => 0], Translation\Pages::progress( $batch['id'] ) );

            Artisan::call( 'queue:work', ['connection' => 'database', '--queue' => config( 'cms.queue.name' ) ?: 'default', '--stop-when-empty' => true,
                // the worker stops after each job if the test process already uses more memory
                '--memory' => (int) ceil( memory_get_usage( true ) / 1048576 ) + 256] );
        }
        finally
        {
            config( ['cms.queue.connection' => null] );
        }

        $this->assertSame( ['total' => 1, 'done' => 1, 'failed' => 0], Translation\Pages::progress( $batch['id'] ) );
        $this->assertEquals( '[de] Title', PageView::language( 'de' )->findOrFail( $page->id )->latest->data->title );
        $this->assertNull( Translation\Pages::progress( 'unknown' ) );

        // batches of other tenants and of the application aren't reported
        $this->assertNull( Tenancy::run( 'other', fn() => Translation\Pages::progress( $batch['id'] ) ) );
        $this->assertNull( Translation\Pages::progress( \Illuminate\Support\Facades\Bus::batch( [] )->name( 'app' )->dispatch()->id ) );
    }
}
