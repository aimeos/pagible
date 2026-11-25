<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests\Typo3;

use Aimeos\Cms\Import\Elements;
use Aimeos\Cms\Import\Typo3;
use Aimeos\Cms\Models\File;
use Illuminate\Support\Collection;


/**
 * Tests the conversion of the TYPO3 content elements and the rewriting of their HTML.
 */
class ContentTest extends TestAbstract
{
    public function testHtmlRewritesFilesAndLinks(): void
    {
        $html = new Typo3\Html(
            fn( string $url ) => str_contains( $url, 'missing' ) ? null : ['id' => 'u:' . basename( $url ), 'url' => '/files/' . basename( $url )],
            fn( int $uid, string $fragment ) => $uid === 5 ? '/page-5' . ( $fragment !== '' ? '#' . $fragment : '' ) : null
        );

        $result = $html->rewrite( '<p><link 5#c1 _blank btn "A title">Legacy</link> <link 7>Gone</link> <link https://example.org>Ext</link>'
            . ' <a href="t3://page?uid=5&amp;fragment=x">New</a> <a href="t3://page?uid=9">Missing</a>'
            . ' <img data-src="fileadmin/lazy.png"> <img src="fileadmin/a.png" data-src="fileadmin/b.png">'
            . ' <img srcset="fileadmin/s1.png 1x, fileadmin/missing.png 2x"> <img src="/fileadmin/missing.png">'
            . ' <a href=fileadmin/doc.pdf>Doc</a> <a href="https://other.org/x.png">Other</a></p>' );

        // unknown pages keep their text or URL, rejected files are removed
        $this->assertSame( '<p><a href="/page-5#c1" target="_blank" class="btn" title="A title">Legacy</a> Gone <a href="https://example.org">Ext</a>'
            . ' <a href="/page-5#x">New</a> Missing'
            . ' <img src="/files/lazy.png"> <img src="/files/a.png">'
            . ' <img srcset="/files/s1.png 1x">  <a href=/files/doc.pdf>Doc</a> <a href="https://other.org/x.png">Other</a></p>', $result['html'] );
        $this->assertSame( ['u:s1.png', 'u:lazy.png', 'u:a.png', 'u:doc.pdf'], $result['fileIds'] );
    }


    public function testConverterMapsRecords(): void
    {
        $refs = fn( array $map ) => Collection::make( $map )->map( fn( $list ) => Collection::make( array_map( fn( $v ) => (object) $v, $list ) ) );
        $typo3 = new Typo3\Content(
            fn( object $ref ) => ( $name = $ref->name ?? 'none' ) !== 'none' ? 'f:' . $name : null,
            new Typo3\Html(
                fn( string $url ) => str_contains( $url, 'missing' ) ? null : ['id' => 'u:' . basename( $url ), 'url' => '/files/' . basename( $url )],
                fn( int $uid, string $fragment ) => $uid === 5 ? '/page-5' . ( $fragment !== '' ? '#' . $fragment : '' ) : null
            ),
            fn() => $refs( [1 => [['name' => 'a.png'], ['name' => 'b.png']], 2 => [['name' => 'none']], 3 => [['name' => 'c.png']]] ),
            fn() => $refs( [10 => [['header' => 'Q1', 'bodytext' => '<p>A1 <img src="fileadmin/q.png"> <a href="t3://page?uid=5">Go</a></p>'], ['header' => '', 'bodytext' => 'skip']]] ),
            fn() => $refs( [20 => [['uid' => 201], ['uid' => 202]], 21 => [['uid' => 211]]] ),
            fn() => $refs( [201 => [['name' => 's1.png'], ['name' => 'none']], 202 => [['name' => 's2.png']], 211 => [['name' => 's3.png']]] )
        );
        $typo3->prefetch( array_map( fn( $uid ) => (object) ['uid' => $uid, 'CType' => $uid >= 20 ? 'carousel' : ( $uid >= 10 ? 'accordion' : 'text' )], [1, 2, 3, 10, 11, 20, 21, 22] ) );
        $convert = fn( array $record ) => $typo3->convert( (object) $record );
        $data = fn( ?array $result ) => $result === null ? null : [array_map( fn( $el ) => [$el['type'], $el['data']], $result['elements'] ), $result['fileIds'] ?? null];

        $file = fn( string $id ) => Elements::file( $id );

        $this->assertSame( [[['heading', ['level' => 3, 'title' => 'H3']]], null], $data( $convert( ['CType' => 'header', 'header' => 'H3', 'header_layout' => '3'] ) ) );
        $this->assertSame( [[['heading', ['level' => 2, 'title' => 'H']]], null], $data( $convert( ['CType' => 'header', 'header' => 'H', 'header_layout' => 7] ) ) );
        $this->assertSame( [[['heading', ['level' => 4, 'title' => 'H']]], null], $data( $convert( ['CType' => 'header', 'header' => 'H', 'header_layout' => 4] ) ) );
        $this->assertNull( $convert( ['CType' => 'header', 'header' => ''] ) );

        // images in the text (17) and beside the text (25) become image-text elements, further images separate elements
        $this->assertSame( [[
            ['heading', ['level' => 1, 'title' => 'T']],
            ['image-text', ['text' => 'Text', 'file' => $file( 'f:a.png' ), 'position' => 'end']],
            ['image', ['file' => $file( 'f:b.png' )]],
        ], ['f:a.png', 'f:b.png']], $data( $convert( ['CType' => 'textpic', 'uid' => 1, 'header' => 'T', 'header_layout' => '1', 'bodytext' => '<p>Text</p>', 'imageorient' => 17] ) ) );
        $this->assertSame( [[['image-text', ['text' => 'Text', 'file' => $file( 'f:c.png' ), 'position' => 'grid-end']]], ['f:c.png']],
            $data( $convert( ['CType' => 'textmedia', 'uid' => 3, 'header' => 'T', 'header_layout' => '100', 'bodytext' => '<p>Text</p>', 'imageorient' => 25] ) ) );
        $this->assertSame( [[['image-text', ['text' => 'Text', 'file' => $file( 'f:c.png' ), 'position' => 'start']]], ['f:c.png']],
            $data( $convert( ['CType' => 'textmedia', 'uid' => 3, 'bodytext' => '<p>Text</p>', 'imageorient' => 18] ) ) );
        // images above (0) and below (8) the text are separate elements
        $this->assertSame( [[['image', ['file' => $file( 'f:c.png' )]], ['html', ['text' => '<p>Text</p>']]], ['f:c.png']],
            $data( $convert( ['CType' => 'textmedia', 'uid' => 3, 'bodytext' => '<p>Text</p>', 'imageorient' => 0] ) ) );
        $this->assertSame( [[['html', ['text' => '<p>Text</p>']], ['image', ['file' => $file( 'f:c.png' )]]], ['f:c.png']],
            $data( $convert( ['CType' => 'textmedia', 'uid' => 3, 'bodytext' => '<p>Text</p>', 'imageorient' => 8] ) ) );
        $this->assertSame( [[['image', ['file' => $file( 'f:c.png' )]], ['html', ['text' => '<p>T </p>']]], ['u:s.png', 'f:c.png']],
            $data( $convert( ['CType' => 'textpic', 'uid' => 3, 'bodytext' => '<p>T <img srcset="fileadmin/s.png 1x, fileadmin/missing.png 2x, other.png 3x"><img src="fileadmin/missing.png"><img data-src="fileadmin/missing.png"></p>'] ) ) );
        $this->assertSame( [[['html', ['text' => '<p>Only</p>']]], []], $data( $convert( ['CType' => 'textpic', 'uid' => 2, 'bodytext' => '<p>Only</p>'] ) ) );
        $this->assertSame( [[['image', ['file' => $file( 'f:c.png' )]]], ['f:c.png']], $data( $convert( ['CType' => 'textpic', 'uid' => 3, 'bodytext' => ' '] ) ) );
        $this->assertNull( $convert( ['CType' => 'textpic', 'uid' => 2] ) );

        // several images become a slideshow
        $this->assertSame( [[['heading', ['level' => 2, 'title' => 'I']], ['slideshow', ['title' => '', 'files' => [$file( 'f:a.png' ), $file( 'f:b.png' )]]]], ['f:a.png', 'f:b.png']],
            $data( $convert( ['CType' => 'image', 'uid' => 1, 'header' => 'I'] ) ) );
        // headings of image elements without images are kept like TYPO3 shows them
        $this->assertSame( [[['heading', ['level' => 2, 'title' => 'I']]], []], $data( $convert( ['CType' => 'image', 'uid' => 2, 'header' => 'I'] ) ) );

        $this->assertSame( [[['html', ['text' => '<p><a href="/page-5#c1" target="_blank" class="cls" title="My title" rel="noreferrer noopener">a</a> b <a href="https://x.org">c</a>'
            . ' <a href="/page-5#f">d</a> e</p><img src="/files/l.png" class="lazy" alt="l.png" /><img src="x.png" alt="x.png" />'
            . '<img src="/files/u.png" alt="u.png" /><a href="/files/doc.pdf">doc</a>']]], ['u:s1.png', 'u:l.png', 'u:doc.pdf', 'u:u.png']],
            $data( $convert( ['CType' => 'html', 'bodytext' => '<p><link 5#c1 _blank cls "My title">a</link> <link 9>b</link> <link https://x.org - - ->c</link>'
                . ' <a href="t3://page?uid=5&amp;fragment=f">d</a> <a href="t3://page?uid=9">e</a></p>'
                . '<img data-src="fileadmin/l.png" class="lazy"><img src="x.png" data-src="fileadmin/m.png"><img src="/fileadmin/missing.png">'
                . '<img srcset="fileadmin/s1.png 1x, fileadmin/missing.png 2x, other.png 3x"><img src=fileadmin/u.png><a href="fileadmin/doc.pdf">doc</a>'] ) ) );
        $this->assertNull( $convert( ['CType' => 'html', 'bodytext' => ''] ) );

        $this->assertSame( [[['questions', ['title' => 'Acc', 'items' => [['title' => 'Q1', 'text' => 'A1  [Go](/page-5)']]]]], ['u:q.png']],
            $data( $convert( ['CType' => 'accordion', 'uid' => 10, 'header' => 'Acc'] ) ) );
        // accordions without items are shown like text elements
        $this->assertSame( [[['html', ['text' => '<p>Body</p>']]], []],
            $data( $convert( ['CType' => 'accordion', 'uid' => 11, 'bodytext' => '<p>Body</p>'] ) ) );
        $this->assertNull( $convert( ['CType' => 'accordion', 'uid' => 11] ) );

        $this->assertSame( [[['slideshow', ['title' => 'Car', 'files' => [$file( 'f:s1.png' ), $file( 'f:s2.png' )]]]], ['f:s1.png', 'f:s2.png']],
            $data( $convert( ['CType' => 'carousel', 'uid' => 20, 'header' => 'Car'] ) ) );
        $this->assertSame( [[['image', ['file' => $file( 'f:s3.png' )]]], ['f:s3.png']], $data( $convert( ['CType' => 'carousel', 'uid' => 21] ) ) );
        $this->assertSame( [[['html', ['text' => '<p>X</p>']]], []],
            $data( $convert( ['CType' => 'carousel', 'uid' => 22, 'header' => 'Fallback', 'header_layout' => '100', 'bodytext' => '<p>X</p>'] ) ) );

        $this->assertSame( [[['heading', ['level' => 2, 'title' => 'Txt']], ['html', ['text' => '<p>Body <img src="/files/t.png" alt="t.png" /></p>']]], ['u:t.png']],
            $data( $convert( ['CType' => 'text', 'header' => 'Txt', 'bodytext' => '<p>Body <img src="fileadmin/t.png"></p>'] ) ) );
        $this->assertSame( [[['heading', ['level' => 2, 'title' => 'Zero']], ['html', ['text' => '<p>0</p>']]], []], $data( $convert( ['CType' => 'text', 'header' => 'Zero', 'bodytext' => '0'] ) ) );
        $this->assertNull( $convert( ['CType' => 'text'] ) );
        $this->assertSame( ['main'], array_values( array_unique( array_column( $convert( ['CType' => 'image', 'uid' => 1, 'header' => 'I'] )['elements'] ?? [], 'group' ) ) ) );
    }


    public function testConverterMapsBootstrapPackageAndCoreElements(): void
    {
        $refs = fn( array $map ) => Collection::make( $map )->map( fn( $list ) => Collection::make( array_map( fn( $v ) => (object) $v, $list ) ) );
        $sysFile = fn( string $name, array $data = [] ) => (object) ( ['identifier' => '/' . $name, 'name' => $name, 'extension' => pathinfo( $name, PATHINFO_EXTENSION )] + $data );
        $warnings = $itemQueries = [];

        $typo3 = new Typo3\Content(
            fn( object $ref ) => 'f:' . ( $ref->name ?? $ref->sys_file->name ),
            new Typo3\Html(
                fn( string $url ) => ['id' => 'u:' . basename( $url ), 'url' => '/files/' . basename( $url )],
                fn( int $uid, string $fragment ) => $uid === 5 ? '/page-5' : null
            ),
            fn() => $refs( [
                // only the files of the field shown by TYPO3 for the content type are used
                30 => [['name' => 'old.png', 'fieldname' => 'image'], ['name' => 'new.png', 'fieldname' => 'assets']],
                31 => [['name' => 'pic.png', 'fieldname' => 'image', 'link' => 't3://page?uid=5', 'alternative' => 'Alt'], ['name' => 'two.png', 'fieldname' => 'image']],
                50 => [
                    ['name' => 'b.pdf', 'fieldname' => 'media', 'sys_file' => $sysFile( 'b.pdf', ['size' => 20] )],
                    ['name' => 'a.pdf', 'fieldname' => 'media', 'sys_file' => $sysFile( 'a.pdf', ['size' => 30] )],
                ],
            ] ),
            fn() => Collection::make(),
            fn() => $refs( [40 => [['uid' => 401, 'header' => 'Slide', 'bodytext' => '<p>Slide text</p>', 'link' => 't3://page?uid=5'], ['uid' => 402, 'header' => '', 'bodytext' => '']]] ),
            fn() => $refs( [401 => [['name' => 'bg.png', 'fieldname' => 'background_image'], ['name' => 'slide.png', 'fieldname' => 'image']]] ),
            null,
            null,
            fn( object $ref ) => 'fileadmin/' . $ref->name,
            function( string $table, array $uids, array $columns ) use ( $refs, &$itemQueries ) {
                $itemQueries[] = [$table, $uids, $columns];
                return match( $table ) {
                    'tx_bootstrappackage_tab_item' => $refs( [60 => [['uid' => 601, 'header' => 'Tab 1', 'bodytext' => '<p>One</p>'], ['uid' => 602, 'header' => 'Empty', 'bodytext' => '']]] ),
                    'tx_bootstrappackage_card_group_item' => $refs( [61 => [['uid' => 611, 'header' => 'Card', 'subheader' => 'Sub', 'bodytext' => '<p>Body</p>', 'link' => 't3://page?uid=5 _blank']]] ),
                    'tx_bootstrappackage_timeline_item' => $refs( [62 => [['uid' => 621, 'header' => 'Start', 'date' => 86400, 'bodytext' => '<p>Begin</p>'], ['uid' => 622, 'header' => '', 'date' => 0]]] ),
                    'tx_bootstrappackage_icon_group_item' => $refs( [63 => [['uid' => 631, 'header' => 'Icon', 'subheader' => '', 'bodytext' => '', 'link' => 'https://x.org']]] ),
                    default => Collection::make(),
                };
            },
            fn( string $table, array $uids, array $fields ) => $table === 'tx_bootstrappackage_card_group_item' ? $refs( [611 => [['name' => 'card.png', 'fieldname' => 'image']]] ) : Collection::make(),
            fn( array $uids ) => ['refs' => Collection::make( [(object) ['name' => 'c.pdf', 'sys_file' => $sysFile( 'c.pdf', ['size' => 10] )]] ), 'skipped' => [8 => 'category']],
            function( string $message ) use ( &$warnings ) { $warnings[] = $message; }
        );

        $types = [30 => 'textmedia', 31 => 'image', 40 => 'carousel', 50 => 'uploads', 60 => 'tab', 61 => 'card_group', 62 => 'timeline', 63 => 'icon_group'];
        $typo3->prefetch( array_map( fn( $uid, $type ) => (object) ['uid' => $uid, 'CType' => $type], array_keys( $types ), $types ) );
        $convert = fn( array $record ) => $typo3->convert( (object) $record );
        $data = fn( ?array $result ) => $result === null ? null : [array_map( fn( $el ) => [$el['type'], $el['data']], $result['elements'] ), $result['fileIds'] ?? null];
        $file = fn( string $id ) => Elements::file( $id );

        // the items are fetched once per table and only for the content elements of their type
        $this->assertSame( [
            ['tx_bootstrappackage_tab_item', [60], []],
            ['tx_bootstrappackage_card_group_item', [61], ['subheader', 'link']],
            ['tx_bootstrappackage_timeline_item', [62], ['date']],
            ['tx_bootstrappackage_icon_group_item', [63], ['subheader', 'link', 'icon']],
        ], $itemQueries );

        $this->assertSame( [[['image', ['file' => $file( 'f:new.png' )]]], ['f:new.png']], $data( $convert( ['CType' => 'textmedia', 'uid' => 30] ) ) );

        // linked images become html elements because image elements can't contain links
        $this->assertSame( [[
            ['html', ['text' => '<p><a href="/page-5"><img src="/files/pic.png" alt="Alt" /></a></p>']],
            ['image', ['file' => $file( 'f:two.png' )]],
        ], ['u:pic.png', 'f:two.png']], $data( $convert( ['CType' => 'image', 'uid' => 31] ) ) );

        // carousels with texts become cards, the images of the items are preferred to their backgrounds
        $this->assertSame( [[['cards', ['title' => 'Car', 'cards' => [['title' => 'Slide', 'text' => 'Slide text', 'url' => '/page-5', 'file' => $file( 'f:slide.png' )]]]]], ['f:slide.png']],
            $data( $convert( ['CType' => 'carousel', 'uid' => 40, 'header' => 'Car'] ) ) );

        // the files of the collections follow the referenced files, all files are sorted like configured
        $this->assertSame( [[['file', ['file' => $file( 'f:a.pdf' )]], ['file', ['file' => $file( 'f:b.pdf' )]], ['file', ['file' => $file( 'f:c.pdf' )]]], ['f:a.pdf', 'f:b.pdf', 'f:c.pdf']],
            $data( $convert( ['CType' => 'uploads', 'uid' => 50, 'file_collections' => '7,8', 'filelink_sorting' => 'size', 'filelink_sorting_direction' => 'desc'] ) ) );
        $this->assertSame( [[['file', ['file' => $file( 'f:b.pdf' )]], ['file', ['file' => $file( 'f:a.pdf' )]]], ['f:b.pdf', 'f:a.pdf']],
            $data( $convert( ['CType' => 'uploads', 'uid' => 50] ) ) );

        $this->assertSame( [[['questions', ['title' => 'Tabs', 'items' => [['title' => 'Tab 1', 'text' => 'One']]]]], []],
            $data( $convert( ['CType' => 'tab', 'uid' => 60, 'header' => 'Tabs'] ) ) );
        $this->assertSame( [[['cards', ['title' => 'Cards', 'cards' => [['title' => 'Card', 'text' => "**Sub**\n\nBody", 'url' => '/page-5', 'file' => $file( 'f:card.png' )]]]]], ['f:card.png']],
            $data( $convert( ['CType' => 'card_group', 'uid' => 61, 'header' => 'Cards'] ) ) );
        $this->assertSame( [[['timeline', ['title' => '', 'items' => [['label' => '1970-01-02', 'title' => 'Start', 'text' => 'Begin']]]]], []],
            $data( $convert( ['CType' => 'timeline', 'uid' => 62] ) ) );
        $this->assertSame( [[['cards', ['title' => 'Icons', 'cards' => [['title' => 'Icon', 'url' => 'https://x.org']]]]], []],
            $data( $convert( ['CType' => 'icon_group', 'uid' => 63, 'header' => 'Icons'] ) ) );
        // elements without items fall back to their header and text
        $this->assertSame( [[['html', ['text' => '<p>Text</p>']]], []], $data( $convert( ['CType' => 'tab', 'uid' => 64, 'bodytext' => '<p>Text</p>'] ) ) );

        // dividers are only visual separators
        $this->assertNull( $convert( ['CType' => 'div', 'uid' => 70, 'header' => 'Divider'] ) );

        // the texts of bullet lists are text, not HTML
        $this->assertSame( [[['text', ['text' => "- a \\<b> c\n- d"]]], []], $data( $convert( ['CType' => 'bullets', 'uid' => 71, 'bodytext' => "a <b> c\nd"] ) ) );

        // quoted cells can span several lines and links in the cells become Markdown links
        $this->assertSame( [[['table', ['title' => '', 'header' => 'row', 'table' => [['Name', 'Text'], ['A', 'Line 1 Line 2'], ['B', '[Go](/page-5) & more']]]]], []],
            $data( $convert( ['CType' => 'table', 'uid' => 72, 'table_enclosure' => 34, 'table_header_position' => 1,
                'bodytext' => "Name|Text\nA|\"Line 1\nLine 2\"\nB|<a href=\"t3://page?uid=5\">Go</a> &amp; more"] ) ) );

        // plugins and menus are reported and only their header is imported
        $this->assertSame( [[['heading', ['level' => 2, 'title' => 'Login']]], []], $data( $convert( ['CType' => 'felogin_login', 'uid' => 73, 'header' => 'Login'] ) ) );
        $this->assertNull( $convert( ['CType' => 'menu_subpages', 'uid' => 74] ) );
        $this->assertNull( $convert( ['CType' => 'custom_plugin', 'uid' => 75] ) );

        $this->assertSame( [
            'File collection [8] of type "category" used by content element [50] can\'t be imported',
            'Content element [73] of type "felogin_login" can\'t be imported, only its header is used',
            'Content element [74] of type "menu_subpages" can\'t be imported',
            'Content element [75] of unknown type "custom_plugin" has no content to import',
        ], $warnings );
    }


    public function testHtmlLinksToTranslationsOfPages(): void
    {
        $html = new Typo3\Html(
            fn( string $url ) => null,
            fn( int $uid, string $fragment, ?int $lang = null ) => '/page-' . $uid . ( $lang !== null ? '-' . $lang : '' )
        );

        $this->assertSame( '<a href="/page-5-1">a</a> <a href="/page-5-2">b</a> <a href="/page-5">c</a>',
            $html->rewrite( '<a href="t3://page?uid=5&amp;_language=1">a</a> <a href="t3://page?uid=5&L=2">b</a> <a href="t3://page?uid=5&_language=x">c</a>' )['html'] );
    }


    public function testConverterShowsHeadersLikeFluidStyledContent(): void
    {
        $refs = fn( array $map ) => Collection::make( $map )->map( fn( $list ) => Collection::make( array_map( fn( $v ) => (object) $v, $list ) ) );
        $sysFile = fn( string $name ) => (object) ['identifier' => '/' . $name, 'name' => $name, 'extension' => pathinfo( $name, PATHINFO_EXTENSION )];
        $read = [];

        $typo3 = new Typo3\Content(
            fn( object $ref ) => 'f:' . ( $ref->name ?? $ref->sys_file->name ),
            new Typo3\Html(
                fn( string $url ) => ['id' => 'u:' . basename( $url ), 'url' => '/files/' . basename( $url )],
                fn( int $uid, string $fragment ) => $uid === 5 ? '/page-5' : null
            ),
            fn() => $refs( [
                20 => [['fieldname' => 'assets', 'sys_file' => $sysFile( 'data.csv' )]],
                21 => [['fieldname' => 'assets', 'sys_file' => $sysFile( 'empty.csv' )]],
            ] ),
            fn() => $refs( [10 => [['uid' => 101, 'header' => 'Q', 'bodytext' => '<p>A</p>']]] ),
            fn() => $refs( [
                11 => [['uid' => 111, 'header' => '', 'bodytext' => ''], ['uid' => 112, 'header' => '', 'bodytext' => '']],
                12 => [['uid' => 121, 'header' => '', 'bodytext' => '']],
                13 => [
                    ['uid' => 131, 'header' => 'Shown', 'bodytext' => '<p>Text</p>', 'link' => 't3://page?uid=5', 'button_text' => 'More'],
                    ['uid' => 132, 'header' => 'Hidden', 'header_layout' => '100', 'bodytext' => '<p>Other</p>', 'link' => '', 'button_text' => 'Ignored'],
                ],
            ] ),
            fn() => $refs( [111 => [['name' => 's1.png', 'fieldname' => 'image']], 112 => [['name' => 's2.png', 'fieldname' => 'image']], 121 => [['name' => 's3.png', 'fieldname' => 'image']]] ),
            null,
            null,
            fn( object $ref ) => 'fileadmin/' . ( $ref->name ?? $ref->sys_file->name ),
            fn( string $table, array $uids, array $columns ) => $table === 'tx_bootstrappackage_icon_group_item'
                ? $refs( [14 => [['uid' => 141, 'header' => 'Icon', 'subheader' => '', 'bodytext' => '', 'link' => '']]] ) : Collection::make(),
            fn( string $table, array $uids, array $fields ) => $table === 'tx_bootstrappackage_icon_group_item' && $fields === ['icon_file']
                ? $refs( [141 => [['name' => 'icon.svg', 'fieldname' => 'icon_file']]] ) : Collection::make(),
            null,
            null,
            function( string $id ) use ( &$read ) {
                $read[] = $id;
                return $id === 'f:data.csv' ? "a,b\nc,d" : '';
            }
        );

        $types = [10 => 'accordion', 11 => 'carousel', 12 => 'carousel_fullscreen', 13 => 'carousel', 14 => 'icon_group', 15 => 'accordion', 20 => 'csv', 21 => 'csv'];
        $typo3->prefetch( array_map( fn( $uid, $type ) => (object) ['uid' => $uid, 'CType' => $type], array_keys( $types ), $types ) );
        $convert = fn( array $record ) => $typo3->convert( (object) $record );
        $data = fn( ?array $result ) => $result === null ? null : [array_map( fn( $el ) => [$el['type'], $el['data']], $result['elements'] ), $result['fileIds'] ?? null];
        $file = fn( string $id ) => Elements::file( $id );
        $items = ['title' => '', 'items' => [['title' => 'Q', 'text' => 'A']]];

        // plain headers are the titles, hidden headers aren't shown and other headers are shown in front
        $this->assertSame( [[['questions', ['title' => 'FAQ'] + $items]], []], $data( $convert( ['CType' => 'accordion', 'uid' => 10, 'header' => 'FAQ'] ) ) );
        $this->assertSame( [[['questions', $items]], []], $data( $convert( ['CType' => 'accordion', 'uid' => 10, 'header' => 'FAQ', 'header_layout' => '100'] ) ) );
        $this->assertSame( [[['heading', ['level' => 3, 'title' => 'FAQ']], ['questions', $items]], []],
            $data( $convert( ['CType' => 'accordion', 'uid' => 10, 'header' => 'FAQ', 'header_layout' => '3'] ) ) );
        $this->assertSame( [[['heading', ['level' => 2, 'title' => 'FAQ']], ['heading', ['level' => 3, 'title' => 'Sub']], ['questions', $items]], []],
            $data( $convert( ['CType' => 'accordion', 'uid' => 10, 'header' => 'FAQ', 'subheader' => 'Sub'] ) ) );
        $this->assertSame( [[['html', ['text' => '<h2><a href="/page-5">FAQ</a></h2>']], ['questions', $items]], []],
            $data( $convert( ['CType' => 'accordion', 'uid' => 10, 'header' => 'FAQ', 'header_link' => 't3://page?uid=5'] ) ) );

        // accordions without items are shown like text elements
        $this->assertSame( [[['heading', ['level' => 2, 'title' => 'Empty']], ['html', ['text' => '<p>Text</p>']]], []],
            $data( $convert( ['CType' => 'accordion', 'uid' => 15, 'header' => 'Empty', 'bodytext' => '<p>Text</p>'] ) ) );

        // slideshows use the header as title, single images get the header in front
        $this->assertSame( [[['slideshow', ['title' => 'Slides', 'files' => [$file( 'f:s1.png' ), $file( 'f:s2.png' )]]]], ['f:s1.png', 'f:s2.png']],
            $data( $convert( ['CType' => 'carousel', 'uid' => 11, 'header' => 'Slides'] ) ) );
        $this->assertSame( [[['heading', ['level' => 2, 'title' => 'One']], ['image', ['file' => $file( 'f:s3.png' )]]], ['f:s3.png']],
            $data( $convert( ['CType' => 'carousel_fullscreen', 'uid' => 12, 'header' => 'One'] ) ) );

        // the button texts become links and hidden item headers aren't used
        $this->assertSame( [[['cards', ['title' => '', 'cards' => [
            ['title' => 'Shown', 'text' => "Text\n\n[More](/page-5)", 'url' => '/page-5'],
            ['title' => '', 'text' => 'Other'],
        ]]]], []], $data( $convert( ['CType' => 'carousel', 'uid' => 13] ) ) );

        // the icons of icon groups are imported
        $this->assertSame( [[['cards', ['title' => 'Icons', 'cards' => [['title' => 'Icon', 'file' => $file( 'f:icon.svg' )]]]]], ['f:icon.svg']],
            $data( $convert( ['CType' => 'icon_group', 'uid' => 14, 'header' => 'Icons'] ) ) );

        // the CSV files are rendered as tables and stay attached, unreadable files are file elements
        $this->assertSame( [[['table', ['title' => '', 'header' => '', 'table' => [['a', 'b'], ['c', 'd']]]]], ['f:data.csv']],
            $data( $convert( ['CType' => 'csv', 'uid' => 20, 'table_delimiter' => 44] ) ) );
        $this->assertSame( [[['file', ['file' => $file( 'f:empty.csv' )]]], ['f:empty.csv']], $data( $convert( ['CType' => 'csv', 'uid' => 21] ) ) );
        $this->assertSame( ['f:data.csv', 'f:empty.csv'], $read );
    }


    public function testConverterMapsMoreBootstrapPackageElements(): void
    {
        $refs = fn( array $map ) => Collection::make( $map )->map( fn( $list ) => Collection::make( array_map( fn( $v ) => (object) $v, $list ) ) );
        $warnings = $carouselUids = [];

        $typo3 = new Typo3\Content(
            fn( object $ref ) => 'f:' . $ref->name,
            new Typo3\Html(
                fn( string $url ) => ['id' => 'u:' . basename( $url ), 'url' => '/files/' . basename( $url )],
                fn( int $uid, string $fragment ) => $uid === 5 ? '/page-5' : null
            ),
            fn() => $refs( [
                80 => [['name' => 'g1.png', 'fieldname' => 'image'], ['name' => 'g2.png', 'fieldname' => 'image'], ['name' => 'old.png', 'fieldname' => 'assets']],
                81 => [['name' => 'm.png', 'fieldname' => 'assets'], ['name' => 'old.png', 'fieldname' => 'image']],
                82 => [['name' => 'icon.png', 'fieldname' => 'icon_file']],
            ] ),
            fn() => Collection::make(),
            function( array $uids ) use ( $refs, &$carouselUids ) {
                $carouselUids = $uids;
                return $refs( [90 => [['uid' => 901, 'header' => '', 'bodytext' => ''], ['uid' => 902, 'header' => '', 'bodytext' => '']], 91 => [['uid' => 911, 'header' => '', 'bodytext' => '']]] );
            },
            fn() => $refs( [901 => [['name' => 's1.png', 'fieldname' => 'image']], 902 => [['name' => 's2.png', 'fieldname' => 'image']], 911 => [['name' => 's3.png', 'fieldname' => 'image']]] ),
            null,
            null,
            fn( object $ref ) => 'fileadmin/' . $ref->name,
            null,
            null,
            null,
            function( string $message ) use ( &$warnings ) { $warnings[] = $message; }
        );

        $types = [80 => 'gallery', 81 => 'media', 82 => 'texticon', 90 => 'carousel_small', 91 => 'carousel_fullscreen'];
        $typo3->prefetch( array_map( fn( $uid, $type ) => (object) ['uid' => $uid, 'CType' => $type], array_keys( $types ), $types ) );
        $convert = fn( array $record ) => $typo3->convert( (object) $record );
        $data = fn( ?array $result ) => $result === null ? null : [array_map( fn( $el ) => [$el['type'], $el['data']], $result['elements'] ), $result['fileIds'] ?? null];
        $file = fn( string $id ) => Elements::file( $id );

        // the items of all carousel variants are fetched at once
        $this->assertSame( [90, 91], $carouselUids );
        $this->assertSame( [[['slideshow', ['title' => 'Small', 'files' => [$file( 'f:s1.png' ), $file( 'f:s2.png' )]]]], ['f:s1.png', 'f:s2.png']],
            $data( $convert( ['CType' => 'carousel_small', 'uid' => 90, 'header' => 'Small'] ) ) );
        $this->assertSame( [[['image', ['file' => $file( 'f:s3.png' )]]], ['f:s3.png']], $data( $convert( ['CType' => 'carousel_fullscreen', 'uid' => 91] ) ) );

        // galleries use the images, media elements the assets like in TYPO3
        $this->assertSame( [[['slideshow', ['title' => '', 'files' => [$file( 'f:g1.png' ), $file( 'f:g2.png' )]]]], ['f:g1.png', 'f:g2.png']],
            $data( $convert( ['CType' => 'gallery', 'uid' => 80] ) ) );
        $this->assertSame( [[['html', ['text' => '<p>Text</p>']], ['image', ['file' => $file( 'f:m.png' )]]], ['f:m.png']],
            $data( $convert( ['CType' => 'media', 'uid' => 81, 'bodytext' => '<p>Text</p>', 'imageorient' => 8] ) ) );

        // the files of other content types are imported after their text
        $this->assertSame( [[['html', ['text' => '<p>Icon text</p>']], ['image', ['file' => $file( 'f:icon.png' )]]], ['f:icon.png']],
            $data( $convert( ['CType' => 'texticon', 'uid' => 82, 'bodytext' => '<p>Icon text</p>'] ) ) );

        $video = $convert( ['CType' => 'external_media', 'uid' => 83, 'external_media_source' => 'https://www.youtube.com/watch?v=abc123'] );
        $this->assertSame( 'html', $video['elements'][0]['type'] ?? null );
        $this->assertStringContainsString( 'youtube', $video['elements'][0]['data']['text'] ?? '' );

        $this->assertSame( [[['text', ['text' => "- One\n- Two"]]], []], $data( $convert( ['CType' => 'listgroup', 'uid' => 84, 'bodytext' => "One\n\nTwo"] ) ) );

        $quote = $data( $convert( ['CType' => 'quote', 'uid' => 85, 'bodytext' => 'Wise words', 'quote_source' => 'Someone', 'quote_link' => 't3://page?uid=5'] ) );
        $this->assertSame( 'text', $quote[0][0][0] );
        $this->assertStringStartsWith( '> Wise words', $quote[0][0][1]['text'] );
        $this->assertStringContainsString( '[Someone](/page-5)', $quote[0][0][1]['text'] );

        $this->assertSame( [[['table', ['title' => '', 'header' => '', 'table' => [['a', 'b'], ['c', 'd']]]]], []],
            $data( $convert( ['CType' => 'csv', 'uid' => 86, 'bodytext' => "a|b\nc|d"] ) ) );

        // TYPO3 limits the cells to the number of columns and keeps empty rows
        $this->assertSame( [[['table', ['title' => '', 'header' => '', 'table' => [['a'], [''], ['c']]]]], []],
            $data( $convert( ['CType' => 'table', 'uid' => 87, 'cols' => 1, 'bodytext' => "a|b\n\nc|d"] ) ) );

        // the subheader and the date are shown without header too, the subheader one level below the header
        $this->assertSame( [[['heading', ['level' => 3, 'title' => 'Sub']], ['text', ['text' => '1970-01-02']]]], array_slice( $data( $convert( ['CType' => 'header', 'uid' => 88, 'subheader' => 'Sub', 'date' => 86400] ) ), 0, 1 ) );
        $this->assertNull( $convert( ['CType' => 'header', 'uid' => 89, 'subheader' => 'Sub', 'header_layout' => 100] ) );

        // plugins registered as content types are reported even if their header is imported, containers aren't
        $this->assertSame( [[['heading', ['level' => 2, 'title' => 'News']]], []], $data( $convert( ['CType' => 'news_pi1', 'uid' => 92, 'header' => 'News'] ) ) );
        $this->assertNull( $convert( ['CType' => 'b13-2cols', 'uid' => 93, '_pagible_container' => true] ) );

        $this->assertSame( [
            'Content element [92] of unknown type "news_pi1" may not be imported completely, only its header, text and files are used',
        ], $warnings );
    }


    public function testConverterWrapsLinesAndKeepsLinkTargets(): void
    {
        $refs = fn( array $map ) => Collection::make( $map )->map( fn( $list ) => Collection::make( array_map( fn( $v ) => (object) $v, $list ) ) );
        $warnings = [];

        $typo3 = new Typo3\Content(
            fn( object $ref ) => 'f:' . $ref->name,
            new Typo3\Html(
                fn( string $url ) => ['id' => 'u:' . basename( $url ), 'url' => '/files/' . basename( $url )],
                fn( int $uid, string $fragment ) => $uid === 5 ? '/page-5' : null
            ),
            fn() => $refs( [
                3 => [['name' => 'linked.png', 'fieldname' => 'image', 'link' => 't3://page?uid=5 _blank - "Title"']],
            ] ),
            fn() => Collection::make(),
            fn() => Collection::make(),
            fn() => Collection::make(),
            null,
            null,
            fn( object $ref ) => 'fileadmin/' . $ref->name,
            fn( string $table, array $uids, array $columns ) => $table === 'tx_bootstrappackage_icon_group_item' && in_array( 'icon', $columns, true )
                ? $refs( [8 => [['uid' => 81, 'header' => 'Set', 'subheader' => '', 'bodytext' => '', 'link' => '', 'icon' => 'heart']]] ) : Collection::make(),
            fn() => Collection::make(),
            null,
            function( string $message ) use ( &$warnings ) { $warnings[] = $message; }
        );

        $types = [1 => 'text', 2 => 'header', 3 => 'image', 4 => 'mailform', 5 => 'menu', 6 => 'quote', 7 => 'texticon', 8 => 'icon_group'];
        $typo3->prefetch( array_map( fn( $uid, $type ) => (object) ['uid' => $uid, 'CType' => $type], array_keys( $types ), $types ) );
        $convert = fn( array $record ) => $typo3->convert( (object) $record );
        $data = fn( ?array $result ) => $result === null ? null : [array_map( fn( $el ) => [$el['type'], $el['data']], $result['elements'] ), $result['fileIds'] ?? null];

        // lines outside of block elements become paragraphs like in TYPO3 and empty lines are dropped
        $this->assertSame( "<p>Line 1</p>\n<p>Line <b>2</b></p>\n\n\n<ul><li>Item</li></ul>\n<p>After</p>", Typo3\Html::paragraphs( "Line 1\nLine <b>2</b>\n\n&nbsp;\n<ul><li>Item</li></ul>\nAfter" ) );
        $this->assertSame( "<p>One</p>\n<p>Two</p>", Typo3\Html::paragraphs( "<p>One</p>\n<p>Two</p>" ) );
        $this->assertSame( '<div><div>Nested</div>Text</div><p>Tail</p>', Typo3\Html::paragraphs( '<div><div>Nested</div>Text</div>Tail' ) );
        $this->assertSame( [[['html', ['text' => "<p>Line 1</p>\n<p>Line 2</p>"]]], []], $data( $convert( ['CType' => 'text', 'uid' => 1, 'bodytext' => "Line 1\nLine 2"] ) ) );

        // the targets of the typolink fields are kept
        $this->assertSame( [[['html', ['text' => '<h2><a href="/page-5" target="_blank" rel="noreferrer noopener">Head</a></h2>']]], null],
            $data( $convert( ['CType' => 'header', 'uid' => 2, 'header' => 'Head', 'header_link' => 't3://page?uid=5 _blank - "Title"'] ) ) );
        $this->assertStringContainsString( '<a href="/page-5" target="_blank"', $convert( ['CType' => 'image', 'uid' => 3] )['elements'][0]['data']['text'] ?? '' );

        // typolink fields in legacy notation are resolved
        $this->assertSame( [[['html', ['text' => '<h2><a href="/page-5">Legacy</a></h2>']]], null],
            $data( $convert( ['CType' => 'header', 'uid' => 2, 'header' => 'Legacy', 'header_link' => '5'] ) ) );

        // generated content of old content types isn't imported
        $this->assertSame( [[['heading', ['level' => 2, 'title' => 'Form']]], []],
            $data( $convert( ['CType' => 'mailform', 'uid' => 4, 'header' => 'Form', 'bodytext' => 'Name: | *name=input,40'] ) ) );
        $this->assertNull( $convert( ['CType' => 'menu', 'uid' => 5] ) );

        // the lines of rich text quotes become paragraphs
        $quote = $data( $convert( ['CType' => 'quote', 'uid' => 6, 'bodytext' => "<b>Wise</b>\nwords"] ) );
        $this->assertSame( [[['text', ['text' => "> **Wise**\n>\n> words"]]], []], $quote );

        // icons of icon sets are reported
        $convert( ['CType' => 'texticon', 'uid' => 7, 'header' => 'Icon', 'icon' => 'star'] );
        $convert( ['CType' => 'icon_group', 'uid' => 8] );

        $this->assertSame( [
            'Content element [4] of type "mailform" can\'t be imported, only its header is used',
            'Content element [5] of type "menu" can\'t be imported',
            'Icon "star" of content element [7] is from an icon set and can\'t be imported',
            'Icon "heart" of content element [8] is from an icon set and can\'t be imported',
        ], $warnings );
    }


    public function testHtmlResolvesLegacyTypolinks(): void
    {
        $html = new Typo3\Html(
            fn( string $url ) => ['id' => 'u:' . basename( $url ), 'url' => '/files/' . basename( $url )],
            fn( int $uid, string $fragment, ?int $lang = null ) => $uid === 5 ? '/page-5' . ( $lang ? '-' . $lang : '' ) . ( $fragment !== '' ? '#' . $fragment : '' ) : null,
            fn( int $uid ) => $uid === 12 ? ['id' => 'f12', 'url' => '/files/f12.pdf'] : null
        );

        // legacy notations still resolved by TYPO3's LinkService are converted
        $this->assertSame( 't3://page?uid=5', Typo3\Html::typolink( '5 _blank - "Title"' ) );
        $this->assertSame( 't3://page?uid=5#c3', Typo3\Html::typolink( '5,3#c3' ) );
        $this->assertSame( 't3://file?uid=12', Typo3\Html::typolink( 'file:12' ) );
        $this->assertSame( 'mailto:info@example.com', Typo3\Html::typolink( 'info@example.com' ) );
        $this->assertSame( 'https://www.example.com/path', Typo3\Html::typolink( 'www.example.com/path' ) );
        $this->assertSame( '/fileadmin/doc.pdf', Typo3\Html::typolink( 'fileadmin/doc.pdf' ) );
        $this->assertSame( '', Typo3\Html::typolink( 'javascript:alert(1)' ) );
        $this->assertSame( 'https://example.com/', Typo3\Html::typolink( 'https://example.com/ _blank' ) );

        // the language of the additional parameters is added to page links
        $this->assertSame( 't3://page?uid=5&L=1', Typo3\Html::typolink( 't3://page?uid=5 - - "My title" &L=1' ) );
        $this->assertSame( 't3://page?uid=5&L=2#c3', Typo3\Html::typolink( '5#c3 _blank - - &foo=bar&_language=2' ) );
        $this->assertSame( 't3://page?uid=5&L=1', Typo3\Html::typolink( 't3://page?uid=5&L=1 - - - &L=2' ) );
        $this->assertSame( 'https://example.com/', Typo3\Html::typolink( 'https://example.com/ - - - &L=1' ) );
        $this->assertSame( '/page-5-1#c3', $html->href( '5#c3 - - - &L=1' ) );

        $this->assertSame( '/page-5', $html->href( '5' ) );
        $this->assertSame( '/page-5#c3', $html->href( '5#c3 _blank' ) );
        $this->assertSame( '/page-5', $html->href( '5,3' ) );
        $this->assertSame( '/files/f12.pdf', $html->href( 'file:12' ) );
        $this->assertSame( 'mailto:info@example.com', $html->href( 'info@example.com' ) );
        $this->assertSame( 'https://www.example.com', $html->href( 'www.example.com' ) );
        $this->assertSame( '', $html->href( '99' ) );
        $this->assertSame( '', $html->href( 'javascript:alert(1)' ) );
        $this->assertSame( '', $html->href( '' ) );
    }


    public function testPrefetchesFileReferencesOfMissingRecords(): void
    {
        $fetched = ['refs' => [], 'accordions' => [], 'carousels' => [], 'carouselRefs' => []];
        $fetch = function( string $type, \Closure $fn ) use ( &$fetched ) {
            return function( array $uids ) use ( $type, $fn, &$fetched ) {
                $fetched[$type][] = $uids;
                return Collection::make( array_combine( $uids, array_map( $fn, $uids ) ) );
            };
        };
        $typo3 = new class( fn() => null, new Typo3\Html( fn() => null, fn() => null ),
            $fetch( 'refs', fn( int $uid ) => Collection::make( [(object) ['uid_local' => $uid]] ) ),
            $fetch( 'accordions', fn( int $uid ) => Collection::make( [(object) ['uid' => $uid * 10]] ) ),
            $fetch( 'carousels', fn( int $uid ) => Collection::make( [(object) ['uid' => $uid * 10], (object) ['uid' => $uid * 10 + 1]] ) ),
            $fetch( 'carouselRefs', fn( int $uid ) => Collection::make( [(object) ['uid_local' => $uid]] ) )
        ) extends Typo3\Content {
            /** @return array<string, array<int|string>> */
            public function keys(): array
            {
                return [
                    'refs' => $this->refs->keys()->all(),
                    'accordions' => $this->accordionItems->keys()->all(),
                    'carousels' => $this->carouselItems->keys()->all(),
                    'carouselRefs' => $this->carouselFileRefs->keys()->all(),
                ];
            }
        };
        $records = fn( string $type, int ...$uids ) => array_map( fn( int $uid ) => (object) ['uid' => $uid, 'CType' => $type], $uids );

        // only the data of the records which weren't prefetched before is fetched and the one of other records is kept
        $typo3->prefetch( [...$records( 'text', 1 ), ...$records( 'accordion', 2 ), ...$records( 'carousel', 3 )] );
        $typo3->prefetch( [...$records( 'carousel', 3 ), ...$records( 'accordion', 4 ), ...$records( 'carousel', 5 )] );
        $this->assertSame( ['refs' => [1, 2, 3, 4, 5], 'accordions' => [2, 4], 'carousels' => [3, 5], 'carouselRefs' => [30, 31, 50, 51]], $typo3->keys() );
        $this->assertSame( [3, 4, 5, 30, 31, 50, 51], $typo3->references()->pluck( 'uid_local' )->all() );

        // records prefetched before aren't fetched again
        $typo3->prefetch( $records( 'text', 1 ) );
        $this->assertSame( ['refs' => [1, 2, 3, 4, 5], 'accordions' => [2, 4], 'carousels' => [3, 5], 'carouselRefs' => [30, 31, 50, 51]], $typo3->keys() );
        $this->assertSame( [1], $typo3->references()->pluck( 'uid_local' )->all() );

        // only the data of the current and the upcoming records is kept when the ones of the upcoming pages are fetched
        $this->assertSame( [6], $typo3->ahead( $records( 'text', 6 ) )->pluck( 'uid_local' )->all() );
        $this->assertSame( ['refs' => [1, 6], 'accordions' => [], 'carousels' => [], 'carouselRefs' => []], $typo3->keys() );

        // dropped records are fetched again
        $typo3->prefetch( $records( 'accordion', 2 ) );
        $this->assertSame( [
            'refs' => [[1, 2, 3], [4, 5], [6], [2]],
            'accordions' => [[2], [4], [2]],
            'carousels' => [[3], [5]],
            'carouselRefs' => [[30, 31], [50, 51]],
        ], $fetched );
    }


    public function testHtmlRewritesContentAnchorsAndCurrentPage(): void
    {
        $calls = [];
        $html = new Typo3\Html(
            fn( string $url ) => null,
            function( int $uid, string $fragment ) use ( &$calls ) {
                $calls[] = [$uid, $fragment];
                return '/page-' . $uid . ( $fragment !== '' ? '#' . Typo3\Urls::fragment( $fragment ) : '' );
            }
        );

        $anchor = Typo3\Urls::anchor( 7 );

        // anchors of content elements on the same page and links to the current page
        $this->assertSame( '<a href="#' . $anchor . '">a</a> <a href="#top">b</a> <a href="/page-0#' . $anchor . '">c</a>',
            $html->rewrite( '<a href="#c7">a</a> <a href="#top">b</a> <a href="t3://page?uid=current#c7">c</a>' )['html'] );
        $this->assertSame( [[0, 'c7']], $calls );
    }
}
