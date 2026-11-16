<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests\Joomla;

use Aimeos\Cms\Import\Files;
use Aimeos\Cms\Import\Html;
use Aimeos\Cms\Import\Joomla;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;


/**
 * Tests of the content of the Joomla articles and categories: texts, HTML, shortcodes, modules, custom fields, tags and page breaks.
 */
class ContentTest extends TestAbstract
{
    public function testTextBuildsEscapedLinksAndImages(): void
    {
        $this->assertSame( '<a href="/a?b=1&amp;c=2">A &lt;b&gt;</a>', Joomla\Markup::link( '/a?b=1&c=2', 'A <b>' ) );
        $this->assertSame( '<a href="https://x.test/" rel="noopener noreferrer" target="_blank">X</a>', Joomla\Markup::link( 'https://x.test/', 'X', true ) );
        $this->assertSame( '<img src="a&quot;.png" alt="&lt;A&gt;">', Joomla\Markup::image( 'a".png', '<A>' ) );
        $this->assertSame( '<img src="a.png" alt="">', Joomla\Markup::image( 'a.png' ) );
        $this->assertSame( '<img src="a.png">', Joomla\Markup::image( 'a.png', null ) );
    }


    public function testText(): void
    {
        $resolve = fn( array $params ) => ( $params['id'] ?? null ) === '5:about' ? '/about&more' : null;

        $this->assertSame( '<a href="/about&amp;more#top">A</a> <a href=\'/about&amp;more\'>B</a>',
            Joomla\HtmlUrls::links( '<a href="https://example.com/index.php?option=com_content&amp;id=5:about#top">A</a> <a href=\'index.php?id=5:about\'>B</a>', $resolve, ['example.com'] ) );
        $this->assertSame( '<a href="https://other.com/index.php?id=5:about">A</a> <a href="index.php?id=6">B</a> <a href="/x/index.php?id=5:about">C</a>',
            Joomla\HtmlUrls::links( '<a href="https://other.com/index.php?id=5:about">A</a> <a href="index.php?id=6">B</a> <a href="/x/index.php?id=5:about">C</a>', $resolve, ['example.com'] ) );
        $this->assertSame( '<a href="/about&amp;more">A</a> <a href="/about&amp;more">B</a> <a href="/about&amp;more">C</a> <a href="https://example.com/x/index.php?id=5:about">D</a>',
            Joomla\HtmlUrls::links( '<a href="http://example.com/index.php?id=5:about">A</a> <a href="//EXAMPLE.com:443/index.php?id=5:about">B</a> <a href="https://www.test.com/joomla/index.php?id=5:about">C</a> <a href="https://example.com/x/index.php?id=5:about">D</a>', $resolve, ['example.com', 'www.test.com/joomla/', ''] ) );

        // hosts without port only match the default ports, the directory of the site is case-sensitive and data-href isn't a link
        $this->assertSame( '<a href="https://example.com:8080/index.php?id=5:about">A</a> <a href="/about&amp;more">B</a> <a href="https://www.test.com/Joomla/index.php?id=5:about">C</a>',
            Joomla\HtmlUrls::links( '<a href="https://example.com:8080/index.php?id=5:about">A</a> <a href="https://example.com:443/index.php?id=5:about">B</a> <a href="https://www.test.com/Joomla/index.php?id=5:about">C</a>', $resolve, ['example.com', 'www.test.com/joomla'] ) );
        $this->assertSame( '<a href="/about&amp;more">A</a>', Joomla\HtmlUrls::links( '<a href="https://example.com:8080/index.php?id=5:about">A</a>', $resolve, ['example.com:8080'] ) );
        $this->assertSame( '<div data-href="index.php?id=5:about" x-href="index.php?id=5:about"></div>',
            Joomla\HtmlUrls::links( '<div data-href="index.php?id=5:about" x-href="index.php?id=5:about"></div>', $resolve ) );

        // the HTML entities of the fragments aren't encoded twice
        $this->assertSame( '<a href="/about&amp;more#a&amp;b">A</a>', Joomla\HtmlUrls::links( '<a href="index.php?id=5:about#a&amp;b">A</a>', $resolve ) );

        // the host and the directory of the site must be followed by a slash
        $this->assertSame( '<a href="/joomlaindex.php?id=5:about">A</a> <a href="https://example.comindex.php?id=5:about">B</a> <a href="/about&amp;more">C</a>',
            Joomla\HtmlUrls::links( '<a href="/joomlaindex.php?id=5:about">A</a> <a href="https://example.comindex.php?id=5:about">B</a> <a href="/joomla/index.php?id=5:about">C</a>', $resolve, ['example.com/joomla', 'example.com'] ) );

        // the directory of the site must be a whole path segment
        $this->assertSame( '<a href="/joomla2/index.php?id=5:about">A</a> <a href="https://example.com/joomlax/index.php?id=5:about">B</a>',
            Joomla\HtmlUrls::links( '<a href="/joomla2/index.php?id=5:about">A</a> <a href="https://example.com/joomlax/index.php?id=5:about">B</a>', $resolve, ['example.com/joomla'] ) );

        // empty sites don't match links to other sites without scheme
        $this->assertSame( '<a href="//index.php?id=5:about">A</a>', Joomla\HtmlUrls::links( '<a href="//index.php?id=5:about">A</a>', $resolve, ['/', ''] ) );
        $this->assertSame( ['a.jpg', 'b.png'], Joomla\Html::sources( '<img src="a.jpg"><img src=""><IMG alt="x"><img src="b.png"><img src="DATA:image/png;base64,AAAA">' ) );
        // the sources are trimmed like the images of articles and categories, whitespace only sources are skipped
        $this->assertSame( ['https://cdn.test/a.jpg', 'images/b.jpg'], Joomla\Html::sources( '<img src=" https://cdn.test/a.jpg "><img src=" "><img src="images/b.jpg#joomlaImage://local-images/b.jpg">' ) );
        $this->assertSame( ['src' => 'a.jpg', 'alt' => 'A'], Joomla\Html::imageSource( '<img src="&#32;a.jpg" alt="A">' ) );
        $this->assertNull( Joomla\Html::imageSource( '<img src=" data:image/png;base64,AAAA">' ) );
        // the HTML entities of the sources are decoded once
        $this->assertSame( ['images/a b.jpg?x=1&y=2', 'images/c&amp;d.jpg'], Joomla\Html::sources( '<img src="images/a&#32;b.jpg?x=1&amp;y=2"><img src="images/c&amp;amp;d.jpg">' ) );
        $this->assertSame( ['src' => 'a.jpg?x=1&y=2', 'alt' => 'A & B'], Html::decodedSource( '<img src="a.jpg?x=1&amp;y=2" alt="A &amp; B">' ) );
        $this->assertNull( Html::decodedSource( '<img alt="A">' ) );

        $this->assertSame( '<p>A</p><h2>Part 2</h2><p>B</p><h2>Page 3</h2><p>C</p>', Joomla\PageBreaks::headings( '<p>A</p><hr title="Part 2" class="system-pagebreak" /><p>B</p><HR CLASS="SYSTEM-PAGEBREAK"><p>C</p>' ) );
        // like Joomla, only the class in double quotes without other classes is a page break
        $this->assertSame( '<hr class="x system-pagebreak"><hr class=\'system-pagebreak\'>', Joomla\PageBreaks::headings( '<hr class="x system-pagebreak"><hr class=\'system-pagebreak\'>' ) );
        $this->assertSame( '<hr class="pagebreak">', Joomla\PageBreaks::headings( '<hr class="pagebreak">' ) );
        // like Joomla's default layout, a non-empty alternative text is used before the title, empty ones are skipped
        $this->assertSame( '<h2>0</h2><h2>Next</h2><h2>Part 4</h2><h2>Last</h2><h2>Page 6</h2>', Joomla\PageBreaks::headings( '<hr class="system-pagebreak" title="T" alt=" 0 "><hr class="system-pagebreak" alt=" " title="Next">'
            . '<hr class="system-pagebreak" alt="" title="Part 4"><hr class="system-pagebreak" data-alt="x" title="Last"><hr class="system-pagebreak" alt="">' ) );
        // like Joomla's Utility::parseAttributes(), only double-quoted values with lower case names are read and the last one is used
        $this->assertSame( '<h2>T</h2><h2>Page 3</h2><h2>B</h2><h2>Double</h2>', Joomla\PageBreaks::headings( '<hr class="system-pagebreak" alt=\'X\' title="T"><hr class="system-pagebreak" ALT="X" Title="Y">'
            . '<hr class="system-pagebreak" alt="A" alt="B"><hr class="system-pagebreak" title=\'Single\' alt = "Double">' ) );
        // titles of non-breaking or other white space only are no titles
        $this->assertSame( '<h2>Page 2</h2><h2>Page 3</h2><h2>A</h2>', Joomla\PageBreaks::headings( '<hr class="system-pagebreak" title="&nbsp;"><hr class="system-pagebreak" alt="&#160; " title=" &#x2003;"><hr class="system-pagebreak" title="&nbsp;A&nbsp;">' ) );
        $this->assertSame( 'a &amp; b', Html::attribute( '<img data-alt="x" ALT=\'a &amp; b\' title="t">', 'alt' ) );
        $this->assertSame( '', Html::attribute( '<img src="a.jpg">', 'alt' ) );
        $this->assertSame( '', Html::attribute( '<img data-x="1">', 'data.x' ) );
        // spaces around the equal sign are allowed like in HTML
        $this->assertSame( 'a b', Html::attribute( '<img src="x.jpg" alt = "a b">', 'alt' ) );

        $calls = [];
        $replace = function( Joomla\Shortcode $shortcode ) use ( &$calls ) {
            $calls[] = [$shortcode->name, $shortcode->args, $shortcode->arg];
            return $shortcode->arg === 'keep' ? null : "[{$shortcode->name}]";
        };
        $shortcodes = fn( string $html, \Closure $replace, string $style = 'none' ) => Joomla\Shortcodes::modules( Joomla\Shortcodes::fields( $html, $replace ), $replace, $style );

        // the field shortcodes are replaced first, Joomla only searches for module shortcodes in lower case but replaces them in any case
        $this->assertSame( '[loadposition]{EMAILCLOAK=off}<p>a [loadmodule] b</p>[field] {other x} {field} [loadmodule] <p>{loadposition keep}</p>',
            $shortcodes( '<p class="x"> {loadposition top} </p>{EMAILCLOAK=off}<p>a {LoadModule mod_custom, My &amp; title ,card} b</p>{field 3,x} {other x} {field} {loadmodule mod_menu} <p>{loadposition keep}</p>', $replace, 'html5' ) );
        $this->assertSame( [
            ['field', ['id' => '3'], '3,x'],
            ['loadposition', ['position' => 'top', 'style' => 'html5'], 'top'],
            ['loadposition', ['position' => 'keep', 'style' => 'html5'], 'keep'],
            ['loadmodule', ['type' => 'mod_custom', 'title' => 'My & title', 'style' => 'card'], 'mod_custom, My &amp; title ,card'],
            ['loadmodule', ['type' => 'mod_menu', 'title' => '', 'style' => 'html5'], 'mod_menu'],
        ], $calls );
        // the e-mail cloaking shortcodes are left to the content
        $this->assertSame( 'a{emailcloak=off}b', $shortcodes( 'a{emailcloak=off}b', $replace ) );

        // the single paragraph of shortcodes inside of a paragraph is removed, so the paragraphs aren't nested
        $html = fn( Joomla\Shortcode $shortcode ) => $shortcode->args['position'] === 'p' ? '<p>X</p>' : '<p>Y</p><p>Z</p>';
        $this->assertSame( '<p>a X b</p><p>X</p><pre>c <p>X</p></pre><p class="x">d <p>Y</p><p>Z</p></p>',
            $shortcodes( '<p>a {loadposition p} b</p>{loadposition p}<pre>c {loadposition p}</pre><p class="x">d {loadposition q}</p>', $html ) );
        // paragraphs with attributes are removed too but not other tags starting with "p"
        $html = fn( Joomla\Shortcode $shortcode ) => $shortcode->args['position'] === 'p' ? '<p class="m">X</p>' : '<pre>Y</pre>';
        $this->assertSame( '<p>a X b</p><p>c <pre>Y</pre></p>', $shortcodes( '<p>a {loadposition p} b</p><p>c {loadposition q}</p>', $html ) );
        // only paragraphs are removed, not other tags starting with "p" like "pre" or "picture"
        $this->assertSame( '<pre><p class="m">X</p></p><picture> <p class="m">X</p></p><p class="m">X</p>',
            $shortcodes( '<pre>{loadposition p}</p><picture> {loadposition p}</p><p id="a">{loadposition p}</p>', $html ) );

        // shortcodes are only searched for in lower case
        $calls = [];
        $this->assertSame( '{LoadPosition top} {LOADMODULE x} {LoadModuleId 1} {FIELD 3}', $shortcodes( '{LoadPosition top} {LOADMODULE x} {LoadModuleId 1} {FIELD 3}', $replace ) );
        $this->assertSame( [], $calls );

        // like Joomla, shortcodes which aren't "field" with an ID are field groups
        $this->assertSame( '[fieldgroup] [fieldgroup] [fieldgroup] [fieldgroup] [fieldgroup] [fieldgroup] [fieldgroup]',
            $shortcodes( '{FIELD 3} {field abc} {fieldgroup *,x} {fieldgroup *} {field 0} {fieldgroup 2} {fieldgroup * }', $replace ) );
        $this->assertSame( [['fieldgroup', ['id' => '3'], '3'], ['fieldgroup', ['id' => '0'], 'abc'], ['fieldgroup', ['id' => '0'], '*,x'], ['fieldgroup', ['id' => '*'], '*'], ['fieldgroup', ['id' => '0'], '0'], ['fieldgroup', ['id' => '2'], '2'], ['fieldgroup', ['id' => '0'], '* ']], $calls );

        // the spaces between the field shortcode name and its arguments aren't part of the arguments
        $calls = [];
        $this->assertSame( '[field]', $shortcodes( '{field  3}', $replace ) );
        $this->assertSame( [['field', ['id' => '3'], '3']], $calls );

        // only module IDs are replaced and always in the "none" style
        $calls = [];
        $this->assertSame( '[loadmoduleid] {loadmoduleid 05} {loadmoduleid x} {loadmoduleid 5 }', $shortcodes( '{loadmoduleid 5} {loadmoduleid 05} {loadmoduleid x} {loadmoduleid 5 }', $replace, 'html5' ) );
        $this->assertSame( [['loadmoduleid', ['id' => '5', 'style' => 'none'], '5']], $calls );

        // the shortcodes in the output of the former shortcodes are replaced by the later ones
        $nested = fn( Joomla\Shortcode $shortcode ) => match( $shortcode->name ) {
            'loadposition' => '{field 1}{loadposition a}{loadmodule b}',
            'loadmodule' => '{loadmoduleid 1}',
            default => "[{$shortcode->name}]",
        };
        $this->assertSame( '{field 1}{loadposition a}[loadmoduleid]', $shortcodes( '{loadposition a}', $nested ) );

        // shortcodes of disabled plugins are kept
        $calls = [];
        // the raw arguments start after the first space like in Joomla
        $this->assertSame( '[loadposition]', $shortcodes( '{loadposition  top}', $replace ) );
        $this->assertSame( [['loadposition', ['position' => 'top', 'style' => 'none'], ' top']], $calls );

        // like Joomla, any white space separates the name and the arguments
        $calls = [];
        $this->assertSame( '[loadposition] [loadmodule] [loadmoduleid]', $shortcodes( "{loadposition\ttop} {loadmodule\nmod_custom} {loadmoduleid\t5}", $replace ) );
        $this->assertSame( [['loadposition', ['position' => 'top', 'style' => 'none'], 'top'], ['loadmodule', ['type' => 'mod_custom', 'title' => '', 'style' => 'none'], 'mod_custom'], ['loadmoduleid', ['id' => '5', 'style' => 'none'], '5']], $calls );

        $calls = [];
        $this->assertSame( '[field] {loadposition a}', Joomla\Shortcodes::fields( '{field 1} {loadposition a}', $replace ) );
        $this->assertSame( '{field 1} [loadposition]', Joomla\Shortcodes::modules( '{field 1} {loadposition a}', $replace ) );
        $this->assertSame( [['field', ['id' => '1'], '1'], ['loadposition', ['position' => 'a', 'style' => 'none'], 'a']], $calls );
    }


    public function testFields(): void
    {
        $fields = $this->fields( [
            (object) ['id' => 1, 'title' => 'Colors', 'label' => '', 'type' => 'checkboxes', 'params' => '{"display":"1"}', 'group_id' => 2,
                'fieldparams' => '{"options":{"options0":{"name":"Red","value":"r"},"options1":{"value":"g"},"options2":"broken"}}'],
            (object) ['id' => 2, 'title' => 'Bio', 'label' => 'About', 'type' => 'editor', 'params' => '{"showlabel":"0"}', 'fieldparams' => '{}', 'group_id' => 2],
            (object) ['id' => 3, 'title' => 'Gallery', 'type' => 'imagelist', 'params' => '{"display":"3"}', 'fieldparams' => '{"directory":"/photos/"}'],
            (object) ['id' => 4, 'title' => 'Root', 'type' => 'imagelist', 'params' => '{"display":"3"}', 'fieldparams' => '{"directory":"/"}'],
            (object) ['id' => 5, 'title' => 'Sub', 'type' => 'subform', 'params' => '{"display":"3"}'],
            (object) ['id' => 6, 'title' => 'Logo', 'type' => 'media', 'params' => '{"display":"3"}'],
            (object) ['id' => 7, 'title' => 'Note', 'type' => 'text', 'params' => '{"prefix":"<","suffix":" cm"}'],
            (object) ['id' => 8, 'title' => 'Text', 'type' => 'textarea', 'params' => '{"display":"0"}'],
            (object) ['id' => 9, 'title' => 'Size', 'type' => 'text', 'params' => '{"display":"0"}', 'default_value' => 'M', 'group_id' => 3],
            (object) ['id' => 10, 'title' => 'Empty', 'type' => 'text', 'params' => '{"display":"0"}', 'default_value' => ' ', 'group_id' => 3],
            (object) ['id' => 11, 'title' => 'Zero', 'type' => 'text', 'params' => '{"display":"0","showlabel":"0","prefix":"0","suffix":"0"}'],
        ] );
        $values = [1 => ['g', 'r', 'b', ' '], 2 => ['<b>Bold</b>'], 3 => ['a.jpg', '-1', '0'], 4 => ['b.png'], 5 => ['{"row":1}'], 6 => ['images/logo.png'], 7 => ['<i>'], 8 => ['x'], 9 => [], 10 => [''], 11 => ['5']];

        $this->assertSame( '<ul class="fields"><li><strong>Colors: </strong>Red, g</li></ul>', $fields->position( Joomla\Fields::AFTER_TITLE, new Joomla\FieldContext( $values ) ) );
        $this->assertSame( '<ul class="fields"><li><b>Bold</b></li><li><strong>Note: </strong>&lt;&lt;i&gt; cm</li></ul>', $fields->position( Joomla\Fields::BEFORE_CONTENT, new Joomla\FieldContext( $values ) ) );
        // fields without key aren't shown for the article
        $this->assertSame( '<ul class="fields"><li><b>Bold</b></li></ul>', $fields->position( Joomla\Fields::BEFORE_CONTENT, new Joomla\FieldContext( array_diff_key( $values, [7 => true] ) ) ) );
        $this->assertSame( '', $fields->field( 7, new Joomla\FieldContext( array_diff_key( $values, [7 => true] ) ) ) );
        $this->assertSame( '<ul class="fields"><li><strong>Gallery: </strong><img src="images/photos/a.jpg" alt=""></li>'
            . '<li><strong>Root: </strong><img src="images/b.png" alt=""></li><li><strong>Logo: </strong><img src="images/logo.png"></li></ul>',
            $fields->position( Joomla\Fields::AFTER_CONTENT, new Joomla\FieldContext( $values ) ) );
        // like Joomla, the alternative text of media fields is only added if there's one or it's marked as empty
        $this->assertStringContainsString( '<img src="images/a.png" alt="A">', $fields->field( 6, new Joomla\FieldContext( [6 => ['{"imagefile":"images/a.png","alt_text":"A"}']] ) ) );
        $this->assertStringContainsString( '<img src="images/a.png" alt="">', $fields->field( 6, new Joomla\FieldContext( [6 => ['{"imagefile":"images/a.png","alt_text":"","alt_empty":1}']] ) ) );
        $this->assertStringContainsString( '<img src="images/a.png">', $fields->field( 6, new Joomla\FieldContext( [6 => ['{"imagefile":"images/a.png","alt_text":""}']] ) ) );
        $this->assertSame( '', $fields->position( Joomla\Fields::AFTER_CONTENT, new Joomla\FieldContext( [] ) ) );

        $this->assertSame( '<p><strong>Colors: </strong>Red, g</p>', $fields->field( 1, new Joomla\FieldContext( $values ) ) );
        $this->assertSame( '', $fields->field( 5, new Joomla\FieldContext( $values ) ) );
        $this->assertSame( '', $fields->field( 99, new Joomla\FieldContext( $values ) ) );
        // like Joomla, prefixes and suffixes like "0" aren't shown
        $this->assertSame( '<p>5</p>', $fields->field( 11, new Joomla\FieldContext( $values ) ) );
        $this->assertSame( '<ul class="fields"><li><strong>Colors: </strong>Red, g</li><li><b>Bold</b></li></ul>', $fields->group( 2, new Joomla\FieldContext( $values ) ) );
        $this->assertSame( '', $fields->group( 4, new Joomla\FieldContext( $values ) ) );

        // the default value is used if the article has no value, other values replace it
        $this->assertSame( '<ul class="fields"><li><strong>Size: </strong>M</li></ul>', $fields->group( 3, new Joomla\FieldContext( $values ) ) );
        $this->assertSame( '<ul class="fields"><li><strong>Size: </strong>L</li></ul>', $fields->group( 3, new Joomla\FieldContext( [9 => ['', 'L'], 10 => []] ) ) );
        $this->assertSame( '', $fields->group( 3, new Joomla\FieldContext( [10 => []] ) ) );

        // all fields are shown for "{fieldgroup *}"
        $this->assertSame( '<ul class="fields"><li><strong>Colors: </strong>Red, g</li><li><strong>Size: </strong>M</li></ul>', $fields->group( '*', new Joomla\FieldContext( [1 => $values[1], 9 => []] ) ) );
    }


    public function testUrlFieldsAreInternalOnlyForTheHostsOfTheSite(): void
    {
        $urls = ['http://example.com/page', 'https://www.example.com/page', 'https://example.co/page', 'https://example.com.evil.org/page', '/page', 'page'];
        $list = array_map( fn( int $id ) => (object) ['id' => $id, 'title' => 'F' . $id, 'type' => 'url', 'params' => '{"showlabel":"0"}', 'fieldparams' => '{}'], array_keys( $urls ) );
        $values = array_map( fn( string $url ) => [$url], $urls );
        $internal = function( Joomla\Fields $fields ) use ( $urls, $values ) {
            return array_map( fn( int $id ) => !str_contains( $fields->field( $id, new Joomla\FieldContext( $values ) ), 'target="_blank"' ), array_keys( $urls ) );
        };

        // the http URLs of the domain are internal, other hosts like lookalike ones aren't, relative URLs always are
        $this->assertSame( [true, false, false, false, true, true], $internal( $this->fields( $list, new Joomla\Origin( 'example.com' ) ) ) );
        // without sites, all absolute URLs are external
        $this->assertSame( [false, false, false, false, true, true], $internal( $this->fields( $list ) ) );
    }


    public function testImageSourcesAreDecodedOnce(): void
    {
        $images = [];
        $host = Joomla\Host::forTests( image: function( string $path, string $alt ) use ( &$images ) {
            $images[] = [$path, $alt];
            return null;
        } );
        $content = $this->wiring( $this->site( collect() ), host: $host )->assemble( articles: $this->articles(), folders: new Joomla\Folders(), home: new Joomla\HomeRoot(), redirects: [] )->content;

        // the entities of the image tags in the texts and the ones built for media fields are decoded once
        $html = '<p><img src="images/a.jpg?x=1&amp;y=2" alt="A &amp; B"><img src="images/b&amp;amp;c.jpg" alt="&lt;b&gt;"></p>' . Joomla\Markup::image( 'images/d&e.jpg', 'D & E' );
        $content->content( $html, Joomla\Rendering::article( [], '*', 0 ) );
        $this->assertSame( [['images/a.jpg?x=1&y=2', 'A & B'], ['images/b&amp;c.jpg', '<b>'], ['images/d&e.jpg', 'D & E']], $images );
    }


    public function testRootRelativeImageSourcesOfSitesInSubdirectoriesAreUnbased(): void
    {
        $images = [];
        $host = Joomla\Host::forTests( image: function( string $path, string $alt ) use ( &$images ) {
            $images[] = $path;
            return null;
        } );
        $origin = new Joomla\Origin( 'example.com', 'https://example.com/joomla/' );
        $content = $this->wiring( $this->site( collect() ), host: $host, origin: $origin )->assemble( articles: $this->articles(), folders: new Joomla\Folders(), home: new Joomla\HomeRoot(), redirects: [] )->content;

        // root-relative sources contain the directory of the site which is also part of the file base and must be removed once
        $html = '<p><img src="/joomla/images/a.jpg"><img src="images/b.jpg"><img src="/other/c.jpg"><img src="//cdn.test/d.jpg"><img src="https://cdn.test/e.jpg">'
            . '<img src="https://EXAMPLE.com:443/joomla/images/f.jpg"><img src="https://example.com/other/g.jpg"><img src="/Joomla/images/h.jpg">'
            . '<img src="data:image/png;base64,AAAA"><img src=" https://example.com/joomla/images/t.jpg "></p>';
        // scheme-relative sources use HTTPS, absolute sources below the file base are relative to it, the directory is case-sensitive
        // sources with whitespace are trimmed when imported and prefetched, so they are relative to the file base too
        $expected = ['/images/a.jpg', 'images/b.jpg', '/other/c.jpg', 'https://cdn.test/d.jpg', 'https://cdn.test/e.jpg', '/images/f.jpg', 'https://example.com/other/g.jpg', '/Joomla/images/h.jpg', '/images/t.jpg'];
        $content->content( $html, Joomla\Rendering::article( [], '*', 0 ) );
        $this->assertSame( $expected, $images );
        $this->assertSame( 'https://example.com/joomla/images/a.jpg', Files::url( $images[0], 'https://example.com/joomla' ) );

        // the paths of the prefetched images are the same so the imported files are found again
        DB::connection( 'joomla' )->table( 'content' )->where( 'id', 1 )->update( ['fulltext' => $html] );
        $texts = new Joomla\Texts( $this->reader(), $origin );
        $texts->prefetch( [1] );
        $this->assertSame( $expected, $texts->imagePaths( [1] ) );
        // the image values of articles and categories are relative to the file base like the images in the texts
        $this->assertSame( '/images/i.jpg', $texts->imageSource( 'https://example.com/joomla/images/i.jpg' ) );
        $this->assertSame( '/images/i.jpg', $texts->imageSource( '/joomla/images/i.jpg#joomlaImage://local-images/i.jpg?width=8' ) );
        $this->assertSame( 'images/i.jpg', $texts->imageSource( 'images/i.jpg' ) );
        $this->assertSame( '', $texts->imageSource( 'data:image/png;base64,AAAA' ) );
        $this->assertSame( '', $texts->imageSource( ' ' ) );
        DB::connection( 'joomla' )->table( 'content' )->where( 'id', 1 )->update( ['images' => '{"image_fulltext":"https://example.com/joomla/images/full.jpg"}'] );
        $texts = new Joomla\Texts( $this->reader(), $origin );
        $texts->prefetch( [1] );
        $this->assertSame( ['/images/full.jpg', ...$expected], $texts->imagePaths( [1] ) );
        // the suffix of the Joomla 4+ media field is removed from the full text image
        DB::connection( 'joomla' )->table( 'content' )->where( 'id', 1 )->update( ['images' => '{"image_fulltext":"/joomla/images/full.jpg#joomlaImage://local-images/full.jpg?width=8"}'] );
        $texts = new Joomla\Texts( $this->reader(), $origin );
        $texts->prefetch( [1] );
        $this->assertSame( ['/images/full.jpg', ...$expected], $texts->imagePaths( [1] ) );
        // sites without directory keep the root-relative sources
        $this->assertSame( '/images/a.jpg', ( new Joomla\Origin( 'example.com', 'https://example.com/' ) )->fileSource( '/images/a.jpg' ) );
        // absolute and relative sources of the same file are the same file URL
        $this->assertSame( '/images/a.jpg', ( new Joomla\Origin( 'example.com', 'https://example.com/' ) )->fileSource( 'https://example.com/images/a.jpg' ) );
        $this->assertSame( Files::url( 'images/b.jpg', 'https://example.com/joomla' ), Files::url( $origin->fileSource( 'https://example.com/joomla/images/b.jpg' ), 'https://example.com/joomla' ) );
        // without file base, absolute sources are kept
        $this->assertSame( 'https://example.com/images/a.jpg', ( new Joomla\Origin( 'example.com' ) )->fileSource( 'https://example.com/images/a.jpg' ) );
    }


    public function testOriginComparesHostsWithPortsAndNormalizesBaseUrlsWithoutScheme(): void
    {
        // domains with port only match URLs with the same port, plain hosts match the default ports
        $port = new Joomla\Origin( 'Example.com:8080' );
        $this->assertSame( '/x', $port->local( 'https://example.com:8080/x' ) );
        $this->assertNull( $port->local( 'https://example.com/x' ) );
        $plain = new Joomla\Origin( 'example.com' );
        $this->assertSame( '/x', $plain->local( 'https://example.com:443/x' ) );
        $this->assertSame( '/x', $plain->local( 'http://user@example.com:80/x' ) );
        $this->assertNull( $plain->local( 'https://example.com:8080/x' ) );
        $this->assertSame( ['example.com:8080'], $port->sites() );

        // base URLs without scheme which start with a host use HTTPS, local paths are kept
        $this->assertSame( 'https://cdn.example.org/images', Joomla\Origin::baseUrl( 'cdn.example.org/images' ) );
        $this->assertSame( 'https://cdn.example.org:8443', Joomla\Origin::baseUrl( 'cdn.example.org:8443' ) );
        $this->assertSame( 'storage/joomla', Joomla\Origin::baseUrl( 'storage/joomla' ) );
        $this->assertSame( 'https://example.com', Joomla\Origin::baseUrl( 'https://example.com' ) );
        $this->assertSame( 'https://localhost/joomla', Joomla\Origin::baseUrl( 'localhost/joomla' ) );
        $this->assertSame( 'https://localhost:8080', Joomla\Origin::baseUrl( 'localhost:8080' ) );
        $this->assertSame( 'https://127.0.0.1:8080/joomla', Joomla\Origin::baseUrl( '127.0.0.1:8080/joomla' ) );
        // dotted local paths without top level domain of letters or with the extension of backups aren't hosts
        $this->assertSame( 'backup.v2/images', Joomla\Origin::baseUrl( 'backup.v2/images' ) );
        $this->assertSame( 'joomla.bak/images', Joomla\Origin::baseUrl( 'joomla.bak/images' ) );
        $this->assertSame( 'site-1.2/images', Joomla\Origin::baseUrl( 'site-1.2/images' ) );
        $this->assertSame( 'localhostx/images', Joomla\Origin::baseUrl( 'localhostx/images' ) );
        $this->assertSame( 'joomla.BAK', Joomla\Origin::baseUrl( 'joomla.BAK' ) );
        $this->assertSame( 'site.old:8080/images', Joomla\Origin::baseUrl( 'site.old:8080/images' ) );
        // existing local folders named like hosts are local paths
        $cwd = (string) getcwd();
        $dir = sys_get_temp_dir() . '/joomla-origin-' . uniqid();
        mkdir( $dir . '/files.example.org/images', 0777, true );
        chdir( $dir );

        try
        {
            $this->assertSame( 'files.example.org/images', Joomla\Origin::baseUrl( 'files.example.org/images' ) );
            $this->assertSame( 'files.example.org', Joomla\Origin::baseUrl( 'files.example.org' ) );
            $this->assertSame( 'https://other.example.org/images', Joomla\Origin::baseUrl( 'other.example.org/images' ) );
        }
        finally
        {
            chdir( $cwd );
            rmdir( $dir . '/files.example.org/images' );
            rmdir( $dir . '/files.example.org' );
            rmdir( $dir );
        }

        // the query of the base URL itself is kept for the root of the file base
        $this->assertSame( '/?x=1', ( new Joomla\Origin( 'example.com', 'https://example.com/joomla' ) )->fileSource( 'https://example.com/joomla?x=1' ) );
        $cdn = new Joomla\Origin( 'example.com', 'cdn.example.org/images' );
        $this->assertSame( ['cdn.example.org/images', 'example.com'], $cdn->sites() );
        $this->assertSame( '/a.jpg', $cdn->fileSource( 'https://cdn.example.org/images/a.jpg' ) );
        $this->assertSame( '/a.jpg', $cdn->fileSource( '/images/a.jpg' ) );
    }


    public function testModuleHeaderTagsAreAllowlisted(): void
    {
        $modules = ( new \ReflectionClass( Joomla\Modules::class ) )->newInstanceWithoutConstructor();
        $tag = fn( mixed $header ) => ( new \ReflectionMethod( Joomla\Modules::class, 'headerTag' ) )->invoke( $modules, ['header_tag' => $header] );

        $this->assertSame( ['h1', 'h6', 'p', 'div', 'span', 'h4'], array_map( $tag, ['h1', 'h6', 'p', 'div', 'span', 'H4'] ) );
        // other tags like scripts or unknown elements use the default tag of Joomla
        $this->assertSame( ['h3', 'h3', 'h3', 'h3', 'h3'], array_map( $tag, ['script', 'iframe', 'h7', 'h4 onclick', null] ) );
    }


    public function testFieldTypes(): void
    {
        $schema = Schema::connection( 'joomla' );
        $schema->create( 'jos_users', function( Blueprint $table ) {
            $table->id();
            $table->string( 'name' );
        } );
        $schema->create( 'jos_usergroups', function( Blueprint $table ) {
            $table->id();
            $table->unsignedInteger( 'parent_id' );
            $table->string( 'title' );
            $table->unsignedInteger( 'lft' );
        } );
        $db = DB::connection( 'joomla' );
        $db->table( 'jos_users' )->insert( [['id' => 1, 'name' => 'Alice'], ['id' => 2, 'name' => 'Bob <b>']] );
        $db->table( 'jos_usergroups' )->insert( [
            ['id' => 1, 'parent_id' => 0, 'title' => 'Public', 'lft' => 1],
            ['id' => 2, 'parent_id' => 1, 'title' => 'Registered', 'lft' => 3],
            ['id' => 9, 'parent_id' => 1, 'title' => 'Guest', 'lft' => 2],
        ] );
        $field = fn( int $id, string $type, string $fieldparams = '{}' ) => (object) ['id' => $id, 'title' => 'F' . $id, 'type' => $type, 'params' => '{"showlabel":"0"}', 'fieldparams' => $fieldparams];
        $list = [
            $field( 1, 'textarea' ),
            $field( 2, 'calendar' ),
            $field( 3, 'calendar', '{"showtime":"1"}' ),
            $field( 4, 'calendar' ),
            $field( 5, 'url' ),
            $field( 6, 'url' ),
            $field( 7, 'url', '{"show_url":"1"}' ),
            $field( 8, 'integer' ),
            $field( 9, 'user' ),
            $field( 10, 'usergrouplist' ),
            $field( 11, 'sql', '{"query":"SELECT id AS value, name AS text FROM #__users ORDER BY name DESC;"}' ),
            $field( 12, 'sql', '{"query":"DELETE FROM #__users"}' ),
            $field( 13, 'sql', '{"query":"SELECT id AS value, name AS text FROM #__users INTO OUTFILE \'/tmp/users\'"}' ),
            $field( 14, 'sql', '{"query":"SELECT value FROM #__missing"}' ),
            $field( 15, 'sql', '{"query":"SELECT 1 AS value; DELETE FROM #__users"}' ),
            $field( 16, 'imagelist', '{"directory":"/"}' ),
            $field( 17, 'list', '{"options":"broken"}' ),
            $field( 18, 'editor' ),
            $field( 19, 'url' ),
            $field( 20, 'sql', '{"query":"SELECT 1 AS value, \'First\' AS text UNION ALL SELECT 1 AS value, \'Second\' AS text"}' ),
            $field( 21, 'url' ),
            $field( 22, 'url' ),
            $field( 23, 'url' ),
            $field( 24, 'url' ),
            $field( 25, 'url' ),
            $field( 26, 'url' ),
            $field( 27, 'url' ),
            $field( 28, 'url' ),
            $field( 29, 'url' ),
            $field( 30, 'url' ),
            $field( 31, 'url' ),
            $field( 32, 'url' ),
            $field( 33, 'url' ),
            $field( 34, 'url' ),
            $field( 35, 'url' ),
        ];
        $values = [
            1 => ["<p>A {loadmodule x}</p>\n<p>B</p>"], 2 => ['2024-05-01 13:45:00'], 3 => ['2024-05-01 13:45:00'], 4 => ['no <date>'],
            5 => ['https://example.org/'], 6 => ['/contact'], 7 => ['https://example.org/?a=1&b=2'], 8 => ['12abc'], 9 => ['2', '0', '99', '1'], 10 => ['9', '2', '5'],
            11 => ['1', '2', '3'], 12 => ['1'], 13 => ['1'], 14 => ['1'], 15 => ['1'], 16 => ['a.jpg', '-1', 'b.jpg'], 17 => ['x'], 18 => ['<p>{loadmodule x}</p>'],
            19 => ['http-docs/page'], 20 => ['1'], 21 => ['index.php?Itemid=5'], 22 => ['https://site.test/page'], 23 => ['https://site.tester/page'],
            24 => ['//site.test/index.php?x=1'], 25 => ['//site.test/page'], 26 => ['https://site.test'], 27 => [' index.php?x=1'], 28 => ['https://SITE.test/page'],
            29 => ['http://site.test/index.php?x=1'], 30 => ['https://site.test\\page'], 31 => ['index.php?x="1"'],
            32 => ['index.php?x=a b'], 33 => ['docs/index.php'], 34 => ['https://site.te/page'], 35 => ['https://site.test:8080/page'],
        ];
        $fields = $this->fields( $list, new Joomla\Origin( fileBase: 'https://site.test/' ), reader: $this->reader( $db, 'jos_' ) );

        // the names of the users are fetched in chunks of IDs
        $db->enableQueryLog();
        $this->assertSame( [1 => 'Alice', 2 => 'Bob <b>'], ( $this->reader( $db, 'jos_' ) )->users( [...range( 3, 2 + \Aimeos\Cms\DB::IN_SIZE ), 2, 1] ) );
        $this->assertCount( 2, array_filter( $db->getQueryLog(), fn( $query ) => str_contains( $query['query'], '"jos_users"' ) ) );
        $db->disableQueryLog();
        $db->flushQueryLog();
        $prepare = fn( string $html ) => str_replace( '{loadmodule x}', 'Module', $html );

        // text areas and editors aren't escaped and their shortcodes are replaced by the content plugins, block values aren't nested in paragraphs
        $this->assertSame( "<div><p>A Module</p>\n<p>B</p></div>", $fields->field( 1, new Joomla\FieldContext( $values, $prepare ) ) );
        $this->assertSame( '<div><p>{loadmodule x}</p></div>', $fields->field( 18, new Joomla\FieldContext( $values ) ) );
        $this->assertSame( '<div><p>Module</p></div>', $fields->field( 18, new Joomla\FieldContext( $values, $prepare ) ) );
        // dates are shown in the default format of Joomla, with time if configured
        $this->assertSame( '<p>2024-05-01</p>', $fields->field( 2, new Joomla\FieldContext( $values ) ) );
        $this->assertSame( '<p>2024-05-01 13:45</p>', $fields->field( 3, new Joomla\FieldContext( $values ) ) );
        // the formats are translated to the language like the Joomla date formats
        app( 'translator' )->addLines( ['*.Y-m-d' => 'd.m.Y', '*.Y-m-d H:i' => 'd.m.Y H:i'], 'de' );
        try {
            $this->assertSame( '<p>01.05.2024</p>', $fields->field( 2, new Joomla\FieldContext( $values, locale: 'de' ) ) );
            $this->assertSame( '<p>01.05.2024 13:45</p>', $fields->field( 3, new Joomla\FieldContext( $values, locale: 'de' ) ) );
        } finally {
            app( 'translator' )->addLines( ['*.Y-m-d' => 'Y-m-d', '*.Y-m-d H:i' => 'Y-m-d H:i'], 'de' );
        }
        // Joomla stores the dates in UTC and shows them in the time zone of the site
        $local = $this->fields( $list, timezone: new \DateTimeZone( 'Pacific/Auckland' ) );
        $this->assertSame( '<p>2024-05-02</p>', $local->field( 2, new Joomla\FieldContext( $values ) ) );
        $this->assertSame( '<p>2024-05-02 01:45</p>', $local->field( 3, new Joomla\FieldContext( $values ) ) );
        $this->assertSame( '<p>no &lt;date&gt;</p>', $fields->field( 4, new Joomla\FieldContext( $values ) ) );
        // URLs are linked by the texts of Joomla, external ones open in a new window
        $this->assertSame( '<p><a href="https://example.org/" rel="noopener noreferrer" target="_blank">Visit Website</a></p>', $fields->field( 5, new Joomla\FieldContext( $values ) ) );
        // relative URLs and the ones of the hosts of the site are internal
        $this->assertSame( '<p><a href="/contact">Visit Link</a></p>', $fields->field( 6, new Joomla\FieldContext( $values ) ) );
        $this->assertSame( '<p><a href="http-docs/page">Visit Link</a></p>', $fields->field( 19, new Joomla\FieldContext( $values ) ) );
        $this->assertSame( '<p><a href="docs/index.php">Visit Link</a></p>', $fields->field( 33, new Joomla\FieldContext( $values ) ) );
        $this->assertSame( '<p><a href="index.php?Itemid=5">Visit Link</a></p>', $fields->field( 21, new Joomla\FieldContext( $values ) ) );
        $this->assertSame( '<p><a href="https://site.test/page">Visit Link</a></p>', $fields->field( 22, new Joomla\FieldContext( $values ) ) );
        $this->assertSame( '<p><a href="https://site.tester/page" rel="noopener noreferrer" target="_blank">Visit Website</a></p>', $fields->field( 23, new Joomla\FieldContext( $values ) ) );
        // URLs without scheme, with other cases or the http scheme are internal if the host is the one of the site
        foreach( [24 => '//site.test/index.php?x=1', 25 => '//site.test/page', 28 => 'https://SITE.test/page', 29 => 'http://site.test/index.php?x=1'] as $id => $url ) {
            $this->assertSame( '<p><a href="' . e( $url ) . '">Visit Link</a></p>', $fields->field( $id, new Joomla\FieldContext( $values ) ) );
        }
        // other ports than the default ones are other sites
        $this->assertSame( '<p><a href="https://site.test:8080/page" rel="noopener noreferrer" target="_blank">Visit Website</a></p>', $fields->field( 35, new Joomla\FieldContext( $values ) ) );
        // the link texts are translated to the language of the article
        app( 'translator' )->addLines( ['*.Visit Website' => 'Website & mehr', '*.Visit Link' => 'Link besuchen'], 'de' );
        $this->assertSame( '<p><a href="https://example.org/" rel="noopener noreferrer" target="_blank">Website &amp; mehr</a></p>', $fields->field( 5, new Joomla\FieldContext( $values, null, 'de' ) ) );
        $this->assertSame( '<ul class="fields"><li><a href="index.php?Itemid=5">Link besuchen</a></li></ul>', $fields->group( '*', new Joomla\FieldContext( array_intersect_key( $values, [21 => true] ), null, 'de' ) ) );
        $this->assertSame( '<p><a href="index.php?Itemid=5">Visit Link</a></p>', $fields->field( 21, new Joomla\FieldContext( $values, null, 'en' ) ) );
        $this->assertSame( '<p><a href="https://site.test">Visit Link</a></p>', $fields->field( 26, new Joomla\FieldContext( $values ) ) );
        $this->assertSame( '<p><a href="https://site.test\\page">Visit Link</a></p>', $fields->field( 30, new Joomla\FieldContext( $values ) ) );
        // URLs with spaces or quotes and the ones of other hosts aren't internal
        foreach( [27, 31, 32, 34] as $id ) {
            $this->assertStringContainsString( 'target="_blank">Visit Website</a>', $fields->field( $id, new Joomla\FieldContext( $values ) ) );
        }
        $this->assertSame( '<p><a href="https://example.org/?a=1&amp;b=2" rel="noopener noreferrer" target="_blank">https://example.org/?a=1&amp;b=2</a></p>', $fields->field( 7, new Joomla\FieldContext( $values ) ) );
        $this->assertSame( '<p>12</p>', $fields->field( 8, new Joomla\FieldContext( $values ) ) );
        // values of integer fields not starting with a number aren't shown instead of "0"
        $this->assertSame( '', $fields->field( 8, new Joomla\FieldContext( [8 => ['abc']] ) ) );
        $this->assertSame( '<p>-3</p>', $fields->field( 8, new Joomla\FieldContext( [8 => [' -3']] ) ) );
        // users are shown by their names, unknown ones by empty names like Joomla does
        $this->assertSame( '<p>Bob &lt;b&gt;, , Alice</p>', $fields->field( 9, new Joomla\FieldContext( $values ) ) );
        $this->assertSame( '', $fields->field( 9, new Joomla\FieldContext( [9 => ['99']] ) ) );
        // user groups and options of SQL fields are shown in the order of Joomla, unknown values are skipped
        $this->assertSame( '<p>Guest, Registered</p>', $fields->field( 10, new Joomla\FieldContext( $values ) ) );
        $this->assertSame( '<p>Bob &lt;b&gt;, Alice</p>', $fields->field( 11, new Joomla\FieldContext( $values ) ) );
        // the table prefix is replaced and the first text of duplicate values is used like Joomla does
        $this->assertSame( '<p>First</p>', $fields->field( 20, new Joomla\FieldContext( $values ) ) );
        // only single SELECT queries without INTO are run, failing queries show nothing
        foreach( [12, 13, 14, 15] as $id ) {
            $this->assertSame( '', $fields->field( $id, new Joomla\FieldContext( $values ) ) );
        }
        $this->assertSame( 2, $db->table( 'jos_users' )->count() );
        // images are shown without separator
        $this->assertSame( '<p><img src="images/a.jpg" alt=""><img src="images/b.jpg" alt=""></p>', $fields->field( 16, new Joomla\FieldContext( $values ) ) );
        $this->assertSame( '', $fields->field( 17, new Joomla\FieldContext( $values ) ) );

        // users, user groups and options of SQL fields are fetched only once
        $db->table( 'jos_users' )->delete();
        $db->table( 'jos_usergroups' )->delete();
        $this->assertSame( '<p>Bob &lt;b&gt;, , Alice</p>', $fields->field( 9, new Joomla\FieldContext( $values ) ) );
        $this->assertSame( '<p>Guest, Registered</p>', $fields->field( 10, new Joomla\FieldContext( $values ) ) );
        $this->assertSame( '<p>Bob &lt;b&gt;, Alice</p>', $fields->field( 11, new Joomla\FieldContext( $values ) ) );
        // unknown users are remembered too and not fetched again
        $db->table( 'jos_users' )->insert( ['id' => 99, 'name' => 'Late'] );
        $this->assertSame( '', $fields->field( 9, new Joomla\FieldContext( [9 => ['99']] ) ) );
    }


    public function testSqlFieldOptionsOnlyRunReadOnlyQueries(): void
    {
        $db = DB::connection( 'joomla' );
        $db->getSchemaBuilder()->create( 'users', function( Blueprint $table ) {
            $table->integer( 'id' );
            $table->string( 'name' );
        } );
        $db->table( 'users' )->insert( [['id' => 1, 'name' => 'Alice'], ['id' => 2, 'name' => 'Bob']] );
        $warnings = [];
        $reader = $this->reader( $db, '', function( string $msg ) use ( &$warnings ) { $warnings[] = $msg; } );

        // functions known to be harmless, conditions, the table prefix and quoted keywords as aliases are allowed
        $this->assertSame( ['1' => 'ALICE'], $reader->sqlOptions()->fetch( 'SELECT id AS value, UPPER( name ) AS text FROM #__users WHERE id IN (1)' ) );
        $this->assertSame( ['2' => 'Bob'], $reader->sqlOptions()->fetch( 'SELECT id AS value, name AS text, 0 AS "into" FROM #__users WHERE name = \'Bob\'' ) );
        $this->assertSame( ['1' => 'select; --'], $reader->sqlOptions()->fetch( 'SELECT 1 AS value, \'select; --\' AS text' ) );
        $this->assertSame( [], $warnings );

        // statements with side effects, subqueries, comments, variables, locks and unknown functions are rejected with a warning
        $rejected = [
            'DELETE FROM #__users',
            'SELECT id AS value FROM #__users; DELETE FROM #__users',
            'SELECT id AS value FROM #__users FOR UPDATE',
            'SELECT id AS value FROM #__users LOCK IN SHARE MODE',
            'SELECT id AS value FROM #__users INTO OUTFILE \'/tmp/users\'',
            'SELECT SLEEP(5) AS value',
            'SELECT BENCHMARK(1000000, MD5(1)) AS value',
            'SELECT id AS value FROM #__users WHERE id IN (SELECT id FROM #__users)',
            'SELECT id AS value FROM #__users -- comment',
            'SELECT id AS value FROM #__users # comment',
            'SELECT id AS value /* comment */ FROM #__users',
            'SELECT @a := 1 AS value',
            'SELECT \'\\\' AS value',
            'SELECT \'open AS value',
            // quoted identifiers can be the names of any function
            'select `sleep`(10) as value',
            'SELECT `get_lock`(\'a\', 10) AS value',
            'select "x"(1) as value',
            'SELECT `concat` ( 1 ) AS value',
            'SELECT `sleep`(1) AS value',
            'SELECT `sleep` (1) AS value',
            // backslashes inside strings can escape the closing quote depending on the SQL mode
            'SELECT \'a\\\'b\' AS value',
            'SELECT \'a\\\\\' AS value FROM #__users',
            // SELECTs after UNION mustn't contain subqueries either
            'SELECT id AS value FROM #__users UNION (SELECT id FROM #__users)',
            'SELECT id AS value FROM #__users UNION SELECT id FROM (SELECT id FROM #__users) t',
            'SELECT id AS value FROM #__users UNION SELECT id FROM #__users WHERE id IN ( SELECT id FROM #__users )',
            'SELECT id AS value FROM #__users UNION TABLE #__users',
        ];
        foreach( $rejected as $sql ) {
            $this->assertSame( [], $reader->sqlOptions()->fetch( $sql ), $sql );
        }
        $this->assertCount( count( $rejected ), $warnings );
        $this->assertStringContainsString( 'only single SELECT queries without side effects', $warnings[0] );
        $this->assertSame( 2, $db->table( 'users' )->count() );

        // failing queries are shown as warning too
        $this->assertSame( [], $reader->sqlOptions()->fetch( 'SELECT value FROM #__missing' ) );
        $this->assertStringContainsString( 'its query failed', end( $warnings ) );
    }


    public function testAbsoluteUrls(): void
    {
        $html = '<a href="docs/a.pdf">A</a> <img src="images/x.jpg" srcset="images/x.jpg 1x, /images/y.jpg 2x, https://e.com/z.jpg 3x" data-src="images/d.jpg">'
            . ' <video poster="images/p.jpg"></video> <a href="/abs">B</a> <a href="#top">C</a> <a href="mailto:a@b.c">D</a> <a href=\'rel\'>E</a> <a href="https://x">F</a> <a href="\'+url+\'">G</a>';

        // the "System - SEF" plugin of Joomla makes the relative URLs of double quoted attributes absolute
        $this->assertSame( '<a href="/docs/a.pdf">A</a> <img src="/images/x.jpg" srcset="/images/x.jpg 1x, /images/y.jpg 2x, https://e.com/z.jpg 3x" data-src="images/d.jpg">'
            . ' <video poster="/images/p.jpg"></video> <a href="/abs">B</a> <a href="#top">C</a> <a href="mailto:a@b.c">D</a> <a href=\'rel\'>E</a> <a href="https://x">F</a> <a href="\'+url+\'">G</a>',
            Joomla\HtmlUrls::absolute( $html ) );
    }


    public function testTextEmails(): void
    {
        $this->assertSame( '<p><a href="mailto:a.b+c@x-y.example.org">a.b+c@x-y.example.org</a>, <a href="mailto:me@x.de">mail</a> '
            . '<a href="/x">me@x.de</a><script>s@x.de</script> @x.de a@b x@y.c</p>',
            Joomla\Emails::emails( '<p>a.b+c@x-y.example.org, <a href="mailto:me@x.de">mail</a> <a href="/x">me@x.de</a><script>s@x.de</script> @x.de a@b x@y.c</p>' ) );
        $this->assertSame( '<A HREF="/">t@x.de</A> <a href="mailto:u@x.de">u@x.de</a>', Joomla\Emails::emails( '<A HREF="/">t@x.de</A> u@x.de' ) );
        // like Joomla, addresses can contain letters of all languages, apostrophes and digits in the top level domain
        $this->assertSame( '<p><a href="mailto:jörg@müller.de">jörg@müller.de</a> <a href="mailto:o\'brien@x.de">o\'brien@x.de</a> <a href="mailto:a@x.c0m">a@x.c0m</a></p>',
            Joomla\Emails::emails( '<p>jörg@müller.de o\'brien@x.de a@x.c0m</p>' ) );
        // like Joomla, underscores aren't part of the addresses
        $this->assertSame( 'a_<a href="mailto:b@x.de">b@x.de</a>', Joomla\Emails::emails( 'a_b@x.de' ) );

        // if the addresses are shown as text, the links to them are replaced by their link text like Joomla does
        $this->assertSame( '<p>a@x.de Mail me Write <span class="m">Us</span> <strong>Us</strong> <img src="m.png"> <img src="m.png">Mail</p>',
            Joomla\Emails::unlinkEmails( '<p>a@x.de <A class="m" HREF="mailto:a@x.de?subject=Hi">Mail</A> <a href = "mailto:a@x.de">me</a> <a title="x" href="mailto:a@x.de">Write</a> '
                . '<a href="mailto:a@x.de"><span class="m">Us</span></a> <a href="mailto:a@x.de"><strong>Us</strong></a> <a href="mailto:a@x.de"><img src="m.png"></a> '
                . '<a href="mailto:a@x.de"><img src="m.png">Mail</a></p>' ) );
        // links with other link texts, single quotes, encoded addresses or no addresses are kept
        $html = '<p><a href="mailto:a@x.de"><b>Mail</b></a> <a href=\'mailto:a@x.de\'>Mail</a> <a href="mailto:b%40x.de">Mail</a> <a href="/x">c@x.de</a> '
            . '<a href="mailto:me&amp;you">me &amp; you</a> <a href="mailto:a@x.de">M</a></p>';
        $this->assertSame( $html, Joomla\Emails::unlinkEmails( $html ) );

        // like Joomla, link texts starting with any character except controls are replaced, e.g. other scripts, symbols or the no-break space
        $this->assertSame( '<p>Почта Ελλάδα メール € x</p>',
            Joomla\Emails::unlinkEmails( '<p><a href="mailto:a@x.de">Почта</a> <a href="mailto:a@x.de">Ελλάδα</a> <a href="mailto:a@x.de">メール</a> <a href="mailto:a@x.de">€ x</a></p>' ) );
        $this->assertSame( "<p>\u{A0}x</p>", Joomla\Emails::unlinkEmails( "<p><a href=\"mailto:a@x.de\">\u{A0}x</a></p>" ) );
        // texts starting with a "<" which isn't a tag are searched for addresses too
        $this->assertSame( '<p>a</p>< <a href="mailto:b@x.de">b@x.de</a>', Joomla\Emails::emails( '<p>a</p>< b@x.de' ) );
        // stray "<" don't hide the following tags, so links aren't nested
        $this->assertSame( 'a < b <a href="/x">in@x.com</a> <a href="mailto:c@x.com">c@x.com</a>', Joomla\Emails::emails( 'a < b <a href="/x">in@x.com</a> c@x.com' ) );
        $this->assertSame( 'a <3 <a href="mailto:b@x.com">b@x.com</a> <=> <a href="mailto:c@x.com">c@x.com</a>', Joomla\Emails::emails( 'a <3 b@x.com <=> c@x.com' ) );
        // the content of scripts and styles is raw text, "<" in it doesn't start a tag and the closing tag ends it
        $this->assertSame( '<script>if(a<b){x="s@x.com"}</script> <a href="mailto:t@x.com">t@x.com</a>', Joomla\Emails::emails( '<script>if(a<b){x="s@x.com"}</script> t@x.com' ) );
        $this->assertSame( '<style>a</b{}</style><SCRIPT type="x">a<b</SCRIPT > <a href="mailto:t@x.com">t@x.com</a>', Joomla\Emails::emails( '<style>a</b{}</style><SCRIPT type="x">a<b</SCRIPT > t@x.com' ) );
        // comments aren't searched and unclosed scripts hide the rest like in browsers
        $this->assertSame( '<!-- <a> s@x.com --> <a href="mailto:t@x.com">t@x.com</a>', Joomla\Emails::emails( '<!-- <a> s@x.com --> t@x.com' ) );
        $this->assertSame( 'x <script>a<b t@x.com', Joomla\Emails::emails( 'x <script>a<b t@x.com' ) );
        // quotes in unquoted attribute values don't start quoted values like in browsers, quoted values may contain ">"
        $this->assertSame( '<p title=it\'s>mail <a href="mailto:a@example.com">a@example.com</a></p><p>Don\'t <a href="mailto:b@example.com">b@example.com</a></p>',
            Joomla\Emails::emails( '<p title=it\'s>mail a@example.com</p><p>Don\'t b@example.com</p>' ) );
        $this->assertSame( '<a href=/x title=it\'s>a@x.com</a> <p data-x = \'a>b\'><a href="mailto:b@x.com">b@x.com</a></p>',
            Joomla\Emails::emails( '<a href=/x title=it\'s>a@x.com</a> <p data-x = \'a>b\'>b@x.com</p>' ) );
        // invalid UTF-8 doesn't erase the text, the ASCII addresses and link texts are still converted
        $this->assertSame( "<p>Caf\xE9 <a href=\"mailto:info@example.com\">info@example.com</a> text</p>", Joomla\Emails::emails( "<p>Caf\xE9 info@example.com text</p>" ) );
        $this->assertSame( "<p>Caf\xE9 Mail</p>", Joomla\Emails::unlinkEmails( "<p>Caf\xE9 <a href=\"mailto:a@b.de\">Mail</a></p>" ) );
    }


    public function testUrlFieldsOfTheDomainAreInternal(): void
    {
        $field = (object) ['id' => 1, 'title' => 'Link', 'type' => 'url', 'params' => '{"showlabel":"0"}', 'fieldparams' => '{}'];
        $values = [1 => ['https://example.com/page']];

        // like the links in the texts, URLs of the domain the pages are imported for are internal
        $this->assertSame( '<p><a href="https://example.com/page">Visit Link</a></p>', $this->fields( [$field], new Joomla\Origin( 'example.com', 'https://files.test/' ) )->field( 1, new Joomla\FieldContext( $values ) ) );
        $this->assertStringContainsString( 'target="_blank">Visit Website</a>', $this->fields( [$field], new Joomla\Origin( fileBase: 'https://files.test/' ) )->field( 1, new Joomla\FieldContext( $values ) ) );
    }


    public function testFieldValuesOfJoomla3AndDefaultValues(): void
    {
        $field = (object) ['id' => 1, 'title' => 'Link', 'type' => 'url', 'params' => '{"showlabel":"0"}', 'fieldparams' => '{}'];
        $size = (object) ['id' => 2, 'title' => 'Size', 'type' => 'text', 'params' => '{"showlabel":"0"}', 'fieldparams' => '{}', 'default_value' => 'M'];
        $values = [1 => ['https://example.org/']];

        // Joomla 3 always shows the URL as link text
        $this->setJoomlaVersion( 3 );
        $this->assertStringContainsString( '>https://example.org/</a>', $this->fields( [$field] )->field( 1, new Joomla\FieldContext( $values ) ) );
        $this->setJoomlaVersion( null );
        $this->assertStringContainsString( '>Visit Website</a>', $this->fields( [$field] )->field( 1, new Joomla\FieldContext( $values ) ) );

        // only missing or empty values use the default value, values with spaces show nothing
        $fields = $this->fields( [$size] );
        $this->assertSame( '<p>M</p>', $fields->field( 2, new Joomla\FieldContext( [2 => []] ) ) );
        $this->assertSame( '<p>M</p>', $fields->field( 2, new Joomla\FieldContext( [2 => ['']] ) ) );
        $this->assertSame( '', $fields->field( 2, new Joomla\FieldContext( [2 => [' ']] ) ) );
        $this->assertSame( '', $fields->field( 2, new Joomla\FieldContext( [2 => ['', ' ']] ) ) );

        // fields without type have no field plugin which renders them
        unset( $size->type );
        $this->assertSame( '', $this->fields( [$size] )->field( 2, new Joomla\FieldContext( [2 => ['L']] ) ) );
    }


    public function testRendering(): void
    {
        $this->extensionsTable();
        DB::connection( 'joomla' )->table( 'extensions' )->insert( ['type' => 'plugin', 'folder' => 'content', 'element' => 'pagebreak', 'params' => '{}'] );
        $content = $this->importRun( $this->site( collect() ) )->content;
        $html = '<p>A</p><hr class="system-pagebreak" title="Part 2"><p>B</p>';
        $text = fn( Joomla\Rendering $rendering ) => (string) json_encode( $content->content( $html, $rendering ) );

        $article = Joomla\Rendering::article( [1 => ['A']], 'en-GB', 5 );
        $category = Joomla\Rendering::category( [2 => ['C']], 'de-DE', 6 );

        // only the page breaks of articles become headings, the ones of category descriptions are kept as rules
        $this->assertStringContainsString( '"title":"Part 2"', $text( $article ) );
        $this->assertStringContainsString( 'system-pagebreak', $text( $category ) );
        $this->assertStringContainsString( '"title":"Part 2"', $text( $category->embedded() ) );
        // teasers keep their page breaks because the "Content - Page Break" plugin doesn't prepare the intro text
        $this->assertStringContainsString( 'system-pagebreak', $text( $article->teaser() ) );
        $this->assertStringNotContainsString( '"title":"Part 2"', $text( $article->teaser() ) );

        // the field values of articles and category descriptions are the ones of their own component
        $this->assertSame( [Joomla\Fields::ARTICLE_CONTEXT, Joomla\Fields::CATEGORY_CONTEXT], [$article->componentContext(), $category->componentContext()] );

        // embedded content is prepared like an article without field values in the language and menu item of the page
        $embedded = $category->embedded();
        $this->assertSame( [Joomla\Fields::ARTICLE_CONTEXT, [], 'de-DE', 6], [$embedded->componentContext(), $embedded->values, $embedded->language, $embedded->itemId] );
        // embedding an article keeps its language and menu item but drops its field values
        $this->assertSame( [[], 'en-GB', 5], [$article->embedded()->values, $article->embedded()->language, $article->embedded()->itemId] );
    }


    public function testContentFetchesTheTagsAndFieldValuesOfOtherArticlesOnFirstUse(): void
    {
        $this->extraTables();
        $db = DB::connection( 'joomla' );
        $db->table( 'fields' )->insert( ['id' => 1, 'context' => 'com_content.article', 'title' => 'Size', 'label' => 'Size', 'type' => 'text', 'params' => '{}',
            'fieldparams' => '{}', 'state' => 1, 'ordering' => 1, 'access' => 1, 'language' => '*', 'group_id' => 0] );
        $db->table( 'fields_values' )->insert( [['field_id' => 1, 'item_id' => '1', 'value' => 'S'], ['field_id' => 1, 'item_id' => '3', 'value' => 'L']] );
        $db->table( 'tags' )->insert( [
            ['id' => 2, 'title' => 'Small', 'published' => 1, 'access' => 1, 'lft' => 1],
            ['id' => 3, 'title' => 'Large', 'published' => 1, 'access' => 1, 'lft' => 2],
        ] );
        $db->table( 'contentitem_tag_map' )->insert( [
            ['type_alias' => 'com_content.article', 'content_item_id' => 1, 'tag_id' => 2],
            ['type_alias' => 'com_content.article', 'content_item_id' => 3, 'tag_id' => 3],
        ] );

        $reader = $this->reader( $db );
        $categories = $reader->categories();
        $run = $this->importRun( $this->site( $categories ) );
        $extras = $run->extras;
        $content = $run->content;
        $article = fn( int $id ) => (object) ['id' => $id, 'catid' => 0, 'language' => '*'];
        $tags = fn( int $id ) => (string) json_encode( $content->articleTags( $article( $id ), '*' ) );
        $count = function( \Closure $fn ) use ( $db ) {
            $db->flushQueryLog();
            $db->enableQueryLog();
            $fn();
            $db->disableQueryLog();
            return count( $db->getQueryLog() );
        };

        // the tables, fields and tag options are checked once
        $content->articleRendering( $article( 0 ), '*', 0 )->values;
        $tags( 3 );
        $extras->prefetch( [1, 2] );

        // the tags and values of the prefetched articles, also of the ones without tags and values, aren't fetched again
        $this->assertSame( 0, $count( function() use ( $content, $article, $tags ) {
            $this->assertStringContainsString( 'Small', $tags( 1 ) );
            $this->assertSame( 'null', $tags( 2 ) );
            $this->assertSame( [1 => ['S']], $content->articleRendering( $article( 1 ), '*', 0 )->values );
            $this->assertSame( [1 => []], $content->articleRendering( $article( 2 ), '*', 0 )->values );
        } ) );

        // the ones of other articles are fetched on first use and kept
        $this->assertSame( 2, $count( fn() => $this->assertStringContainsString( 'Large', $tags( 3 ) ) ) );
        $this->assertSame( 0, $count( fn() => $this->assertSame( [1 => ['L']], $content->articleRendering( $article( 3 ), '*', 0 )->values ) ) );
        $this->assertSame( 0, $count( fn() => $this->assertStringContainsString( 'Small', $tags( 1 ) ) ) );

        // prefetching the next articles discards the ones fetched before
        $extras->prefetch( [3] );
        $this->assertSame( 0, $count( fn() => $tags( 3 ) ) );
        $db->table( 'fields_values' )->where( 'item_id', '1' )->update( ['value' => 'M'] );
        $this->assertSame( 2, $count( fn() => $this->assertSame( [1 => ['M']], $content->articleRendering( $article( 1 ), '*', 0 )->values ) ) );

        // like Joomla, the content plugins don't change the links below the text
        $links = (string) json_encode( $content->linkContent( ['urla' => 'https://example.org/', 'urlatext' => 'me@example.org {field 1}'], [], '*' ) );
        $this->assertStringContainsString( 'me@example.org {field 1}', $links );
        $this->assertStringNotContainsString( 'mailto', $links );

        // values of unknown fields, e.g. unpublished ones, are skipped
        $this->assertNull( $content->fieldContent( Joomla\Fields::AFTER_CONTENT, Joomla\Rendering::article( [9 => ['X']], '*', 0 ) ) );
    }


    public function testExtrasDontFetchFieldValuesWithoutArticleFields(): void
    {
        $this->extraTables();
        $db = DB::connection( 'joomla' );
        $db->table( 'fields' )->insert( ['id' => 1, 'context' => 'com_content.categories', 'title' => 'Size', 'label' => 'Size', 'type' => 'text', 'params' => '{}',
            'fieldparams' => '{}', 'state' => 1, 'ordering' => 1, 'access' => 1, 'language' => '*', 'group_id' => 0] );
        $db->table( 'fields_values' )->insert( ['field_id' => 1, 'item_id' => '1', 'value' => 'XL'] );

        $reader = $this->reader( $db );
        $categories = $reader->categories();
        $extras = $this->importRun( $this->site( $categories ) )->extras;

        // the fields of the articles are checked once
        $extras->prefetch( [5] );

        $db->enableQueryLog();
        $extras->prefetch( [1, 2] );
        $this->assertSame( [], $extras->articleValues( 1 ) );
        $this->assertSame( [], $extras->articleTags( 2 ) );
        $queries = array_column( $db->getQueryLog(), 'query' );
        $db->disableQueryLog();

        // only the tags are fetched
        $this->assertCount( 1, $queries );
        $this->assertStringNotContainsString( 'fields_values', $queries[0] );

        // the values of article fields Joomla doesn't show aren't fetched either
        $db->table( 'fields' )->update( ['context' => 'com_content.article', 'access' => 5] );
        $db->table( 'fields_values' )->insert( ['field_id' => 1, 'item_id' => '3', 'value' => 'S'] );
        $extras = $this->importRun( $this->site( $categories ) )->extras;
        $this->assertSame( [], $extras->articleValues( 3 ) );
    }


    public function testExtrasFetchTheTagsAndFieldValuesOfTheCategoriesOnce(): void
    {
        $this->extraTables();
        $db = DB::connection( 'joomla' );
        $db->table( 'categories' )->insert( ['id' => 2, 'extension' => 'com_content', 'title' => 'News', 'alias' => 'news', 'parent_id' => 1, 'level' => 1, 'lft' => 1] );
        $db->table( 'fields' )->insert( [
            ['id' => 1, 'context' => 'com_content.categories', 'title' => 'Size', 'label' => 'Size', 'type' => 'text', 'params' => '{}',
                'fieldparams' => '{}', 'state' => 1, 'ordering' => 1, 'access' => 1, 'language' => '*', 'group_id' => 0],
            ['id' => 3, 'context' => 'com_content.article', 'title' => 'Color', 'label' => 'Color', 'type' => 'text', 'params' => '{}',
                'fieldparams' => '{}', 'state' => 1, 'ordering' => 2, 'access' => 1, 'language' => '*', 'group_id' => 0],
        ] );
        // the article with the same ID as the category has values too
        $db->table( 'fields_values' )->insert( [['field_id' => 1, 'item_id' => '2', 'value' => 'XL'], ['field_id' => 3, 'item_id' => '2', 'value' => 'Red']] );
        $db->table( 'fields_categories' )->insert( ['field_id' => 1, 'category_id' => 2] );
        $db->table( 'tags' )->insert( ['id' => 2, 'title' => 'Hot', 'published' => 1, 'access' => 1, 'lft' => 1] );
        $db->table( 'contentitem_tag_map' )->insert( ['type_alias' => 'com_content.category', 'content_item_id' => 2, 'tag_id' => 2] );

        $reader = $this->reader( $db );
        $categories = $reader->categories();
        $extras = $this->importRun( $this->site( $categories ) )->extras;

        $this->assertSame( [1 => ['XL']], $extras->categoryValues( 2 ) );
        $this->assertSame( [3 => ['Red']], $extras->articleValues( 2 ) );
        $this->assertSame( ['Hot'], array_column( $extras->categoryTags( 2 ), 'title' ) );

        // the values and tags of all categories are fetched on first use only
        $db->enableQueryLog();
        $this->assertSame( [], $extras->categoryValues( 3 ) );
        $this->assertSame( [], $extras->categoryTags( 3 ) );
        $this->assertSame( [], $db->getQueryLog() );

        // without custom fields for categories, their values aren't fetched
        $db->table( 'fields' )->where( 'context', 'com_content.categories' )->delete();
        $extras = $this->importRun( $this->site( $categories ) )->extras;
        $db->flushQueryLog();
        $this->assertSame( [], $extras->categoryValues( 2 ) );
        $this->assertSame( [], array_filter( $db->getQueryLog(), fn( $query ) => str_contains( $query['query'], 'fields_values' ) ) );
        $db->disableQueryLog();
    }


    public function testModulesReplaceShortcodesByCustomModules(): void
    {
        $this->extraTables();
        $db = DB::connection( 'joomla' );
        $db->table( 'modules' )->insert( [
            ['id' => 1, 'title' => 'Custom', 'module' => 'mod_custom', 'position' => 'top', 'content' => '<p>Custom</p>', 'showtitle' => 1, 'ordering' => 1, 'params' => '{}'],
            ['id' => 2, 'title' => 'Menu', 'module' => 'mod_menu', 'position' => 'top', 'content' => '', 'showtitle' => 1, 'ordering' => 2, 'params' => '{}'],
            ['id' => 3, 'title' => 'Raw', 'module' => 'mod_custom', 'position' => 'raw', 'content' => '<p>Raw</p>', 'showtitle' => 1, 'ordering' => 1, 'params' => '{"prepare_content":"0"}'],
            ['id' => 4, 'title' => 'Plain', 'module' => 'mod_custom', 'position' => 'plain', 'content' => '<p>Plain</p>', 'showtitle' => 1, 'ordering' => 1, 'params' => '{"style":"html5","prepare_content":false}'],
            ['id' => 5, 'title' => 'None', 'module' => 'mod_custom', 'position' => 'plain', 'content' => '<p>None</p>', 'showtitle' => 1, 'ordering' => 2, 'params' => '{"style":"System-none","prepare_content":false}'],
        ] );
        $reader = $this->reader( $db );
        $categories = $reader->categories();
        $site = $this->site( $categories );
        $warnings = [];
        $modules = new Joomla\Modules( $site, $reader, function( string $msg ) use ( &$warnings ) { $warnings[] = $msg; } );
        $prepared = [];
        $prepare = function( string $html ) use ( &$prepared ) { $prepared[] = $html; return strtoupper( $html ); };

        // custom modules are prepared and shown in the style of the shortcode, other modules are removed with a warning
        $this->assertSame( '<P>CUSTOM</P>', $modules->shortcode( new Joomla\Shortcode( 'loadposition', ['position' => 'top', 'style' => 'none'], 'top,none' ), Joomla\Rendering::article( [], '*', 0 ), $prepare ) );
        $this->assertSame( '<h3>Custom</h3><P>CUSTOM</P>', $modules->shortcode( new Joomla\Shortcode( 'loadmoduleid', ['id' => '1', 'style' => 'xhtml'], '1,xhtml' ), Joomla\Rendering::article( [], '*', 0 ), $prepare ) );
        // without style, the modules are shown in the "none" style
        $this->assertSame( '<P>CUSTOM</P>', $modules->shortcode( new Joomla\Shortcode( 'loadmoduleid', ['id' => '1'], '1' ), Joomla\Rendering::article( [], '*', 0 ), $prepare ) );
        // the removed modules of the same shortcode in several texts are reported by the same message, which the command shows once
        $this->assertSame( '<P>CUSTOM</P>', $modules->shortcode( new Joomla\Shortcode( 'loadposition', ['position' => 'top', 'style' => 'none'], 'top,none' ), Joomla\Rendering::article( [], '*', 0 ), $prepare ) );
        array_pop( $prepared );
        $this->assertSame( ['  Removed module [2] Menu of shortcode {loadposition top,none} because only custom modules are supported'], array_values( array_unique( $warnings ) ) );
        $this->assertSame( ['<p>Custom</p>', '<p>Custom</p>', '<p>Custom</p>'], $prepared );
        // modules not preparing their content aren't passed to the content plugins
        $this->assertSame( '<p>Raw</p>', $modules->shortcode( new Joomla\Shortcode( 'loadmodule', ['type' => 'custom', 'title' => 'Raw', 'style' => 'none'], 'custom,Raw,none' ), Joomla\Rendering::article( [], '*', 0 ), $prepare ) );
        $this->assertSame( '', $modules->shortcode( new Joomla\Shortcode( 'loadmodule', ['type' => 'mod_custom', 'title' => 'Other', 'style' => 'none'], 'mod_custom,Other,none' ), Joomla\Rendering::article( [], '*', 0 ), $prepare ) );
        $this->assertCount( 3, $prepared );
        // like Joomla, module styles without template name are kept and show the title, JSON false doesn't prepare the content
        $this->assertSame( '<h3>Plain</h3><p>Plain</p><p>None</p>', $modules->shortcode( new Joomla\Shortcode( 'loadposition', ['position' => 'plain', 'style' => 'none'], 'plain,none' ), Joomla\Rendering::article( [], '*', 0 ), $prepare ) );
        $this->assertCount( 3, $prepared );

        // shortcodes loading the module whose content is replaced at the moment are kept and the modules are fetched once
        $db->enableQueryLog();
        $inner = 'unset';
        $this->assertSame( "<p>Custom</p>|NULL", $modules->shortcode( new Joomla\Shortcode( 'loadposition', ['position' => 'top', 'style' => 'none'], 'top,none' ), Joomla\Rendering::article( [], '*', 0 ), function( $html ) use ( $modules, &$inner ) {
            $inner = $modules->shortcode( new Joomla\Shortcode( 'loadposition', ['position' => 'top', 'style' => 'none'], 'top,none' ), Joomla\Rendering::article( [], '*', 0 ), fn( $html ) => $html );
            return $html;
        } ) . '|' . var_export( $inner, true ) );
        $this->assertSame( [], $db->getQueryLog() );
        $db->disableQueryLog();

        // a failing module doesn't keep its shortcode marked as loading
        try {
            $modules->shortcode( new Joomla\Shortcode( 'loadposition', ['position' => 'top', 'style' => 'none'], 'top,none' ), Joomla\Rendering::article( [], '*', 0 ), fn( $html ) => throw new \RuntimeException( 'failed' ) );
            $this->fail( 'Exception expected' );
        } catch( \RuntimeException $e ) {
            $this->assertSame( 'failed', $e->getMessage() );
        }
        $this->assertSame( '<p>Custom</p>', $modules->shortcode( new Joomla\Shortcode( 'loadposition', ['position' => 'top', 'style' => 'none'], 'top,none' ), Joomla\Rendering::article( [], '*', 0 ), fn( $html ) => $html ) );

        // other shortcodes with the same arguments aren't the module replaced at the moment
        $inner = 'unset';
        $modules->shortcode( new Joomla\Shortcode( 'loadposition', ['position' => 'top', 'style' => 'none'], 'top,none' ), Joomla\Rendering::article( [], '*', 0 ), function( $html ) use ( $modules, &$inner ) {
            $inner = $modules->shortcode( new Joomla\Shortcode( 'loadmodule', ['type' => 'top', 'style' => 'none'], 'top,none' ), Joomla\Rendering::article( [], '*', 0 ), fn( $html ) => $html );
            return $html;
        } );
        $this->assertSame( '', $inner );
    }


    public function testModulesFetchUnknownMenuAssignmentsOnce(): void
    {
        $this->extraTables();
        $db = DB::connection( 'joomla' );
        $db->table( 'modules' )->insert( ['id' => 1, 'title' => 'Custom', 'module' => 'mod_custom', 'position' => 'top', 'content' => '<p>Custom</p>',
            'showtitle' => 0, 'ordering' => 1, 'params' => '{}'] );
        $reader = new class( $db, '', fn() => null ) extends Joomla\Reader {
            public int $calls = 0;

            public function moduleMenus(): ?array
            {
                $this->calls++;
                return null;
            }
        };
        $site = $this->site( $reader->categories(), reader: $reader );
        $modules = new Joomla\Modules( $site, $reader, fn( string $msg ) => null );

        // without menu assignments, the modules are shown on all pages and the unknown assignments aren't fetched again
        $this->assertSame( '<p>Custom</p>', $modules->shortcode( new Joomla\Shortcode( 'loadposition', ['position' => 'top'], 'top' ), Joomla\Rendering::article( [], '*', 5 ), fn( $html ) => $html ) );
        $this->assertSame( '<p>Custom</p>', $modules->shortcode( new Joomla\Shortcode( 'loadposition', ['position' => 'top'], 'top' ), Joomla\Rendering::article( [], '*', 6 ), fn( $html ) => $html ) );
        $this->assertSame( 1, $reader->calls );
    }


    public function testMarkupListsOnlyContainItems(): void
    {
        $this->assertSame( '', Joomla\Markup::list( [], 'links' ) );
        $this->assertSame( '<ul class="tags"><li>A</li><li>B</li></ul>', Joomla\Markup::list( ['A', 'B'], 'tags' ) );
    }


    public function testLinksAreShownAboveOrBelowTheText(): void
    {
        $positions = function( array $urls, array $options = [] ) {
            $links = Joomla\LinkPositions::of( $urls, $options );
            return [$links->above, $links->below, $links->shown()];
        };

        // like Joomla, the links are shown above the text by default
        $this->assertSame( [true, false, true], $positions( [] ) );
        $this->assertSame( [true, false, true], $positions( ['urls_position' => '0'], ['urls_position' => '0'] ) );
        // the article or the options of the page show them below the text, which excludes above
        $this->assertSame( [false, true, true], $positions( ['urls_position' => '1'] ) );
        $this->assertSame( [false, true, true], $positions( ['urls_position' => '0'], ['urls_position' => '1'] ) );
        // other positions hide them
        $this->assertSame( [false, false, false], $positions( ['urls_position' => '-1'] ) );
    }


    public function testEnabledOptionsLikeJoomla(): void
    {
        // "0", 0 and false disable options, NULL, empty strings and missing options use the default
        foreach( ['0', 0, false, ' 0 '] as $value ) {
            $this->assertFalse( Joomla\Value::enabled( ['show' => $value], 'show' ) );
        }
        foreach( [null, '', ' '] as $value ) {
            $this->assertTrue( Joomla\Value::enabled( ['show' => $value], 'show' ) );
            $this->assertFalse( Joomla\Value::enabled( ['show' => $value], 'show', false ) );
        }
        foreach( ['1', 1, true, 'yes'] as $value ) {
            $this->assertTrue( Joomla\Value::enabled( ['show' => $value], 'show', false ) );
        }
    }


    public function testIframesInFiguresAndParagraphsAreEmbedded(): void
    {
        $warnings = [];
        $html = new Joomla\Html( fn( $tag ) => 'F1', function( string $msg ) use ( &$warnings ) { $warnings[] = $msg; } );
        $texts = fn( string $text ) => array_map( fn( $element ) => [$element['type'], $element['data']['text'] ?? $element['data']['file']['id'] ?? ''], $html->elements( $text )['elements'] );
        $iframe = '<iframe src="https://www.youtube.com/embed/x" width="560" height="315" loading="lazy" allow="fullscreen; picture-in-picture" allowfullscreen></iframe>';

        // iframes in figures without images become embeds followed by their captions
        $this->assertSame( [['html', $iframe], ['html', '<p>Video</p>']], $texts( '<figure class="media"><iframe src="https://www.youtube.com/embed/x" width="560" height="315"></iframe><figcaption>Video</figcaption></figure>' ) );
        $this->assertSame( [['html', $iframe]], $texts( '<figure><div class="wrap"><iframe src="https://www.youtube.com/embed/x" width="560" height="315"></iframe></div></figure>' ) );
        // iframes wrapped in paragraphs or divs by the editor are embedded too
        $this->assertSame( [['html', '<p>A</p>'], ['html', $iframe], ['html', '<p>B</p>']], $texts( '<p>A</p><p><iframe src="https://www.youtube.com/embed/x" width="560" height="315"></iframe></p><p>B</p>' ) );
        $this->assertSame( [['html', $iframe]], $texts( '<div><iframe src="https://www.youtube.com/embed/x" width="560" height="315"></iframe></div>' ) );
        // figures with images are still images with their captions
        $this->assertSame( [['image-text', 'Photo']], $texts( '<figure><img src="a.jpg"><figcaption>Photo</figcaption></figure>' ) );
        // iframes of figures with images are kept next to the images, the caption becomes a paragraph after them
        $this->assertSame( [['image', 'F1'], ['html', $iframe], ['html', '<p>Cap</p>']],
            $texts( '<figure><img src="a.jpg"><iframe src="https://www.youtube.com/embed/x" width="560" height="315"></iframe><figcaption>Cap</figcaption></figure>' ) );
        // the captions of nested figures are paragraphs after the iframes
        $this->assertSame( [['html', $iframe], ['html', '<p>In</p>'], ['html', '<p>Out</p>']],
            $texts( '<figure class="outer"><figure><iframe src="https://www.youtube.com/embed/x" width="560" height="315"></iframe><figcaption>In</figcaption></figure><figcaption>Out</figcaption></figure>' ) );
        // media custom fields rendered as list keep the list intact, the image follows it
        $this->assertSame( [['html', '<ul class="fields"><li><strong>Author: </strong>Ann</li><li><strong>Photo: </strong></li><li><strong>Year: </strong>2020</li></ul>'], ['image', 'F1']],
            $texts( '<ul class="fields"><li><strong>Author: </strong>Ann</li><li><strong>Photo: </strong><img src="images/a.jpg"></li><li><strong>Year: </strong>2020</li></ul>' ) );
        $this->assertSame( [], $warnings );

        // iframes without web URLs are removed with a warning
        $this->assertSame( [['html', '<p>Map</p>']], $texts( '<figure><iframe src="javascript:alert(1)"></iframe><figcaption>Map</figcaption></figure>' ) );
        $this->assertSame( ['Iframe without web URL removed: "javascript:alert(1)"'], $warnings );
        // without warning closure, they are removed silently
        $this->assertSame( [], ( new Joomla\Html( fn( $tag ) => null ) )->elements( '<iframe src="ftp://example.com/x"></iframe>' )['elements'] );

        // the base converter has no media blocks, so the iframe is removed by the sanitizer but the caption is still a paragraph
        $this->assertSame( '<p>Video</p>', ( new Html( fn( $tag ) => null ) )->elements( '<figure><iframe src="https://www.youtube.com/embed/x"></iframe><figcaption>Video</figcaption></figure>' )['elements'][0]['data']['text'] );
    }


    public function testListsAndTablesStayIntact(): void
    {
        $html = new Joomla\Html( fn( $tag ) => 'F1' );
        $texts = fn( string $text ) => array_map( fn( $element ) => [$element['type'], $element['data']['text'] ?? $element['data']['file']['id'] ?? ''], $html->elements( $text )['elements'] );
        $iframe = '<iframe src="https://www.youtube.com/embed/x" loading="lazy" allow="fullscreen; picture-in-picture" allowfullscreen></iframe>';

        // headings and preformatted text are part of the table or list
        $this->assertSame( [['html', '<table><tr><td><h3>T</h3>x</td><td>y</td></tr></table>']], $texts( '<table><tr><td><h3>T</h3>x</td><td>y</td></tr></table>' ) );
        $this->assertSame( [['html', '<ul><li><pre>x</pre></li></ul>']], $texts( '<ul><li><pre>x</pre></li></ul>' ) );
        $this->assertSame( '<ul><li><h2>A</h2></li><li>B</li></ul>', ( new Html( fn( $tag ) => null ) )->elements( '<ul><li><h2>A</h2></li><li>B</li></ul>' )['elements'][0]['data']['text'] ?? '' );
        // media and figures with images are moved after the table or list, the other cells and items are kept
        $this->assertSame( [['html', '<table><tr><td></td><td>Text</td></tr></table>'], ['html', $iframe]],
            $texts( '<table><tr><td><iframe src="https://www.youtube.com/embed/x"></iframe></td><td>Text</td></tr></table>' ) );
        $this->assertSame( [['html', '<table><tr><td></td><td>T</td></tr></table>'], ['html', $iframe], ['html', '<p>V</p>']],
            $texts( '<table><tr><td><figure><iframe src="https://www.youtube.com/embed/x"></iframe><figcaption>V</figcaption></figure></td><td>T</td></tr></table>' ) );
        $this->assertSame( [['html', '<ul><li></li><li>B</li></ul>'], ['image-text', 'C']], $texts( '<ul><li><figure><img src="a.jpg"><figcaption>C</figcaption></figure></li><li>B</li></ul>' ) );
        // text starting with an unclosed heading is split at images like other text
        $this->assertSame( [['html', '<h3>Unclosed</h3>'], ['image', 'F1'], ['html', 'text']], $texts( '<h3>Unclosed <img src="a.jpg"> text' ) );
    }


    public function testUnclosedListsAndFiguresAreSplitInLinearTime(): void
    {
        $html = new Joomla\Html( fn( $tag ) => 'F1' );
        $split = new \ReflectionMethod( Html::class, 'splitIntoBlocks' );

        foreach( ['figure', 'ul', 'table', 'h3'] as $tag )
        {
            $text = str_repeat( '<' . $tag . '><p>' . str_repeat( '<b>a</b>', 50 ) . "</p>\n", 2000 );
            $start = microtime( true );
            $blocks = $split->invoke( $html, $text );

            $this->assertLessThan( 1, microtime( true ) - $start, $tag );
            $this->assertSame( strlen( trim( $text ) ), strlen( implode( '', $blocks ) ), $tag );
        }

        // nested and unclosed lists are matched including the lists nested in them
        $fragments = new \ReflectionMethod( Html::class, 'fragments' );
        $groups = ['container' => ['ul|ol|dl|table', true]];
        $this->assertSame( [[0, 32, 'container']], $fragments->invoke( $html, '<ul><li><ol><li>a</li></ol></ul>', $groups ) );
        $this->assertSame( [[5, 10, 'container'], [15, 10, 'container']], $fragments->invoke( $html, '<ul>a<ul>b</ul><ul>c</ul>', $groups ) );
    }


    public function testFailedRegularExpressionsKeepTheContent(): void
    {
        $warnings = [];
        $html = new Joomla\Html( fn( $tag ) => 'F1', function( string $msg ) use ( &$warnings ) { $warnings[] = $msg; } );
        $result = new \ReflectionMethod( Html::class, 'result' );

        // PCRE functions return NULL on errors like the backtracking limit, the input is kept instead of losing the content
        $this->assertSame( '<p>A</p>', $result->invoke( $html, null, '<p>A</p>' ) );
        $this->assertSame( ['Processing HTML failed, content kept unchanged: ' . preg_last_error_msg()], $warnings );
        $this->assertSame( '<p>B</p>', $result->invoke( $html, '<p>B</p>', '<p>A</p>' ) );
        // without warning closure, the input is kept silently
        $this->assertSame( '<p>A</p>', $result->invoke( new Html( fn( $tag ) => null ), null, '<p>A</p>' ) );
    }


    public function testEmptyIconTagsAndParagraphsAfterImagesAreKept(): void
    {
        $html = new Joomla\Html( fn( $tag ) => 'F1' );
        $texts = fn( string $text ) => array_map( fn( $element ) => [$element['type'], $element['data']['text'] ?? $element['data']['file']['id'] ?? ''], $html->elements( $text )['elements'] );

        // empty inline tags with attributes may be icons, the ones without attributes are removed
        $this->assertSame( [['html', '<p><i class="fa fa-check"></i> Done</p>']], $texts( '<p><i class="fa fa-check"></i> Done</p>' ) );
        $this->assertSame( [['html', '<p><span class="icon-star"></span> Rated</p>']], $texts( '<p><span class="icon-star"></span> Rated</p>' ) );
        $this->assertSame( [['html', '<p>a</p>']], $texts( '<p>a<strong></strong></p>' ) );
        // empty paragraphs left over from splitting at images are removed even with attributes
        $this->assertSame( [['image', 'F1'], ['html', '<p>B</p>']], $texts( '<p class="center"><img src="a.jpg"></p><p>B</p>' ) );
        // the text after inline images keeps its paragraph
        $this->assertSame( [['html', '<p>Text</p>'], ['image', 'F1'], ['html', '<p>after</p>']], $texts( '<p>Text <img src="a.jpg"> after</p>' ) );
        $this->assertSame( [['html', '<p class="x">A</p>'], ['image', 'F1'], ['html', '<p class="x">B</p>'], ['image', 'F1'], ['html', '<p class="x">C</p>']],
            $texts( '<p class="x">A <img src="a.jpg"> B <img src="b.jpg"> C</p>' ) );
        $this->assertSame( [['html', '<p>A <a href="/x">link</a></p>'], ['image', 'F1'], ['html', '<p> B</p>']], $texts( '<p>A <a href="/x">link <img src="a.jpg"></a> B</p>' ) );
        // only links are removed with the images, not other tags starting with "a"
        $this->assertSame( ['<p><abbr title="x">', '<img src="a.jpg">', '<p></a></p>'],
            ( new \ReflectionMethod( Html::class, 'splitText' ) )->invoke( $html, '<p><abbr title="x"><img src="a.jpg"></a></p>' ) );
    }


    public function testModulePrefixIsOnlyRemovedAtTheStart(): void
    {
        $modules = ( new \ReflectionClass( Joomla\Modules::class ) )->newInstanceWithoutConstructor();
        $typed = fn( string $module, string $type ) => ( new \ReflectionMethod( Joomla\Modules::class, 'typed' ) )->invoke( $modules, (object) ['module' => $module], $type );

        $this->assertTrue( $typed( 'mod_custom', 'custom' ) );
        $this->assertTrue( $typed( 'mod_custom', 'mod_custom' ) );
        $this->assertTrue( $typed( 'custom', 'custom' ) );
        $this->assertFalse( $typed( 'xmod_custom', 'custom' ) );
        $this->assertFalse( $typed( 'abcdcustom', 'custom' ) );
    }


    public function testModuleTypesAreCaseSensitiveLikeJoomla(): void
    {
        $this->extraTables();
        $db = DB::connection( 'joomla' );
        $db->table( 'modules' )->insert( ['id' => 1, 'title' => 'Note', 'module' => 'mod_custom', 'position' => 'top', 'content' => '<p>Note</p>', 'showtitle' => 0, 'ordering' => 1, 'params' => '{}'] );
        $reader = $this->reader( $db );
        $modules = new Joomla\Modules( $this->site( $reader->categories() ), $reader, fn( string $msg ) => null );
        $load = fn( string $type ) => $modules->shortcode( new Joomla\Shortcode( 'loadmodule', ['type' => $type, 'title' => '', 'style' => 'none'], $type ), Joomla\Rendering::article( [], '*', 0 ), fn( $html ) => $html );

        // Joomla's ModuleHelper::getModule() compares the types of the loaded modules case-sensitively
        $this->assertSame( ['<p>Note</p>', '<p>Note</p>'], [$load( 'custom' ), $load( 'mod_custom' )] );
        $this->assertSame( ['', '', ''], [$load( 'MOD_CUSTOM' ), $load( 'Custom' ), $load( 'mod_Custom' )] );
    }


    public function testTeasersOfArticlesArePreparedOnce(): void
    {
        $article = Joomla\Rendering::article( [], 'en-GB', 5 )->teaser();

        // teasers are prepared once like article texts but keep their page breaks, only category descriptions are prepared twice
        $this->assertSame( [false, false], [$article->preparedTwice(), $article->pageBreaks()] );
        $this->assertTrue( Joomla\Rendering::category( [], 'en-GB', 5 )->preparedTwice() );
    }


    public function testPageBreakTitlesAreEscaped(): void
    {
        // titles with entities are decoded once and escaped again, tags are escaped
        $this->assertSame( '<h2>A &amp; B &lt;b&gt;</h2>', Joomla\PageBreaks::headings( '<hr class="system-pagebreak" title="A &amp; B &lt;b&gt;">' ) );
        $this->assertSame( '<h2>&quot;Q&quot;</h2>', Joomla\PageBreaks::headings( '<hr class="system-pagebreak" alt="&quot;Q&quot;">' ) );
        // the pages without title are numbered from 2 like in Joomla
        $this->assertSame( '<h2>Page 2</h2><h2>Page 3</h2>', Joomla\PageBreaks::headings( '<hr class="system-pagebreak"><hr class="system-pagebreak">', 'en' ) );
    }


    public function testFieldsAreReturnedByIdAndRenderableByType(): void
    {
        $field = (object) ['id' => 3, 'type' => 'text', 'name' => 'color', 'params' => '{}', 'fieldparams' => '{}'];
        $fields = $this->fields( [$field] );

        $this->assertSame( $field, $fields->get( 3 ) );
        $this->assertSame( $field, $fields->get( '3' ) );
        $this->assertNull( $fields->get( 4 ) );
        $this->assertTrue( Joomla\Fields::renderable( $field ) );
        $this->assertSame( 'text', Joomla\Fields::type( $field ) );
        $this->assertFalse( Joomla\Fields::renderable( (object) ['type' => 'subform'] ) );
        $this->assertFalse( Joomla\Fields::renderable( (object) ['type' => 'repeatable'] ) );
        $this->assertFalse( Joomla\Fields::renderable( (object) [] ) );
        $this->assertSame( '', Joomla\Fields::type( (object) [] ) );
    }


    public function testImageSourcesAreTheOnesOfTheImageTagsOnly(): void
    {
        $sources = [];
        $html = new Html( function( string $tag ) use ( &$sources ) {
            $source = Html::source( $tag );
            $sources[] = $source['src'] ?? null;
            return $source && $source['src'] !== 'images/fail.jpg' ? 'F' . count( $sources ) : null;
        } );

        // lazy load attributes of links, figures and captions around the image aren't its source
        $html->elements( '<p><a href="/big.jpg" data-src="#gallery-1" data-fancybox="g"><img src="images/a.jpg" alt="A"></a></p>' );
        $html->elements( '<figure data-original="x.jpg"><img src="images/b.jpg" alt="B"><figcaption>See <span data-src="x.gif">here</span></figcaption></figure>' );
        $html->elements( '<figure><a href="/x" data-original="images/big.jpg"><img src="images/c.jpg"></a></figure>' );
        $html->elements( '<p>Text <a href="/x" data-src="#box"><img src="images/d.jpg"></a> more</p>' );
        $this->assertSame( ['images/a.jpg', 'images/b.jpg', 'images/c.jpg', 'images/d.jpg'], $sources );

        // the lazy load attributes of the image itself are preferred
        $sources = [];
        $html->elements( '<p><img data-lazy-src="images/e.jpg" src="placeholder.gif"></p><p><a href="/x"><img data-original="images/f.jpg" src="data:image/gif;base64,R0"></a></p>' );
        $this->assertSame( ['images/e.jpg', 'images/f.jpg'], $sources );

        // further images of figures are imported, failed ones are dropped and don't lose the imported ones
        $sources = [];
        $result = $html->elements( '<figure><img src="images/g.jpg"><table><tr><td>T</td></tr></table><img src="images/fail.jpg"><a href="/x" data-src="#y"><img src="images/h.jpg"></a></figure>' );
        $this->assertSame( ['images/g.jpg', 'images/fail.jpg', 'images/h.jpg'], $sources );
        $this->assertSame( ['F1', 'F3'], array_values( $result['fileIds'] ) );
        $this->assertSame( ['image', 'html', 'image'], array_column( $result['elements'], 'type' ) );
        $this->assertStringNotContainsString( 'img', (string) json_encode( $result['elements'] ) );
    }
}
