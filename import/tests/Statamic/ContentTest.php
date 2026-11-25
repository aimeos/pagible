<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests\Statamic;

use Aimeos\Cms\Import\Statamic\Bard;
use Aimeos\Cms\Import\Statamic\Content;
use Aimeos\Cms\Import\Statamic\Meta;
use Aimeos\Cms\Import\Statamic\Reader;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;


/**
 * Tests of the Statamic content, meta data and database readers.
 */
class ContentTest extends TestAbstract
{
    /** @var array<int, array{0: string, 1: string}> Imported assets with their alt text */
    protected array $imported = [];


    public function testAsset(): void
    {
        $this->assertSame( 'assets::img/a.jpg', Content::asset( 'asset::assets::img/a.jpg', 'assets' ) );
        $this->assertSame( 'media::img/a.jpg', Content::asset( 'statamic://asset::media::/img/a.jpg', 'assets' ) );
        $this->assertSame( 'media::img/a.jpg', Content::asset( 'img/a.jpg', 'media' ) );
        $this->assertSame( 'https://x.test/asset::a::b', Content::asset( 'https://x.test/asset::a::b', 'assets' ) );
        $this->assertSame( '/img/a.jpg', Content::asset( '/img/a.jpg', 'assets' ) );
        $this->assertSame( '', Content::asset( ' ', 'assets' ) );
    }


    public function testAssets(): void
    {
        $data = [
            'image' => 'img/a.jpg',
            'body' => [['type' => 'set', 'attrs' => ['values' => ['type' => 'gallery', 'images' => ['img/b.jpg', 'asset::media::c.jpg']]]]],
            'title' => 'img/no.jpg',
        ];

        $this->assertSame( ['assets::img/a.jpg', 'assets::img/b.jpg', 'media::c.jpg'], $this->content()->assets( $data ) );
    }


    public function testBard(): void
    {
        $result = $this->content()->elements( ['type' => 'doc', 'content' => [
            ['type' => 'paragraph', 'content' => [
                ['type' => 'text', 'text' => 'Bold', 'marks' => [['type' => 'bold']]],
                ['type' => 'text', 'text' => ' and '],
                ['type' => 'text', 'text' => 'link', 'marks' => [['type' => 'link', 'attrs' => ['href' => 'statamic://entry::about']]]],
                ['type' => 'text', 'text' => ' and '],
                ['type' => 'text', 'text' => 'unsafe', 'marks' => [['type' => 'link', 'attrs' => ['href' => 'javascript:alert(1)']]]],
            ]],
            ['type' => 'heading', 'attrs' => ['level' => 9], 'content' => [['type' => 'text', 'text' => 'Title']]],
            ['type' => 'bulletList', 'content' => [
                ['type' => 'listItem', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'One']]]]],
                ['type' => 'listItem', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Two']]]]],
            ]],
            ['type' => 'codeBlock', 'attrs' => ['language' => 'php'], 'content' => [['type' => 'text', 'text' => 'echo 1;']]],
            ['type' => 'horizontalRule'],
            ['type' => 'table', 'content' => [
                ['type' => 'tableRow', 'content' => [
                    ['type' => 'tableHeader', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Name']]]]],
                ]],
                ['type' => 'tableRow', 'content' => [
                    ['type' => 'tableCell', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Alice']]]]],
                ]],
            ]],
            ['type' => 'set', 'attrs' => ['enabled' => false, 'values' => ['type' => 'text', 'text' => 'Hidden']]],
            ['type' => 'image', 'attrs' => ['src' => 'asset::assets::img/a.jpg', 'alt' => 'A']],
        ]] );

        $this->assertSame( [
            ['text', ['text' => '**Bold** and [link](</about>) and unsafe']],
            ['heading', ['level' => 6, 'title' => 'Title']],
            ['text', ['text' => "- One\n- Two"]],
            ['code', ['language' => 'php', 'text' => 'echo 1;']],
            ['html', ['text' => '<hr>']],
            ['table', ['title' => '', 'header' => 'row', 'table' => [['Name'], ['Alice']]]],
            ['image', 'file-assets::img/a.jpg'],
        ], $this->data( $result ) );
        $this->assertSame( [['assets::img/a.jpg', 'A']], $this->imported );
        $this->assertSame( ['file-assets::img/a.jpg'], $result['fileIds'] );
    }


    public function testBardTableHeaderColumnAndSetextLines(): void
    {
        $cell = fn( string $type, string $text ) => ['type' => $type, 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => $text]]]]];
        $table = fn( array ...$rows ) => ['type' => 'doc', 'content' => [['type' => 'table', 'content' => array_map( fn( $row ) => ['type' => 'tableRow', 'content' => $row], $rows )]]];

        $this->assertSame( [['table', ['title' => '', 'header' => 'col', 'table' => [['A', '1'], ['B', '2']]]]], $this->data( $this->content()->elements(
            $table( [$cell( 'tableHeader', 'A' ), $cell( 'tableCell', '1' )], [$cell( 'tableHeader', 'B' ), $cell( 'tableCell', '2' )] )
        ) ) );
        $this->assertSame( [['table', ['title' => '', 'header' => 'row+col', 'table' => [['', 'X'], ['B', '2']]]]], $this->data( $this->content()->elements(
            $table( [$cell( 'tableHeader', '' ), $cell( 'tableHeader', 'X' )], [$cell( 'tableHeader', 'B' ), $cell( 'tableCell', '2' )] )
        ) ) );

        // "===" after a line break would make the line before a heading
        $result = $this->content()->elements( ['type' => 'doc', 'content' => [['type' => 'paragraph', 'content' => [
            ['type' => 'text', 'text' => 'Title'], ['type' => 'hardBreak'], ['type' => 'text', 'text' => '==='],
        ]]]] );
        $this->assertStringNotContainsString( '<h1>', Str::markdown( $this->data( $result )[0][1]['text'] ) );
    }


    public function testBardInlineImages(): void
    {
        $warnings = [];
        $content = new Content( fn( string $asset ) => 'file-' . $asset, fn() => null, 'assets', Content::FIELDS, function( string $key, string $msg ) use ( &$warnings ) {
            $warnings[$key] = $msg;
        } );

        $result = $content->elements( ['type' => 'doc', 'content' => [
            ['type' => 'paragraph', 'content' => [
                ['type' => 'text', 'text' => 'Before'],
                ['type' => 'image', 'attrs' => ['src' => 'asset::assets::img/a.jpg', 'alt' => 'A']],
                ['type' => 'text', 'text' => 'After'],
            ]],
            ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Next']]],
            ['type' => 'bulletList', 'content' => [
                ['type' => 'listItem', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'image', 'attrs' => ['src' => 'img/b.jpg']]]]]],
            ]],
        ]] );

        // images in paragraphs split the text, the text after the image is merged with the following paragraphs
        $this->assertSame( [
            ['text', ['text' => 'Before']],
            ['image', 'file-assets::img/a.jpg'],
            ['text', ['text' => "After\n\nNext\n\n-"]],
        ], $this->data( $result ) );
        $this->assertArrayHasKey( 'bard:image', $warnings );
    }


    public function testBardSuperscriptAndSubscript(): void
    {
        $result = $this->content()->elements( [['type' => 'paragraph', 'content' => [
            ['type' => 'text', 'text' => 'E = mc'],
            ['type' => 'text', 'text' => '2', 'marks' => [['type' => 'superscript']]],
            ['type' => 'text', 'text' => ' and H'],
            ['type' => 'text', 'text' => '2', 'marks' => [['type' => 'subscript']]],
            ['type' => 'text', 'text' => 'O'],
        ]]] );

        $this->assertSame( [['html', ['text' => '<p>E = mc<sup>2</sup> and H<sub>2</sub>O</p>']]], $this->data( $result ) );

        // tags in the text are escaped and don't make it HTML
        $result = $this->content()->elements( [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Use <sup> tags']]]] );

        $this->assertSame( 'text', $result['elements'][0]['type'] ?? null );
    }


    public function testBardMarksWithHtml(): void
    {
        $result = $this->content()->elements( [['type' => 'paragraph', 'content' => [
            ['type' => 'text', 'text' => 'Under', 'marks' => [['type' => 'underline']]],
            ['type' => 'text', 'text' => ' '],
            ['type' => 'text', 'text' => 'small', 'marks' => [['type' => 'small']]],
            ['type' => 'text', 'text' => ' '],
            ['type' => 'text', 'text' => 'new', 'marks' => [['type' => 'link', 'attrs' => ['href' => 'https://example.com/', 'target' => '_blank', 'title' => 'Ex']]]],
        ]]] );

        $html = (string) ( $result['elements'][0]['data']['text'] ?? '' );

        $this->assertSame( 'html', $result['elements'][0]['type'] ?? null );
        $this->assertStringContainsString( '<u>Under</u> <small>small</small>', $html );
        $this->assertStringContainsString( 'href="https://example.com/"', $html );
        $this->assertStringContainsString( 'target="_blank"', $html );
        $this->assertStringContainsString( 'title="Ex"', $html );

        // link types like "nofollow" need HTML links and are kept by the sanitizer
        $result = $this->content()->elements( [['type' => 'paragraph', 'content' => [
            ['type' => 'text', 'text' => 'Ad', 'marks' => [['type' => 'link', 'attrs' => ['href' => 'https://shop.test/', 'rel' => 'Sponsored  nofollow']]]],
        ]]] );

        $this->assertSame( 'html', $result['elements'][0]['type'] ?? null );
        $this->assertStringContainsString( '<a href="https://shop.test/" rel="sponsored nofollow">Ad</a>', (string) ( $result['elements'][0]['data']['text'] ?? '' ) );

        // links in the same window keep their title in Markdown
        $result = $this->content()->elements( [['type' => 'paragraph', 'content' => [
            ['type' => 'text', 'text' => 'About', 'marks' => [['type' => 'link', 'attrs' => ['href' => 'entry::about', 'title' => 'Say "hi"']]]],
        ]]] );

        $this->assertSame( [['text', ['text' => '[About](</about> "Say \\"hi\\"")']]], $this->data( $result ) );
    }


    public function testBardInline(): void
    {
        $result = $this->content()->elements( [
            ['type' => 'text', 'text' => 'Bold', 'marks' => [['type' => 'bold']]],
            ['type' => 'hardBreak'],
            ['type' => 'text', 'text' => 'text'],
        ] );

        $this->assertSame( [['text', ['text' => "**Bold**  \ntext"]]], $this->data( $result ) );
    }


    public function testBardMarkSpacesAndAdjacentMarks(): void
    {
        $result = $this->content()->elements( [['type' => 'paragraph', 'content' => [
            ['type' => 'text', 'text' => 'A '],
            ['type' => 'text', 'text' => ' bold ', 'marks' => [['type' => 'bold']]],
            ['type' => 'text', 'text' => 'both', 'marks' => [['type' => 'bold'], ['type' => 'italic']]],
            ['type' => 'text', 'text' => 'again ', 'marks' => [['type' => 'bold']]],
            ['type' => 'text', 'text' => 'end'],
        ]]] );

        // spaces are moved outside the marks and marks of adjacent text stay open
        $this->assertSame( [['text', ['text' => 'A  **bold *both*again** end']]], $this->data( $result ) );

        $result = $this->content()->elements( [['type' => 'paragraph', 'content' => [
            ['type' => 'text', 'text' => 'one', 'marks' => [['type' => 'italic']]],
            ['type' => 'text', 'text' => 'two', 'marks' => [['type' => 'italic'], ['type' => 'bold']]],
            ['type' => 'text', 'text' => 'three', 'marks' => [['type' => 'bold']]],
        ]]] );

        // marks directly following closed marks use HTML because Markdown would join their syntax
        $this->assertSame( 'html', $result['elements'][0]['type'] ?? null );
        $this->assertStringContainsString( '<em>one<strong>two</strong></em><strong>three</strong>', (string) ( $result['elements'][0]['data']['text'] ?? '' ) );
    }


    public function testBardNestedCodeFence(): void
    {
        $result = $this->content()->elements( [['type' => 'bulletList', 'content' => [['type' => 'listItem', 'content' => [
            ['type' => 'codeBlock', 'content' => [['type' => 'text', 'text' => "a\n````\nb"]]],
        ]]]]] );

        $this->assertSame( [['text', ['text' => "- `````\n  a\n  ````\n  b\n  `````"]]], $this->data( $result ) );
    }


    public function testHtmlAndMarkdownReferences(): void
    {
        $result = $this->content()->elements( '<p><a href="statamic://entry::about">About</a> <a href="statamic://entry::missing">Gone</a></p>' );
        $html = (string) ( $result['elements'][0]['data']['text'] ?? '' );

        $this->assertStringContainsString( '<a href="/about">About</a>', $html );
        $this->assertStringContainsString( 'Gone', $html );
        $this->assertStringNotContainsString( 'statamic://', $html );

        $result = $this->content()->elements( 'See [About](statamic://entry::about) and [Gone](statamic://entry::missing)' );

        $this->assertSame( [['text', ['text' => 'See [About](</about>) and Gone']]], $this->data( $result ) );
    }


    public function testBlueprintContainers(): void
    {
        $this->blueprint( 'pages', 'hidden', ['image' => 'hidden'], ['hide' => true, 'order' => 1] );
        $this->blueprint( 'pages', 'special', ['image' => 'special'] );
        $this->blueprint( 'pages', 'page', ['hero' => 'images', 'image' => 'photos'], ['order' => 2] );
        $reader = new Reader( DB::connection( 'statamic' ), 'en', ['pages'] );

        // entries without or with unknown blueprint use the first ordered blueprint which isn't hidden
        $this->assertEquals( ['hero' => 'images', 'image' => 'photos'], $reader->containers( (object) ['collection' => 'pages', 'blueprint' => null] ) );
        $this->assertEquals( ['hero' => 'images', 'image' => 'photos'], $reader->containers( (object) ['collection' => 'pages', 'blueprint' => 'unknown'] ) );
        $this->assertEquals( ['image' => 'special'], $reader->containers( (object) ['collection' => 'pages', 'blueprint' => 'special'] ) );
        $this->assertEquals( ['image' => 'hidden'], $reader->containers( (object) ['collection' => 'pages', 'blueprint' => 'hidden'] ) );
        // older versions of the Eloquent driver store the blueprint in the data
        $this->assertEquals( ['image' => 'special'], $reader->containers( (object) ['collection' => 'pages', 'blueprint' => null, 'data' => ['blueprint' => 'special']] ) );
        $this->assertSame( [], $reader->containers( (object) ['collection' => 'news'] ) );

        $content = $this->content()->containers( ['hero' => 'images', 'image' => 'photos'] );
        $data = ['hero' => 'a.jpg', 'blocks' => [['type' => 'image', 'image' => 'b.jpg'], ['type' => 'gallery', 'images' => ['c.jpg']]]];

        $this->assertSame( ['images::a.jpg', 'photos::b.jpg', 'assets::c.jpg'], $content->assets( $data ) );
        $this->assertSame( [['image', 'file-photos::b.jpg'], ['image', 'file-assets::c.jpg']], array_map(
            fn( $el ) => [$el['type'] ?? null, $el['data']['file']['id'] ?? null], $content->elements( $data['blocks'] )['elements']
        ) );
    }


    public function testBlueprintTexts(): void
    {
        $this->blueprint( 'pages', 'page', [
            'body' => ['type' => 'markdown', 'automatic_line_breaks' => false, 'automatic_links' => true],
            'intro' => ['type' => 'textarea'],
            'blocks' => ['type' => 'replicator', 'sets' => ['main' => ['sets' => ['text' => ['fields' => [['handle' => 'text', 'field' => ['type' => 'markdown']]]]]]]],
            'image' => 'images',
        ] );
        $reader = new Reader( DB::connection( 'statamic' ), 'en', ['pages'] );
        $entry = (object) ['collection' => 'pages', 'blueprint' => null];

        $this->assertEquals( [
            'body' => ['type' => 'markdown', 'breaks' => false, 'links' => true, 'escape' => false, 'smart' => false, 'antlers' => false],
            'intro' => ['type' => 'textarea', 'breaks' => true, 'links' => false, 'escape' => false, 'smart' => false, 'antlers' => false],
            'text.text' => ['type' => 'markdown', 'breaks' => true, 'links' => false, 'escape' => false, 'smart' => false, 'antlers' => false],
            'text' => ['type' => 'markdown', 'breaks' => true, 'links' => false, 'escape' => false, 'smart' => false, 'antlers' => false],
        ], $reader->texts( $entry ) );
        $this->assertEquals( ['image' => 'images'], $reader->containers( $entry ) );
    }


    public function testBlueprintFieldsets(): void
    {
        $this->fieldset( 'common', [
            ['handle' => 'body', 'field' => ['type' => 'markdown', 'automatic_links' => true]],
            ['handle' => 'image', 'field' => ['type' => 'assets', 'container' => 'media', 'max_files' => 1]],
            ['import' => 'seo', 'prefix' => 'seo_'],
        ] );
        $this->fieldset( 'seo', [['handle' => 'note', 'field' => ['type' => 'textarea', 'antlers' => true]]] );
        $this->blueprint( 'pages', 'page', [
            ['import' => 'common', 'prefix' => 'x_'],
            ['handle' => 'intro', 'field' => 'common.body', 'config' => ['automatic_line_breaks' => false]],
            ['handle' => 'note', 'field' => 'common.seo_note'],
            ['handle' => 'gone', 'field' => 'common.gone'],
            'links' => ['type' => 'grid', 'fields' => [['handle' => 'url', 'field' => ['type' => 'text']]]],
            'blocks' => ['type' => 'replicator', 'sets' => [
                'quote' => ['fields' => [['handle' => 'text', 'field' => ['type' => 'textarea']], ['handle' => 'image', 'field' => ['type' => 'assets', 'container' => 'quotes']]]],
                'promo' => ['fields' => [['handle' => 'clip', 'field' => ['type' => 'video']], ['handle' => 'target', 'field' => ['type' => 'link']],
                    ['handle' => 'global', 'field' => ['type' => 'link', 'select_across_sites' => true]], ['handle' => 'points', 'field' => ['type' => 'list']], ['handle' => 'facts', 'field' => ['type' => 'array', 'keys' => ['size' => 'Size', 'age' => '']]],
                    ['handle' => 'specs', 'field' => ['type' => 'array', 'key_header' => 'Spec', 'value_header' => 'Value', 'keys' => [['key' => 'cpu', 'value' => 'CPU']]]],
                    ['handle' => 'address', 'field' => ['type' => 'array', 'mode' => 'keyed', 'key_header' => 'Ignored']]]],
                'article' => ['fields' => [['handle' => 'text', 'field' => ['type' => 'markdown', 'escape_markup' => true, 'smartypants' => true]], ['import' => 'seo']]],
            ]],
        ] );
        $reader = new Reader( DB::connection( 'statamic' ), 'en', ['pages'] );
        $entry = (object) ['collection' => 'pages', 'blueprint' => 'page'];

        $this->assertEquals( [
            'x_body' => ['type' => 'markdown', 'breaks' => true, 'links' => true, 'escape' => false, 'smart' => false, 'antlers' => false],
            'x_seo_note' => ['type' => 'textarea', 'breaks' => true, 'links' => false, 'escape' => false, 'smart' => false, 'antlers' => true],
            'intro' => ['type' => 'markdown', 'breaks' => false, 'links' => true, 'escape' => false, 'smart' => false, 'antlers' => false],
            'note' => ['type' => 'textarea', 'breaks' => true, 'links' => false, 'escape' => false, 'smart' => false, 'antlers' => true],
            'url' => ['type' => 'text', 'breaks' => true, 'links' => false, 'escape' => false, 'smart' => false, 'antlers' => false],
            'quote.text' => ['type' => 'textarea', 'breaks' => true, 'links' => false, 'escape' => false, 'smart' => false, 'antlers' => false],
            'article.text' => ['type' => 'markdown', 'breaks' => true, 'links' => false, 'escape' => true, 'smart' => true, 'antlers' => false],
            'article.note' => ['type' => 'textarea', 'breaks' => true, 'links' => false, 'escape' => false, 'smart' => false, 'antlers' => true],
            'text' => ['type' => 'textarea', 'breaks' => true, 'links' => false, 'escape' => false, 'smart' => false, 'antlers' => false],
        ], $reader->texts( $entry ) );
        $this->assertEquals( ['x_image' => 'media', 'quote.image' => 'quotes', 'image' => 'quotes'], $reader->containers( $entry ) );
        $this->assertSame( ['links' => true], $reader->grids( $entry ) );
        $this->assertSame( ['promo.clip' => true, 'clip' => true], $reader->videos( $entry ) );
        $this->assertSame( ['promo.target' => true, 'promo.global' => true, 'target' => true, 'global' => true], $reader->links( $entry ) );
        // Link fields selecting entries across sites aren't localized
        $this->assertSame( ['promo.target' => false, 'promo.global' => true, 'target' => false, 'global' => true], $reader->across( $entry ) );
        $this->assertSame( ['promo.points' => true, 'points' => true], $reader->lists( $entry ) );
        // keys are configured as map or as list of key/value pairs, the column headers are only used in the dynamic mode
        $this->assertSame( [
            'promo.facts' => ['labels' => ['size' => 'Size'], 'headers' => ['', '']],
            'promo.specs' => ['labels' => ['cpu' => 'CPU'], 'headers' => ['Spec', 'Value']],
            'promo.address' => ['labels' => [], 'headers' => ['', '']],
            'facts' => ['labels' => ['size' => 'Size'], 'headers' => ['', '']],
            'specs' => ['labels' => ['cpu' => 'CPU'], 'headers' => ['Spec', 'Value']],
            'address' => ['labels' => [], 'headers' => ['', '']],
        ], $reader->arrays( $entry ) );
        $this->assertSame( ['x_image'], $reader->images( $entry ) );
    }


    public function testSetFieldSettings(): void
    {
        $content = $this->content()->texts( [
            'quote.text' => ['type' => 'textarea', 'breaks' => true, 'links' => false, 'escape' => false, 'smart' => false, 'antlers' => false],
            'article.text' => ['type' => 'markdown', 'breaks' => true, 'links' => false, 'escape' => false, 'smart' => false, 'antlers' => false],
            'text' => ['type' => 'textarea', 'breaks' => true, 'links' => false, 'escape' => false, 'smart' => false, 'antlers' => false],
        ] )->containers( ['quote.image' => 'quotes', 'image' => 'other'] );

        $this->assertSame( [
            ['text', ['text' => '> \\*a\\* https\\://example.com' . "\n" . '> -- *A \\*B\\**']],
            ['text', ['text' => '*a*']],
        ], $this->data( $content->elements( [
            ['type' => 'quote', 'text' => '*a* https://example.com', 'cite' => 'A *B*'],
            ['type' => 'article', 'text' => '*a*'],
        ] ) ) );
        $this->assertSame( ['quotes::q.jpg', 'other::o.jpg'], $content->assets( ['blocks' => [['type' => 'quote', 'image' => 'q.jpg'], ['type' => 'other', 'image' => 'o.jpg']]] ) );
    }


    public function testMarkdownHtmlBlocks(): void
    {
        $text = "<div>\nSee https://x.com\nnext\n</div>\n\nText https://y.com\nmore\n\n<!-- a\nhttps://c.com -->\nAfter https://z.com";
        $content = $this->content()->texts( ['body' => ['type' => 'markdown', 'breaks' => true, 'links' => false, 'escape' => false, 'smart' => false, 'antlers' => false]] );
        $html = json_encode( $this->data( $content->elements( $text, 'body' ) ) );

        // URLs and line breaks in HTML blocks are unchanged, the backslashes would be shown
        $this->assertStringNotContainsString( 'https\\\\:', (string) $html );
        $this->assertStringContainsString( 'See https:\\/\\/x.com\\nnext', (string) $html );
        $this->assertStringContainsString( 'Text https:\\/\\/y.com<br', (string) $html );
        $this->assertStringNotContainsString( '<a ', (string) $html );
    }


    public function testMarkdownStatamicExtensions(): void
    {
        $result = $this->data( $this->content()->elements( "Text[^1] and more\n\n[^1]: The note", 'body' ) );
        $this->assertSame( 'html', $result[0][0] ?? null );
        $this->assertStringNotContainsString( '[^1]', (string) json_encode( $result ) );
        $this->assertStringContainsString( 'The note', (string) json_encode( $result ) );

        $result = $this->data( $this->content()->elements( "## Title {.lead}\n\nText", 'body' ) );
        $this->assertStringNotContainsString( '{.lead}', (string) json_encode( $result ) );
        $this->assertStringContainsString( 'Title', (string) json_encode( $result ) );

        $result = $this->data( $this->content()->elements( "Term\n: Definition", 'body' ) );
        $this->assertStringNotContainsString( ': Definition', (string) json_encode( $result ) );

        $content = $this->content()->texts( ['body' => ['type' => 'markdown', 'breaks' => true, 'links' => false, 'escape' => false, 'smart' => true, 'antlers' => false]] );
        $this->assertStringContainsString( '“a”', (string) json_encode( $this->data( $content->elements( '"a" -- b', 'body' ) ), JSON_UNESCAPED_UNICODE ) );
    }


    public function testMarkdownEscapeMarkup(): void
    {
        $content = $this->content()->texts( ['body' => ['type' => 'markdown', 'breaks' => true, 'links' => false, 'escape' => true, 'smart' => false, 'antlers' => false]] );
        $result = $this->data( $content->elements( "<div>A <b>x</b> `<i>` <https://a.example></div>", 'body' ) );

        $this->assertSame( [['text', ['text' => '\\<div>A \\<b>x\\</b> `<i>` <https://a.example>\\</div>']]], $result );
        $this->assertStringContainsString( '&lt;div&gt;A &lt;b&gt;x&lt;/b&gt; <code>&lt;i&gt;</code>', Str::markdown( $result[0][1]['text'] ) );
    }


    public function testGridsAndAntlers(): void
    {
        $warnings = [];
        $content = new Content( fn( string $asset ) => $asset, fn( string $ref ) => null, warn: function( string $key, string $msg ) use ( &$warnings ) {
            $warnings[$key] = $msg;
        } );
        $content->grids( ['links' => true, 'faq.rows' => true] )->texts( ['body' => ['type' => 'markdown', 'breaks' => true, 'links' => false, 'escape' => false, 'smart' => false, 'antlers' => true]] );

        $this->assertSame( [], $content->elements( [['url' => 'https://a.example']], 'links' )['elements'] );
        $this->assertSame( [], $content->elements( [['id' => 'x1', 'label' => 'A']], 'other' )['elements'] );
        $content->elements( [['type' => 'faq', 'rows' => [['q' => 'Q']], 'text' => 'T']] );
        $content->elements( 'Hello {{ name }}', 'body' );

        $this->assertSame( ['grid:links', 'grid:other', 'grid:faq.rows', 'antlers:body'], array_keys( $warnings ) );
    }


    public function testTableFields(): void
    {
        $content = $this->content();
        $result = $this->data( $content->elements( [['cells' => ['*A*', "B\nC"]], ['cells' => ['1', null]]], 'body' ) );

        // the cells of Table fields are inline Markdown like rendered by the "markdown" modifier
        $this->assertSame( [['table', ['title' => '', 'header' => '', 'table' => [['*A*', 'B C'], ['1', '']]]]], $result );
        // URLs aren't linked
        $this->assertSame( [['https\\://example.com']], $content->elements( [['cells' => ['https://example.com']]], 'body' )['elements'][0]['data']['table'] ?? null );
        // the first row is the header if the "first_row_headers" field next to the table is set
        $this->assertSame( 'row', $content->content( ['body' => [['cells' => ['A']], ['cells' => ['1']]], 'first_row_headers' => true] )['elements'][0]['data']['header'] ?? null );
        $this->assertSame( 'row', $content->elements( [['type' => 'table', 'first_row_headers' => true, 'table' => [['cells' => ['A']], ['cells' => ['1']]]]] )['elements'][0]['data']['header'] ?? null );
        $this->assertSame( 'table', $content->elements( [['type' => 'table', 'table' => [['cells' => ['A']]]]] )['elements'][0]['type'] ?? null );
        $this->assertSame( [], $content->elements( [['cells' => ['', '']]], 'body' )['elements'] );
    }


    public function testSetsWithoutTextField(): void
    {
        $warnings = [];
        $content = new Content( fn( string $asset ) => 'file-' . $asset, fn( string $ref ) => null, warn: function( string $key, string $msg ) use ( &$warnings ) {
            $warnings[$key] = $msg;
        } );
        $content->texts( ['hero.subtitle' => ['type' => 'markdown', 'breaks' => true, 'links' => false, 'escape' => false, 'smart' => false, 'antlers' => false]] );

        $result = $this->data( $content->elements( [
            ['type' => 'hero', 'id' => 'h1', 'title' => 'Hero', 'subtitle' => 'Some *text*', 'style' => 'dark', 'image' => 'img/a.jpg'],
            ['type' => 'section', 'blocks' => [['type' => 'text', 'text' => 'Inner']]],
            ['type' => 'columns', 'left' => 'Left', 'right' => 'Right'],
            ['type' => 'spacer', 'id' => 's1'],
        ] ) );

        $this->assertSame( [
            ['heading', ['level' => 2, 'title' => 'Hero']],
            ['text', ['text' => 'Some *text*']],
            ['image', 'file-assets::img/a.jpg'],
            ['text', ['text' => 'Inner']],
        ], $result );
        // sets whose fields are unknown are reported, sets without fields aren't
        $this->assertSame( ['set:columns'], array_keys( $warnings ) );
    }


    public function testSetsWithTextAndOtherFields(): void
    {
        $content = $this->content();
        $content->texts( [
            'feature.button_text' => ['type' => 'text', 'breaks' => true, 'links' => false, 'escape' => false, 'smart' => false, 'antlers' => false],
            'feature.anchor' => ['type' => 'text', 'breaks' => true, 'links' => false, 'escape' => false, 'smart' => false, 'antlers' => false],
            'feature.notes' => ['type' => 'textarea', 'breaks' => true, 'links' => false, 'escape' => false, 'smart' => false, 'antlers' => false],
        ] );

        $result = $this->data( $content->elements( [
            ['type' => 'feature', 'heading' => "Big\nNews", 'level' => 'h3', 'text' => 'Body', 'image' => 'img/a.jpg',
                'button_text' => 'Read more', 'anchor' => 'news', 'notes' => 'Plain', 'file' => 'docs/a b.pdf'],
            ['type' => 'hero', 'text' => '', 'title' => 'Hero'],
        ] ) );

        // all fields of the sets are imported in their order, Text fields only if they are named like text fields
        $this->assertSame( [
            ['heading', ['level' => 3, 'title' => 'Big News']],
            ['text', ['text' => 'Body']],
            ['image', 'file-assets::img/a.jpg'],
            ['text', ['text' => 'Plain']],
            ['file', ['file' => ['id' => 'file-assets::docs/a b.pdf', 'type' => 'file']]],
            ['heading', ['level' => 2, 'title' => 'Hero']],
        ], $result );

        // heading levels of Select fields like "h4" and line breaks in Bard headings
        $this->assertSame( 4, Bard::level( 'h4' ) );
        $this->assertSame( 2, Bard::level( 'big' ) );
        $this->assertSame( [['heading', ['level' => 2, 'title' => 'A B']]], $this->data( $content->elements( [['type' => 'heading', 'attrs' => ['level' => 2],
            'content' => [['type' => 'text', 'text' => 'A'], ['type' => 'hardBreak'], ['type' => 'text', 'text' => 'B']]]] ) ) );
    }


    public function testFileFields(): void
    {
        $content = new Content( fn( string $asset ) => str_contains( $asset, 'missing' ) ? null : 'file-' . $asset, fn( string $ref ) => null );

        $result = $content->elements( [
            ['type' => 'download', 'file' => 'docs/a b.pdf'],
            ['type' => 'download', 'file' => ['media/clip.mp4', 'media/song.mp3', 'docs/missing.pdf']],
        ] );

        // files which aren't images become file, video and audio elements, files which couldn't be imported are left out
        $this->assertSame( [
            ['file', ['file' => ['id' => 'file-assets::docs/a b.pdf', 'type' => 'file']]],
            ['video', ['file' => ['id' => 'file-assets::media/clip.mp4', 'type' => 'file']]],
            ['audio', ['file' => ['id' => 'file-assets::media/song.mp3', 'type' => 'file']]],
        ], $this->data( $result ) );
        $this->assertSame( ['file-assets::docs/a b.pdf', 'file-assets::media/clip.mp4', 'file-assets::media/song.mp3'], $result['fileIds'] );
    }


    public function testImageFieldsWithAltAndCaption(): void
    {
        $result = $this->content()->elements( [
            ['type' => 'figure', 'image' => 'img/a.jpg', 'alt' => 'Alt A', 'caption' => 'Caption A'],
            ['type' => 'figure', 'image' => 'img/b.jpg', 'caption' => 'Caption B'],
            ['type' => 'figure', 'image' => 'img/c.jpg'],
        ] );

        $this->assertSame( [['assets::img/a.jpg', 'Alt A'], ['assets::img/b.jpg', 'Caption B'], ['assets::img/c.jpg', '']], $this->imported );
        $this->assertSame( ['image-text', 'image-text', 'image'], array_column( $result['elements'], 'type' ) );
        $this->assertSame( 'Caption A', $result['elements'][0]['data']['text'] );
    }


    public function testCodeFields(): void
    {
        $warnings = [];
        $content = new Content( fn( string $asset ) => 'file-' . $asset, fn( string $ref ) => null, warn: function( string $key, string $msg ) use ( &$warnings ) {
            $warnings[$key] = $msg;
        } );
        $content->texts( ['example.snippet' => ['type' => 'code', 'breaks' => true, 'links' => false, 'escape' => false, 'smart' => false, 'antlers' => false, 'mode' => 'php']] );

        $result = $this->data( $content->elements( [
            ['type' => 'example', 'source' => ['code' => '<?php echo 1;', 'mode' => 'php'], 'snippet' => 'echo *2*;'],
        ] ) );

        $this->assertSame( [
            ['code', ['language' => 'php', 'text' => '<?php echo 1;']],
            ['code', ['language' => 'php', 'text' => 'echo *2*;']],
        ], $result );
        $this->assertSame( [], $warnings );
    }


    public function testCodeLanguages(): void
    {
        $content = $this->content();
        $content->texts( [
            'example.html' => ['type' => 'code', 'breaks' => true, 'links' => false, 'escape' => false, 'smart' => false, 'antlers' => false, 'mode' => 'htmlmixed'],
            'example.other' => ['type' => 'code', 'breaks' => true, 'links' => false, 'escape' => false, 'smart' => false, 'antlers' => false],
        ] );

        $result = $this->data( $content->elements( [
            ['type' => 'example', 'html' => '<p>A</p>', 'other' => '<p>B</p>', 'source' => ['code' => 'class A {}', 'mode' => 'text/x-java']],
            ['type' => 'code', 'code' => ['code' => '# A', 'mode' => 'gfm']],
        ] ) );
        $result = array_merge( $result, $this->data( $content->elements( [
            ['type' => 'codeBlock', 'attrs' => ['language' => 'JSX'], 'content' => [['type' => 'text', 'text' => '<A />']]],
        ] ) ) );

        $this->assertSame( [
            ['code', ['language' => 'html', 'text' => '<p>A</p>']],
            ['code', ['language' => 'html', 'text' => '<p>B</p>']],
            ['code', ['language' => 'java', 'text' => 'class A {}']],
            ['code', ['language' => 'markdown', 'text' => '# A']],
            ['code', ['language' => 'javascript', 'text' => '<A />']],
        ], $result );
    }


    public function testGroupFields(): void
    {
        $warnings = [];
        $content = new Content( fn( string $asset ) => 'file-' . $asset, fn( string $ref ) => null, warn: function( string $key, string $msg ) use ( &$warnings ) {
            $warnings[$key] = $msg;
        } );

        $result = $this->data( $content->elements( ['heading' => 'Hi', 'text' => 'Group text', 'color' => 'red'], 'body' ) );

        $this->assertSame( [['heading', ['level' => 2, 'title' => 'Hi']], ['text', ['text' => 'Group text']]], $result );
        $this->assertSame( [], $content->elements( ['color' => 'red'], 'intro' )['elements'] );
        $this->assertSame( ['group:intro'], array_keys( $warnings ) );
    }


    public function testMarkdownImages(): void
    {
        $text = "Text [About](statamic://entry::about)\n\n![Alt](statamic://asset::media::img/a.jpg)\n\n![Rel](/assets/b.jpg)";
        $result = $this->data( $this->content()->elements( $text, 'body' ) );

        // images become image elements whose assets are imported with their meta data
        $this->assertSame( [
            ['html', ['text' => '<p>Text <a href="/about">About</a></p>']],
            ['image', 'file-media::img/a.jpg'],
            ['image', 'file-/assets/b.jpg'],
        ], $result );
    }


    public function testBardTableCells(): void
    {
        $cell = fn( array $content, array $attrs = [], string $type = 'tableCell' ) => ['type' => $type, 'attrs' => $attrs, 'content' => $content];
        $text = fn( string $text, array $marks = [] ) => ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => $text, 'marks' => $marks]]];

        $result = $this->data( $this->content()->elements( [['type' => 'table', 'content' => [
            ['type' => 'tableRow', 'content' => [
                $cell( [$text( 'Wide', [['type' => 'bold']] )], ['colspan' => 2] ),
                $cell( [$text( 'Tall' )], ['rowspan' => 2] ),
            ]],
            ['type' => 'tableRow', 'content' => [
                $cell( [$text( 'Link', [['type' => 'link', 'attrs' => ['href' => 'https://x.test']]] )] ),
                $cell( [$text( '2*3*4' ), $text( '# Not' )] ),
            ]],
            ['type' => 'tableRow', 'content' => [$cell( [$text( 'A' )] ), $cell( [$text( 'B' )] ), $cell( [$text( 'C' )] )]],
        ]]] ) );

        $this->assertSame( [['table', ['title' => '', 'header' => '', 'table' => [
            ['**Wide**', '', 'Tall'],
            ['[Link](<https://x.test>)', '2\\*3\\*4 \\# Not', ''],
            ['A', 'B', 'C'],
        ]]]], $result );
    }


    public function testMarkdownLineBreaksAndLinks(): void
    {
        $text = "Line one\nline two https://example.com/a_b www.example.com me@example.com\n\n```\nhttps://code.example\nnext\n```\n\n`https://span.example` <https://auto.example> [Link](https://link.example)\n\n    https://indented.example";
        $result = $this->data( $this->content()->elements( $text, 'body' ) );

        // Statamic converts single line breaks to line breaks and doesn't link URLs by default
        $this->assertSame( [['text', ['text' => "Line one  \nline two https\\://example.com/a_b www\\.example.com me\\@example.com\n\n```\nhttps://code.example\nnext\n```\n\n`https://span.example` <https://auto.example> [Link](https\\://link.example)\n\n    https://indented.example"]]], $result );

        $html = Str::markdown( $result[0][1]['text'] );
        $this->assertStringContainsString( "Line one<br />\nline two https://example.com/a_b www.example.com me@example.com</p>", $html );
        $this->assertStringContainsString( '<a href="https://link.example">Link</a>', $html );
        $this->assertStringContainsString( '<a href="https://auto.example">', $html );
        $this->assertSame( 2, substr_count( $html, '<a ' ) );

        // the settings of the Markdown field are used
        $content = $this->content()->texts( ['body' => ['type' => 'markdown', 'breaks' => false, 'links' => true]] );
        $this->assertSame( [['text', ['text' => "A\nB https://example.com"]]], $this->data( $content->elements( "A\nB https://example.com", 'body' ) ) );

        // Bard doesn't link URLs either
        $bard = ['type' => 'doc', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'See https://example.com']]]]];
        $this->assertSame( [['text', ['text' => 'See https\\://example.com']]], $this->data( $this->content()->elements( $bard ) ) );
    }


    public function testPlainTextFields(): void
    {
        $content = $this->content()->texts( ['intro' => ['type' => 'textarea', 'breaks' => true, 'links' => false], 'title' => ['type' => 'text', 'breaks' => true, 'links' => false]] );

        $this->assertSame( [['text', ['text' => "\\# Not \\*a\\* heading\n1\\. https\\://example.com"]]], $this->data( $content->elements( "# Not *a* heading\n1. https://example.com", 'intro' ) ) );
        $this->assertSame( [['text', ['text' => 'A \\_b\\_']]], $this->data( $content->elements( 'A _b_', 'title' ) ) );
        // HTML in plain text fields isn't Markdown
        $this->assertSame( 'html', $content->elements( '<b>A</b> *b*', 'intro' )['elements'][0]['type'] ?? null );
        $this->assertStringContainsString( '*b*', (string) json_encode( $content->elements( '<b>A</b> *b*', 'intro' )['elements'] ) );
    }


    public function testReplicatorSets(): void
    {
        $result = $this->content()->elements( [
            ['type' => 'heading', 'heading' => 'Intro', 'level' => 3],
            ['type' => 'text', 'text' => 'Some *text*'],
            ['type' => 'code', 'code' => ['code' => '<?php', 'mode' => 'php']],
            ['type' => 'quote', 'quote' => 'Wise words', 'cite' => 'Someone'],
            ['type' => 'callout', 'callout_type' => 'warning', 'text' => 'Careful'],
            ['type' => 'callout', 'text' => 'Plain note'],
            ['type' => 'gallery', 'images' => ['img/b.jpg', 'img/c.jpg']],
            ['type' => 'image', 'image' => ['img/d.jpg'], 'caption' => 'Caption'],
            ['type' => 'text', 'enabled' => false, 'text' => 'Hidden'],
            ['type' => 'custom_set', 'body' => 'Custom body'],
            ['type' => 'custom_set'],
        ] );
        $data = $this->data( $result );

        $this->assertSame( ['heading', ['level' => 3, 'title' => 'Intro']], $data[0] );
        $this->assertSame( ['text', ['text' => 'Some *text*']], $data[1] );
        $this->assertSame( ['code', ['language' => 'php', 'text' => '<?php']], $data[2] );
        $this->assertStringContainsString( 'Wise words', (string) json_encode( $data[3] ) );
        $this->assertSame( ['text', ['text' => '**Warning:** Careful']], $data[4] );
        $this->assertSame( ['text', ['text' => '**Note:** Plain note']], $data[5] );
        $this->assertSame( ['image', 'file-assets::img/b.jpg'], $data[6] );
        $this->assertSame( ['image', 'file-assets::img/c.jpg'], $data[7] );
        $this->assertSame( ['image-text', 'file-assets::img/d.jpg'], [$result['elements'][8]['type'] ?? null, $result['elements'][8]['data']['file']['id'] ?? null] );
        $this->assertStringContainsString( 'Caption', (string) json_encode( $result['elements'][8] ) );
        $this->assertSame( ['text', ['text' => 'Custom body']], $data[9] );
        $this->assertCount( 10, $data );
        $this->assertStringNotContainsString( 'Hidden', (string) json_encode( $data ) );
    }


    public function testVideoSetOnlyEmbedsVideoPlayers(): void
    {
        $video = fn( string $url ) => $this->content()->elements( [['type' => 'video', 'url' => $url]] )['elements'];
        $text = fn( string $url ) => (string) ( $video( $url )[0]['data']['text'] ?? '' );

        $this->assertStringContainsString( 'src="https://www.youtube-nocookie.com/embed/abcdef"', $text( 'https://www.youtube.com/watch?v=abcdef' ) );
        $this->assertStringContainsString( 'src="https://player.vimeo.com/video/123?dnt=1"', $text( 'https://vimeo.com/123' ) );
        $this->assertStringContainsString( 'src="https://player.vimeo.com/video/123?h=abc&amp;dnt=1#t=75s"', $text( 'https://vimeo.com/123/abc#t=1m15s' ) );
        $this->assertStringContainsString( 'src="https://www.youtube-nocookie.com/embed/abcdef?start=42"', $text( 'https://www.youtube.com/watch?v=abcdef&t=42s' ) );
        $this->assertStringContainsString( 'src="https://customer-abc123.cloudflarestream.com/0123456789abcdef0123456789abcdef/iframe"',
            $text( 'https://customer-abc123.cloudflarestream.com/0123456789abcdef0123456789abcdef/watch' ) );
        $this->assertStringContainsString( 'src="https://iframe.videodelivery.net/0123456789abcdef0123456789abcdef"',
            $text( 'https://watch.videodelivery.net/0123456789abcdef0123456789abcdef' ) );

        // other pages are linked instead of embedded
        $this->assertStringNotContainsString( '<iframe', $text( 'https://example.com/embed/x' ) );
        $this->assertStringContainsString( '<a href="https://example.com/embed/x">https://example.com/embed/x</a>', $text( 'https://example.com/embed/x' ) );

        $this->assertSame( [], $video( 'javascript:alert(1)' ) );
        $this->assertSame( [], $video( '//evil.example/embed' ) );
        $this->assertSame( [], $video( '/embed/video' ) );
    }


    public function testVideoFields(): void
    {
        $content = $this->content()->videos( ['hero.clip' => true, 'video.youtube' => true] );

        $result = $content->elements( [
            ['type' => 'hero', 'title' => 'Hi', 'clip' => 'https://youtu.be/abcdefg?t=1m30s'],
            ['type' => 'video', 'url' => 'https://vimeo.com/123', 'youtube' => 'https://www.youtube.com/watch?v=hijklmn'],
            ['type' => 'feature', 'clip' => 'https://youtu.be/opqrstu'],
        ] );
        $texts = array_map( fn( $el ) => (string) ( $el['data']['text'] ?? $el['data']['title'] ?? '' ), $result['elements'] );

        // Video fields are imported in any set, Video fields of the blueprint are used by video sets first
        $this->assertSame( ['heading', 'html', 'html'], array_column( $result['elements'], 'type' ) );
        $this->assertStringContainsString( 'src="https://www.youtube-nocookie.com/embed/abcdefg?start=90"', $texts[1] );
        $this->assertStringContainsString( 'src="https://www.youtube-nocookie.com/embed/hijklmn"', $texts[2] );
    }


    public function testLinkFields(): void
    {
        $warnings = [];
        $content = new Content( fn( string $asset ) => 'file-' . $asset, fn( string $ref ) => match( $ref ) {
            'entry::about' => '/about', '@child' => '/about/team', 'asset::assets::docs/a.pdf' => 'https://cdn.example.com/docs/a.pdf', default => null,
        }, warn: function( string $key, string $msg ) use ( &$warnings ) {
            $warnings[$key] = $msg;
        } );
        $content->links( ['button.link' => true, 'cta.url' => true] );

        $result = $content->elements( [
            ['type' => 'button', 'label' => 'About *us*', 'link' => 'entry::about'],
            ['type' => 'button', 'button_text' => 'Team', 'link' => '@child'],
            ['type' => 'button', 'label' => 'Docs', 'link' => 'asset::assets::docs/a.pdf'],
            ['type' => 'cta', 'title' => 'Join', 'text' => 'Sign up now', 'url' => 'https://example.com/join'],
            ['type' => 'button', 'label' => 'Gone', 'link' => 'entry::gone'],
            ['type' => 'button', 'label' => 'Bad', 'link' => 'javascript:alert(1)'],
        ] );

        // labels are used for the links only, other fields of the set are imported as before
        $this->assertSame( [
            ['text', ['text' => '[About \\*us\\*](</about>)']],
            ['text', ['text' => '[Team](</about/team>)']],
            ['text', ['text' => '[Docs](<https://cdn.example.com/docs/a.pdf>)']],
            ['heading', ['level' => 2, 'title' => 'Join']],
            ['text', ['text' => 'Sign up now']],
            ['text', ['text' => '[https\\://example.com/join](<https://example.com/join>)']],
        ], $this->data( $result ) );
        $this->assertArrayHasKey( 'link:entry::gone', $warnings );
        $this->assertArrayHasKey( 'link:javascript:alert(1)', $warnings );
    }


    public function testLinkLabelsOfSeveralLinks(): void
    {
        $content = $this->content()->links( ['buttons.primary_link' => true, 'buttons.secondary' => true, 'buttons.more' => true] );

        $result = $content->elements( [[
            'type' => 'buttons', 'label' => 'Shared', 'primary_link' => '/start', 'primary_label' => 'Start',
            'secondary' => '/contact', 'secondary_text' => 'Contact', 'more' => '/more',
        ]] );

        // labels are paired by the handles of the Link fields, the common label fields are only used for single links
        $this->assertSame( [
            ['text', ['text' => '[Start](</start>)']],
            ['text', ['text' => '[Contact](</contact>)']],
            ['text', ['text' => '[/more](</more>)']],
        ], $this->data( $result ) );
    }


    public function testLinkFieldsWithoutBlueprint(): void
    {
        $result = $this->content()->elements( [
            ['type' => 'button', 'label' => 'About', 'link' => 'statamic://entry::about'],
            ['type' => 'teaser', 'text' => 'entry::about is no link in a text field', 'target' => 'https://example.com'],
        ] );

        // references only Link fields store are links, URLs need the blueprint because Text fields contain them too
        $this->assertSame( [
            ['text', ['text' => '[About](</about>)']],
            ['text', ['text' => 'entry::about is no link in a text field']],
        ], $this->data( $result ) );
    }


    public function testBardInlineCodeAndNestedCode(): void
    {
        $code = fn( string $text ) => ['type' => 'text', 'text' => $text, 'marks' => [['type' => 'code']]];
        $cell = fn( array $node ) => ['type' => 'tableCell', 'content' => [['type' => 'paragraph', 'content' => [$node]]]];

        $result = $this->content()->elements( ['type' => 'doc', 'content' => [
            ['type' => 'paragraph', 'content' => [$code( 'x ``y`` z' )]],
            ['type' => 'bulletList', 'content' => [['type' => 'listItem', 'content' => [
                ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Item']]],
                ['type' => 'codeBlock', 'content' => [['type' => 'text', 'text' => 'echo 1;']]],
            ]]]],
            ['type' => 'blockquote', 'content' => [['type' => 'table', 'content' => [['type' => 'tableRow', 'content' => [$cell( $code( 'a|b' ) ), $cell( ['type' => 'text', 'text' => 'c'] )]]]]]],
        ]] );
        $md = (string) ( $this->data( $result )[0][1]['text'] ?? '' );
        $html = Str::markdown( $md );

        // the backticks around code are longer than the ones in the code and pipes in table cells are escaped
        $this->assertStringContainsString( '<code>x ``y`` z</code>', $html );
        $this->assertStringContainsString( "  ```\n  echo 1;\n  ```", $md );
        $this->assertStringContainsString( '<td><code>a|b</code></td>', $html );
        $this->assertStringContainsString( '<td>c</td>', $html );
    }


    public function testListAndArrayFields(): void
    {
        $warnings = [];
        $content = new Content( fn( string $asset ) => 'file-' . $asset, fn( string $ref ) => null, warn: function( string $key, string $msg ) use ( &$warnings ) {
            $warnings[$key] = $msg;
        } );
        $content->lists( ['features.items' => true, 'features.tagged' => true] )->arrays( [
            'features.facts' => ['labels' => ['size' => 'Size *in* m'], 'headers' => ['', '']],
            'features.sizes' => ['labels' => ['42' => 'Large'], 'headers' => ['Size', 'Name']],
        ] );

        $result = $content->elements( [[
            'type' => 'features', 'text' => 'Intro', 'items' => ['One', '- Two', ' '], 'tagged' => ['A & B', '<b>Bold</b> <script>x</script>'],
            'facts' => ['size' => '3', 'age' => '2 years', 'empty' => ''],
            'sizes' => [['key' => '42', 'value' => 'Medium'], ['key' => '7', 'value' => 'Small'], ['key' => '9', 'value' => null]],
        ]] );
        $data = $this->data( $result );

        $this->assertSame( ['text', ['text' => 'Intro']], $data[0] );
        $this->assertSame( ['text', ['text' => "- One\n- \\- Two"]], $data[1] );
        // items with HTML tags are converted as sanitized HTML like Statamic templates output them
        $this->assertSame( ['html', ['text' => '<ul><li>A &amp; B</li><li><b>Bold</b> </li></ul>']], $data[2] );
        $this->assertSame( ['table', ['title' => '', 'header' => 'col', 'table' => [['Size \\*in\\* m', '3'], ['age', '2 years']]]], $data[3] );
        // expanded arrays are lists of key/value pairs and the column headers are the first row
        $this->assertSame( ['table', ['title' => '', 'header' => 'row+col', 'table' => [['Size', 'Name'], ['Large', 'Medium'], ['7', 'Small']]]], $data[4] );
        $this->assertSame( [], $warnings );
    }


    public function testBardNestedRulesAndTables(): void
    {
        $text = fn( string $value ) => ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => $value]]];
        $cell = fn( string $type, string $value ) => ['type' => $type, 'content' => [$text( $value )]];
        $table = ['type' => 'table', 'content' => [
            ['type' => 'tableRow', 'content' => [$cell( 'tableHeader', 'Name' ), $cell( 'tableHeader', 'Age' )]],
            ['type' => 'tableRow', 'content' => [$cell( 'tableCell', 'A|B' ), $cell( 'tableCell', '3' )]],
        ]];

        $result = $this->content()->elements( ['type' => 'doc', 'content' => [
            ['type' => 'blockquote', 'content' => [$text( 'Quote' ), ['type' => 'horizontalRule'], $table]],
            ['type' => 'bulletList', 'content' => [
                ['type' => 'listItem', 'content' => [$text( 'Item' ), ['type' => 'horizontalRule']]],
                ['type' => 'listItem', 'content' => [$text( 'Rows' ), ['type' => 'table', 'content' => [
                    ['type' => 'tableRow', 'content' => [$cell( 'tableCell', 'x' ), $cell( 'tableCell', 'y' )]],
                ]]]],
            ]],
        ]] );
        $md = (string) ( $this->data( $result )[0][1]['text'] ?? '' );

        $this->assertSame( "> Quote\n>\n> ***\n>\n> | Name | Age |\n> | --- | --- |\n> | A\\|B | 3 |\n\n"
            . "- Item\n  ***\n- Rows\n\n  |  |  |\n  | --- | --- |\n  | x | y |", $md );

        $html = Str::markdown( $md );
        $this->assertSame( 2, substr_count( $html, '<hr />' ) );
        $this->assertSame( 2, substr_count( $html, '<table>' ) );
        $this->assertStringContainsString( '<th>Name</th>', $html );
        $this->assertStringContainsString( '<td>A|B</td>', $html );
        $this->assertStringContainsString( '<p>Rows</p>', $html );
    }


    public function testVideoSetWithVideoFiles(): void
    {
        $result = $this->content()->elements( [
            ['type' => 'video', 'url' => 'https://example.com/media/clip.mp4?v=1'],
            ['type' => 'video', 'video' => ['media/b.webm']],
            ['type' => 'video', 'url' => 'javascript:alert(1)//a.mp4'],
        ] );

        $this->assertSame( ['video', 'video'], array_column( $result['elements'], 'type' ) );
        $this->assertSame( [['https://example.com/media/clip.mp4?v=1', ''], ['assets::media/b.webm', '']], $this->imported );
    }


    public function testMarkdownAndHtml(): void
    {
        $markdown = $this->data( $this->content()->elements( "# Title\n\nText with <strong>bold</strong> and <script>alert(1)</script>" ) );
        $html = $this->data( $this->content()->elements( '<h2>Heading</h2><p>Paragraph <img src="/img/e.jpg" alt="E"></p>' ) );

        $this->assertSame( ['heading', ['level' => 1, 'title' => 'Title']], $markdown[0] );
        // Markdown with inline HTML is sanitized by the HTML converter
        $this->assertStringContainsString( '<strong>bold</strong>', (string) ( $markdown[1][1]['text'] ?? '' ) );
        $this->assertStringNotContainsString( '<script', (string) ( $markdown[1][1]['text'] ?? '' ) );
        $this->assertSame( ['heading', ['level' => 2, 'title' => 'Heading']], $html[0] );
        $this->assertContains( ['/img/e.jpg', 'E'], $this->imported );
    }


    public function testContentOfConfiguredFields(): void
    {
        $warnings = [];
        $content = new Content( fn() => null, fn() => null, 'assets', ['body', 'blocks'], function( string $key, string $msg ) use ( &$warnings ) {
            $warnings[$key] = $msg;
        } );
        $result = $content->content( [
            'body' => 'Body text', 'title' => 'Title', 'blocks' => [['type' => 'text', 'text' => 'Block text']],
            'sidebar' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Side']]]],
        ] );
        $texts = (string) json_encode( $result['elements'] );

        $this->assertStringContainsString( 'Body text', $texts );
        $this->assertStringContainsString( 'Block text', $texts );
        $this->assertStringNotContainsString( 'Title', $texts );
        $this->assertStringNotContainsString( 'Side', $texts );
        $this->assertSame( ['field:sidebar'], array_keys( $warnings ) );
    }


    public function testLink(): void
    {
        $content = $this->content();

        $this->assertSame( '/about', $content->link( 'entry::about' ) );
        $this->assertSame( '/about', $content->link( 'statamic://entry::about' ) );
        $this->assertNull( $content->link( 'entry::missing' ) );
        $this->assertSame( 'https://example.com/', $content->link( 'https://example.com/' ) );
        $this->assertSame( 'mailto:a@example.com', $content->link( 'mailto:a@example.com' ) );
        $this->assertSame( '#top', $content->link( '#top' ) );
        $this->assertNull( $content->link( '//evil.example/' ) );
        $this->assertNull( $content->link( 'javascript:alert(1)' ) );
    }


    public function testMeta(): void
    {
        $images = [];
        $meta = new Meta( function( string $asset ) use ( &$images ) {
            $images[] = $asset;
            return 'file-' . $asset;
        } );

        $result = $meta->build( ['og_title' => 'Social', 'og_image' => 'img/og.jpg', 'meta_description' => 'Desc {{ title }}'], 'Title' );

        $this->assertNull( $result['title'] );
        $this->assertArrayNotHasKey( 'meta-tags', $result['meta'] );
        $this->assertSame( 'Social', $result['meta']['social-media']['data']['title'] ?? null );
        $this->assertSame( ['file-assets::img/og.jpg'], $result['fileIds'] );
        $this->assertSame( ['assets::img/og.jpg'], $images );

        $result = $meta->build( ['seo' => ['title' => '@seo:headline', 'canonical_url' => '/relative'], 'headline' => '<b>Headline</b>'], 'Title' );

        $this->assertSame( 'Headline', $result['title'] );
        $this->assertArrayNotHasKey( 'canonical', $result['meta'] );
        $this->assertSame( ['title' => null, 'meta' => [], 'fileIds' => []], $meta->build( ['seo' => false, 'meta_title' => 'X'], 'Title' ) );
    }


    public function testMetaSeoPro(): void
    {
        $warnings = [];
        $meta = new Meta( fn( string $asset ) => 'file-' . $asset, 'assets', function( string $key, string $msg ) use ( &$warnings ) {
            $warnings[$key] = $msg;
        } );

        $result = $meta->build( ['seo' => [
            'title' => 'About', 'site_name' => 'Example', 'site_name_separator' => '-',
            'og_title' => 'Social', 'description' => 'Desc', 'twitter_description' => 'Tweet', 'robots' => ['nofollow', 'noarchive'],
            'robots_noindex' => true,
        ]], 'Title' );

        // the Twitter values are only used for Twitter, the robots toggles are ignored if the robots checkboxes are set
        $this->assertSame( 'About - Example', $result['title'] );
        $this->assertSame( ['Social', 'Desc'], [$result['meta']['social-media']['data']['title'] ?? null, $result['meta']['social-media']['data']['description'] ?? null] );
        $this->assertStringContainsString( 'nofollow', (string) json_encode( $result['meta']['robots'] ?? null ) );
        $this->assertStringNotContainsString( 'noindex', (string) json_encode( $result['meta']['robots'] ?? null ) );
        $this->assertStringContainsString( 'noarchive', $warnings['seo:robots'] ?? '' );

        $result = $meta->build( ['seo' => ['title' => 'About', 'site_name' => 'Example', 'site_name_position' => 'before', 'twitter_title' => 'Tweet', 'image' => 'og.jpg']], 'Title' );

        // the Open Graph title falls back to the SEO title without site name
        $this->assertSame( 'Example | About', $result['title'] );
        $this->assertSame( 'About', $result['meta']['social-media']['data']['title'] ?? null );

        // disabled values don't fall back to the other fields and the site name is used without SEO title
        $result = $meta->build( ['seo' => ['title' => false, 'description' => false, 'site_name' => 'Example'], 'meta_title' => 'Meta', 'meta_description' => 'Meta desc'], 'Title' );
        $this->assertSame( 'Example', $result['title'] );
        $this->assertArrayNotHasKey( 'meta-tags', $result['meta'] );

        // the robots checkboxes may contain options with their keys
        $this->assertStringContainsString( 'noindex', (string) json_encode( $meta->build( ['seo' => ['robots' => [['key' => 'noindex']]]], 'Title' )['meta']['robots'] ?? null ) );
        $this->assertSame( 'About', $meta->build( ['seo' => ['title' => 'About', 'site_name' => 'Example', 'site_name_position' => 'none']], 'Title' )['title'] );

        // SEO Pro toggles of the robots directives and disabled SEO
        $warnings = [];
        $result = $meta->build( ['seo' => ['robots_noindex' => true, 'robots_nosnippet' => true, 'json_ld_schema' => '{"@type":"Thing"}']], 'Title' );

        $this->assertStringContainsString( 'noindex', (string) json_encode( $result['meta']['robots'] ?? null ) );
        $this->assertStringContainsString( 'nosnippet', $warnings['seo:robots'] ?? '' );
        $this->assertArrayHasKey( 'seo:json-ld', $warnings );
        $this->assertSame( ['title' => null, 'meta' => [], 'fileIds' => []], $meta->build( ['seo' => ['enabled' => false, 'title' => 'X'], 'meta_title' => 'X'], 'Title' ) );

        // SEO Pro has no Open Graph description and uses the meta description
        $result = $meta->build( ['seo' => ['og_title' => 'Social', 'og_description' => 'Ignored', 'description' => 'Desc']], 'Title' );
        $this->assertSame( 'Desc', $result['meta']['social-media']['data']['description'] ?? null );

        // inherited values referencing empty fields use the section and then the site defaults
        $fallbacks = [['title' => '@seo:headline', 'description' => '@seo:summary'], ['description' => 'Site description', 'image' => 'site.jpg']];
        $result = $meta->build( ['seo' => ['title' => '@seo:empty', 'description' => '@seo:empty', 'image' => '@seo:empty'], 'headline' => 'Headline', 'summary' => ''], 'Title', [], $fallbacks );
        $this->assertSame( 'Headline', $result['title'] );
        $this->assertStringContainsString( 'Site description', (string) json_encode( $result['meta']['meta-tags'] ?? null ) );
        $this->assertSame( ['file-assets::site.jpg'], $result['fileIds'] );

        // the plain text of Bard fields is used for references
        $content = [
            ['type' => 'heading', 'attrs' => ['level' => 2], 'content' => [['type' => 'text', 'text' => 'Intro']]],
            ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Bold', 'marks' => [['type' => 'bold']]], ['type' => 'text', 'text' => ' & more']]],
        ];
        $result = $meta->build( ['seo' => ['description' => '@seo:content'], 'content' => $content], 'Title' );
        $this->assertStringContainsString( 'Intro Bold & more', (string) json_encode( $result['meta']['meta-tags'] ?? null, JSON_UNESCAPED_UNICODE ) );

        // the container of the social media image field is taken from the blueprint
        $this->assertSame( ['file-media::og.jpg'], $meta->build( ['og_image' => 'og.jpg'], 'Title', ['og_image' => 'media'] )['fileIds'] );
    }


    public function testReaderCollectionSettings(): void
    {
        $this->collections( [
            'pages' => ['routes' => ['en' => '{parent_uri}/{slug}', 'de' => null], 'structure' => ['root' => true]],
            'news' => ['routes' => null, 'dated' => true, 'future_date_behavior' => 'private'],
            'events' => ['future_date_behavior' => 'private'],
        ] );
        $reader = new Reader( DB::connection( 'statamic' ), 'en', ['pages', 'news', 'events', 'other'] );
        $future = gmdate( 'Y-m-d H:i:s', time() + 3600 );

        $this->assertSame( [true, false, true, true], array_map( fn( $handle ) => $reader->routable( $handle ), ['pages', 'news', 'events', 'other'] ) );
        $this->assertFalse( ( new Reader( DB::connection( 'statamic' ), 'de', ['pages'] ) )->routable( 'pages' ) );
        $this->assertSame( [true, false], [$reader->expectsRoot( 'pages' ), $reader->expectsRoot( 'news' )] );
        $this->assertSame( [true, false], [$reader->isStructured( 'pages' ), $reader->isStructured( 'news' )] );
        $this->assertSame( [true, false], [$reader->hasSettings( 'events' ), $reader->hasSettings( 'other' )] );

        // the date is only used in dated collections
        $this->assertSame( strtotime( $future . ' UTC' ), $reader->dateRestriction( (object) ['collection' => 'news', 'date' => $future] )['at'] ?? null );
        $this->assertSame( ['private', 'public'], array_values( array_intersect_key( $reader->dateRestriction( (object) ['collection' => 'news', 'date' => $future] ) ?? [], array_flip( ['behavior', 'then'] ) ) ) );
        $this->assertNull( $reader->dateRestriction( (object) ['collection' => 'events', 'date' => $future] ) );
        $this->assertTrue( $reader->refresh( 'news', time() ) );
        $this->assertFalse( $reader->refresh( 'events', time() ) );
    }


    public function testReaderSites(): void
    {
        $this->site( 'de', 'de_DE', 'https://example.de/' );
        $this->site( 'ch', 'de_CH', '/ch/', 'gsw' );

        $this->assertSame( [
            'en' => ['lang' => 'en', 'url' => '/'],
            'de' => ['lang' => 'de', 'url' => 'https://example.de/'],
            'ch' => ['lang' => 'gsw', 'url' => '/ch/'],
        ], Reader::sites( DB::connection( 'statamic' ) ) );

        DB::connection( 'statamic' )->getSchemaBuilder()->drop( 'sites' );
        $this->assertNull( Reader::sites( DB::connection( 'statamic' ) ) );
    }


    public function testReaderDataAndTitle(): void
    {
        $reader = new Reader( DB::connection( 'statamic' ), 'en', ['pages'] );

        $this->assertSame( ['title' => 'JSON'], $reader->data( (object) ['data' => '{"title":"JSON"}'] ) );
        $this->assertSame( ['title' => 'Object'], $reader->data( (object) ['data' => (object) ['title' => 'Object']] ) );
        $this->assertSame( 'Title', $reader->title( (object) ['slug' => 'slug'], ['title' => 'Title'] ) );
        $this->assertSame( 'slug', $reader->title( (object) ['slug' => 'slug'], ['title' => ''] ) );
        $this->assertSame( 'Untitled', $reader->title( (object) ['slug' => ''], [] ) );
    }


    public function testReaderMergesDataOfOrigins(): void
    {
        $this->entries( [
            ['id' => 'en', 'slug' => 'about', 'data' => ['title' => 'About', 'body' => 'Text', 'image' => 'a.jpg']],
            ['id' => 'de', 'site' => 'de', 'origin_id' => 'en', 'slug' => 'ueber', 'data' => ['title' => 'Über']],
            ['id' => 'ch', 'site' => 'ch', 'origin_id' => 'de', 'slug' => 'ueber', 'data' => ['body' => 'Text CH']],
        ] );
        $reader = new Reader( DB::connection( 'statamic' ), 'en', ['pages'] );

        $entries = Collection::make( $reader->rows( ['en'] ) );
        $loaded = $reader->withData( $entries )->first() ?? new \stdClass();
        $translations = $reader->translations( $reader->withData( $entries ), ['de', 'ch'] );
        $ch = $reader->withData( Collection::make( [$translations['en']['ch'] ?? new \stdClass()] ) )->first() ?? new \stdClass();

        $this->assertSame( 'en', $loaded->root ?? null );
        $this->assertEqualsCanonicalizing( ['ch', 'de'], array_keys( $translations['en'] ?? [] ) );
        // localized entries inherit the values of their origins along the origin chain
        $this->assertEquals( ['title' => 'Über', 'body' => 'Text CH', 'image' => 'a.jpg'], $reader->data( $ch ) );
    }


    public function testReaderLocalizedFields(): void
    {
        $this->entries( [
            ['id' => 'en', 'slug' => 'about', 'data' => ['title' => 'About', 'body' => 'New text', 'image' => 'b.jpg']],
            // localized entries store an outdated copy of the origin data and the fields which are localized
            ['id' => 'de', 'site' => 'de', 'origin_id' => 'en', 'slug' => 'ueber', 'data' => [
                'title' => 'Über', 'body' => 'Old text', 'image' => 'a.jpg', '__localized_fields' => ['title'],
            ]],
        ] );
        $reader = new Reader( DB::connection( 'statamic' ), 'de', ['pages'] );

        $this->assertEquals( ['title' => 'Über', 'body' => 'New text', 'image' => 'b.jpg'], $reader->data( (object) ['id' => 'de', 'collection' => 'pages', 'site' => 'de'] ) );
    }


    public function testReaderMappedColumnsAndDefaults(): void
    {
        $db = DB::connection( 'statamic' );
        $db->getSchemaBuilder()->table( 'entries', fn( $table ) => $table->string( 'summary' )->nullable() );
        $db->getSchemaBuilder()->create( 'addon_settings', function( $table ) {
            $table->string( 'addon' )->primary();
            $table->json( 'settings' )->nullable();
        } );
        $db->table( 'addon_settings' )->insert( ['addon' => 'statamic/seo-pro', 'settings' => json_encode( [
            'site_defaults' => ['site_name' => 'Example', 'title' => '@seo:title', 'description' => 'Site'],
        ] )] );

        $this->collections( ['pages' => ['inject' => ['protect' => 'logged_in', 'layout' => 'wide', 'seo' => ['description' => 'Section']]]] );
        $this->entries( [['id' => 'en', 'slug' => 'about', 'summary' => 'Mapped', 'data' => [
            'title' => 'About', 'layout' => 'narrow', 'seo' => ['title' => 'Own', 'description' => null, 'site_name' => null],
        ]]] );
        $reader = new Reader( $db, 'en', ['pages'] );
        $entry = (object) ['id' => 'en', 'collection' => 'pages', 'site' => 'en'];

        // entry values override the injected values, SEO Pro merges site, section and entry values, inherited NULL values use the defaults
        $this->assertEquals( [
            'protect' => 'logged_in', 'layout' => 'narrow', 'title' => 'About', 'summary' => 'Mapped',
            'seo' => ['site_name' => 'Example', 'title' => 'Own', 'description' => 'Section'],
        ], $reader->data( $entry ) );
        $this->assertEquals( [['description' => 'Section'], ['site_name' => 'Example', 'title' => '@seo:title', 'description' => 'Site']], $reader->seoFallbacks( $entry ) );
        $this->assertTrue( $reader->hasSeoSettings() );

        // collections disabling SEO only disable it for entries without own SEO values
        $db->table( 'collections' )->where( 'handle', 'pages' )->update( ['settings' => json_encode( ['inject' => ['seo' => false]] )] );
        $reader = new Reader( $db, 'en', ['pages'] );
        $this->assertEquals( ['site_name' => 'Example', 'title' => 'Own', 'description' => 'Site'], $reader->data( $entry )['seo'] ?? null );
        $this->assertEquals( [[], ['site_name' => 'Example', 'title' => '@seo:title', 'description' => 'Site']], $reader->seoFallbacks( $entry ) );

        $db->table( 'entries' )->where( 'id', 'en' )->update( ['data' => json_encode( ['title' => 'About'] )] );
        $this->assertFalse( ( new Reader( $db, 'en', ['pages'] ) )->data( $entry )['seo'] ?? null );
        $this->assertFalse( ( new Reader( DB::connection( 'testing' ), 'en', ['pages'] ) )->hasSeoSettings() );
    }


    public function testReaderSorted(): void
    {
        $this->collections( [
            'news' => ['dated' => true],
            'people' => [],
            'products' => ['sort_field' => 'price', 'sort_dir' => 'desc'],
            'pages' => ['structure' => ['max_depth' => 3]],
        ] );
        $this->entries( [
            ['id' => 'old', 'collection' => 'news', 'date' => '2020-01-01 10:00:00', 'data' => ['title' => 'A']],
            ['id' => 'new', 'collection' => 'news', 'date' => '2024-01-01 10:00:00', 'data' => ['title' => 'B']],
            ['id' => 'zoe', 'collection' => 'people', 'data' => ['title' => 'Zoe']],
            ['id' => 'ann', 'collection' => 'people', 'data' => ['title' => 'Ann']],
            ['id' => 'cheap', 'collection' => 'products', 'data' => ['title' => 'Cheap', 'price' => 5]],
            ['id' => 'pricey', 'collection' => 'products', 'data' => ['title' => 'Pricey', 'price' => 50]],
        ] );
        $reader = new Reader( DB::connection( 'statamic' ), 'en', ['news', 'people', 'products', 'pages', 'other'] );

        // dated collections are sorted by date descending, others by title or their sort settings
        $this->assertSame( ['new', 'old'], $reader->sorted( 'news' ) );
        $this->assertSame( ['ann', 'zoe'], $reader->sorted( 'people' ) );
        $this->assertSame( ['pricey', 'cheap'], $reader->sorted( 'products' ) );
        $this->assertNull( $reader->sorted( 'pages' ) );
        $this->assertNull( $reader->sorted( 'other' ) );
    }


    public function testReaderParentTree(): void
    {
        $this->entries( [
            ['id' => 'home', 'order' => 1, 'data' => []],
            ['id' => 'about', 'order' => 2, 'data' => []],
            ['id' => 'team', 'order' => 3, 'data' => ['parent' => 'about']],
            ['id' => 'loop', 'order' => 4, 'data' => ['parent' => 'loop']],
            ['id' => 'orphan', 'order' => 5, 'data' => ['parent' => 'gone']],
        ] );
        $reader = new Reader( DB::connection( 'statamic' ), 'en', ['pages'] );

        $this->assertSame( [
            ['entry' => 'home'],
            ['entry' => 'about', 'children' => [['entry' => 'team']]],
            ['entry' => 'loop'],
            ['entry' => 'orphan'],
        ], $reader->parentTree( 'pages' ) );
        $this->assertSame( [], $reader->parentTree( 'news' ) );
    }


    public function testReaderAssets(): void
    {
        $this->asset( 'assets', 'img/a.jpg', ['title' => 'Title A', 'alt' => 'Alt A'], 'image/jpeg' );
        $this->asset( 'assets', 'img/b.png', ['alt' => 'Alt B'] );
        $this->asset( 'media', 'img/a.jpg' );
        $reader = new Reader( DB::connection( 'statamic' ), 'en', ['pages'] );

        $db = DB::connection( 'statamic' );
        $reader->prefetchAssets( ['assets::img/a.jpg', 'assets::img/b.png', 'media::img/a.jpg', 'assets::img/gone.jpg'] );
        $db->enableQueryLog();

        $this->assertSame( ['name' => 'Title A', 'extension' => 'jpg', 'mime' => 'image/jpeg', 'alt' => 'Alt A'], $reader->asset( 'assets', 'img/a.jpg' ) );
        $this->assertSame( ['name' => 'Alt B', 'extension' => 'png', 'mime' => '', 'alt' => 'Alt B'], $reader->asset( 'assets', 'img/b.png' ) );
        $this->assertSame( ['name' => 'a', 'extension' => 'jpg', 'mime' => '', 'alt' => ''], $reader->asset( 'media', 'img/a.jpg' ) );
        $this->assertNull( $reader->asset( 'assets', 'img/gone.jpg' ) );
        // prefetched assets aren't fetched again
        $this->assertSame( [], $db->getQueryLog() );
        $db->disableQueryLog();
    }


    /**
     * Returns the content converter which records the imported assets.
     */
    protected function content(): Content
    {
        return new Content( function( string $asset, string $alt = '' ) {
            $this->imported[] = [$asset, $alt];
            return 'file-' . $asset;
        }, fn( string $ref ) => $ref === 'entry::about' ? '/about' : null );
    }


    /**
     * Returns the types and data of the elements, image elements with their file ID only.
     *
     * @param array{elements: array<int, array<string, mixed>>} $result Converted content
     * @return array<int, array{0: mixed, 1: mixed}>
     */
    protected function data( array $result ): array
    {
        return array_map( fn( $el ) => [$el['type'] ?? null, ( $el['type'] ?? null ) === 'image' ? $el['data']['file']['id'] ?? null : $el['data'] ?? null], $result['elements'] );
    }
}
