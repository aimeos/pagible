<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests;

use Aimeos\Cms\FileStorage;
use Aimeos\Cms\Import\Date;
use Aimeos\Cms\Import\Drupal;
use Aimeos\Cms\Import\Elements;
use Aimeos\Cms\Import\Files;
use Aimeos\Cms\Import\Html;
use Aimeos\Cms\Import\Pages;
use Aimeos\Cms\Import\SharedElements;
use Aimeos\Cms\Import\Status;
use Aimeos\Cms\Import\Typo3;
use Aimeos\Cms\Import\Typo3Destinations;
use Aimeos\Cms\Import\Typo3Files;
use Aimeos\Cms\Import\Typo3Html;
use Aimeos\Cms\Import\Typo3Languages;
use Aimeos\Cms\Import\Typo3Layouts;
use Aimeos\Cms\Import\Typo3Records;
use Aimeos\Cms\Import\Typo3Site;
use Aimeos\Cms\Import\Typo3Tree;
use Aimeos\Cms\Import\Typo3Urls;
use Aimeos\Cms\Import\Variants;
use Aimeos\Cms\Import\WordPress;
use Aimeos\Cms\Import\WpArticle;
use Aimeos\Cms\Import\WpMedia;
use Aimeos\Cms\Models\Element;
use Aimeos\Cms\Models\File;
use Aimeos\Cms\Models\Page;
use Aimeos\Cms\Commands;
use Aimeos\Cms\Commands\DrupalImport;
use Aimeos\Cms\Commands\JoomlaImport;
use Aimeos\Cms\Commands\StatamicImport;
use Aimeos\Cms\Commands\T3Import;
use Aimeos\Cms\Models\PageView;
use Aimeos\Cms\Models\PageVariant;
use Aimeos\Cms\Translation\Hashes;
use Illuminate\Console\OutputStyle;
use Illuminate\Database\Events\TransactionCommitted;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\Filesystem;
use League\Flysystem\Local\LocalFilesystemAdapter;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use PHPUnit\Framework\Attributes\DataProvider;


class ImporterVersionTest extends ImportTestAbstract
{
    use CmsWithMigrations;
    use RefreshDatabase;


    public function testSharedHtmlParserPreservesElementMapping(): void
    {
        $tags = [];
        $html = new Html( function( string $tag ) use ( &$tags ) {
            $tags[] = $tag;
            return 'file-1';
        } );
        $result = $html->elements( "<h2>Heading</h2><p>Body</p><img src=\"image.jpg\" />\r\n\r\n<pre class=\"language-PHP\"><code>&lt;?php</code></pre>" );

        $this->assertSame( ['heading', 'html', 'image', 'code'], array_column( $result['elements'], 'type' ) );
        $this->assertSame( ['file-1'], $result['fileIds'] );
        $this->assertSame( ['<img src="image.jpg" />'], $tags );
        $this->assertSame( ['language' => 'php', 'text' => '<?php'], $result['elements'][3]['data'] );

        foreach( ['<img src="image.jpg">', '<img src="image.jpg" />', '<IMG SRC="image.jpg" alt="Image">'] as $code ) {
            $this->assertSame( ['image'], array_column( $html->elements( $code )['elements'], 'type' ), $code );
        }

        $this->assertSame( ['image', 'html'], array_column( $html->elements( '<p><img src="image.jpg"> Text</p>' )['elements'], 'type' ) );
        $this->assertSame( ['html'], array_column( $html->elements( '<p>Text <imgx src="image.jpg"></p>' )['elements'], 'type' ) );
        // the same image tags are found everywhere, other tags starting with "img" aren't images
        $tags = [];
        $this->assertNotContains( 'image', array_column( $html->elements( '<imgx src="image.jpg">' )['elements'], 'type' ) );
        $this->assertSame( ['html'], array_column( $html->elements( '<p><a href="/"><imgx src="image.jpg"></a> Text</p>' )['elements'], 'type' ) );
        $this->assertSame( [], $tags );
        $this->assertSame( ['image'], array_column( $html->elements( "<IMG\nsrc=\"image.jpg\">" )['elements'], 'type' ) );
        $this->assertSame( ['html', 'image', 'html'], array_column( $html->elements( '<p>Text <img> more</p>' )['elements'], 'type' ) );
        $this->assertSame( ["<IMG\nsrc=\"image.jpg\">", '<img>'], $tags );
        $this->assertNull( $html->text( '<p> </p>' ) );
        $this->assertSame( '<p>Text</p>', $html->text( ' <p>Text</p> ' )['elements'][0]['data']['text'] ?? null );
    }


    public function testSharedHtmlParserImportsImagesInFiguresAndKeepsFailedOnes(): void
    {
        $tags = [];
        $html = new Html( function( string $tag ) use ( &$tags ) {
            $tags[] = $tag;
            return str_contains( $tag, 'fail' ) ? null : 'file-1';
        } );

        // unlinked images after the caption are found, the caption becomes the Markdown text
        $result = $html->elements( '<figure><figcaption>Cap <b>bold</b></figcaption><img src="x.jpg"></figure>' );
        $this->assertSame( ['image-text'], array_column( $result['elements'], 'type' ) );
        $this->assertSame( ['text' => 'Cap **bold**', 'file' => Elements::file( 'file-1' )], $result['elements'][0]['data'] );
        $this->assertSame( ['<img src="x.jpg">'], $tags );

        // captions after the image are kept too, image-text elements have no link, so the link target is lost
        $result = $html->elements( '<figure><img src="x.jpg"><figcaption>Cap</figcaption></figure><figure><a href="big.jpg"><img src="x.jpg"></a><figcaption>Big</figcaption></figure>' );
        $this->assertSame( ['Cap', 'Big'], array_map( fn( $el ) => $el['data']['text'] ?? null, $result['elements'] ) );

        // images of a figure which can't be imported are removed, the caption is kept
        $result = $html->elements( '<figure><figcaption>Cap</figcaption><img src="fail.jpg"></figure>' );
        $this->assertSame( ['html'], array_column( $result['elements'], 'type' ) );
        $this->assertStringNotContainsString( '<img', $result['elements'][0]['data']['text'] );
        $this->assertStringContainsString( 'Cap', $result['elements'][0]['data']['text'] );

        // images which can't be imported are tried once and the link is kept with the alternative text
        $tags = [];
        $result = $html->elements( '<a href="https://x.org/"><img src="fail.jpg" alt="A &amp; B"></a>' );
        $this->assertSame( ['<a href="https://x.org/"><img src="fail.jpg" alt="A &amp; B"></a>'], $tags );
        $this->assertSame( ['html'], array_column( $result['elements'], 'type' ) );
        $this->assertStringContainsString( '<a href="https://x.org/">A &amp; B</a>', $result['elements'][0]['data']['text'] );

        // links without alternative text and bare images which can't be imported are removed
        $tags = [];
        $this->assertSame( [], $html->elements( '<a href="/"><img src="fail.jpg"></a><img src="fail.jpg">' )['elements'] );
        $this->assertCount( 2, $tags );
        $this->assertSame( [], $html->elements( '<p><img src="fail.jpg"></p>' )['elements'] );
        $result = $html->elements( '<figure><figcaption>Cap</figcaption><a href="/x"><img src="fail.jpg" alt="Alt"></a></figure>' );
        $this->assertStringContainsString( '<a href="/x">Alt</a>', $result['elements'][0]['data']['text'] ?? '' );

        // links around text and the image keep their closing tag
        $result = $html->elements( '<figure><a href="/x">Text <img src="x.jpg"></a></figure>' );
        $this->assertSame( ['text' => '[Text ](/x)', 'file' => Elements::file( 'file-1' )], $result['elements'][0]['data'] );

        // the remaining HTML of the image-text element is converted to Markdown, so no raw HTML is left
        $result = $html->elements( '<figure><figcaption><script>alert(1)</script><i>Cap</i></figcaption><img src="x.jpg"></figure>' );
        $this->assertStringNotContainsString( '<', $result['elements'][0]['data']['text'] );
    }


    public function testSharedHtmlParserKeepsContentOfFigures(): void
    {
        $tags = [];
        $html = new Html( function( string $tag ) use ( &$tags ) {
            $tags[] = $tag;
            return str_contains( $tag, 'fail' ) ? null : 'file-1';
        } );

        // figures starting with the image keep their text like the ones starting with the caption, further images follow
        $result = $html->elements( '<figure><img src="x.jpg"><p>Text</p><img src="b.jpg"><figcaption>Cap</figcaption></figure>' );
        $this->assertSame( ['image-text', 'image'], array_column( $result['elements'], 'type' ) );
        $this->assertSame( "Text\n\nCap", $result['elements'][0]['data']['text'] ?? null );
        $this->assertSame( ['<img src="x.jpg">', '<img src="b.jpg">'], $tags );
        $result = $html->elements( '<figure><figcaption>Cap</figcaption><img src="x.jpg"><p>Text</p></figure>' );
        $this->assertSame( "Cap\n\nText", $result['elements'][0]['data']['text'] ?? null );

        // figures with only the image and the caption are still images with the caption as text
        $result = $html->elements( '<figure class="f"> <a href="/big.jpg"> <img src="x.jpg"> </a> <figcaption>Cap</figcaption> </figure>' );
        $this->assertSame( ['image-text'], array_column( $result['elements'], 'type' ) );
        $this->assertSame( 'Cap', $result['elements'][0]['data']['text'] ?? null );
        $this->assertSame( ['image'], array_column( $html->elements( '<figure><img src="x.jpg"></figure>' )['elements'], 'type' ) );

        // block structure like tables is kept as HTML after the image instead of being converted to Markdown
        $result = $html->elements( '<figure><table><tr><td><img src="x.jpg"></td><td>A</td></tr></table><figcaption>Cap</figcaption></figure>' );
        $this->assertSame( ['image', 'html'], array_column( $result['elements'], 'type' ) );
        $this->assertSame( ['file-1'], $result['fileIds'] );
        $text = $result['elements'][1]['data']['text'] ?? '';
        $this->assertStringContainsString( '<td>A</td>', $text );
        $this->assertStringContainsString( '<p>Cap</p>', $text );
        $this->assertStringNotContainsString( '<img', $text );
        $result = $html->elements( '<figure><img src="x.jpg"><blockquote><p>Quote</p></blockquote><img src="b.jpg"></figure>' );
        $this->assertSame( ['image', 'html', 'image'], array_column( $result['elements'], 'type' ) );
        $this->assertSame( '<blockquote><p>Quote</p></blockquote>', $result['elements'][1]['data']['text'] ?? null );

        // the link text of failed linked images isn't glued to the caption
        $result = $html->elements( '<figure><a href="/x"><img src="fail.jpg" alt="Al"></a><figcaption>Cap</figcaption></figure>' );
        $this->assertSame( ['html'], array_column( $result['elements'], 'type' ) );
        $this->assertSame( '<p><a href="/x">Al</a></p><p>Cap</p>', $result['elements'][0]['data']['text'] ?? null );
        $result = $html->elements( '<figure><figcaption>Cap</figcaption><img src="fail.jpg"><p>Text</p></figure>' );
        $this->assertSame( '<p>Cap</p><p>Text</p>', $result['elements'][0]['data']['text'] ?? null );

        // further images of figures whose first image fails are imported as images after the text instead of being
        // hotlinked, links containing only them are removed from the text, links with text are kept
        $tags = [];
        $result = $html->elements( '<figure><img src="fail.jpg"><p>Hi</p><img src="b.jpg"><p><a href="/y"><img src="c.jpg"></a> <a href="/z">Z <img src="d.jpg"></a></p></figure>' );
        $this->assertSame( ['<img src="fail.jpg">', '<img src="b.jpg">', '<a href="/y"><img src="c.jpg"></a>', '<img src="d.jpg">'], $tags );
        $this->assertSame( ['html', 'image', 'image', 'image'], array_column( $result['elements'], 'type' ) );
        $this->assertStringNotContainsString( '<img', $result['elements'][0]['data']['text'] ?? '<img' );
        $this->assertSame( '<p>Hi</p><p> <a href="/z">Z </a></p>', $result['elements'][0]['data']['text'] ?? null );

        // further linked images in the text of image-text elements don't leave empty Markdown links
        $result = $html->elements( '<figure><img src="a.jpg"><p>Text <a href="/y"><img src="b.jpg"></a> after</p></figure>' );
        $this->assertSame( ['image-text', 'image'], array_column( $result['elements'], 'type' ) );
        $this->assertSame( 'Text  after', $result['elements'][0]['data']['text'] ?? null );

        // only the figure tags are removed, the divs inside are kept for the HTML element
        $result = $html->elements( '<figure><div class="wrap"><img src="fail.jpg"><p>Hi</p></div><figcaption>Cap</figcaption></figure>' );
        $this->assertSame( '<div class="wrap"><p>Hi</p></div><p>Cap</p>', $result['elements'][0]['data']['text'] ?? null );
        $result = $html->elements( '<figure><div><img src="x.jpg"><table><tr><td>A</td></tr></table></div></figure>' );
        $this->assertSame( ['image', 'html'], array_column( $result['elements'], 'type' ) );
        $this->assertStringStartsWith( '<div><table>', $result['elements'][1]['data']['text'] ?? '' );
        $result = $html->elements( '<figure><div class="wrap"><img src="x.jpg"><p>Text</p></div></figure>' );
        $this->assertSame( 'Text', $result['elements'][0]['data']['text'] ?? null );
    }


    public function testSharedHtmlParserImportsAllImagesOfFigures(): void
    {
        $tags = [];
        $html = new Html( function( string $tag ) use ( &$tags ) {
            $tags[] = $tag;
            return str_contains( $tag, 'fail' ) ? null : 'file-' . count( $tags );
        } );

        // all images of figures are imported, the further ones become images after the first image with the caption
        $result = $html->elements( '<figure><img src="a.jpg"><img src="b.jpg"><figcaption>Two</figcaption></figure>' );
        $this->assertSame( ['<img src="a.jpg">', '<img src="b.jpg">'], $tags );
        $this->assertSame( ['image-text', 'image'], array_column( $result['elements'], 'type' ) );
        $this->assertSame( ['text' => 'Two', 'file' => Elements::file( 'file-1' )], $result['elements'][0]['data'] );
        $this->assertSame( Elements::file( 'file-2' ), $result['elements'][1]['data']['file'] ?? null );
        $this->assertSame( ['file-1', 'file-2'], $result['fileIds'] );

        // further images which can't be imported are dropped
        $tags = [];
        $result = $html->elements( '<figure><img src="a.jpg"><img src="fail.jpg"><figcaption>Two</figcaption></figure>' );
        $this->assertSame( ['<img src="a.jpg">', '<img src="fail.jpg">'], $tags );
        $this->assertSame( ['image-text'], array_column( $result['elements'], 'type' ) );
        $this->assertSame( ['file-1'], $result['fileIds'] );

        // if the first image fails, the caption is kept as text followed by the further images
        $tags = [];
        $result = $html->elements( '<figure><img src="fail.jpg"><img src="b.jpg"><figcaption>Two</figcaption></figure>' );
        $this->assertSame( ['html', 'image'], array_column( $result['elements'], 'type' ) );
        $this->assertSame( '<p>Two</p>', $result['elements'][0]['data']['text'] ?? null );
        $this->assertSame( ['file-2'], $result['fileIds'] );
    }


    public function testSharedHtmlParserKeepsListsAndTablesWithImages(): void
    {
        $tags = [];
        $html = new Html( function( string $tag ) use ( &$tags ) {
            $tags[] = $tag;
            return str_contains( $tag, 'fail' ) ? null : 'file-' . count( $tags );
        } );
        $data = fn( array $result ) => array_map( fn( $element ) => [$element['type'], $element['data']['text'] ?? $element['data']['file']['id'] ?? ''], $result['elements'] );

        // lists like the custom fields of Joomla articles stay intact, their images become image elements after them
        $result = $html->elements( '<p>Intro</p><ul class="fields"><li><strong>Author: </strong>Ann</li><li><strong>Photo: </strong><a href="/big"><img src="images/a.jpg"></a></li>'
            . '<li><strong>Year: </strong>2020</li></ul><p>Outro</p>' );
        $this->assertSame( [
            ['html', '<p>Intro</p>'],
            ['html', '<ul class="fields"><li><strong>Author: </strong>Ann</li><li><strong>Photo: </strong></li><li><strong>Year: </strong>2020</li></ul>'],
            ['image', 'file-1'],
            ['html', '<p>Outro</p>'],
        ], $data( $result ) );
        $this->assertSame( ['file-1'], $result['fileIds'] );
        $this->assertSame( ['<a href="/big"><img src="images/a.jpg"></a>'], $tags );

        // nested lists and tables are matched as a whole, images which can't be imported are dropped
        $tags = [];
        $result = $html->elements( "<table><tr><td><ul><li><img src=\"b.jpg\"></li></ul></td>\n\n<td><img src=\"fail.jpg\"></td></tr></table>" );
        $this->assertSame( [['html', "<table><tr><td><ul><li></li></ul></td>\n\n<td></td></tr></table>"], ['image', 'file-1']], $data( $result ) );
        // lists without images aren't split at blank lines and stay part of the text around them
        $this->assertSame( [['html', "<p>A</p><ul><li>B</li>\n\n<li>C</li></ul>"]], $data( $html->elements( "<p>A</p><ul><li>B</li>\n\n<li>C</li></ul>" ) ) );
    }


    public function testSharedHtmlParserRemovesNestedEmptyTagsAtStartAndEnd(): void
    {
        $html = new Html( fn( string $tag ) => 'file-1' );

        $this->assertNull( $html->text( '<div class="row"><div class="col"></div></div>' ) );
        $this->assertNull( $html->text( '<p><span></span></p><p><strong> </strong></p>' ) );
        // empty inline tags with attributes may be icons, so they are kept
        $this->assertSame( '<p><span class="x"></span></p>', $html->text( '<p><span class="x"></span></p><p><strong> </strong></p>' )['elements'][0]['data']['text'] ?? null );
        $this->assertSame( '<p>Text</p>', $html->text( '<div><p><em></em></p></div><p>Text</p><p><b></b></p>' )['elements'][0]['data']['text'] ?? null );
        $this->assertSame( '<div><p>Text</p></div>', $html->text( '<div><p><span></span>Text</p><p></p></div>' )['elements'][0]['data']['text'] ?? null );
        // empty paragraphs between the content are kept as spacers, links with targets too
        $this->assertSame( '<p>A</p><p></p><p>B</p>', $html->text( '<p>A</p><p></p><p>B</p>' )['elements'][0]['data']['text'] ?? null );
        $this->assertSame( '<p><a href="/x"></a></p>', $html->text( '<p><a href="/x"></a></p>' )['elements'][0]['data']['text'] ?? null );

        // the text before an image in a paragraph of a container doesn't keep the empty paragraph of the image
        $drupal = new Drupal\Html( fn( string $tag ) => 'image-1', fn() => null, fn() => null, fn() => null );
        $result = $drupal->elements( '<div class="text"><p>Intro</p><p><img src="a.jpg"></p></div>' );
        $this->assertSame( ['html', 'image'], array_column( $result['elements'], 'type' ) );
        $this->assertSame( '<div class="text"><p>Intro</p></div>', $result['elements'][0]['data']['text'] );
    }


    public function testSharedHtmlParserUnwrapsFiguresWithoutImagesAndNestedFigures(): void
    {
        $html = new Html( fn( string $tag ) => 'file-1' );
        $data = fn( array $result ) => array_map( fn( $element ) => [$element['type'], $element['data']['text'] ?? $element['data']['file']['id'] ?? ''], $result['elements'] );

        // captions of figures without images are paragraphs after their content
        $this->assertSame( [['html', '<table><tr><td>A</td></tr></table><p>Cap</p>']],
            $data( $html->elements( '<figure class="table"><table><tr><td>A</td></tr></table><figcaption>Cap</figcaption></figure>' ) ) );
        // the figures nested in other figures are kept, the caption of the outer figure becomes a paragraph
        $this->assertSame( [['image-text', 'A'], ['image', 'file-1'], ['html', '<p>Gallery</p>']], $data( $html->elements(
            '<figure class="gallery"><figure><img src="a.jpg"><figcaption>A</figcaption></figure><figure><img src="b.jpg"></figure><figcaption>Gallery</figcaption></figure>' ) ) );

        // embedded media of figures with images are kept with the image and the caption as paragraph after them
        $drupal = new Drupal\Html( fn( string $tag ) => 'image-1', fn() => null, fn() => null, fn( string $type, string $src ) => 'video-1' );
        $this->assertSame( [['image', 'image-1'], ['video', 'video-1'], ['html', '<p>Cap</p>']],
            $data( $drupal->elements( '<figure><img src="a.jpg"><video src="/a.mp4"></video><figcaption>Cap</figcaption></figure>' ) ) );
        // the captions of nested media figures are paragraphs after their media
        $this->assertSame( [['video', 'video-1'], ['html', '<p>In</p>'], ['html', '<p>Out</p>']],
            $data( $drupal->elements( '<figure class="outer"><figure><video src="/a.mp4"></video><figcaption>In</figcaption></figure><figcaption>Out</figcaption></figure>' ) ) );
    }


    public function testDrupalHtmlParserUsesHandlers(): void
    {
        $calls = [];
        $html = new Drupal\Html(
            fn( string $tag ) => 'image-1',
            function( string $tag ) use ( &$calls ) {
                $calls[] = ['embed', $tag];
                return ['elements' => [Elements::create( 'file', ['file' => Elements::file( 'file-1' )] )], 'fileIds' => ['file-1']];
            },
            function( string $url, string $text ) use ( &$calls ) {
                $calls[] = ['link', $url, $text];
                return $url === 'https://example.com/doc.pdf' ? Elements::imageContent( 'file-2' ) : null;
            },
            function( string $type, string $src ) use ( &$calls ) {
                $calls[] = ['media', $type, $src];
                return 'file-3';
            }
        );

        $result = $html->elements( '<drupal-media data-entity-uuid="u1"></drupal-media><p><a href="https://example.com/doc.pdf">Doc &amp; more</a></p>'
            . "\n\n" . '<p><a href="https://example.com/page">Page</a></p><video controls><source src="/movie.mp4"></video>'
            . '<audio src="data:audio/mp3"></audio><iframe src="javascript:alert(1)"></iframe>' );

        $this->assertSame( ['file', 'image', 'html', 'video'], array_column( $result['elements'], 'type' ) );
        $this->assertSame( ['file-1', 'file-2', 'file-3'], $result['fileIds'] );
        $this->assertSame( [
            ['embed', '<drupal-media data-entity-uuid="u1">'],
            ['link', 'https://example.com/doc.pdf', 'Doc & more'],
            ['link', 'https://example.com/page', 'Page'],
            ['media', 'video', '/movie.mp4'],
        ], $calls );
        $this->assertSame( ['audio' => true, 'iframe' => true], $html->removed() );
        $this->assertSame( [], $html->removed() );

        $result = $html->elements( '<p><img src="a.jpg" data-align="right" data-caption="&lt;em&gt;Cap&lt;/em&gt;"></p><img src="b.jpg" class="align-left"><p>Next</p>' );

        $this->assertSame( ['image-text', 'image-text'], array_column( $result['elements'], 'type' ) );
        $this->assertSame( ['text' => '*Cap*', 'file' => Elements::file( 'image-1' ), 'position' => 'end'], $result['elements'][0]['data'] );
        $this->assertSame( ['text' => 'Next', 'file' => Elements::file( 'image-1' ), 'position' => 'start'], $result['elements'][1]['data'] );

        // the caption of images which can't be imported is kept as text
        $failed = new Drupal\Html( fn( string $tag ) => null, fn() => null, fn() => null, fn() => null );
        $result = $failed->elements( '<img src="a.jpg" alt="A" data-caption="&lt;em&gt;Cap&lt;/em&gt;">' );

        $this->assertSame( ['html'], array_column( $result['elements'], 'type' ) );
        $this->assertSame( '<em>Cap</em>', $result['elements'][0]['data']['text'] );
        $this->assertSame( [], $result['fileIds'] );

        $this->assertSame( 'https://www.youtube.com/watch?v=abc-1', Drupal\Html::streamUrl( 'youtube://v/abc-1' ) );
        $this->assertSame( 'https://vimeo.com/42', Drupal\Html::streamUrl( 'vimeo://v/42' ) );
        $this->assertSame( 'https://example.com/v', Drupal\Html::streamUrl( 'oembed://https%3A//example.com/v' ) );
        $this->assertSame( '', Drupal\Html::streamUrl( 'public://video.mp4' ) );
        $this->assertSame( 'a & b', Drupal\Html::decodedAttribute( '<img alt="a &amp; b">', 'alt' ) );
        $this->assertSame( '', Drupal\Html::decodedAttribute( '<img data-alt="x">', 'alt' ) );
        $this->assertSame( 'a &amp; b', Drupal\Html::attribute( '<img alt="a &amp; b">', 'alt' ) );

        // the iframe source is decoded only once, "&amp;amp;" is the encoded "&amp;" of the URL
        $iframe = $html->elements( '<iframe src="https://example.com/map?a=1&amp;amp;b=2" title="A &amp;amp; B"></iframe>' );
        $this->assertSame( '<iframe src="https://example.com/map?a=1&amp;amp;b=2" title="A &amp;amp; B" loading="lazy" allow="fullscreen; picture-in-picture" allowfullscreen></iframe>',
            $iframe['elements'][0]['data']['text'] ?? null );
    }


    public function testWordPressConverterMapsBlocks(): void
    {
        $files = [];
        $wp = new WordPress(
            fn( string $tag ) => preg_match( '/src="([^"]+)"/', $tag, $m ) && $m[1] !== 'none.png' ? 'img:' . $m[1] : null,
            function( string $url ) use ( &$files ) {
                $files[] = $url;
                return 'file:' . $url;
            }
        );
        $data = fn( array $result ) => array_map( fn( $el ) => [$el['type'], $el['data']], $result['elements'] );

        $result = $wp->elements( '<!-- wp:heading --><h2>Title <em>x</em></h2><!-- /wp:heading -->'
            . '<!-- wp:paragraph --><p>Para <strong>bold</strong></p><!-- /wp:paragraph --><!-- wp:paragraph --><p>Second</p><!-- /wp:paragraph -->'
            . '<!-- wp:code --><pre lang="PHP"><code>&lt;?php</code></pre><!-- /wp:code --><!-- wp:separator --><hr/><!-- /wp:separator -->'
            . '<!-- wp:table --><table><caption>Cap</caption><thead><tr><th>A</th></tr></thead><tbody><tr><td><i>1</i></td></tr></tbody></table><!-- /wp:table -->'
            . '<!-- wp:gallery --><figure><img src="a.png"><img src="b.png"></figure><!-- /wp:gallery -->'
            . '<!-- wp:image {"align":"left"} --><figure><img src="c.png"></figure><!-- /wp:image -->'
            . '<!-- wp:paragraph --><p>Next</p><!-- /wp:paragraph --><!-- wp:list --><ul><li>one</li></ul><!-- /wp:list -->'
            . '<!-- wp:video --><video src="v.mp4"></video><!-- /wp:video --><!-- wp:audio --><audio src="a.mp3"></audio><!-- /wp:audio -->'
            . '<!-- wp:html --><div class="note">Note <b>text</b></div><!-- /wp:html --><!-- wp:html --><div>html</div><!-- /wp:html --><!--more-->' );

        $this->assertSame( [
            ['heading', ['level' => 2, 'title' => 'Title x']],
            ['text', ['text' => "Para **bold**\n\nSecond"]],
            ['code', ['language' => 'php', 'text' => '<?php']],
            ['table', ['title' => 'Cap', 'header' => 'row', 'table' => [['A'], ['1']]]],
            ['slideshow', ['title' => '', 'files' => [Elements::file( 'img:a.png' ), Elements::file( 'img:b.png' )]]],
            ['image-text', ['text' => "Next\n\n- one", 'file' => Elements::file( 'img:c.png' ), 'position' => 'start']],
            ['video', ['file' => Elements::file( 'file:v.mp4' )]],
            ['audio', ['file' => Elements::file( 'file:a.mp3' )]],
            ['text', ['text' => '**Note:** Note text']],
            ['html', ['text' => '<div>html</div>']],
        ], $data( $result ) );
        $this->assertSame( ['img:a.png', 'img:b.png', 'img:c.png', 'file:v.mp4', 'file:a.mp3'], $result['fileIds'] );
        $this->assertSame( ['v.mp4', 'a.mp3'], $files );
        $this->assertSame( ['main'], array_values( array_unique( array_column( $result['elements'], 'group' ) ) ) );

        $result = $wp->elements( '<!-- wp:columns --><div><!-- wp:column --><div><p>Left</p></div><!-- /wp:column -->'
            . '<!-- wp:column --><div><!-- wp:image --><figure><img src="d.png"></figure><!-- /wp:image --></div><!-- /wp:column --></div><!-- /wp:columns -->'
            . '<!-- wp:columns --><div><!-- wp:column --><div><!-- wp:heading --><h4>Col</h4><!-- /wp:heading --></div><!-- /wp:column -->'
            . '<!-- wp:column --><div><!-- wp:paragraph --><p>Para</p><!-- /wp:paragraph --></div><!-- /wp:column --></div><!-- /wp:columns -->'
            . '<!-- wp:image {"align":"right"} --><figure><img src="none.png"></figure><!-- /wp:image --><!-- wp:paragraph --><p>Orphan</p><!-- /wp:paragraph -->' );

        $this->assertSame( [
            ['image-text', ['text' => 'Left', 'file' => Elements::file( 'img:d.png' ), 'position' => 'end']],
            ['heading', ['level' => 4, 'title' => 'Col']],
            ['text', ['text' => "Para\n\nOrphan"]],
        ], $data( $result ) );

        $result = $wp->elements( "<h1>Classic</h1>\r\n<p>First</p>\r\n\r\n<p>Second</p>\n<pre lang=\"Bash\">ls</pre>\n<div class=\"tip\">Tip</div>\n"
            . "<img src=\"e.png\" />\n<p>Inline <a href=\"/\"><img src=\"f.png\"></a> text</p>\n\n<table><tr><td>t</td></tr></table>" );

        $this->assertSame( [
            ['heading', ['level' => 1, 'title' => 'Classic']],
            ['text', ['text' => "First\n\nSecond"]],
            ['code', ['language' => 'bash', 'text' => 'ls']],
            ['text', ['text' => '**Tip:** Tip']],
            ['image', ['file' => Elements::file( 'img:e.png' )]],
            ['text', ['text' => 'Inline']],
            ['image', ['file' => Elements::file( 'img:f.png' )]],
            ['text', ['text' => 'text']],
            ['html', ['text' => '<table><tr><td>t</td></tr></table>']],
        ], $data( $result ) );

        $this->assertNull( $wp->text( ' ' ) );

        $this->assertSame( 'Intro & more', WpArticle::intro( "<p>Intro &amp;\n <b>more</b></p><!--more--><p>Rest</p>" ) );
        $this->assertSame( str_repeat( 'a', 297 ) . '...', WpArticle::intro( str_repeat( 'a', 301 ) ) );
        $this->assertSame( str_repeat( 'a', 300 ), WpArticle::intro( str_repeat( 'a', 300 ) ) );

        $article = WpArticle::element( 'Intro', 'cover', 'preview' );
        $this->assertSame( ['type' => 'article', 'group' => 'main', 'files' => ['cover'], 'data' => ['text' => 'Intro', 'file' => ['id' => 'cover', 'type' => 'file']]],
            array_diff_key( $article, ['id' => 1] ) );
        $this->assertNotEmpty( $article['id'] );
        $this->assertSame( ['preview'], WpArticle::element( 'Intro', null, 'preview' )['files'] );
        $this->assertSame( ['text' => 'Intro'], WpArticle::element( 'Intro', null, 'preview' )['data'] );
        $this->assertSame( [], WpArticle::element( 'Intro', null )['files'] );
        $this->assertSame( ['elements' => [], 'fileIds' => []], $wp->elements( '' ) );
    }


    public function testSharedElementOnlyUpdatesChangedOwnElements(): void
    {
        $used = Files::reference( 'image/png', 'Used', 'https://old.example/used.png', 'importer' )->id ?? '';
        $unused = Files::reference( 'image/png', 'Unused', 'https://old.example/unused.png', 'importer' )->id ?? '';
        $element = fn( string $text ) => Elements::create( 'image-text', ['text' => $text, 'file' => Elements::file( $used )] );

        $id = SharedElements::save( 'Block', 'en', $element( 'A' ), [$used, $unused], 'importer' );
        $shared = Element::findOrFail( $id );

        $this->assertSame( ['Block', 'en', 'image-text', 'importer'], [$shared->name, $shared->lang, $shared->type, $shared->editor] );
        $this->assertSame( 1, $shared->versions()->count() );
        $this->assertSame( [$used], $shared->versions()->firstOrFail()->files()->pluck( 'id' )->all() );

        $this->assertSame( $id, SharedElements::save( 'Block', 'en', $element( 'A' ), [$used], 'importer' ) );
        $this->assertSame( 1, $shared->versions()->count() );

        $shared->delete();
        $this->assertSame( $id, SharedElements::save( 'Block', 'en', $element( 'B' ), [$used], 'importer' ) );
        $this->assertFalse( Element::findOrFail( $id )->trashed() );
        $this->assertSame( 'B', Element::findOrFail( $id )->data->text ?? null );
        $this->assertSame( 2, $shared->versions()->count() );

        $this->assertSame( $id, SharedElements::save( 'Block', 'en', ['type' => 'text'] + $element( 'B' ), [$used], 'importer' ) );
        $this->assertSame( 'text', Element::findOrFail( $id )->type );
        $this->assertSame( 3, $shared->versions()->count() );

        Element::whereKey( $id )->update( ['editor' => 'someone'] );
        $this->assertSame( $id, SharedElements::save( 'Block', 'en', $element( 'C' ), [$used], 'importer' ) );
        $this->assertSame( 'B', Element::findOrFail( $id )->data->text ?? null );
        $this->assertSame( 3, $shared->versions()->count() );

        $this->assertNotSame( $id, SharedElements::save( 'Block', 'de', $element( 'A' ), [], 'importer' ) );
    }


    public function testTransactionForgetsFilesOfFailedImports(): void
    {
        $command = new class extends Commands {
            protected $signature = 'test:base-import';

            /** @return array{0: mixed, 1: string} */
            public function check(): array
            {
                $this->editor = 'importer';
                $this->createdFiles = Collection::make();
                $failed = $this->transaction( 'failed', function() {
                    $this->createFile( 'image/png', 'Image', 'https://old.example/image.png' );
                    throw new \RuntimeException( 'failed' );
                } );

                return [$failed, $this->createFile( 'image/png', 'Image', 'https://old.example/image.png' )];
            }
        };
        $command->setOutput( new OutputStyle( new ArrayInput( [] ), $output = new BufferedOutput() ) );

        [$failed, $id] = $command->check();

        $this->assertNull( $failed );
        $this->assertNotNull( File::find( $id ) );
        $this->assertStringContainsString( 'Failed to import failed: failed', $output->fetch() );
    }


    public function testTransactionKeepsEntriesOfEarlierImports(): void
    {
        $command = new class extends Commands {
            protected $signature = 'test:base-import';

            /** @return array{0: array<string, string>, 1: array<string, bool>} */
            public function check(): array
            {
                $this->createdFiles = Collection::make( ['a.png' => '1'] );
                $this->usedPages = ['p1' => true];

                $this->transaction( 'failed', function() {
                    $this->createdFiles->put( 'b.png', '2' );
                    $this->usedPages['p1'] = true;
                    $this->usedPages['p2'] = true;
                    throw new \RuntimeException( 'failed' );
                } );

                $this->transaction( 'ok', function() {
                    $this->createdFiles->put( 'c.png', '3' );
                    $this->usedPages['p3'] = true;
                } );

                return [$this->createdFiles->all(), $this->usedPages];
            }
        };
        $command->setOutput( new OutputStyle( new ArrayInput( [] ), new BufferedOutput() ) );

        [$files, $used] = $command->check();

        $this->assertSame( ['a.png' => '1', 'c.png' => '3'], $files );
        $this->assertSame( ['p1' => true, 'p3' => true], $used );
    }


    public function testPrefetchedPagesAreUsedOnce(): void
    {
        $command = new class extends Commands {
            protected const PREFETCH_SIZE = 2;
            protected $signature = 'test:base-import';

            /** @return array{0: list<string>, 1: string|null, 2: string|null, 3: string|null, 4: string|null, 5: list<string>, 6: string|null, 7: string|null} */
            public function check(): array
            {
                $this->domain = 'example.com';
                $about = Pages::create( ['name' => 'About', 'path' => 'about', 'domain' => 'example.com'], [] );
                $team = Pages::create( ['name' => 'Team', 'path' => '/team', 'domain' => 'example.com'], [] );

                $this->prefetchPages( ['/about', 'team', 'new', 'about'] );
                $prefetched = array_keys( $this->prefetchedPages );

                $results = [$this->pageByPath( 'about' )?->id, $this->pageByPath( '/team/' )?->id, $this->pageByPath( 'new' )?->id];
                // pages created after prefetching are found because each prefetched page is used once
                $created = Pages::create( ['name' => 'New', 'path' => 'new', 'domain' => 'example.com'], [] );
                $results[] = $this->pageByPath( 'new' )?->id === $created->id ? 'created' : null;

                // only the last pages are kept if there are more than allowed
                $this->prefetchPages( ['a', 'b'] );
                $this->prefetchPages( ['c'] );

                return [$prefetched, ...$results, array_keys( $this->prefetchedPages ), $about->id, $team->id];
            }
        };
        $command->setOutput( new OutputStyle( new ArrayInput( [] ), new BufferedOutput() ) );

        [$prefetched, $about, $team, $new, $created, $kept, $aboutId, $teamId] = $command->check();

        $this->assertSame( ['about', 'team', 'new'], $prefetched );
        $this->assertSame( $aboutId, $about );
        $this->assertSame( $teamId, $team );
        $this->assertNull( $new );
        $this->assertSame( 'created', $created );
        $this->assertSame( ['c'], $kept );
    }


    public function testImportedKeepsPagesWithoutContent(): void
    {
        $command = new class extends Commands {
            protected $signature = 'test:base-import';

            /** @return array{0: PageView, 1: PageView|null, 2: PageView|null, 3: string|null, 4: string|null, 5: string|null} */
            public function check( PageView $page ): array
            {
                $this->createdPages = Collection::make();
                $this->tally = new \Aimeos\Cms\Import\Tally();

                $returned = $this->imported( 1, $page, 'About', 'about' );

                return [$returned, $this->createdPage( 1 ), $this->createdPage( 2 ), $this->createdPages->get( 1 ), $this->createdPath( 1 ), $this->createdPath( 2 )];
            }
        };
        $command->setOutput( new OutputStyle( new ArrayInput( [] ), new BufferedOutput() ) );

        $data = ['name' => 'About', 'title' => 'About', 'path' => 'about', 'lang' => 'en', 'domain' => '', 'status' => 1, 'editor' => 'importer'];
        $content = [['id' => 'content-1', 'type' => 'text', 'group' => 'main', 'data' => ['type' => 'text', 'text' => 'Body']]];
        $page = Pages::create( $data, $content );
        $page->setRelation( 'files', Collection::make() );
        $page->load( 'page' );

        [$returned, $stored, $missing, $kept, $path, $noPath] = $command->check( $page );

        $this->assertSame( $page, $returned );
        $this->assertSame( 'content-1', $returned->content[0]->id ?? null );
        $this->assertInstanceOf( PageView::class, $stored );
        $this->assertNotSame( $page, $stored );
        $this->assertSame( [], $stored->getRelations() );
        $this->assertEqualsCanonicalizing( ['id', 'variant_id', 'parent_id', 'tenant_id', 'lang', 'domain', 'path'], array_keys( $stored->getAttributes() ) );
        $this->assertSame( $page->id, $stored->id );
        $this->assertSame( 'about', $stored->path );
        $this->assertSame( 'en', $stored->lang );
        $this->assertSame( '', $stored->domain );
        $this->assertNull( $stored->parent_id );
        $this->assertTrue( $stored->exists );
        $this->assertSame( 'about', $path );
        $this->assertNull( $noPath );
        $this->assertNull( $missing );
        // only the IDs, language and URL are kept packed into a string
        $this->assertSame( implode( "\x1F", [$page->id, $page->variant_id, "\0", $page->tenant_id, 'en', '', 'about'] ), $kept );

        // the tree node is loaded again if the kept page is used as parent
        $child = Pages::create( ['path' => 'about/team'] + $data, [], $stored );

        $this->assertSame( $page->id, $child->parent_id );
    }


    public function testBatchesAreCommittedAfterSomeTime(): void
    {
        $run = function( int $seconds ): array {
            $command = new class( $seconds ) extends Commands {
                protected $signature = 'test:base-import';

                public function __construct( int $seconds )
                {
                    parent::__construct();
                    $this->batchSeconds = $seconds;
                }

                public function handle(): void
                {
                    $this->editor = 'importer';
                    $this->connection = 'test';
                    $this->createdFiles = Collection::make();

                    foreach( range( 1, 3 ) as $num ) {
                        $this->transaction( "file {$num}", fn() => $this->createFile( 'image/png', "File {$num}", "https://time.example/{$num}.png" ) );
                    }
                }
            };
            $command->setLaravel( app() );

            $db = DB::connection( config( 'cms.db', 'sqlite' ) );
            $level = $db->transactionLevel();
            $saved = [];
            Event::listen( TransactionCommitted::class, function( $event ) use ( $level, &$saved ) {
                if( $event->connection->transactionLevel() === $level ) {
                    $saved[] = File::count();
                }
            } );

            $this->assertSame( 0, $command->run( new ArrayInput( [] ), new BufferedOutput() ) );
            Event::forget( TransactionCommitted::class );
            File::query()->forceDelete();

            return $saved;
        };

        // incomplete batches are committed when they take too long and at the end
        $this->assertSame( [1, 2, 3, 3], $run( 0 ) );
        $this->assertSame( [3], $run( 10 ) );
    }


    public function testPrefetchedFilesAreUsedOnce(): void
    {
        $command = new class extends Commands {
            protected $signature = 'test:base-import';
            /** @var array<int, string> */
            public array $ids = [];
            /** @var array<int, string|null> */
            public array $once = [];

            public function handle(): void
            {
                $this->editor = 'importer';
                $this->connection = 'test';
                $this->createdFiles = Collection::make();

                // the files are looked up by their path
                $this->createdFiles->put( 'https://pre.example/created.png', 'created' );
                $this->prefetchFiles( ['https://pre.example/a.png', 'https://pre.example/b.png', 'https://pre.example/c.png', 'https://map.example/b.png', 'https://pre.example/created.png', ''] );
                $this->prefetchFiles( ['https://pre.example/a.png'] );

                foreach( ['a', 'b', 'a', 'created'] as $name ) {
                    $this->ids[] = $this->createFile( 'image/png', "New {$name}", "https://pre.example/{$name}.png" );
                }

                // the first file of the path is prefetched and it's only used once
                foreach( [1, 2] as $num ) {
                    $this->once[] = $this->prefetchedFile( 'https://pre.example/c.png', fn() => null )?->id;
                }
            }
        };
        $command->setLaravel( app() );

        $file = Files::reference( 'image/png', 'Old a', 'https://pre.example/a.png', 'importer' );
        $first = Files::reference( 'image/png', 'First c', 'https://pre.example/c.png', 'importer' );
        Files::reference( 'image/png', 'Second c', 'https://pre.example/c.png', 'importer' );
        Files::reference( 'image/png', 'Other b', 'https://map.example/b.png', 'importer' );

        $db = DB::connection( config( 'cms.db', 'sqlite' ) );
        $db->enableQueryLog();
        $this->assertSame( 0, $command->run( new ArrayInput( [] ), new BufferedOutput() ) );
        $queries = Collection::make( $db->getQueryLog() );
        $db->disableQueryLog();

        $byPath = $queries->filter( fn( $query ) => str_starts_with( $query['query'], 'select * from "cms_files" where "path"' ) );

        // the files are fetched at once, the created ones aren't fetched again
        $this->assertSame( [
            ['https://pre.example/a.png', 'https://pre.example/b.png', 'https://pre.example/c.png', 'https://map.example/b.png'],
        ], $byPath->map( fn( $query ) => array_slice( $query['bindings'], 0, -1 ) )->values()->all() );

        $this->assertSame( (string) $file->id, $command->ids[0] );
        $this->assertNotContains( $command->ids[1], [(string) $file->id, ''] );
        $this->assertSame( [(string) $file->id, 'created'], array_slice( $command->ids, 2 ) );
        $this->assertSame( 'New a', $file->fresh()?->name );
        $this->assertSame( [(string) $first->id, null], $command->once );
    }


    public function testPrefetchedFilesOfUpcomingRecordsAreKept(): void
    {
        $command = new class extends Commands {
            protected const PREFETCH_SIZE = 4;
            protected $signature = 'test:base-import';
            /** @var array<int, array<int, string>> */
            public array $keys = [];
            public ?string $file = null;

            public function handle(): void
            {
                $this->createdFiles = Collection::make();

                // the files of the upcoming records are kept while the files of single records are prefetched
                $this->prefetchFiles( ['a', 'b', 'c'] );
                $this->prefetchFiles( ['c', 'c', 'd'], false );
                $this->prefetchFiles( ['e'], false );
                $this->keys[] = array_keys( $this->prefetchedFiles );
                $this->file = $this->prefetchedFile( 'c', fn() => null )?->id;

                // files prefetched again are kept as the newest ones
                $this->prefetchFiles( ['b', 'f', 'g'] );
                $this->keys[] = array_keys( $this->prefetchedFiles );
            }
        };
        $command->setLaravel( app() );
        $file = Files::reference( 'image/png', 'C', 'c', 'importer' );

        $db = DB::connection( config( 'cms.db', 'sqlite' ) );
        $db->enableQueryLog();
        $this->assertSame( 0, $command->run( new ArrayInput( [] ), new BufferedOutput() ) );
        $queries = Collection::make( $db->getQueryLog() )->filter( fn( $query ) => str_starts_with( $query['query'], 'select * from "cms_files" where "path"' ) );
        $db->disableQueryLog();

        $this->assertSame( [['a', 'b', 'c', 'd', 'e'], ['b', 'f', 'g']], $command->keys );

        // files prefetched before aren't looked up again, even if their path is passed twice
        $this->assertSame( [['a', 'b', 'c'], ['d'], ['e'], ['f', 'g']], $queries->map( fn( $query ) => array_slice( $query['bindings'], 0, -1 ) )->values()->all() );
        $this->assertSame( (string) $file->id, $command->file );
    }


    public function testSaveVariantReusesLoadedVariant(): void
    {
        $data = ['name' => 'About', 'title' => 'About', 'path' => 'about', 'lang' => 'en', 'domain' => '', 'status' => 1, 'editor' => 'importer'];
        $content = [['id' => 'content-1', 'type' => 'text', 'group' => 'main', 'data' => ['type' => 'text', 'text' => 'Body']]];
        $page = Pages::create( $data, $content );
        Pages::publish( $page, $data, ['content' => $content], [], [], 'en', 'importer' );

        $language = ['lang' => 'de', 'domain' => ''];
        $translation = ['name' => 'Über', 'title' => 'Über', 'path' => 'ueber', 'lang' => 'de', 'domain' => '', 'status' => 1, 'editor' => 'importer'];
        $count = 0;
        DB::listen( function() use ( &$count ) { $count++; } );
        $save = function( mixed $variant ) use ( $page, $language, $translation, &$count ) {
            $count = 0;
            $args = [$page, $language, 'ueber', $translation, ['elements' => [['type' => 'text', 'group' => 'main', 'data' => ['type' => 'text', 'text' => 'Text']]]], 'importer'];
            $result = Variants::save( ...( $variant !== false ? [...$args, $variant] : $args ) );

            return [$result, $count];
        };

        [$created] = $save( false );
        [$loaded, $lookup] = $save( false );
        $variant = PageView::withTrashed()->language( 'de', true )->find( $page->id );
        [$reused, $count] = $save( $variant );

        $this->assertSame( $created?->variant_id, $loaded?->variant_id );
        $this->assertSame( $created?->variant_id, $reused?->variant_id );
        // the variant isn't loaded again
        $this->assertSame( $lookup - 1, $count );
    }


    public function testSaveVariantChecksOnlyChangedUrls(): void
    {
        $data = ['name' => 'About', 'title' => 'About', 'path' => 'about', 'lang' => 'en', 'domain' => '', 'status' => 1, 'editor' => 'importer'];
        $content = [['id' => 'content-1', 'type' => 'text', 'group' => 'main', 'data' => ['type' => 'text', 'text' => 'Body']]];
        $page = Pages::create( $data, $content );
        Pages::publish( $page, $data, ['content' => $content], [], [], 'en', 'importer' );
        $other = Pages::create( ['name' => 'Team', 'title' => 'Team', 'path' => 'team'] + $data, $content );

        $elements = ['elements' => [['type' => 'text', 'group' => 'main', 'data' => ['type' => 'text', 'text' => 'Text']]]];
        $translation = ['name' => 'Über', 'title' => 'Über'] + $data;
        $checks = 0;
        DB::listen( function( $query ) use ( &$checks ) {
            $checks += (int) str_contains( $query->sql, 'from "cms_page_variants" where "domain" = ? and "path" = ?' );
        } );

        $this->assertInstanceOf( PageView::class, Variants::save( $other, ['lang' => 'de', 'domain' => ''], 'team-de', $translation, $elements, 'importer' ) );
        $this->assertInstanceOf( PageView::class, Variants::save( $page, ['lang' => 'de', 'domain' => ''], 'ueber', $translation, $elements, 'importer' ) );
        $this->assertSame( 2, $checks );

        // the URL of an existing translation isn't checked again if it's unchanged
        $this->assertInstanceOf( PageView::class, Variants::save( $page, ['lang' => 'de', 'domain' => ''], 'ueber', $translation, $elements, 'importer' ) );
        $this->assertSame( 2, $checks );

        // changed URLs used by other pages are still skipped
        $this->assertNull( Variants::save( $page, ['lang' => 'de', 'domain' => ''], 'team-de', $translation, $elements, 'importer' ) );
        $this->assertSame( 3, $checks );
        $this->assertSame( 'ueber', PageView::language( 'de' )->findOrFail( $page->id )->path );
    }


    public function testSaveVariantLoadsSourceOfTranslation(): void
    {
        $data = ['name' => 'About', 'title' => 'About', 'path' => 'about', 'lang' => 'en', 'domain' => '', 'status' => 1, 'editor' => 'importer'];
        $content = [['id' => 'content-1', 'type' => 'text', 'group' => 'main', 'data' => ['type' => 'text', 'text' => 'Body']]];
        $page = Pages::create( $data, $content );
        Pages::publish( $page, $data, ['content' => $content], [], [], 'en', 'importer' );

        $elements = ['elements' => [['type' => 'text', 'group' => 'main', 'data' => ['type' => 'text', 'text' => 'Text']]]];
        $de = Variants::save( $page, ['lang' => 'de', 'domain' => ''], 'ueber', ['name' => 'Über', 'title' => 'Über'] + $data, $elements, 'importer' );

        $this->assertInstanceOf( PageView::class, $de );

        // the translation is passed instead of the source page
        $fr = Variants::save( $de, ['lang' => 'fr', 'domain' => ''], 'a-propos', ['name' => 'À propos', 'title' => 'À propos'] + $data, $elements, 'importer' );

        $this->assertInstanceOf( PageView::class, $fr );
        $this->assertSame( 'content-1', $fr->content[0]->id ?? null );
        $this->assertEquals( Hashes::published( PageView::findOrFail( $page->id ) ), $fr->hashes );
    }

    public function testSaveVariantLoadsSourceOncePerPage(): void
    {
        $data = ['name' => 'About', 'title' => 'About', 'path' => 'about', 'lang' => 'en', 'domain' => '', 'status' => 1, 'editor' => 'importer'];
        $content = [['id' => 'content-1', 'type' => 'text', 'group' => 'main', 'data' => ['type' => 'text', 'text' => 'Body']]];
        $page = Pages::create( $data, $content );
        Pages::publish( $page, $data, ['content' => $content], [], [], 'en', 'importer' );

        $elements = ['elements' => [['type' => 'text', 'group' => 'main', 'data' => ['type' => 'text', 'text' => 'Text']]], 'fileIds' => []];
        $de = Variants::save( $page, ['lang' => 'de', 'domain' => ''], 'ueber', ['name' => 'Über', 'title' => 'Über'] + $data, $elements, 'importer' );
        $this->assertInstanceOf( PageView::class, $de );

        // prepared source variants are used as they are
        $prepared = ['hashes' => ['content-1' => 'prepared']] + Variants::source( $de );
        $es = Variants::save( $de, ['lang' => 'es', 'domain' => ''], 'sobre', ['name' => 'Sobre', 'title' => 'Sobre'] + $data, $elements, 'importer', null, $prepared );
        $this->assertEquals( ['content-1' => 'prepared'], $es?->hashes );

        $command = new class extends Commands {
            protected $signature = 'test:base-import';

            /** @param array{elements: array<int, array<string, mixed>>, fileIds: array<int, string>} $elements */
            public function check( PageView $page, array $elements ): int
            {
                $this->editor = 'importer';
                $count = 0;
                DB::listen( function( $query ) use ( &$count ) {
                    $count += (int) preg_match( '/"cms_page_view"."id" = \? and .* limit 1$/', $query->sql );
                } );

                foreach( ['fr' => 'a-propos', 'it' => 'chi-siamo'] as $lang => $slug ) {
                    $this->saveVariant( $page, ['lang' => $lang, 'domain' => ''], $slug, ['name' => $slug, 'title' => $slug, 'status' => 1], $elements, null );
                }

                // a new version of the page needs its source again
                $page->latest_id = 'new';
                $this->saveVariant( $page, ['lang' => 'nl', 'domain' => ''], 'over', ['name' => 'Over', 'title' => 'Over', 'status' => 1], $elements, null );

                return $count;
            }
        };
        $command->setOutput( new OutputStyle( new ArrayInput( [] ), new BufferedOutput() ) );

        // the source of the translation is loaded once for all languages of the same version
        $this->assertSame( 2, $command->check( $de, $elements ) );
        $this->assertSame( ['de', 'en', 'es', 'fr', 'it', 'nl'], PageVariant::where( 'page_id', $page->id )->orderBy( 'lang' )->pluck( 'lang' )->all() );
    }


    public function testLinkPagesAreOnlySavedOverOwnLinkPages(): void
    {
        $command = new class extends Commands {
            protected $signature = 'test:base-import';

            public function start(): PageView
            {
                $this->editor = 'importer';
                $this->lang = 'en';
                $this->domain = '';
                $this->usedPages = [];

                return $this->getOrCreateRoot();
            }

            /**
             * @param array<string, mixed> $data Page data overwriting the default ones
             * @return array{page: PageView, status: Status}|null
             */
            public function link( string $slug, PageView $parent, array $data = [], bool $replace = false ): ?array
            {
                $pageData = $data + ['to' => '/target'] + $this->pageData( $slug, $slug, 2 );

                if( !$replace ) {
                    return $this->saveLink( $slug, $pageData, $parent );
                }

                // own link pages are replaced even if saved by this import like the Drupal redirects do
                $page = $this->pageByPath( $slug );
                return !$page || $this->linkOwned( $page, $pageData ) ? $this->storeLink( $pageData, $parent, $page ) : null;
            }
        };
        $command->setOutput( new OutputStyle( new ArrayInput( [] ), new BufferedOutput() ) );

        $root = $command->start();
        $new = $command->link( 'old', $root );
        $this->assertSame( Status::Imported, $new['status'] ?? null );
        // link pages saved by this import aren't overwritten unless replacing them is allowed
        $this->assertNull( $command->link( 'old', $root, ['to' => '/other'] ) );
        $this->assertSame( Status::Updated, $command->link( 'old', $root, ['to' => '/other'], true )['status'] ?? null );
        // pages which aren't link pages or are saved in another language or by another editor are kept
        $this->assertNull( $command->link( '', $root ) );
        $this->assertSame( Status::Imported, $command->link( 'fremd', $root, ['lang' => 'de'] )['status'] ?? null );
        $command->start();
        $this->assertNull( $command->link( 'fremd', $root ) );
        $this->assertSame( Status::Unchanged, $command->link( 'fremd', $root, ['lang' => 'de'] )['status'] ?? null );
        $command->link( 'extern', $root );
        PageVariant::where( 'path', 'extern' )->update( ['editor' => 'someone'] );
        $command->start();
        $this->assertNull( $command->link( 'extern', $root ) );

        // link pages of previous imports are restored and moved below the new parent once per import
        $folder = $command->link( 'folder', $root )['page'] ?? $root;
        Page::findOrFail( $new['page']->id ?? '' )->delete();
        $updated = $command->link( 'old', $folder, ['to' => '/new'] );
        $this->assertSame( Status::Updated, $updated['status'] ?? null );
        $this->assertSame( $new['page']->id ?? '', $updated['page']->id ?? null );
        $page = PageView::findOrFail( $new['page']->id ?? '' );
        $this->assertSame( ['/new', $folder->id], [$page->to, $page->parent_id] );
        $this->assertNull( $command->link( 'old', $folder ) );
    }


    public function testImportsAreCommittedInBatches(): void
    {
        $command = new class extends Commands {
            protected $signature = 'test:base-import {--fail}';
            protected int $batchSize = 2;

            public function handle(): void
            {
                $this->createdFiles = Collection::make();

                foreach( range( 1, 5 ) as $num )
                {
                    $this->transaction( "record {$num}", function() use ( $num ) {
                        Element::forceCreate( ['lang' => 'en', 'type' => 'text', 'name' => "Batch {$num}", 'data' => ['type' => 'text', 'text' => 'Text'], 'editor' => 'importer'] );

                        if( $num === 3 ) {
                            throw new \RuntimeException( 'failed' );
                        }
                    } );
                }

                if( $this->option( 'fail' ) ) {
                    throw new \RuntimeException( 'aborted' );
                }
            }
        };
        $command->setLaravel( app() );

        $db = DB::connection( config( 'cms.db', 'sqlite' ) );
        $level = $db->transactionLevel();
        $commits = 0;
        Event::listen( TransactionCommitted::class, function( $event ) use ( $level, &$commits ) {
            $commits += (int) ( $event->connection->transactionLevel() === $level );
        } );

        $this->assertSame( 0, $command->run( new ArrayInput( [] ), new BufferedOutput() ) );
        // after the 2nd and 4th record and at the end, the failed record is rolled back only
        $this->assertSame( 3, $commits );
        $this->assertSame( $level, $db->transactionLevel() );
        $this->assertSame( ['Batch 1', 'Batch 2', 'Batch 4', 'Batch 5'], Element::where( 'name', 'like', 'Batch %' )->orderBy( 'name' )->pluck( 'name' )->all() );

        // records imported before an error are kept
        Element::where( 'name', 'like', 'Batch %' )->forceDelete();

        try {
            $command->run( new ArrayInput( ['--fail' => true] ), new BufferedOutput() );
            $this->fail( 'Exception expected' );
        } catch( \RuntimeException $e ) {
            $this->assertSame( 'aborted', $e->getMessage() );
        }

        $this->assertSame( $level, $db->transactionLevel() );
        $this->assertSame( 4, Element::where( 'name', 'like', 'Batch %' )->count() );
    }


    public function testCreatedFilesAreRemovedOnRollback(): void
    {
        $keys = ['cms.allow-internal', 'cms.disks.public.name', 'cms.image.preview-sizes'];
        $config = array_combine( $keys, array_map( fn( $key ) => config( $key ), $keys ) );
        config( ['cms.allow-internal' => true, 'cms.disks.public.name' => 'import-rollback', 'cms.image.preview-sizes' => []] );
        Storage::fake( 'import-rollback' );

        $png = (string) base64_decode( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=' );
        Http::fake( fn() => Http::response( $png, 200, ['Content-Length' => (string) strlen( $png ), 'Content-Type' => 'image/png'] ) );

        try
        {
            $kept = Files::download( 'image/png', 'Kept', 'http://127.0.0.1/kept.png', 'importer' );
            $file = null;

            try {
                DB::connection( config( 'cms.db', 'sqlite' ) )->transaction( function() use ( &$file ) {
                    $file = Files::download( 'image/png', 'Removed', 'http://127.0.0.1/removed.png', 'importer' );
                    Storage::disk( FileStorage::diskName( (string) $file->disk ) )->assertExists( (string) $file->path );
                    throw new \RuntimeException( 'failed' );
                } );
            } catch( \RuntimeException ) {
            }

            $this->assertInstanceOf( File::class, $file );
            Storage::disk( 'import-rollback' )->assertMissing( (string) $file->getOriginal( 'path' ) );
            Storage::disk( 'import-rollback' )->assertExists( (string) $kept->path );
            $this->assertNull( File::find( $file->id ) );

            // referenced files aren't stored by the import, so they are kept if the transaction fails
            // even if they are in the managed storage, which would be removed by deleting the file paths
            $managed = FileStorage::prefix( 'test' ) . '0198b6c2-8a1e-7000-8000-000000000001/referenced.png';
            Storage::disk( 'import-rollback' )->put( 'user_upload/referenced.png', $png );
            Storage::disk( 'import-rollback' )->put( $managed, $png );

            try {
                DB::connection( config( 'cms.db', 'sqlite' ) )->transaction( function() use ( $managed ) {
                    Files::reference( 'image/png', 'Referenced', 'user_upload/referenced.png', 'importer' );
                    Files::reference( 'image/png', 'Managed', $managed, 'importer' );
                    throw new \RuntimeException( 'failed' );
                } );
            } catch( \RuntimeException ) {
            }

            Storage::disk( 'import-rollback' )->assertExists( 'user_upload/referenced.png' );
            Storage::disk( 'import-rollback' )->assertExists( $managed );

            // also if the file can't be created
            Event::listen( 'eloquent.creating: ' . File::class, fn( File $file ) => $file->name === 'Broken' ? throw new \RuntimeException( 'broken' ) : null );

            try {
                Files::reference( 'image/png', 'Broken', $managed, 'importer' );
                $this->fail( 'Exception expected' );
            } catch( \RuntimeException $e ) {
                $this->assertSame( 'broken', $e->getMessage() );
            }

            Storage::disk( 'import-rollback' )->assertExists( $managed );
        }
        finally
        {
            config( $config );
        }
    }


    public function testFindRestoresTrashedPage(): void
    {
        $page = \Aimeos\Cms\Import\Pages::create( ['name' => 'Old', 'path' => 'old', 'domain' => 'example.com'], [] );
        $page->page->delete();

        $found = \Aimeos\Cms\Import\Pages::find( ['domain' => 'example.com', 'path' => 'old'] );

        $this->assertSame( $page->id, $found?->id );
        $this->assertFalse( $found->trashed() );
        $this->assertNotNull( PageView::find( $page->id ) );
    }


    public function testFindAllReturnsPagesByColumnValue(): void
    {
        $numeric = Pages::create( ['name' => 'Year', 'path' => '2026', 'domain' => 'example.com'], [] );
        $trashed = Pages::create( ['name' => 'Old', 'path' => 'old', 'domain' => 'example.com'], [] );
        $trashed->page->delete();
        Pages::create( ['name' => 'Other', 'path' => 'other', 'domain' => 'other.example'], [] );

        $found = Pages::findAll( ['domain' => 'example.com'], 'path', ['2026', 'old', 'other', 'missing'] );

        $this->assertSame( ['2026', 'old', 'other', 'missing'], array_map( strval( ... ), array_keys( $found ) ) );
        [$year, $old, $other, $missing] = array_values( $found );

        $this->assertSame( $numeric->id, $year?->id );
        $this->assertInstanceOf( PageView::class, $old );
        $this->assertSame( $trashed->id, $old->id );
        // trashed pages are returned but not restored
        $this->assertTrue( $old->trashed() );
        $this->assertNull( $other );
        $this->assertNull( $missing );
        $this->assertSame( [], Pages::findAll( ['domain' => 'example.com'], 'path', [] ) );
    }


    public function testRootReturnsRootPageOfDomain(): void
    {
        Pages::create( ['name' => 'Page', 'path' => 'page', 'tag' => 'page', 'domain' => 'example.com'], [] );
        $com = Pages::create( ['name' => 'Home', 'path' => '', 'tag' => 'root', 'domain' => 'example.com'], [] );
        $org = Pages::create( ['name' => 'Home', 'path' => '', 'tag' => 'root', 'domain' => 'example.org'], [] );

        $this->assertSame( $com->id, Pages::root( 'example.com' )?->id );
        $this->assertSame( $org->id, Pages::root( 'example.org' )?->id );
        $this->assertContains( Pages::root()?->id, [$com->id, $org->id] );
        $this->assertNull( Pages::root( 'example.net' ) );
    }


    public function testTypo3HtmlRewritesFilesAndLinks(): void
    {
        $html = new Typo3Html(
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
            . ' <a href="/page-5#x">New</a> <a href="t3://page?uid=9">Missing</a>'
            . ' <img src="/files/lazy.png"> <img src="/files/a.png">'
            . ' <img srcset="/files/s1.png 1x">  <a href=/files/doc.pdf>Doc</a> <a href="https://other.org/x.png">Other</a></p>', $result['html'] );
        $this->assertSame( ['u:s1.png', 'u:lazy.png', 'u:a.png', 'u:doc.pdf'], $result['fileIds'] );
    }


    public function testPublishWritesLatestVersionWithPublishedValues(): void
    {
        $queries = $this->writeQueries( function() {
            $page = Pages::create( ['name' => 'New', 'path' => 'new', 'domain' => 'example.com'], [], null, '2025-04-05 06:07:08' );
            Pages::publish( $page, ['name' => 'New', 'path' => 'new', 'domain' => 'example.com'], ['content' => []], [], [], 'en', 'test' );
        } );

        $page = PageView::where( 'path', 'new' )->firstOrFail();
        $variant = \Aimeos\Cms\Models\PageVariant::whereKey( $page->variant_id )->firstOrFail();
        $version = $page->versions()->firstOrFail();

        // the creation date is part of the insert and the latest version is saved by publishing
        $this->assertSame( '2025-04-05 06:07:08', $variant->created_at?->format( 'Y-m-d H:i:s' ) );
        $this->assertSame( [], array_filter( $queries, fn( $sql ) => str_contains( $sql, 'set "created_at"' ) ) );
        $this->assertCount( 1, array_filter( $queries, fn( $sql ) => stripos( $sql, 'update "cms_page_variants"' ) === 0 && str_contains( $sql, '"latest_id"' ) ) );
        $this->assertSame( $version->id, $variant->latest_id );
        $this->assertTrue( (bool) $version->published );
    }


    public function testCreateLoadsParentNodeOnce(): void
    {
        $parent = Pages::create( ['name' => 'Blog', 'path' => 'blog', 'domain' => 'example.com'], [] );
        $parents = 0;

        DB::connection( config( 'cms.db', 'sqlite' ) )->listen( function( $query ) use ( &$parents ) {
            $parents += (int) str_starts_with( $query->sql, 'select * from "cms_pages"' );
        } );

        $first = Pages::create( ['name' => 'First', 'path' => 'first', 'domain' => 'example.com'], [], $parent );
        $second = Pages::create( ['name' => 'Second', 'path' => 'second', 'domain' => 'example.com'], [], $parent );

        // the parent node is loaded for the first page only
        $this->assertSame( 1, $parents );

        $node = Page::findOrFail( $parent->id );

        $this->assertSame( $parent->id, $first->parent_id );
        $this->assertSame( $parent->id, $second->parent_id );
        // the bounds of the reused parent node are still correct
        $one = Page::findOrFail( $first->id );
        $two = Page::findOrFail( $second->id );

        $this->assertSame( [$node->getLft() + 1, $node->getLft() + 2], [$one->getLft(), $one->getRgt()] );
        $this->assertSame( [$one->getRgt() + 1, $one->getRgt() + 2], [$two->getLft(), $two->getRgt()] );
        $this->assertSame( $two->getRgt() + 1, $node->getRgt() );
    }


    public function testCreateRefusesTrashedParent(): void
    {
        $parent = Pages::create( ['name' => 'Blog', 'path' => 'blog', 'domain' => 'example.com'], [] );
        $parent->page?->delete();

        $this->expectException( \Illuminate\Database\Eloquent\ModelNotFoundException::class );
        Pages::create( ['name' => 'Post', 'path' => 'post', 'domain' => 'example.com'], [], $parent );
    }


    public function testCreateUsesCurrentDateWithoutCreationDate(): void
    {
        $page = Pages::create( ['name' => 'New', 'path' => 'new', 'domain' => 'example.com'], [] );

        $this->assertNotNull( $page->created_at );
        $this->assertLessThan( 60, abs( now()->diffInSeconds( $page->created_at ) ) );
    }


    public function testScheduledVersionSavesLatestVersion(): void
    {
        $page = Pages::create( ['name' => 'Page', 'path' => 'page', 'domain' => 'example.com'], [] );
        Pages::publish( $page, ['name' => 'Page', 'path' => 'page', 'domain' => 'example.com'], ['content' => []], [], [], 'en', 'test' );

        $version = Pages::version( $page, ['name' => 'Later', 'path' => 'page', 'domain' => 'example.com'], ['content' => []], [], [], 'en', 'test', '2099-01-01 00:00:00' );
        $variant = \Aimeos\Cms\Models\PageVariant::whereKey( $page->variant_id )->firstOrFail();

        // the scheduled version isn't published yet but it's the latest one
        $this->assertSame( $version->id, $variant->latest_id );
        $this->assertSame( $version->id, $page->latest_id );
        $this->assertSame( 'Page', $variant->name );
        $this->assertFalse( (bool) $version->published );
    }


    public function testFilesAndElementsWriteLatestVersionWithPublishedValues(): void
    {
        $queries = $this->writeQueries( function() use ( &$file, &$renamed, &$element ) {
            $file = Files::reference( 'image/png', 'Image', 'https://example.com/image.png', 'test' );
            $renamed = Files::rename( $file, 'Renamed', 'test' );
            $element = SharedElements::save( 'footer', 'en', ['type' => 'text', 'data' => ['text' => 'Footer']], [], 'test' );
        } );

        $file = File::findOrFail( $file?->id );
        $element = Element::findOrFail( $element );

        // the file is published twice and the element once, each with a single update
        $this->assertCount( 2, array_filter( $queries, fn( $sql ) => stripos( $sql, 'update "cms_files"' ) === 0 ) );
        $this->assertCount( 1, array_filter( $queries, fn( $sql ) => stripos( $sql, 'update "cms_elements"' ) === 0 ) );
        $this->assertSame( 'Renamed', $file->name );
        $this->assertSame( $file->versions()->latest( 'id' )->value( 'id' ), $file->latest_id );
        $this->assertSame( 2, $file->versions()->where( 'published', true )->count() );
        $this->assertSame( $element->versions()->value( 'id' ), $element->latest_id );
        $this->assertSame( 'Footer', $element->data->text ?? null );
    }


    /** @param class-string $class */
    #[DataProvider( 'importerClasses' )]
    public function testPublishedVersionPreservesPageContent( string $class ): void
    {
        $node = new Page( ['source' => ''] );
        $node->save();

        $root = $node->variants()->create( ['name' => 'Home', 'path' => '/', 'tag' => 'root'] )->view;
        $data = ['name' => 'Imported', 'path' => '/imported', 'status' => 1];
        $content = [['id' => 'content-1', 'type' => 'text', 'group' => 'main', 'data' => ['text' => 'Body']]];
        $page = \Aimeos\Cms\Import\Pages::create( $data, $content, $root );
        $command = new $class();

        ( new \ReflectionProperty( $class, 'lang' ) )->setValue( $command, 'en' );
        ( new \ReflectionProperty( $class, 'editor' ) )->setValue( $command, 'test' );
        ( new \ReflectionMethod( $class, 'createVersion' ) )->invoke( $command, $page, $data, $content, [] );

        $page->refresh()->load( 'latest' );
        $published = (array) $page->content;
        $versioned = (array) $page->latest?->aux->content;

        $this->assertSame( 'Body', $published[0]->data->text ?? null );
        $this->assertSame( 'Body', $versioned[0]->data->text ?? null );
    }


    /**
     * @return array<string, array{class-string}>
     */
    public static function importerClasses(): array
    {
        return [
            'Drupal' => [DrupalImport::class],
            'Joomla' => [JoomlaImport::class],
            'Statamic' => [StatamicImport::class],
        ];
    }


    /**
     * Returns the write statements executed by the function.
     *
     * @return array<int, string> SQL statements without selects
     */
    protected function writeQueries( \Closure $fn ): array
    {
        $queries = [];
        $db = DB::connection( config( 'cms.db', 'sqlite' ) );
        $db->listen( function( $query ) use ( &$queries ) {
            if( !str_starts_with( $query->sql, 'select' ) ) {
                $queries[] = $query->sql;
            }
        } );

        $fn();

        return $queries;
    }
    public function testTypo3ConverterMapsRecords(): void
    {
        $refs = fn( array $map ) => Collection::make( $map )->map( fn( $list ) => Collection::make( array_map( fn( $v ) => (object) $v, $list ) ) );
        $typo3 = new Typo3(
            fn( object $ref ) => ( $name = $ref->name ?? 'none' ) !== 'none' ? 'f:' . $name : null,
            new Typo3Html(
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

        $this->assertSame( [[
            ['heading', ['level' => 1, 'title' => 'T']],
            ['image-text', ['text' => '<p>Text</p>', 'file' => $file( 'f:a.png' ), 'position' => 'start']],
        ], ['f:a.png']], $data( $convert( ['CType' => 'textpic', 'uid' => 1, 'header' => 'T', 'header_layout' => '1', 'bodytext' => '<p>Text</p>', 'imageorient' => 17] ) ) );
        $this->assertSame( [[['image-text', ['text' => '<p>Text</p>', 'file' => $file( 'f:c.png' ), 'position' => 'end']]], ['f:c.png']],
            $data( $convert( ['CType' => 'textmedia', 'uid' => 3, 'header' => 'T', 'header_layout' => '100', 'bodytext' => '<p>Text</p>', 'imageorient' => 25] ) ) );
        $this->assertSame( [[['image-text', ['text' => '<p>Text</p>', 'file' => $file( 'f:c.png' ), 'position' => 'auto']]], ['f:c.png']],
            $data( $convert( ['CType' => 'textmedia', 'uid' => 3, 'bodytext' => '<p>Text</p>', 'imageorient' => 0] ) ) );
        $this->assertSame( [[['image-text', ['text' => '<p>T <img srcset="/files/s.png 1x, other.png 3x"></p>', 'file' => $file( 'f:c.png' ), 'position' => 'auto']]], ['u:s.png', 'f:c.png']],
            $data( $convert( ['CType' => 'textpic', 'uid' => 3, 'bodytext' => '<p>T <img srcset="fileadmin/s.png 1x, fileadmin/missing.png 2x, other.png 3x"><img src="fileadmin/missing.png"><img data-src="fileadmin/missing.png"></p>'] ) ) );
        $this->assertSame( [[['html', ['text' => '<p>Only</p>']]], []], $data( $convert( ['CType' => 'textpic', 'uid' => 2, 'bodytext' => '<p>Only</p>'] ) ) );
        $this->assertSame( [[['image', ['file' => $file( 'f:c.png' )]]], ['f:c.png']], $data( $convert( ['CType' => 'textpic', 'uid' => 3, 'bodytext' => ' '] ) ) );
        $this->assertNull( $convert( ['CType' => 'textpic', 'uid' => 2] ) );

        $this->assertSame( [[['heading', ['level' => 2, 'title' => 'I']], ['image', ['file' => $file( 'f:a.png' )]]], ['f:a.png']],
            $data( $convert( ['CType' => 'image', 'uid' => 1, 'header' => 'I'] ) ) );
        $this->assertNull( $convert( ['CType' => 'image', 'uid' => 2, 'header' => 'I'] ) );

        $this->assertSame( [[['html', ['text' => '<p><a href="/page-5#c1" target="_blank" class="cls" title="My title" rel="noreferrer noopener">a</a> b <a href="https://x.org">c</a>'
            . ' <a href="/page-5#f">d</a> <a>e</a></p><img src="/files/l.png" class="lazy" alt="l.png" /><img src="x.png" alt="x.png" />'
            . '<img src="/files/u.png" alt="u.png" /><a href="/files/doc.pdf">doc</a>']]], ['u:s1.png', 'u:l.png', 'u:doc.pdf', 'u:u.png']],
            $data( $convert( ['CType' => 'html', 'bodytext' => '<p><link 5#c1 _blank cls "My title">a</link> <link 9>b</link> <link https://x.org - - ->c</link>'
                . ' <a href="t3://page?uid=5&amp;fragment=f">d</a> <a href="t3://page?uid=9">e</a></p>'
                . '<img data-src="fileadmin/l.png" class="lazy"><img src="x.png" data-src="fileadmin/m.png"><img src="/fileadmin/missing.png">'
                . '<img srcset="fileadmin/s1.png 1x, fileadmin/missing.png 2x, other.png 3x"><img src=fileadmin/u.png><a href="fileadmin/doc.pdf">doc</a>'] ) ) );
        $this->assertNull( $convert( ['CType' => 'html', 'bodytext' => ''] ) );

        $this->assertSame( [[['questions', ['title' => 'Acc', 'items' => [['title' => 'Q1', 'text' => 'A1  [Go](/page-5)']]]]], ['u:q.png']],
            $data( $convert( ['CType' => 'accordion', 'uid' => 10, 'header' => 'Acc'] ) ) );
        $this->assertSame( [[['questions', ['title' => '', 'items' => [['title' => 'Item', 'text' => 'Body']]]]], []],
            $data( $convert( ['CType' => 'accordion', 'uid' => 11, 'bodytext' => '<p>Body</p>'] ) ) );
        $this->assertNull( $convert( ['CType' => 'accordion', 'uid' => 11] ) );

        $this->assertSame( [[['slideshow', ['title' => 'Car', 'files' => [$file( 'f:s1.png' ), $file( 'f:s2.png' )]]]], ['f:s1.png', 'f:s2.png']],
            $data( $convert( ['CType' => 'carousel', 'uid' => 20, 'header' => 'Car'] ) ) );
        $this->assertSame( [[['image', ['file' => $file( 'f:s3.png' )]]], ['f:s3.png']], $data( $convert( ['CType' => 'carousel', 'uid' => 21] ) ) );
        $this->assertSame( [[['html', ['text' => '<p>X</p>']]], []],
            $data( $convert( ['CType' => 'carousel', 'uid' => 22, 'header' => 'Fallback', 'header_layout' => '100', 'bodytext' => '<p>X</p>'] ) ) );

        $this->assertSame( [[['heading', ['level' => 2, 'title' => 'Txt']], ['html', ['text' => '<p>Body <img src="/files/t.png" alt="t.png" /></p>']]], ['u:t.png']],
            $data( $convert( ['CType' => 'text', 'header' => 'Txt', 'bodytext' => '<p>Body <img src="fileadmin/t.png"></p>'] ) ) );
        $this->assertSame( [[['heading', ['level' => 2, 'title' => 'Zero']]], []], $data( $convert( ['CType' => 'text', 'header' => 'Zero', 'bodytext' => '0'] ) ) );
        $this->assertNull( $convert( ['CType' => 'text'] ) );
        $this->assertSame( ['main'], array_values( array_unique( array_column( $convert( ['CType' => 'image', 'uid' => 1, 'header' => 'I'] )['elements'] ?? [], 'group' ) ) ) );
    }

    public function testHtmlSourceReturnsSourceAndAlt(): void
    {
        $this->assertSame( ['src' => 'a.png', 'alt' => 'Alt'], Html::source( '<IMG SRC="a.png" alt="Alt">' ) );
        $this->assertSame( ['src' => 'b.png', 'alt' => ''], Html::source( "<img src='b.png'>" ) );
        $this->assertNull( Html::source( '<img alt="x">' ) );
        // the real sources of lazy loaded images are preferred to the placeholder in "src"
        $this->assertSame( ['src' => 'real.png', 'alt' => ''], Html::source( '<img data-src="real.png" src="placeholder.gif">' ) );
        $this->assertSame( ['src' => 'real.png', 'alt' => ''], Html::source( '<img src="placeholder.gif" data-lazy-src="real.png">' ) );
        $this->assertSame( ['src' => 'real.png', 'alt' => ''], Html::source( '<img data-original="real.png" src="placeholder.gif">' ) );
        $this->assertSame( ['src' => 'real.png', 'alt' => ''], Html::source( '<img src="" data-src="real.png">' ) );
        $this->assertSame( ['src' => 'lazy.png', 'alt' => ''], Html::source( '<img data-src="lazy.png">' ) );
        // empty lazy load attributes use "src" and "src=" isn't matched within other attribute values
        $this->assertSame( ['src' => 'real.png', 'alt' => ''], Html::source( '<img data-src=" " src="real.png">' ) );
        // placeholders with data: URIs in the lazy load attributes are skipped, the ones in "src" are returned as last resort
        $this->assertSame( ['src' => 'real.png', 'alt' => ''], Html::source( '<img data-src="data:image/gif;base64,R0lG" src="real.png">' ) );
        $this->assertSame( ['src' => 'lazy.png', 'alt' => ''], Html::source( '<img data-src=" DATA:image/gif;base64,R0lG" data-original="lazy.png" src="data:image/gif;base64,R0lG">' ) );
        $this->assertSame( ['src' => 'data:image/png;base64,AAAA', 'alt' => ''], Html::source( '<img data-src="data:image/gif;base64,R0lG" src="data:image/png;base64,AAAA">' ) );
        $this->assertNull( Html::source( '<img data-src="data:image/gif;base64,R0lG">' ) );
        $this->assertSame( ['src' => 'real.png', 'alt' => ''], Html::source( '<img title="x src=y.png" src="real.png">' ) );
        $this->assertNull( Html::source( '<img src="" data-src="">' ) );
        // spaces around the equal sign are allowed like in HTML and the values keep their HTML entities
        $this->assertSame( ['src' => 'c.png?a=1&amp;b=2', 'alt' => 'A &amp; B'], Html::source( '<img src = "c.png?a=1&amp;b=2" alt =\'A &amp; B\'>' ) );
        $this->assertNull( Html::source( '<img src="">' ) );
    }


    public function testDatabaseFirstDateReturnsFirstNonEmptyDate(): void
    {
        $this->assertSame( '2020-01-02 03:04:05', Date::first( [null, '', 1, '0000-00-00 00:00:00.000', '2020-01-02 03:04:05', '2021-01-01 00:00:00'] ) );
        // "0" isn't empty
        $this->assertSame( '0', Date::first( ['0', '2020-01-02 03:04:05'] ) );
        $this->assertNull( Date::first( [null, '0000-00-00 00:00:00'] ) );
        $this->assertNull( Date::first( [] ) );
        $this->assertSame( '0000-00-00 00:00:00', Date::first( ['1970-01-01 00:00:00', '0000-00-00 00:00:00'], ['1970-01-01 00:00:00'] ) );
        // the empty dates without time are skipped too, e.g. of date only columns
        $this->assertSame( '2020-01-02', Date::first( ['0000-00-00', '2020-01-02'] ) );
        // the empty dates of Joomla 3 on PostgreSQL and SQL Server are only empty for Joomla
        $this->assertSame( '1970-01-01 00:00:00', Date::first( ['1970-01-01 00:00:00'] ) );
    }


    public function testCommandsDateSkipsEmptyDates(): void
    {
        $command = new class extends Commands {
            protected $signature = 'test:base-import-date';

            public function check( ?string ...$dates ): ?string
            {
                return $this->date( ...$dates );
            }
        };

        // the importers share the empty dates, also the ones without time and with fractions of seconds
        $this->assertSame( '2020-01-02 03:04:05', $command->check( null, '', '0000-00-00 00:00:00', '0000-00-00', '0000-00-00 00:00:00.000', '2020-01-02 03:04:05' ) );
        $this->assertSame( '1970-01-01 00:00:00', $command->check( '1970-01-01 00:00:00', '2020-01-02 03:04:05' ) );
        $this->assertNull( $command->check( '0000-00-00', null ) );
    }


    public function testFilesUrlPrefixesRelativePaths(): void
    {
        $this->assertSame( 'https://x.org/a.png', Files::url( 'https://x.org/a.png', 'https://cdn.org' ) );
        $this->assertSame( 'https://cdn.org/a/b.png', Files::url( '/a/b.png', 'https://cdn.org' ) );
        $this->assertSame( 'a.png', Files::url( '/a.png', '' ) );
        // URLs with any scheme or with spaces and other characters are absolute, also with other cases
        $this->assertSame( 'HTTP://x.org/a b/ä.png', Files::url( 'HTTP://x.org/a b/ä.png', 'https://cdn.org' ) );
        $this->assertSame( 'ftp://x.org/a.png', Files::url( 'ftp://x.org/a.png', '' ) );
        // URLs without scheme use the one of the base or HTTPS
        $this->assertSame( 'http://x.org/a.png', Files::url( '//x.org/a.png', 'http://cdn.org' ) );
        $this->assertSame( 'https://x.org/a.png', Files::url( '//x.org/a.png', 'https://cdn.org' ) );
        $this->assertSame( 'https://x.org/a.png', Files::url( '//x.org/a.png', '' ) );
        $this->assertSame( 'https://x.org/a.png', Files::url( '//x.org/a.png', 'storage/files' ) );
        // relative paths containing "://" later on aren't absolute
        $this->assertSame( 'https://cdn.org/a/b://c.png', Files::url( 'a/b://c.png', 'https://cdn.org' ) );
        // URIs with scheme but without authority are absolute too
        $this->assertSame( 'mailto:a@x.org', Files::url( 'mailto:a@x.org', 'https://cdn.org' ) );
        $this->assertSame( 'tel:+49123', Files::url( 'tel:+49123', 'https://cdn.org' ) );
        $this->assertSame( 'data:image/png;base64,AAAA', Files::url( 'data:image/png;base64,AAAA', 'https://cdn.org' ) );
        $this->assertSame( 'https://x.org/x', Files::url( '//x.org/x', 'https://cdn.org' ) );
        // colons after the first character which isn't a scheme are part of relative paths
        $this->assertSame( 'https://cdn.org/1:a.png', Files::url( '1:a.png', 'https://cdn.org' ) );
    }


    public function testWpMediaFindsFirstAttachmentInUrl(): void
    {
        $create = fn( string $mime, string $name, string $path, bool $download ) => "{$name}|{$path}";
        $attachment = fn( string $name, string $title ) => ['guid' => "https://wp.org/wp-content/uploads/{$name}", 'title' => $title, 'mime' => 'image/png'];
        $attachments = [1 => $attachment( 'b.png', 'B' ), 2 => $attachment( 'ab.png', 'AB' ), 3 => $attachment( '2024', 'Year' )];
        $fetch = fn( array $ids ) => array_intersect_key( $attachments, array_flip( $ids ) );
        $media = new WpMedia( [], $create, ['b.png' => 1, 'ab.png' => 2, '2024' => 3], $fetch );

        // the first attachment whose basename is part of the URL is used
        $this->assertSame( 'B|https://wp.org/wp-content/uploads/b.png', $media->image( '<img src="https://x.org/ab.png">' ) );
        $this->assertSame( 'Year|https://wp.org/wp-content/uploads/2024', $media->image( '<img src="https://x.org/2024/c.png">' ) );
        $this->assertSame( 'c-300x200.png|https://x.org/c-300x200.png', $media->image( '<img src="https://x.org/c-300x200.png">' ) );

        $media = new WpMedia( [], $create, ['ab.png' => 2, 'b.png' => 1], $fetch );
        $this->assertSame( 'AB|https://wp.org/wp-content/uploads/ab.png', $media->image( '<img src="https://x.org/ab.png">' ) );
        $this->assertSame( 'B|https://wp.org/wp-content/uploads/b.png', $media->image( '<img src="https://x.org/b.png">' ) );

        // attachments which weren't prefetched are fetched once, also if they don't exist any more
        $fetched = [];
        $media = new WpMedia( [], $create, ['b.png' => 1, 'gone.png' => 4], function( array $ids ) use ( $fetch, &$fetched ) {
            $fetched[] = $ids;
            return $fetch( $ids );
        } );
        $media->prefetch( [5 => 2], ['<img src="https://x.org/b.png">'] );

        $this->assertSame( 'B|https://wp.org/wp-content/uploads/b.png', $media->image( '<img src="https://x.org/b.png">' ) );
        $this->assertSame( 'AB|https://wp.org/wp-content/uploads/ab.png', $media->featured( 5 ) );
        $this->assertSame( 'gone.png|https://x.org/gone.png', $media->image( '<img src="https://x.org/gone.png">' ) );
        $this->assertSame( 'gone.png|https://x.org/gone.png', $media->image( '<img src="https://x.org/gone.png">' ) );
        $this->assertSame( [[2, 1], [4]], $fetched );
    }


    public function testWpMediaReturnsPathsOfPrefetchedFiles(): void
    {
        $attachments = [1 => ['guid' => 'https://wp.org/wp-content/uploads/b.png', 'title' => 'B', 'mime' => 'image/png']];
        $basenames = ( function() {
            yield 'b.png' => 3;
            yield 'c.png' => 2;
            yield 'b.png' => 1;
        } )();
        $media = new WpMedia( ['https://cdn.org'], fn( string $mime, string $name, string $path ) => "{$name}|{$path}", $basenames, fn( array $ids ) => array_intersect_key( $attachments, array_flip( $ids ) ) );

        $media->prefetch( [5 => 1, 6 => 9], ['<img src="https://wp.org/wp-content/uploads/x.png"><img src="https://x.org/y.png">', '<img src="https://x.org/y.png">'] );

        // the attachments and the images without attachment are rewritten like by url()
        $this->assertSame( ['https://cdn.org/x.png', 'https://x.org/y.png', 'https://cdn.org/b.png'], $media->paths() );

        // later attachments with the same basename replace the ID but keep the position of the first one
        $this->assertSame( 'B|https://cdn.org/b.png', $media->image( '<img src="https://x.org/c.png/b.png">' ) );
        $this->assertSame( [], $media->prefetch( [] )->paths() );
    }


    public function testWpMediaRemembersAttachmentsOfImageUrls(): void
    {
        $media = new class( [], fn() => null, ['b.png' => 1] ) extends WpMedia {
            protected const CACHE_SIZE = 4;

            /** @return array<string, int|string|null> */
            public function found(): array
            {
                return $this->found;
            }

            public function lookup( string $src ): int|string|null
            {
                // without the basenames, only remembered attachments are found
                $this->basenames = [];
                return $this->find( $src );
            }
        };
        $img = fn( string $name ) => '<img src="https://x.org/' . $name . '">';

        // the attachments of the image URLs of the current posts are remembered up to the cache size
        $media->prefetch( [], [$img( 'a.png' ) . $img( 'c.png' ), $img( 'd.png' ), $img( 'b.png' ) . $img( 'e.png' )] );
        $this->assertSame( ['https://x.org/b.png' => 1, 'https://x.org/e.png' => null], $media->found() );

        $media->prefetch( [], [$img( 'b.png' ) . $img( 'c.png' )] );
        $this->assertSame( ['https://x.org/b.png' => 1, 'https://x.org/c.png' => null], $media->found() );
        $this->assertSame( 1, $media->lookup( 'https://x.org/b.png' ) );
    }


    public function testWpMediaRewritesAndCreatesFiles(): void
    {
        $calls = [];
        $create = function( string $mime, string $name, string $path, bool $download ) use ( &$calls ) {
            $calls[] = [$mime, $name, $path, $download];
            return 'file-' . count( $calls );
        };
        $bases = [' https://cdn.org/media/ ', '', 'https://cdn.org/media', 'http://other.org:8080/up', 1];
        $media = new WpMedia( $bases, $create );

        $this->assertSame( 'https://cdn.org/media/2020/a.jpg', $media->rewrite( 'https://wp.org/wp-content/uploads/2020/a.jpg' ) );
        $this->assertSame( 'https://wp.org/x.jpg', $media->rewrite( 'https://wp.org/x.jpg' ) );

        $this->assertTrue( $media->managed( 'https://wp.org/wp-content/uploads/a.png' ) );
        $this->assertTrue( $media->managed( 'https://CDN.org:443/media/a.png' ) );
        $this->assertTrue( $media->managed( 'http://other.org:8080/up' ) );
        $this->assertFalse( $media->managed( 'https://cdn.org/mediax/a.png' ) );
        $this->assertFalse( $media->managed( 'http://cdn.org/media/a.png' ) );
        $this->assertFalse( $media->managed( 'http://other.org/up/a.png' ) );

        $this->assertSame( 'file-1', $media->url( 'https://wp.org/wp-content/uploads/a.png' ) );
        $this->assertSame( 'file-2', $media->url( 'https://x.org/b.jpg', 'Name', 'image/webp' ) );
        $this->assertSame( 'file-3', $media->image( '<img src="https://x.org/c.gif" alt="Alt">' ) );
        $this->assertNull( $media->image( '<p>text</p>' ) );
        $this->assertNull( $media->featured( 5 ) );

        $this->assertSame( [
            ['image/png', 'a.png', 'https://cdn.org/media/a.png', true],
            ['image/webp', 'Name', 'https://x.org/b.jpg', false],
            ['image/gif', 'Alt', 'https://x.org/c.gif', false],
        ], $calls );

        $attachments = [7 => ['guid' => 'https://wp.org/wp-content/uploads/d.png', 'title' => 'D', 'mime' => 'image/webp']];
        $media = new WpMedia( $bases, $create, ['d.png' => 7], fn( array $ids ) => array_intersect_key( $attachments, array_flip( $ids ) ) );
        $media->prefetch( [5 => 7, 6 => 0, 8 => 9] );

        $this->assertSame( 'file-4', $media->featured( 5 ) );
        $this->assertNull( $media->featured( 6 ) );
        $this->assertNull( $media->featured( 8 ) );
        $this->assertSame( 'file-5', $media->image( '<img src="https://x.org/wp-content/uploads/d.png">' ) );
        $this->assertSame( 'file-6', $media->image( '<img src="https://x.org/d.png" alt="Own">' ) );

        $this->assertSame( [
            ['image/webp', 'D', 'https://cdn.org/media/d.png', true],
            ['image/webp', 'D', 'https://cdn.org/media/d.png', true],
            ['image/webp', 'Own', 'https://cdn.org/media/d.png', true],
        ], array_slice( $calls, 3 ) );
    }


    public function testTypo3LayoutsSortByBackendLayoutColumns(): void
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
        $layout = new Typo3Layouts( $pages, $this->layouts( [3 => (object) ['config' => 'rows { 1 { columns { 1 { colPos = 2 } 2 { colPos = 0 } } } }']] ) );

        $this->assertSame( [9, 11, 10, 12], $layout->sort( $records, $child )->pluck( 'uid' )->all() );
        $this->assertSame( [12, 10, 9, 11], $layout->sort( $records, $pagets )->pluck( 'uid' )->all() );
        $this->assertSame( [10, 11, 9, 12], $layout->sort( $records, $plain )->pluck( 'uid' )->all() );
    }


    public function testTypo3LayoutsResolveColumnsOncePerLayout(): void
    {
        $page = (object) ['uid' => 1, 'pid' => 0, 'backend_layout' => 'db__3'];
        $other = (object) ['uid' => 2, 'pid' => 0, 'backend_layout' => 'db__3'];
        $layout = new class( Collection::make( [1 => $page, 2 => $other] ), $this->layouts( [3 => (object) ['config' => 'colPos = 1']] ) ) extends Typo3Layouts {
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
            $this->assertSame( [11, 10], $layout->sort( $records, $t3Page )->pluck( 'uid' )->all() );
        }

        // pages using the same database layout share its columns
        $this->assertSame( [$page], $layout->resolved );
    }


    public function testTypo3RecordsSelectsAndExpandsPageContent(): void
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
        $records = new Typo3Records( $pages,
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
            new Typo3Layouts( $pages, $this->layouts( [] ) )
        );

        // the targets of the shortcuts are fetched once for all records of the page
        $page = $records->page( $root );
        $this->assertSame( [[30]], $fetched );
        $this->assertSame( [10, 30, 12], $page->pluck( 'uid' )->all() );
        $this->assertSame( [null, 'reference', null], $page->pluck( '_pagible_shared' )->all() );
        $this->assertSame( [null, 3, null], $page->pluck( '_pagible_shared_page' )->all() );
        $this->assertSame( [null, 30, null], $page->pluck( '_pagible_shared_uid' )->all() );
        $this->assertSame( [0, 2, 0], $page->pluck( 'colPos' )->all() );
        $this->assertSame( [1, 2, 4], $page->pluck( 'sorting' )->all() );

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


    public function testTypo3RecordsCachesContentAndTargets(): void
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
            new Typo3Layouts( $pages, $this->layouts( [] ) )
        ) extends Typo3Records {
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


    public function testTypo3PrefetchesFileReferencesOfMissingRecords(): void
    {
        $fetched = ['refs' => [], 'accordions' => [], 'carousels' => [], 'carouselRefs' => []];
        $fetch = function( string $type, \Closure $fn ) use ( &$fetched ) {
            return function( array $uids ) use ( $type, $fn, &$fetched ) {
                $fetched[$type][] = $uids;
                return Collection::make( array_combine( $uids, array_map( $fn, $uids ) ) );
            };
        };
        $typo3 = new class( fn() => null, new Typo3Html( fn() => null, fn() => null ),
            $fetch( 'refs', fn( int $uid ) => Collection::make( [(object) ['uid_local' => $uid]] ) ),
            $fetch( 'accordions', fn( int $uid ) => Collection::make( [(object) ['uid' => $uid * 10]] ) ),
            $fetch( 'carousels', fn( int $uid ) => Collection::make( [(object) ['uid' => $uid * 10], (object) ['uid' => $uid * 10 + 1]] ) ),
            $fetch( 'carouselRefs', fn( int $uid ) => Collection::make( [(object) ['uid_local' => $uid]] ) )
        ) extends Typo3 {
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


    public function testTypo3UrlsResolvesPagesAndRedirects(): void
    {
        $page = (object) ['uid' => 1, 'doktype' => 1, 'slug' => '/about/'];
        $shortcut = (object) ['uid' => 2, 'doktype' => 4, 'shortcut' => 3, 'shortcut_mode' => 0];
        $external = (object) ['uid' => 4, 'doktype' => 3, 'url' => ' https://x.org '];
        $pages = $this->pages( [
            1 => $page,
            2 => $shortcut,
            3 => (object) ['uid' => 3, 'doktype' => 4, 'shortcut' => 1],
            4 => $external,
            5 => (object) ['uid' => 5, 'doktype' => 4, 'shortcut' => 1, 'shortcut_mode' => 1],
            6 => (object) ['uid' => 6, 'doktype' => 4, 'shortcut' => 7],
            7 => (object) ['uid' => 7, 'doktype' => 4, 'shortcut' => 6],
        ] );
        $urls = new Typo3Urls( $pages, [1 => [1 => '/ueber']] );

        $this->assertSame( '/about', $urls->page( 1 ) );
        $this->assertSame( '/about#top', $urls->page( 1, '#top' ) );
        $this->assertSame( '/about#top', $urls->page( 1, 'top' ) );
        $this->assertSame( '/about', $urls->page( 2 ) );
        $this->assertSame( 'https://x.org', $urls->page( 4 ) );
        $this->assertNull( $urls->page( 5 ) );
        $this->assertNull( $urls->page( 6 ) );
        $this->assertNull( $urls->page( 99 ) );

        $this->assertSame( '/ueber', $urls->page( 1, '', ['uid' => 1, 'source' => 'example.com'] ) );
        $this->assertSame( '/ueber', $urls->page( 2, '', ['uid' => 1, 'source' => ''] ) );
        $this->assertSame( 'https://example.com/about', $urls->page( 1, '', ['uid' => 2, 'source' => 'example.com'] ) );
        $this->assertSame( '/about', $urls->page( 1, '', ['uid' => 2, 'source' => ''] ) );

        $this->assertSame( '', $urls->redirect( $page ) );
        $this->assertSame( '/about', $urls->redirect( $shortcut ) );
        $this->assertSame( 'https://x.org', $urls->redirect( $external ) );
        $this->assertSame( 'a/b', Typo3Urls::slug( '/a/b/' ) );
        $this->assertSame( '', Typo3Urls::slug( null ) );
    }


    public function testTypo3TreeSelectsPagesAndFindsParents(): void
    {
        $root = (object) ['uid' => 1, 'pid' => 0, 'is_siteroot' => 0];
        $site = (object) ['uid' => 2, 'pid' => 1, 'is_siteroot' => 1];
        $page = (object) ['uid' => 3, 'pid' => 2, 'is_siteroot' => 0];
        $orphan = (object) ['uid' => 4, 'pid' => 9];
        $tree = new Typo3Tree( $this->pages( [1 => $root, 2 => $site, 3 => $page, 4 => $orphan,
            5 => (object) ['uid' => 5, 'pid' => 6], 6 => (object) ['uid' => 6, 'pid' => 5]] ) );

        $this->assertSame( [$page, $root], $tree->select( [3, 1] )->all() );
        $this->assertSame( '0000000000', $tree->order( $root ) );
        $this->assertSame( '000000000000000000010000000002', $tree->order( $page ) );
        $this->assertSame( $site, $tree->root( $page ) );
        $this->assertSame( $site, $tree->root( $site ) );
        $this->assertSame( $root, $tree->root( $root ) );
        $this->assertSame( $site, $tree->parent( $page ) );
        $this->assertNull( $tree->parent( $root ) );
        $this->assertNull( $tree->parent( $orphan ) );

        try {
            $tree->select( [3, 7, 8] );
            $this->fail( 'Missing pages must be rejected' );
        } catch( \InvalidArgumentException $e ) {
            $this->assertSame( 'TYPO3 page UID not found: 7, 8', $e->getMessage() );
        }

        try {
            $tree->order( $orphan );
            $this->fail( 'Missing parents must be rejected' );
        } catch( \RuntimeException $e ) {
            $this->assertSame( 'TYPO3 parent page [9] is missing from the source tree.', $e->getMessage() );
        }

        $this->expectExceptionMessage( 'Cycle detected in TYPO3 page tree at page [5].' );
        $tree->root( $tree->select( [5] )->first() );
    }


    public function testTypo3FilesResolvesReferencesAndUrls(): void
    {
        $calls = [];
        $calls = $lookups = [];
        $logo = (object) ['uid' => 1, 'identifier' => '/user_upload/Ä b.svg', 'name' => 'Logo.svg', 'extension' => 'svg', 'mime_type' => 'text/plain'];
        $pdf = (object) ['uid' => 2, 'identifier' => 'docs/a.pdf', 'name' => 'a.pdf', 'extension' => 'pdf', 'mime_type' => 'application/pdf'];
        $files = new Typo3Files( function( array $identifiers ) use ( $logo, &$lookups ) {
            $lookups[] = $identifiers;
            return $this->sysFiles( ['/user_upload/Ä b.svg' => $logo] )->only( $identifiers );
        }, 'https://t3.org', function( string $mime, string $name, string $path ) use ( &$calls ) {
            $calls[] = [$mime, $name, $path];
            return 'file-' . count( $calls );
        }, fn( string $path ) => 'url:' . $path );

        $this->assertSame( ['id' => 'file-1', 'url' => 'url:https://t3.org/user_upload/%C3%84%20b.svg'], $files->html( 'https://old.org/fileadmin/user_upload/%C3%84%20b.svg' ) );
        $this->assertSame( 'file-2', $files->reference( (object) ['uid_local' => 2, 'title' => 'Title', 'alternative' => 'Alt', 'sys_file' => $pdf] ) );
        $this->assertSame( 'file-3', $files->reference( (object) ['uid_local' => 2, 'title' => '', 'alternative' => 'Alt', 'sys_file' => $pdf] ) );
        $this->assertNull( $files->reference( (object) ['uid_local' => 3, 'sys_file' => null] ) );

        // the files of the identifiers are only looked up once
        $this->assertSame( ['id' => 'file-4', 'url' => 'url:https://t3.org/user_upload/%C3%84%20b.svg'], $files->html( 'https://old.org/fileadmin/user_upload/%C3%84%20b.svg' ) );
        $this->assertSame( [['/user_upload/%C3%84%20b.svg', '/user_upload/Ä b.svg']], $lookups );
        $this->assertNull( $files->html( 'javascript:fileadmin/a.pdf' ) );
        $this->assertNull( $files->html( 'https://old.org/uploads/a.pdf' ) );

        $this->assertSame( [
            ['image/svg+xml', 'Logo.svg', 'https://t3.org/user_upload/%C3%84%20b.svg'],
            ['application/pdf', 'Title', 'https://t3.org/docs/a.pdf'],
            ['application/pdf', 'Alt', 'https://t3.org/docs/a.pdf'],
            ['image/svg+xml', 'Logo.svg', 'https://t3.org/user_upload/%C3%84%20b.svg'],
        ], $calls );
    }


    public function testTypo3FilesLimitsCachedIdentifiers(): void
    {
        $lookups = [];
        $files = new class( function( array $identifiers ) use ( &$lookups ) {
            $lookups[] = $identifiers;
            return Collection::make();
        }, 'https://t3.org', fn() => 'id', fn( string $path ) => 'url:' . $path ) extends Typo3Files {
            protected const CACHE_SIZE = 2;
        };

        foreach( ['a', 'b', 'c', 'c', 'b', 'a', 'a%20b', 'b'] as $name ) {
            $files->html( "/fileadmin/{$name}.png" );
        }

        // only the last identifiers are kept but the ones just looked up are always kept
        $this->assertSame( [['/a.png'], ['/b.png'], ['/c.png'], ['/b.png'], ['/a.png'], ['/a%20b.png', '/a b.png'], ['/b.png']], $lookups );
    }


    public function testTypo3FilesUsesKnownFilesAndReturnsPaths(): void
    {
        $lookups = $calls = [];
        $known = ['https://t3.org/a.png' => 'known-a', 'https://t3.org/b.png' => 'known-b'];
        $files = new Typo3Files( function( array $identifiers ) use ( &$lookups ) {
            $lookups[] = $identifiers;
            return Collection::make();
        }, 'https://t3.org', function( string $mime, string $name, string $path ) use ( &$calls ) {
            $calls[] = $path;
            return 'new';
        }, fn( string $path ) => $path === 'https://t3.org/b.png' ? null : 'url:' . $path, fn( string $path ) => $known[$path] ?? null );

        // known files are neither looked up nor created again if their URL is available
        $this->assertSame( ['id' => 'known-a', 'url' => 'url:https://t3.org/a.png'], $files->html( '/fileadmin/a.png' ) );
        $this->assertNull( $files->html( '/fileadmin/b.png' ) );
        $this->assertSame( ['id' => 'new', 'url' => 'url:https://t3.org/c.png'], $files->html( '/fileadmin/c.png' ) );
        $this->assertSame( [['/b.png'], ['/c.png']], $lookups );
        $this->assertSame( ['https://t3.org/b.png', 'https://t3.org/c.png'], $calls );

        $refs = [
            (object) ['sys_file' => (object) ['identifier' => '/user_upload/a.png']],
            (object) ['sys_file' => null],
            (object) ['sys_file' => (object) ['identifier' => '/user_upload/a.png']],
        ];
        $texts = [
            '<img src="/fileadmin/x.png" srcset="/fileadmin/y.png 2x, /fileadmin/z.png 3x"><a href=https://old.org/fileadmin/doc.pdf?v=1>Doc</a>',
            null,
            '<a href="/other/x.png">Other</a><a href="javascript:fileadmin/a.pdf">Script</a>',
            '<img srcset="/fileadmin/v.png,/fileadmin/w.png 2x">',
        ];

        $this->assertSame( [
            'https://t3.org/user_upload/a.png', 'https://t3.org/x.png', 'https://t3.org/y.png', 'https://t3.org/z.png', 'https://t3.org/doc.pdf',
            'https://t3.org/v.png', 'https://t3.org/w.png',
        ], $files->paths( $refs, $texts ) );

        // without base URL, the URLs of other hosts are used without query string
        $files = new Typo3Files( fn() => Collection::make(), '', fn() => '', fn() => null );
        $this->assertSame( ['user_upload/a.png', 'x.png', 'y.png', 'z.png', 'https://old.org/fileadmin/doc.pdf'], $files->paths( $refs, [$texts[0], '<img src="fileadmin/x.png">'] ) );
    }


    public function testTypo3FilesStoredReturnsPortableUrls(): void
    {
        $name = config( 'cms.disks.public.name' );
        $file = ( new File )->forceFill( ['disk' => 'public', 'path' => 'cms/a.pdf'] );

        config( ['filesystems.disks.cms-test' => ['driver' => 'local', 'root' => sys_get_temp_dir(), 'url' => 'http://localhost:8000/storage']] );
        config( ['filesystems.disks.cms-remote' => ['driver' => 'cms-remote', 'url' => 'https://cdn.example.com']] );
        Storage::extend( 'cms-remote', fn( $app, array $config ) => new FilesystemAdapter(
            new Filesystem( $adapter = new LocalFilesystemAdapter( sys_get_temp_dir() ) ), $adapter, $config
        ) );

        try
        {
            config( ['cms.disks.public.name' => 'cms-test'] );
            $this->assertSame( '/storage/cms/a.pdf', Typo3Files::stored( $file ) );

            config( ['cms.disks.public.name' => 'cms-remote'] );
            $this->assertSame( 'https://cdn.example.com/cms/a.pdf', Typo3Files::stored( $file ) );
        }
        finally
        {
            config( ['cms.disks.public.name' => $name, 'filesystems.disks.cms-test' => null, 'filesystems.disks.cms-remote' => null] );
        }
    }


    public function testTypo3TreeUidsValidatesPageOptions(): void
    {
        $this->assertNull( Typo3Tree::uids( [] ) );
        $this->assertSame( [3, 1], Typo3Tree::uids( ['3', 1, '03', 3] ) );

        foreach( ['0', 'a', '-1', '1.5', ''] as $value )
        {
            try {
                Typo3Tree::uids( ['2', $value] );
                $this->fail( "Invalid page UID \"{$value}\" must be rejected" );
            } catch( \InvalidArgumentException $e ) {
                $this->assertSame( "Invalid TYPO3 page UID: {$value}", $e->getMessage() );
            }
        }
    }


    public function testTypo3LanguagesMapsUidsAndGroupsTranslations(): void
    {
        $languages = new Typo3Languages( [1 => ['lang' => 'de', 'domain' => ''], 2 => ['lang' => 'fr', 'domain' => '']], ['3:es', '01:da:https://example.dk/'] );

        $this->assertSame( ['lang' => 'da', 'domain' => 'example.dk'], $languages->get( 1 ) );
        $this->assertSame( ['lang' => 'fr', 'domain' => ''], $languages->get( 2 ) );
        $this->assertSame( ['lang' => 'es', 'domain' => ''], $languages->get( 3 ) );
        $this->assertNull( $languages->get( 4 ) );

        $result = $languages->translations( $this->rows( [
            (object) ['uid' => 10, 'l10n_parent' => 5, 'sys_language_uid' => '1', 'slug' => '/ueber'],
            (object) ['uid' => 11, 'l10n_parent' => 5, 'sys_language_uid' => 3, 'slug' => '/sobre'],
            (object) ['uid' => 12, 'l10n_parent' => 6, 'sys_language_uid' => 2, 'slug' => '/contact'],
            (object) ['uid' => 13, 'l10n_parent' => 6, 'sys_language_uid' => 4, 'slug' => '/x'],
            (object) ['uid' => 14, 'l10n_parent' => 7, 'sys_language_uid' => '7', 'slug' => '/y'],
            (object) ['uid' => 15, 'l10n_parent' => 7, 'sys_language_uid' => 4, 'slug' => '/z'],
            (object) ['uid' => 16, 'l10n_parent' => 6, 'sys_language_uid' => 2, 'slug' => '/contact-2'],
            (object) ['uid' => 17, 'l10n_parent' => 8, 'sys_language_uid' => 1],
        ] ) );

        // the first translation of a page in each language is used
        $this->assertSame( [4, 7], $result['unknown'] );
        $this->assertSame( 5, $result['count'] );
        $this->assertSame( [5 => [1 => '/ueber', 3 => '/sobre'], 6 => [2 => '/contact'], 8 => [1 => '']], $result['slugs'] );

        $this->expectExceptionMessage( 'Invalid TYPO3 language: 0:de' );
        new Typo3Languages( [], ['0:de'] );
    }


    public function testTypo3SiteMapsRootsToDomains(): void
    {
        $site = new Typo3Site( ['example.com:1', 'example.de:2'], fn() => [3 => 't3.org', 1 => 'other.org'] );

        $this->assertSame( '', $site->domain() );
        $this->assertSame( 'example.com', $site->domain( (object) ['uid' => '1'] ) );
        $this->assertSame( 'example.de', $site->domain( (object) ['uid' => 2] ) );
        $this->assertSame( 't3.org', $site->domain( (object) ['uid' => 3] ) );
        $this->assertSame( '', $site->domain( (object) ['uid' => 4] ) );
        $this->assertSame( 'https://example.com/fileadmin', $site->fileBase( '' ) );
        $this->assertSame( 'https://files.org', $site->fileBase( 'https://files.org' ) );

        // the domain records aren't used if there's a default domain
        $site = new Typo3Site( ['example.org', 'example.de:2'], fn() => throw new \LogicException( 'Domains must not be read' ) );

        $this->assertSame( 'example.org', $site->domain() );
        $this->assertSame( 'example.org', $site->domain( (object) ['uid' => 3] ) );
        $this->assertSame( 'example.de', $site->domain( (object) ['uid' => 2] ) );
        $this->assertSame( 'https://example.org/fileadmin', $site->fileBase( '' ) );
        $this->assertSame( 'http://t3.org/fileadmin', ( new Typo3Site( [], fn() => [5 => 'http://t3.org/'] ) )->fileBase( '' ) );

        $site = new Typo3Site( ['example.de:0'], fn() => [] );

        $this->assertSame( 'example.de', $site->domain() );
        $this->assertSame( 'example.de', ( new Typo3Site( ['example.de:'], fn() => throw new \LogicException( 'Domains must not be read' ) ) )->domain() );
        $this->assertSame( '', ( new Typo3Site( [], fn() => [] ) )->fileBase( '' ) );
    }


    public function testTypo3DestinationsFindsExistingPages(): void
    {
        $root = Pages::create( ['name' => 'Home', 'path' => '', 'tag' => 'root', 'domain' => 'example.com'], [] );
        $about = Pages::create( ['name' => 'About', 'path' => 'about', 'domain' => 'example.com'], [] );
        $other = Pages::create( ['name' => 'About', 'path' => 'about', 'domain' => 'example.de'], [] );
        $trashed = Pages::create( ['name' => 'Old', 'path' => 'old', 'domain' => 'example.com'], [] );
        $trashed->page->delete();

        $t3Root = (object) ['uid' => 1, 'pid' => 0, 'slug' => '/'];
        $t3About = (object) ['uid' => 2, 'pid' => 1, 'slug' => '/about'];
        $t3Old = (object) ['uid' => 3, 'pid' => 1, 'slug' => '/old'];
        $t3Site = (object) ['uid' => 4, 'pid' => 1, 'is_siteroot' => 1, 'slug' => '/site'];
        $t3New = (object) ['uid' => 5, 'pid' => 1, 'slug' => '/new'];
        $domains = [];

        $destinations = new Typo3Destinations( $this->pages( [$t3Root, $t3About, $t3Old, $t3Site, $t3New] ), function( $page ) use ( &$domains ) {
            $domains[] = $page->uid;
            return 'example.com';
        } );

        $this->assertSame( [1, 2, 3, 4, 5], $domains );
        $this->assertSame( $root->id, $destinations->find( $t3Root, 'example.com' )?->id );
        $this->assertSame( $root->id, $destinations->find( $t3Site, 'example.com' )?->id );
        $this->assertSame( $about->id, $destinations->find( $t3About, 'example.com' )?->id );
        $this->assertSame( $other->id, $destinations->find( $t3About, 'example.de' )?->id );
        $this->assertNull( $destinations->find( $t3New, 'example.com' ) );

        // trashed pages are restored by the lookup
        $this->assertSame( $trashed->id, $destinations->find( $t3Old, 'example.com' )?->id );
        $this->assertFalse( PageView::withTrashed()->findOrFail( $trashed->id )->trashed() );

        // remembered pages are used without lookup
        $destinations->put( 'example.com', 'new', $about );
        $this->assertSame( $about->id, $destinations->find( $t3New, 'example.com' )?->id );

        $destinations->forget( $t3New );
        $this->assertNull( $destinations->find( $t3New, 'example.com' ) );

        $about->page->delete();
        $destinations->forget( $t3About );
        $this->assertSame( $about->id, $destinations->find( $t3About, 'example.com' )?->id );
    }


    public function testVariantsKnownSplitsRowsByLanguage(): void
    {
        $de = (object) ['langcode' => 'de'];
        $fr = (object) ['langcode' => 'fr'];
        $es = (object) ['langcode' => 'es'];
        $result = Variants::known( $this->rows( [$de, $fr, $es, (object) ['langcode' => 'fr'], (object) ['langcode' => 'it']] ),
            fn( $row ) => $row->langcode, fn( $code ) => $code === 'de' || $code === 'es' );

        $this->assertSame( ['fr', 'it'], $result['unknown'] );
        $this->assertSame( [0 => $de, 2 => $es], $result['rows']->all() );

        $empty = Variants::known( $this->rows( [] ), fn( $row ) => $row->langcode, fn( $code ) => true );
        $this->assertSame( [], $empty['unknown'] );
        $this->assertTrue( $empty['rows']->isEmpty() );
    }


    /*
     * Shared Commands and CommitsInBatches tests
     */


    public function testCommitNowCommitsPendingBatch(): void
    {
        $command = new class extends Commands {
            protected $signature = 'test:base-import';
            /** @var array<int, array{0: int, 1: int}> Batched records and committing() calls after each step */
            public array $result = [];
            public int $committing = 0;
            public float $started = 0;

            public function handle(): void
            {
                $this->createdFiles = Collection::make();

                // nothing batched
                $this->commitNow();
                $this->result[] = [$this->batched, $this->committing];

                // within the transaction of a record
                $this->batched = 2;
                DB::connection( config( 'cms.db', 'sqlite' ) )->transaction( fn() => $this->commitNow() );
                $this->result[] = [$this->batched, $this->committing];

                $this->batchStarted = 0;
                $this->commitNow();
                $this->result[] = [$this->batched, $this->committing];
                $this->started = $this->batchStarted;
            }

            /**
             * Batches a record outside of the batch transaction and commits it.
             */
            public function outside(): int
            {
                $this->batched = 1;
                $this->commitNow();

                return $this->batched;
            }

            protected function committing(): void
            {
                $this->committing++;
                parent::committing();
            }
        };
        $command->setLaravel( app() );

        $db = DB::connection( config( 'cms.db', 'sqlite' ) );
        $level = $db->transactionLevel();
        $commits = 0;
        Event::listen( TransactionCommitted::class, function( $event ) use ( $level, &$commits ) {
            if( $event->connection->transactionLevel() === $level ) {
                $commits++;
            }
        } );

        $this->assertSame( 0, $command->run( new ArrayInput( [] ), new BufferedOutput() ) );
        // only the pending batch is committed and the batch is started again
        $this->assertSame( [[0, 0], [2, 0], [0, 1]], $command->result );
        $this->assertGreaterThan( 0, $command->started );
        // by commitNow() and at the end
        $this->assertSame( 2, $commits );
        $this->assertSame( $level, $db->transactionLevel() );

        // nothing is committed outside of the batch transaction
        $this->assertSame( 1, $command->outside() );
        $this->assertSame( 2, $commits );
        $this->assertSame( 1, $command->committing );
    }


    public function testPrefetchedVariantsAreUsedOnce(): void
    {
        $data = ['name' => 'About', 'title' => 'About', 'path' => 'about', 'lang' => 'en', 'domain' => '', 'status' => 1, 'editor' => 'importer'];
        $content = [['id' => 'content-1', 'type' => 'text', 'group' => 'main', 'data' => ['type' => 'text', 'text' => 'Body']]];
        $page = Pages::create( $data, $content );
        Pages::publish( $page, $data, ['content' => $content], [], [], 'en', 'importer' );
        Variants::save( $page, ['lang' => 'de', 'domain' => ''], 'ueber', ['name' => 'Über', 'title' => 'Über'] + $data,
            ['elements' => [['type' => 'text', 'group' => 'main', 'data' => ['type' => 'text', 'text' => 'Text']]]], 'importer' );

        $command = new class extends Commands {
            protected $signature = 'test:base-import';

            /**
             * @param array<int, string> $ids
             */
            public function prefetch( array $ids ): void
            {
                $this->prefetchVariants( $ids );
            }

            /**
             * @return array<int, string>
             */
            public function langs( PageView $page ): array
            {
                $langs = array_keys( $this->variants( $page ) );
                sort( $langs );

                return $langs;
            }
        };

        $db = DB::connection( config( 'cms.db', 'sqlite' ) );
        $count = function( \Closure $fn ) use ( $db ): array {
            $db->flushQueryLog();
            $db->enableQueryLog();
            $result = $fn();
            $db->disableQueryLog();

            return [$result, count( $db->getQueryLog() )];
        };
        $missing = ( new PageView() )->newFromBuilder( ['id' => 'missing-0'] );
        $id = $page->id ?? '';
        $ids = [$id, ...array_map( fn( $num ) => "missing-{$num}", range( 0, \Aimeos\Cms\DB::IN_SIZE ) )];

        // one query per chunk of page IDs
        $this->assertSame( [null, 2], $count( fn() => $command->prefetch( [...$ids, $id] ) ) );
        // prefetched variants are used once, then they are fetched again
        $this->assertSame( [['de', 'en'], 0], $count( fn() => $command->langs( $page ) ) );
        $this->assertSame( [['de', 'en'], 1], $count( fn() => $command->langs( $page ) ) );
        // pages without variants have none without query
        $this->assertSame( [[], 0], $count( fn() => $command->langs( $missing ) ) );
        $this->assertSame( [[], 1], $count( fn() => $command->langs( $missing ) ) );
        // prefetching discards the variants prefetched before
        $command->prefetch( [$id] );
        $command->prefetch( [] );
        $this->assertSame( [['de', 'en'], 1], $count( fn() => $command->langs( $page ) ) );
    }


    public function testFilesAreForgottenAfterCommit(): void
    {
        $run = function( bool $keep ): array {
            $command = new class( $keep ) extends Commands {
                protected const PREFETCH_SIZE = 2;
                protected $signature = 'test:base-import';
                protected int $batchSize = 1;
                /** @var array<int, array<int, string>> */
                public array $result = [];

                public function __construct( private bool $keep )
                {
                    parent::__construct();
                }

                public function handle(): void
                {
                    $this->editor = 'importer';
                    $this->connection = 'test';
                    $this->createdFiles = Collection::make();

                    foreach( range( 1, 4 ) as $num )
                    {
                        $this->transaction( "file {$num}", function() use ( $num ) {
                            $id = $this->createFile( 'image/png', "File {$num}", "https://forget.example/{$num}.png" );
                            $this->reuseFile( "https://reuse.example/{$num}.png", File::findOrFail( $id ) );
                        } );
                    }

                    $this->result = [$this->createdFiles->keys()->all(), array_keys( $this->reusedFiles )];
                }

                protected function keepsFiles(): bool
                {
                    return $this->keep;
                }
            };
            $command->setLaravel( app() );

            $this->assertSame( 0, $command->run( new ArrayInput( [] ), new BufferedOutput() ) );
            File::query()->forceDelete();

            return $command->result;
        };
        $url = fn( string $host, int ...$nums ) => array_map( fn( $num ) => "https://{$host}.example/{$num}.png", $nums );

        // committed files are found by their path again, so only the last ones are kept
        $this->assertSame( [$url( 'forget', 3, 4 ), $url( 'reuse', 3, 4 )], $run( false ) );
        // the created files are kept for pruning but the reused ones aren't needed
        $this->assertSame( [$url( 'forget', 1, 2, 3, 4 ), $url( 'reuse', 3, 4 )], $run( true ) );
    }


    public function testMetaTagsNeedDescriptionAndAreShortened(): void
    {
        $this->assertNull( Elements::metaTags( " \n\t ", 'keyword' ) );
        $this->assertSame( ['type' => 'meta-tags', 'data' => ['description' => 'A b c'], 'files' => []], Elements::metaTags( "  A\n b \t c ", '  ' ) );

        $meta = Elements::metaTags( str_repeat( 'word ', 40 ), ' ' . str_repeat( 'key, ', 60 ) );
        $this->assertSame( str_repeat( 'word ', 31 ) . 'word…', $meta['data']['description'] ?? null );
        $this->assertSame( rtrim( str_repeat( 'key, ', 51 ) ) . '…', $meta['data']['keywords'] ?? null );
    }


    public function testRobotsAreReadFromCommaSeparatedValues(): void
    {
        $this->assertNull( Elements::robots( '' ) );
        $this->assertNull( Elements::robots( 'noarchive' ) );
        $this->assertSame( ['type' => 'robots', 'data' => ['index' => 'noindex', 'follow' => 'follow'], 'files' => []], Elements::robots( 'NoIndex , Follow' ) );
        $this->assertSame( ['type' => 'robots', 'data' => ['index' => 'index', 'follow' => 'nofollow'], 'files' => []], Elements::robots( 'index,nofollow' ) );
        $this->assertSame( ['index' => 'noindex'], Elements::robots( 'noindex, index' )['data'] ?? null );
        $this->assertSame( ['follow' => 'nofollow'], Elements::robots( 'follow, nofollow' )['data'] ?? null );
    }


    public function testDoesNotDownloadReusedFiles(): void
    {
        $config = ['cms.allow-internal' => config( 'cms.allow-internal' )];
        $this->beforeApplicationDestroyed( fn() => config( $config ) );
        config( ['cms.allow-internal' => true] );
        $sent = [];
        Http::fake( function( $request ) use ( &$sent ) {
            $sent[] = $request->url();
            return Http::response( 'data', 200, ['Content-Type' => 'image/png'] );
        } );

        $command = new class extends Commands {
            protected $signature = 'test:base-import';

            public function handle(): void
            {
                $this->editor = 'importer';
                $this->createdFiles = Collection::make();

                // reused files of previous imports may not be in the list of prefetched files any more
                $this->reuseFile( 'http://127.0.0.1/reused/a.png', new File( ['id' => 'reused'] ) );
                $this->download( ['http://127.0.0.1/reused/a.png', 'http://127.0.0.1/reused/b.png'] );
            }
        };
        $command->setLaravel( app() );

        $this->assertSame( 0, $command->run( new ArrayInput( [] ), new BufferedOutput() ) );
        $this->assertSame( ['http://127.0.0.1/reused/b.png'], $sent );
    }


    public function testKeepsDownloadsOfNeededFiles(): void
    {
        $config = ['cms.allow-internal' => config( 'cms.allow-internal' )];
        $this->beforeApplicationDestroyed( fn() => config( $config ) );
        config( ['cms.allow-internal' => true] );
        $sent = [];
        Http::fake( function( $request ) use ( &$sent ) {
            $sent[] = $request->url();
            return Http::response( 'data', 200, ['Content-Type' => 'image/png'] );
        } );

        $command = new class extends Commands {
            protected $signature = 'test:base-import';
            public bool $kept = false;

            public function handle(): void
            {
                $this->editor = 'importer';
                $this->createdFiles = Collection::make();

                // files downloaded before and needed again are kept while the others are removed
                $this->download( [], ['http://127.0.0.1/needed/a.png', 'http://127.0.0.1/needed/b.png'] );
                $this->download( [], ['http://127.0.0.1/needed/a.png'] );
                $this->kept = $this->downloaded( 'http://127.0.0.1/needed/a.png' ) !== null
                    && $this->downloaded( 'http://127.0.0.1/needed/b.png' ) === null;
            }
        };
        $command->setLaravel( app() );

        $this->assertSame( 0, $command->run( new ArrayInput( [] ), new BufferedOutput() ) );
        $this->assertTrue( $command->kept );
        $this->assertSame( ['http://127.0.0.1/needed/a.png', 'http://127.0.0.1/needed/b.png'], $sent );
    }


    public function testRenamesFilesUsedAgainOnlyOncePerImport(): void
    {
        $run = function( array $names ) {
            $command = new class( $names ) extends Commands {
                protected const PREFETCH_SIZE = 1;
                protected $signature = 'test:base-import';
                protected int $batchSize = 1;

                /** @param array<int, string> $names */
                public function __construct( private array $names )
                {
                    parent::__construct();
                }

                public function handle(): void
                {
                    $this->editor = 'importer';
                    $this->connection = 'test';
                    $this->createdFiles = Collection::make();

                    foreach( $this->names as $num => $name )
                    {
                        $this->transaction( "file {$num}", function() use ( $num, $name ) {
                            $this->createFile( 'image/png', $name, 'https://shared.example/logo.png' );
                            $this->createFile( 'image/png', "Other {$num}", "https://other.example/{$num}.png" );
                        } );
                    }
                }

                protected function keepsFiles(): bool
                {
                    return false;
                }
            };
            $command->setLaravel( app() );

            $this->assertSame( 0, $command->run( new ArrayInput( [] ), new BufferedOutput() ) );
            $file = File::where( 'path', 'https://shared.example/logo.png' )->firstOrFail();

            return [$file->name, $file->versions()->count()];
        };

        // the file isn't renamed when it's used again after the created files were forgotten
        $this->assertSame( ['Logo A', 1], $run( ['Logo A', 'Logo B', 'Logo A'] ) );
        // files of previous imports are only renamed by their first use
        $this->assertSame( ['Logo B', 2], $run( ['Logo B', 'Logo A', 'Logo C'] ) );

        File::query()->forceDelete();
    }


    /**
     * Returns the TYPO3 sys_file records in the type returned by the TYPO3 reader.
     *
     * @param array<string, mixed> $files
     * @return Collection<string, mixed>
     */
    protected function sysFiles( array $files ): Collection
    {
        return Collection::make( $files );
    }


    /**
     * Returns TYPO3 pages keyed by UID in the type used by the TYPO3 importer.
     *
     * @param array<int, mixed> $pages
     * @return Collection<int, mixed>
     */
    protected function pages( array $pages ): Collection
    {
        return Collection::make( $pages );
    }


    /**
     * Returns TYPO3 records in the type returned by the TYPO3 reader.
     *
     * @param array<int|string, mixed> $records
     * @return Collection<int|string, mixed>
     */
    protected function records( array $records ): Collection
    {
        return Collection::make( $records );
    }


    /**
     * Returns the database rows in the type returned by the readers.
     *
     * @param array<int, \stdClass> $rows
     * @return Collection<int, \stdClass>
     */
    protected function rows( array $rows ): Collection
    {
        return Collection::make( $rows );
    }


    /**
     * Returns the backend layouts in the type returned by the TYPO3 reader.
     *
     * @param array<int|string, mixed> $layouts
     * @return Collection<int|string, mixed>
     */
    protected function layouts( array $layouts ): Collection
    {
        return Collection::make( $layouts );
    }
}
