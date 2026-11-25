<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests\Typo3;

use Aimeos\Cms\Import\Typo3;
use Illuminate\Support\Collection;


/**
 * Tests the selection of the TYPO3 content records and the backend layouts.
 */
class RecordsTest extends TestAbstract
{
    public function testLayoutsSortByBackendLayoutColumns(): void
    {
        $child = (object) ['uid' => 2, 'pid' => 1];
        $pagets = (object) ['uid' => 4, 'pid' => 0, 'backend_layout' => 'pagets__x',
            'TSconfig' => "mod.web_layout.BackendLayouts.x {\n config { colPos = 7\n colPos = 0 }\n}"];
        $plain = (object) ['uid' => 5, 'pid' => 0];
        $pages = Collection::make( [1 => (object) ['uid' => 1, 'pid' => 0, 'backend_layout_next_level' => 'db__3'], 2 => $child, 4 => $pagets, 5 => $plain] );
        $records = Collection::make( [
            (object) ['uid' => 10, 'colPos' => 0, 'sorting' => 1],
            (object) ['uid' => 11, 'colPos' => 2, 'sorting' => 5],
            (object) ['uid' => 9, 'colPos' => 2, 'sorting' => 5],
            (object) ['uid' => 12, 'colPos' => 7, 'sorting' => 0],
        ] );
        $layout = new Typo3\Layouts( $pages, $this->layouts( [3 => (object) ['config' => 'rows { 1 { columns { 1 { colPos = 2 } 2 { colPos = 0 } } } }']] ) );

        // content in columns which aren't part of the backend layout isn't shown by TYPO3
        $this->assertSame( [9, 11, 10], $layout->sort( $records, $child )->pluck( 'uid' )->all() );
        $this->assertSame( [12, 10], $layout->sort( $records, $pagets )->pluck( 'uid' )->all() );
        // without backend layout, the main column comes first
        $this->assertSame( [10, 9, 11, 12], $layout->sort( $records, $plain )->pluck( 'uid' )->all() );
    }


    public function testLayoutsCopyAndRemoveTsconfigBlocks(): void
    {
        $tsconfig = "mod.web_layout.BackendLayouts {\n base {\n  config { colPos = 3 }\n }\n"
            . " copy < .base\n gone {\n  config { colPos = 4 }\n }\n}\n"
            . "[backend.user.isAdmin]\nmod.web_layout.BackendLayouts.gone >\n[END]\n"
            . "mod.web_layout.BackendLayouts.abs < mod.web_layout.BackendLayouts.base\n";
        $root = (object) ['uid' => 1, 'pid' => 0, 'TSconfig' => $tsconfig];
        $pages = Collection::make( [
            1 => $root,
            2 => (object) ['uid' => 2, 'pid' => 1, 'backend_layout' => 'pagets__copy'],
            3 => (object) ['uid' => 3, 'pid' => 1, 'backend_layout' => 'pagets__abs'],
            4 => (object) ['uid' => 4, 'pid' => 1, 'backend_layout' => 'pagets__gone'],
        ] );
        $records = Collection::make( [(object) ['uid' => 10, 'colPos' => 0], (object) ['uid' => 11, 'colPos' => 3], (object) ['uid' => 12, 'colPos' => 4]] );
        $layout = new Typo3\Layouts( $pages, $this->layouts( [] ) );

        // relative and absolute copies use the columns of the referenced layout
        $this->assertSame( [11], $layout->sort( $records, $pages[2] )->pluck( 'uid' )->all() );
        $this->assertSame( [11], $layout->sort( $records, $pages[3] )->pluck( 'uid' )->all() );
        // removed layouts are unknown, so the main column comes first
        $this->assertSame( [10, 11, 12], $layout->sort( $records, $pages[4] )->pluck( 'uid' )->all() );
    }


    public function testLayoutsResolveColumnsOncePerLayout(): void
    {
        $page = (object) ['uid' => 1, 'pid' => 0, 'backend_layout' => 'db__3'];
        $other = (object) ['uid' => 2, 'pid' => 0, 'backend_layout' => 'db__3'];
        $layout = new class( Collection::make( [1 => $page, 2 => $other] ), $this->layouts( [3 => (object) ['config' => 'colPos = 1']] ) ) extends Typo3\Layouts {
            /** @var list<object> */
            public array $resolved = [];

            protected function resolve( object $t3Page ): array
            {
                $this->resolved[] = $t3Page;
                return parent::resolve( $t3Page );
            }
        };
        $records = Collection::make( [(object) ['uid' => 10, 'colPos' => 0], (object) ['uid' => 11, 'colPos' => 1]] );

        foreach( [$page, $page, $other, $page] as $t3Page ) {
            $this->assertSame( [11], $layout->sort( $records, $t3Page )->pluck( 'uid' )->all() );
        }

        // pages using the same database layout share its columns
        $this->assertSame( [$page], $layout->resolved );
    }


    public function testRecordsSelectsAndExpandsPageContent(): void
    {
        $root = (object) ['uid' => 1, 'pid' => 0];
        $clone = (object) ['uid' => 2, 'pid' => 1, 'content_from_pid' => 3];
        $source = (object) ['uid' => 3, 'pid' => 1];
        $pages = $this->pages( [1 => $root, 2 => $clone, 3 => $source] );
        $content = $this->records( [
            10 => (object) ['uid' => 10, 'pid' => 1, 'CType' => 'text', 'colPos' => 0, 'sorting' => 1, 'sys_language_uid' => 0],
            11 => (object) ['uid' => 11, 'pid' => 1, 'CType' => 'shortcut', 'records' => 'tt_content_30', 'colPos' => 2, 'sorting' => 2, 'sys_language_uid' => 0],
            12 => (object) ['uid' => 12, 'pid' => 1, 'CType' => 'text', 'colPos' => 0, 'sorting' => 4, 'sys_language_uid' => -1],
            30 => (object) ['uid' => 30, 'pid' => 3, 'CType' => 'text', 'colPos' => 0, 'sorting' => 5, 'sys_language_uid' => 0],
            31 => (object) ['uid' => 31, 'pid' => 3, 'CType' => 'text', 'colPos' => 0, 'sorting' => 6, 'sys_language_uid' => 0],
        ] );
        $translated = $this->records( [1 => Collection::make( [
            (object) ['uid' => 20, 'pid' => 1, 'CType' => 'text', 'colPos' => 0, 'sorting' => 3, 'sys_language_uid' => 1],
            (object) ['uid' => 21, 'pid' => 1, 'CType' => 'text', 'colPos' => 0, 'sorting' => 5, 'sys_language_uid' => 3],
        ] )] );
        $fetched = $translations = [];
        $records = new Typo3\Records( $pages,
            fn( array $pids ) => $content->whereIn( 'pid', $pids )->values(),
            function( array $pids ) use ( $translated, &$translations ) {
                $translations[] = $pids[0];
                return $translated->get( $pids[0] ) ?? Collection::make();
            },
            function( array $uids ) use ( $content, &$fetched ) {
                $fetched[] = $uids;
                return Collection::make( $uids )->mapWithKeys( fn( int $uid ) => [$uid => $content->get( $uid )] )->filter();
            },
            $content->where( 'CType', 'shortcut' )->values(),
            new Typo3\Layouts( $pages, $this->layouts( [] ) )
        );

        // the targets of the shortcuts are fetched once for all records of the page
        $page = $records->page( $root );
        $this->assertSame( [[30]], $fetched );
        // without backend layout, the main column comes first
        $this->assertSame( [10, 12, 30], $page->pluck( 'uid' )->all() );
        $this->assertSame( [null, null, 'reference'], $page->pluck( '_pagible_shared' )->all() );
        $this->assertSame( [null, null, 3], $page->pluck( '_pagible_shared_page' )->all() );
        $this->assertSame( [null, null, 30], $page->pluck( '_pagible_shared_uid' )->all() );
        $this->assertSame( [0, 0, 2], $page->pluck( 'colPos' )->all() );
        $this->assertSame( [1, 4, 2], $page->pluck( 'sorting' )->all() );

        $this->assertSame( [20, 12], $records->page( $root, 1 )->pluck( 'uid' )->all() );
        $this->assertSame( [12], $records->page( $root, 2 )->pluck( 'uid' )->all() );
        $this->assertSame( [12, 21], $records->page( $root, 3 )->pluck( 'uid' )->all() );

        // the translated records of all languages are fetched once per page
        $this->assertSame( [1], $translations );

        foreach( [$records->page( $clone ), $records->page( $source )] as $shared )
        {
            $this->assertSame( [30, 31], $shared->pluck( 'uid' )->all() );
            $this->assertSame( ['reference', 'reference'], $shared->pluck( '_pagible_shared' )->all() );
            $this->assertSame( [3, 3], $shared->pluck( '_pagible_shared_page' )->all() );
        }
    }


    public function testRecordsUseOneLevelOfContentFromPid(): void
    {
        $pages = $this->pages( [
            1 => (object) ['uid' => 1, 'pid' => 0, 'content_from_pid' => 2],
            2 => (object) ['uid' => 2, 'pid' => 0, 'content_from_pid' => 3],
            3 => (object) ['uid' => 3, 'pid' => 0],
            4 => (object) ['uid' => 4, 'pid' => 0, 'content_from_pid' => 5],
            5 => (object) ['uid' => 5, 'pid' => 0, 'doktype' => 4, 'shortcut' => 3, 'shortcut_mode' => 0],
            6 => (object) ['uid' => 6, 'pid' => 0, 'content_from_pid' => 6],
        ] );
        $content = $this->records( [
            20 => (object) ['uid' => 20, 'pid' => 2, 'CType' => 'text', 'colPos' => 0, 'sorting' => 1, 'sys_language_uid' => 0],
            30 => (object) ['uid' => 30, 'pid' => 3, 'CType' => 'text', 'colPos' => 0, 'sorting' => 1, 'sys_language_uid' => 0],
            60 => (object) ['uid' => 60, 'pid' => 6, 'CType' => 'text', 'colPos' => 0, 'sorting' => 1, 'sys_language_uid' => 0],
        ] );
        $records = new Typo3\Records( $pages,
            fn( array $pids ) => $content->whereIn( 'pid', $pids )->values(),
            fn( array $pids ) => Collection::make(),
            fn( array $uids ) => Collection::make(),
            Collection::make(),
            new Typo3\Layouts( $pages, $this->layouts( [] ) )
        );

        // TYPO3 doesn't follow the content_from_pid of the source page
        $this->assertSame( [20], $records->page( $pages[1] )->pluck( 'uid' )->all() );
        // but it uses the target of a shortcut source page
        $this->assertSame( [30], $records->page( $pages[4] )->pluck( 'uid' )->all() );
        $this->assertSame( [60], $records->page( $pages[6] )->pluck( 'uid' )->all() );
    }


    public function testRecordsCachesContentAndTargets(): void
    {
        $root = (object) ['uid' => 1, 'pid' => 0];
        $other = (object) ['uid' => 4, 'pid' => 1];
        $nested = (object) ['uid' => 5, 'pid' => 1];
        $pages = $this->pages( [1 => $root, 4 => $other, 5 => $nested] );
        $content = $this->records( [
            10 => (object) ['uid' => 10, 'pid' => 1, 'CType' => 'shortcut', 'records' => '30,31', 'sorting' => 1, 'sys_language_uid' => 0],
            11 => (object) ['uid' => 11, 'pid' => 1, 'CType' => 'shortcut', 'records' => '32', 'sorting' => 2, 'sys_language_uid' => 0],
            12 => (object) ['uid' => 12, 'pid' => 4, 'CType' => 'shortcut', 'records' => '33', 'sorting' => 1, 'sys_language_uid' => 0],
            30 => (object) ['uid' => 30, 'pid' => 9, 'CType' => 'text', 'sorting' => 1, 'sys_language_uid' => 0],
            31 => (object) ['uid' => 31, 'pid' => 9, 'CType' => 'text', 'sorting' => 2, 'sys_language_uid' => 0],
            32 => (object) ['uid' => 32, 'pid' => 9, 'CType' => 'text', 'sorting' => 3, 'sys_language_uid' => 0],
            33 => (object) ['uid' => 33, 'pid' => 9, 'CType' => 'text', 'sorting' => 4, 'sys_language_uid' => 0],
            13 => (object) ['uid' => 13, 'pid' => 5, 'CType' => 'shortcut', 'records' => '34', 'sorting' => 1, 'sys_language_uid' => 0],
            34 => (object) ['uid' => 34, 'pid' => 9, 'CType' => 'shortcut', 'records' => '35,36', 'sorting' => 5, 'sys_language_uid' => 0],
            35 => (object) ['uid' => 35, 'pid' => 9, 'CType' => 'text', 'sorting' => 6, 'sys_language_uid' => 0],
            36 => (object) ['uid' => 36, 'pid' => 9, 'CType' => 'text', 'sorting' => 7, 'sys_language_uid' => 0],
        ] );
        $fetchedPids = $fetched = $translations = [];
        $records = new class( $pages,
            function( array $pids ) use ( $content, &$fetchedPids ) {
                array_push( $fetchedPids, ...$pids );
                return $content->whereIn( 'pid', $pids )->values();
            },
            function( array $pids ) use ( &$translations ) {
                array_push( $translations, ...$pids );
                return Collection::make();
            },
            function( array $uids ) use ( $content, &$fetched ) {
                $fetched[] = $uids;
                return Collection::make( $uids )->mapWithKeys( fn( int $uid ) => [$uid => $content->get( $uid )] )->filter();
            },
            $content->where( 'CType', 'shortcut' )->values(),
            new Typo3\Layouts( $pages, $this->layouts( [] ) )
        ) extends Typo3\Records {
            protected const CACHE_SIZE = 2;
        };

        // the content of a page is fetched once for the page and its translations
        $this->assertSame( [30, 31, 32], $records->page( $root )->pluck( 'uid' )->all() );
        $this->assertSame( [], $records->page( $root, 1 )->pluck( 'uid' )->all() );
        $this->assertSame( [33], $records->page( $other )->pluck( 'uid' )->all() );
        $this->assertSame( [30, 31, 32], $records->page( $root )->pluck( 'uid' )->all() );
        $this->assertSame( [1, 4, 1], $fetchedPids );

        // the translated records are fetched again after the page changed
        $this->assertSame( [], $records->page( $root, 1 )->pluck( 'uid' )->all() );
        $this->assertSame( [1, 1], $translations );

        // targets removed from the cache are fetched again
        $this->assertSame( [[30, 31, 32], [33], [30, 31, 32]], $fetched );

        // nested targets evict the shortcuts referencing them which are fetched again one by one within the cache size
        $fetched = [];
        $this->assertSame( [35, 36], $records->page( $nested )->pluck( 'uid' )->all() );
        $this->assertSame( [[34], [35, 36], [34], [35], [36]], $fetched );
    }


    public function testRecordsSkipRestrictedSubpagesOfShortcutSources(): void
    {
        $pages = $this->pages( [
            1 => (object) ['uid' => 1, 'pid' => 0, 'content_from_pid' => 2],
            2 => (object) ['uid' => 2, 'pid' => 0, 'doktype' => 4, 'shortcut_mode' => 1],
            3 => (object) ['uid' => 3, 'pid' => 2, 'fe_group' => '5'],
            4 => (object) ['uid' => 4, 'pid' => 2],
        ] );
        $records = new Typo3\Records( $pages, fn() => Collection::make(), fn() => Collection::make(), fn() => Collection::make(),
            Collection::make(), new Typo3\Layouts( $pages, $this->layouts( [] ) ) );

        // the first subpage is only visible for frontend users
        $this->assertSame( 4, $records->sourcePage( $pages[1] ) );
        $this->assertSame( 4, $records->sourcePage( $pages[4] ) );
    }
}
