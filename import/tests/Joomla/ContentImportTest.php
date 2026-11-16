<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests\Joomla;

use Aimeos\Cms\Import\Joomla;
use Aimeos\Cms\Models\File;
use Aimeos\Cms\Models\PageView;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;


/**
 * Command-level tests importing the content of the Joomla articles and categories: layouts, fields, modules, tags, images and page breaks.
 */
class ContentImportTest extends TestAbstract
{
    public function testImageFileNamesIgnoreTheQuery(): void
    {
        $db = DB::connection( 'joomla' );
        $db->table( 'content' )->where( 'id', 1 )->update( ['images' => '{"image_fulltext":"images/full.jpg?v=2#joomlaImage://local-images/full.jpg","image_fulltext_alt":" Photo "}',
            'fulltext' => '<p><img src="images/inline.png?v=3"></p>'] );

        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com'] ) );

        $files = File::orderBy( 'name' )->get();
        // the alternative text is used as name
        $this->assertSame( ['Photo', 'inline.png'], $files->pluck( 'name' )->all() );
        $this->assertSame( ['image/jpeg', 'image/png'], $files->pluck( 'mime' )->all() );
    }


    public function testFetchesImageFilesAtOnce(): void
    {
        $db = DB::connection( 'joomla' );
        $db->table( 'content' )->where( 'id', 1 )->update( ['images' => '{"image_intro":"images/intro.jpg","image_fulltext":"images/full.jpg"}', 'fulltext' => '<p><img src="images/inline.png" alt="Inline"></p>'] );
        $db->table( 'content' )->where( 'id', 4 )->update( ['images' => '{"image_intro":"images/intro.jpg","image_fulltext":"images/full.jpg"}'] );

        $run = function(): array {
            $cms = DB::connection( config( 'cms.db', 'sqlite' ) );
            $cms->enableQueryLog();
            $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com'] ) );
            $queries = Collection::make( $cms->getQueryLog() )->filter( fn( $query ) => str_starts_with( $query['query'], 'select * from "cms_files" where "path"' ) );
            $cms->flushQueryLog();
            $cms->disableQueryLog();

            return $queries->map( fn( $query ) => array_slice( $query['bindings'], 0, -1 ) )->values()->all();
        };

        // the files of the images of all articles are looked up at once, also when they are imported again
        // the intro images are only shown in the article lists of Joomla
        $this->assertSame( [['images/full.jpg', 'images/inline.png']], $run() );
        $this->assertSame( ['images/full.jpg', 'images/inline.png'], File::orderBy( 'path' )->pluck( 'path' )->all() );
        // the file type is taken from the file name, not from the alternative text used as name
        $this->assertSame( ['Inline', 'image/png'], array_values( File::where( 'path', 'images/inline.png' )->firstOrFail()->only( ['name', 'mime'] ) ) );

        $db->table( 'content' )->where( 'id', 1 )->update( ['introtext' => '<p>Changed</p>'] );
        $this->assertSame( [['images/full.jpg', 'images/inline.png']], $run() );
        $this->assertSame( 2, File::count() );
    }


    public function testImageValuesAreRelativeToTheFileBaseAndInlineImagesAreRemoved(): void
    {
        $db = DB::connection( 'joomla' );
        $db->table( 'content' )->where( 'id', 1 )->update( [
            'images' => '{"image_fulltext":"/joomla/images/full.jpg#joomlaImage://local-images/full.jpg","image_fulltext_caption":"*Big* [x] & <b>"}',
            'fulltext' => '<p>Text <img src="data:image/png;base64,AAAA" alt="Inline"></p><p><img src="https://example.com/joomla/images/full.jpg"></p>',
        ] );

        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com', '--file-base' => 'https://example.com/joomla'] ) );

        // root-relative and absolute image values of the site are the same file below the file base, inline images aren't files
        $this->assertSame( ['https://example.com/joomla/images/full.jpg'], File::pluck( 'path' )->all() );
        $content = collect( (array) PageView::where( 'name', 'About' )->firstOrFail()->content );
        // the plain text caption is escaped for Markdown
        $this->assertSame( '\\*Big\\* \\[x\\] \\& \\<b\\>', $content->firstWhere( 'type', 'image-text' )?->data->text );
        $this->assertStringNotContainsString( 'data:', (string) json_encode( $content ) );
    }


    public function testIntroTextOptionAndImageCaptions(): void
    {
        $this->accessColumns();
        Schema::connection( 'joomla' )->table( 'content', fn( Blueprint $table ) => $table->text( 'attribs' )->default( '' ) );
        $db = DB::connection( 'joomla' );
        $db->table( 'extensions' )->insert( ['element' => 'com_content', 'params' => '{"show_intro":"0"}'] );
        $db->table( 'content' )->where( 'id', 1 )->update( ['fulltext' => '<p>Full</p>', 'attribs' => '{"show_intro":""}',
            'images' => '{"image_fulltext":"images/full.jpg#joomlaImage://local-images/full.jpg?width=800&height=600","image_fulltext_caption":"Caption"}'] );
        $db->table( 'content' )->where( 'id', 4 )->update( ['fulltext' => '<p>Mehr</p>', 'attribs' => '{"show_intro":"1"}'] );
        $db->table( 'content' )->insert( ['id' => 5, 'title' => 'Short', 'alias' => 'short', 'language' => 'en-GB', 'introtext' => '<p>Only intro</p>', 'fulltext' => ''] );

        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com'] ) );
        $content = fn( $name ) => (string) json_encode( PageView::where( 'name', $name )->firstOrFail()->content );

        // the intro text is hidden by the articles component, shown by the article option and shown if there's no full text
        $this->assertStringNotContainsString( 'Welcome', $content( 'About' ) );
        $this->assertStringContainsString( 'Full', $content( 'About' ) );
        $this->assertStringContainsString( 'Caption', $content( 'About' ) );
        $this->assertStringContainsString( 'Text', $content( 'Nur Deutsch' ) );
        $this->assertStringContainsString( 'Only intro', $content( 'Short' ) );
        // the suffix of the Joomla 4 media field isn't part of the file path
        $this->assertSame( ['images/full.jpg'], File::pluck( 'path' )->all() );
    }


    public function testCategoryPagesContainDescriptionAndImage(): void
    {
        Schema::connection( 'joomla' )->table( 'categories', function( Blueprint $table ) {
            $table->text( 'description' )->nullable();
            $table->text( 'params' )->nullable();
            $table->string( 'metadesc' )->default( '' );
            $table->string( 'metakey' )->default( '' );
            $table->text( 'metadata' )->nullable();
        } );
        $db = DB::connection( 'joomla' );
        $db->table( 'categories' )->insert( [
            ['id' => 2, 'extension' => 'com_content', 'title' => 'Company', 'alias' => 'company', 'parent_id' => 1, 'level' => 1, 'lft' => 1,
                'description' => '', 'params' => '{}', 'metadesc' => '', 'metakey' => '', 'metadata' => '{}'],
            ['id' => 3, 'extension' => 'com_content', 'title' => 'News', 'alias' => 'news', 'parent_id' => 2, 'level' => 2, 'lft' => 2,
                'description' => '<p>Latest news</p>', 'params' => '{"image":"images/news.jpg#joomlaImage://local-images/news.jpg","image_alt":"Newspaper"}',
                'metadesc' => 'News of the company', 'metakey' => 'news', 'metadata' => '{"robots":"noindex, follow"}'],
        ] );
        $db->table( 'content' )->where( 'id', 1 )->update( ['catid' => 3] );
        $db->table( 'content' )->where( 'id', 4 )->update( ['catid' => 2] );

        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com'] ) );
        $root = PageView::where( 'tag', 'root' )->firstOrFail();
        $news = PageView::where( 'name', 'News' )->firstOrFail();

        // top-level categories without menu item don't get pages
        $this->assertSame( 0, PageView::where( 'name', 'Company' )->count() );
        $this->assertSame( $root->id, PageView::where( 'name', 'Nur Deutsch' )->firstOrFail()->parent_id );
        $this->assertSame( $root->id, $news->parent_id );
        $this->assertSame( $news->id, PageView::where( 'name', 'About' )->firstOrFail()->parent_id );

        // the category pages show the image and the description of the category
        $this->assertSame( ['heading', 'image', 'html'], array_map( fn( $element ) => $element->type, (array) $news->content ) );
        $this->assertSame( 'News', $news->content[0]->data->title ?? null );
        $this->assertStringContainsString( 'Latest news', (string) json_encode( $news->content[2] ?? null ) );
        $this->assertSame( ['images/news.jpg'], File::pluck( 'path' )->all() );
        $this->assertSame( 'Newspaper', File::firstOrFail()->name );
        $this->assertSame( 'News of the company', $news->meta->{'meta-tags'}->data->description ?? null );
        $this->assertSame( ['index' => 'noindex', 'follow' => 'follow'], (array) ( $news->meta->robots->data ?? [] ) );

        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com'] ) );
        $this->assertSame( 1, File::count() );
    }


    public function testArticleLayoutWithHeadingsFieldsAndTags(): void
    {
        $this->extraTables();
        $db = DB::connection( 'joomla' );
        $db->table( 'extensions' )->insert( ['element' => 'com_content', 'params' => '{"show_tags":"1","info_block_position":"0"}'] );
        $db->table( 'content' )->where( 'id', 1 )->update( ['fulltext' => '<p>More</p><p><img src="images/inline.png#joomlaImage://local-images/inline.png?width=8" alt="Inline"></p>',
            'images' => '{"image_intro":"images/teaser.jpg","image_fulltext":"images/full.jpg"}', 'attribs' => '{"show_title":"0"}',
            'urls' => '{"urla":"https://example.org/read","urlatext":"Read on","urlb":"","urlc":"index.php?option=com_content&view=article&id=4"}'] );
        $db->table( 'content' )->where( 'id', 4 )->update( ['attribs' => '{"show_title":"0"}'] );
        $db->table( 'menu' )->insert( ['params' => '{"show_page_heading":"1","page_heading":"Our company","show_title":"1"}'] + $this->menuItem( 101, 'Company', 1, 1, 1 ) );
        $field = ['context' => 'com_content.article', 'state' => 1, 'access' => 1, 'language' => '*', 'group_id' => 0, 'params' => '{}', 'fieldparams' => '{}', 'label' => ''];
        $db->table( 'fields' )->insert( [
            ['id' => 1, 'title' => 'color', 'label' => 'Color', 'type' => 'list', 'ordering' => 1, 'params' => '{"display":"1"}',
                'fieldparams' => '{"options":{"options0":{"name":"Red","value":"r"}}}'] + $field,
            ['id' => 2, 'title' => 'Note', 'type' => 'textarea', 'ordering' => 2] + $field,
            ['id' => 3, 'title' => 'Link', 'type' => 'url', 'ordering' => 3, 'params' => '{"display":"3","showlabel":"0"}'] + $field,
            ['id' => 5, 'title' => 'Draft', 'type' => 'text', 'ordering' => 5, 'state' => 0] + $field,
            ['id' => 6, 'title' => 'Photo', 'type' => 'media', 'ordering' => 6, 'params' => '{"display":"3"}'] + $field,
            ['id' => 7, 'title' => 'Grouped', 'type' => 'text', 'ordering' => 7, 'group_id' => 1] + $field,
            ['id' => 8, 'title' => 'Related', 'type' => 'editor', 'ordering' => 8, 'params' => '{"display":"3","showlabel":"0"}'] + $field,
        ] );
        $db->table( 'fields_groups' )->insert( ['id' => 1, 'state' => 0] );
        $db->table( 'fields_values' )->insert( [
            ['field_id' => 1, 'item_id' => '1', 'value' => 'r'],
            ['field_id' => 2, 'item_id' => '1', 'value' => "Line 1\nLine 2"],
            ['field_id' => 3, 'item_id' => '1', 'value' => 'https://example.org/'],
            ['field_id' => 5, 'item_id' => '1', 'value' => 'Draft value'],
            ['field_id' => 6, 'item_id' => '1', 'value' => '{"imagefile":"images/photo.jpg#joomlaImage://local-images/photo.jpg","alt_text":"Photo"}'],
            ['field_id' => 7, 'item_id' => '1', 'value' => 'Grouped value'],
            ['field_id' => 8, 'item_id' => '1', 'value' => '<p><a href="index.php?option=com_content&amp;view=article&amp;id=4">Field link</a></p>'],
        ] );
        $db->table( 'tags' )->insert( [
            ['id' => 2, 'title' => 'Joomla', 'published' => 1, 'access' => 1, 'lft' => 2],
            ['id' => 3, 'title' => 'CMS', 'published' => 1, 'access' => 1, 'lft' => 1],
            ['id' => 4, 'title' => 'Unpublished', 'published' => 0, 'access' => 1, 'lft' => 3],
            ['id' => 5, 'title' => 'Members', 'published' => 1, 'access' => 3, 'lft' => 4],
        ] );
        $db->table( 'contentitem_tag_map' )->insert( [
            ['type_alias' => 'com_content.article', 'content_item_id' => 1, 'tag_id' => 2],
            ['type_alias' => 'com_content.article', 'content_item_id' => 1, 'tag_id' => 3],
            ['type_alias' => 'com_content.article', 'content_item_id' => 1, 'tag_id' => 4],
            ['type_alias' => 'com_content.article', 'content_item_id' => 1, 'tag_id' => 5],
            ['type_alias' => 'com_contact.contact', 'content_item_id' => 4, 'tag_id' => 2],
        ] );

        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com'] ) );
        $page = PageView::where( 'name', 'Company' )->firstOrFail();
        $json = (string) json_encode( $page->content );

        // the page heading of the menu item is shown before the title of the article, the options of the menu item are used before the ones of the article
        $this->assertSame( [1, 'Our company'], [$page->content[0]->data->level ?? null, $page->content[0]->data->title ?? null] );
        $this->assertSame( [2, 'About'], [$page->content[1]->data->level ?? null, $page->content[1]->data->title ?? null] );

        // the fields, tags, image and texts are in the order Joomla shows them
        // Joomla shows the tags ordered by their IDs
        $positions = array_map( fn( $text ) => strpos( $json, $text ), ['Color: ', 'Red', 'Joomla', 'CMS', 'Note: ', 'Line 1', 'Read on', 'Welcome', 'More', '>Visit Website<', 'Photo: '] );
        $this->assertNotContains( false, $positions );
        $sorted = $positions;
        sort( $sorted );
        $this->assertSame( $sorted, $positions );
        $this->assertSame( 'image', collect( (array) $page->content )->firstWhere( 'type', 'image' )?->type );

        // the links of the article are shown above the text, links to Joomla pages use the URL paths of the imported pages and links without text show their URL
        $this->assertStringContainsString( '<a href=\\"https:\\/\\/example.org\\/read\\">Read on<\\/a>', $json );
        $this->assertStringContainsString( '<a href=\\"\\/nur-deutsch\\">index.php?option=com_content&amp;view=article&amp;id=4<\\/a>', $json );
        // the links in the custom fields use the URL paths of the imported pages too
        $this->assertStringContainsString( '<a href=\\"\\/nur-deutsch\\">Field link<\\/a>', $json );

        // unpublished fields and groups, unpublished tags and the label of hidden labels aren't shown
        foreach( ['Draft value', 'Grouped value', 'Unpublished', 'Members', 'Link: '] as $text ) {
            $this->assertStringNotContainsString( $text, $json );
        }

        // only the full text images are shown on the article page, the suffixes of the Joomla 4 media field aren't part of the paths
        $this->assertSame( ['images/full.jpg', 'images/inline.png', 'images/photo.jpg'], File::orderBy( 'path' )->pluck( 'path' )->all() );

        // the title isn't shown if disabled by the article and articles without menu item have no page heading
        $nur = PageView::where( 'name', 'Nur Deutsch' )->firstOrFail();
        $this->assertSame( ['html'], array_column( array_map( fn( $element ) => (array) $element, (array) $nur->content ), 'type' ) );

        // Joomla 3 shows the URLs of the URL fields as link texts
        $this->setJoomlaVersion( 3 );
        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com'] ) );
        $json = (string) json_encode( PageView::where( 'name', 'Company' )->firstOrFail()->content );
        $this->assertStringContainsString( '>https:\\/\\/example.org\\/<\\/a>', $json );
        $this->assertStringNotContainsString( 'Visit Website', $json );
    }


    public function testArticleLinksBelowTextCategoryTagsAndModulesOfTheLanguage(): void
    {
        $this->extraTables();
        Schema::connection( 'joomla' )->table( 'modules', fn( Blueprint $table ) => $table->string( 'language' )->default( '*' ) );
        Schema::connection( 'joomla' )->table( 'categories', fn( Blueprint $table ) => $table->text( 'description' )->nullable() );
        Schema::connection( 'joomla' )->table( 'menu', fn( Blueprint $table ) => $table->string( 'language' )->default( '*' ) );
        $db = DB::connection( 'joomla' );
        $this->languageFilter();
        $db->table( 'categories' )->insert( ['id' => 2, 'extension' => 'com_content', 'title' => 'News', 'alias' => 'news', 'parent_id' => 1, 'level' => 1, 'lft' => 1, 'description' => '<p>All news</p>'] );
        $db->table( 'menu' )->insert( ['id' => 101, 'menutype' => 'mainmenu', 'title' => 'Blog', 'alias' => 'blog', 'parent_id' => 1, 'level' => 1, 'lft' => 1,
            'type' => 'component', 'link' => 'index.php?option=com_content&view=category&layout=blog&id=2', 'params' => '{}', 'language' => 'en-GB'] );
        $db->table( 'tags' )->insert( [
            ['id' => 2, 'title' => 'Topic', 'published' => 1, 'access' => 1, 'lft' => 1],
            ['id' => 3, 'title' => 'Secret', 'published' => 1, 'access' => 3, 'lft' => 2],
        ] );
        $db->table( 'contentitem_tag_map' )->insert( [
            ['type_alias' => 'com_content.category', 'content_item_id' => 2, 'tag_id' => 2],
            ['type_alias' => 'com_content.category', 'content_item_id' => 2, 'tag_id' => 3],
        ] );
        $db->table( 'modules' )->insert( [
            ['id' => 1, 'title' => 'English', 'module' => 'mod_custom', 'position' => 'banner', 'content' => '<p>English banner</p>', 'ordering' => 1, 'language' => 'en-GB', 'access' => 1],
            ['id' => 2, 'title' => 'German', 'module' => 'mod_custom', 'position' => 'banner', 'content' => '<p>German banner</p>', 'ordering' => 2, 'language' => 'de-DE', 'access' => 1],
            ['id' => 3, 'title' => 'All', 'module' => 'mod_custom', 'position' => 'banner', 'content' => '<p>All banner</p>', 'ordering' => 3, 'language' => '*', 'access' => 1],
            ['id' => 4, 'title' => 'Same', 'module' => 'mod_custom', 'position' => 'x', 'content' => '<p>Members note</p>', 'ordering' => 1, 'language' => '*', 'access' => 3],
            ['id' => 5, 'title' => 'Same', 'module' => 'mod_custom', 'position' => 'y', 'content' => '<p>Public note</p>', 'ordering' => 1, 'language' => '*', 'access' => 1],
        ] );
        $db->table( 'content' )->where( 'id', 1 )->update( ['catid' => 2, 'attribs' => '{"urls_position":"1"}', 'urls' => '{"urla":"https://example.org/read","urlatext":"Read on"}',
            'introtext' => '<div class="box"><p>Intro part</p>', 'fulltext' => '<p>Full part</p></div><p>{loadposition banner}</p><p>{loadmodule custom,Same}</p>'] );

        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com'] ) );
        $about = PageView::where( 'name', 'About' )->firstOrFail();
        $json = (string) json_encode( $about->content );

        // with language filter, the menu items in the import language are used for the URLs
        $this->assertSame( 'blog/1-about', $about->path );

        // Joomla joins the intro and full text, so the elements split by the "Read more" break are complete
        $texts = array_map( fn( $element ) => (string) ( $element->data->text ?? '' ), (array) $about->content );
        $this->assertNotEmpty( array_filter( $texts, fn( $text ) => str_contains( $text, 'Intro part' ) && str_contains( $text, 'Full part' ) ) );

        // the links are shown below the text by the option of the article
        $this->assertGreaterThan( strpos( $json, 'Full part' ), strpos( $json, 'Read on' ) );

        // only the modules in the language of the article or for all languages are inserted, restricted modules don't hide visible ones
        $this->assertStringContainsString( 'English banner', $json );
        $this->assertStringContainsString( 'All banner', $json );
        $this->assertStringContainsString( 'Public note', $json );
        $this->assertStringNotContainsString( 'German banner', $json );
        $this->assertStringNotContainsString( 'Members note', $json );

        // the category page shows the public tags of the category
        $blog = (string) json_encode( PageView::where( 'path', 'blog' )->firstOrFail()->content );
        $this->assertStringContainsString( 'Topic', $blog );
        $this->assertGreaterThan( strpos( $blog, 'Topic' ), strpos( $blog, 'All news' ) );
        $this->assertStringNotContainsString( 'Secret', $blog );
        $this->assertStringNotContainsString( 'Topic', $json );
    }


    public function testInternalLinksShortcodesAndPageBreaks(): void
    {
        $this->extraTables();
        $db = DB::connection( 'joomla' );
        $db->table( 'categories' )->insert( ['id' => 2, 'extension' => 'com_content', 'title' => 'News', 'alias' => 'news', 'parent_id' => 1, 'level' => 1, 'lft' => 1] );
        $db->table( 'content' )->where( 'id', 4 )->update( ['catid' => 2] );
        $db->table( 'menu' )->insert( ['id' => 101, 'menutype' => 'mainmenu', 'title' => 'Blog', 'alias' => 'blog', 'parent_id' => 1, 'level' => 1, 'lft' => 1,
            'type' => 'component', 'link' => 'index.php?option=com_content&view=category&layout=blog&id=2', 'params' => '{}'] );
        $db->table( 'extensions' )->insert( ['type' => 'plugin', 'folder' => 'content', 'element' => 'pagebreak', 'params' => '{}'] );
        // the e-mail cloaking plugin removes its "{emailcloak=off}" shortcode
        $db->table( 'extensions' )->insert( ['type' => 'plugin', 'folder' => 'content', 'element' => 'emailcloak', 'params' => '{"mode":"0"}'] );
        Schema::connection( 'joomla' )->table( 'modules', fn( Blueprint $table ) => $table->string( 'language' )->default( 'de-DE' ) );
        $db->table( 'modules' )->insert( [
            ['id' => 1, 'title' => 'Banner', 'module' => 'mod_custom', 'position' => 'banner', 'content' => '<p>Banner text</p>', 'ordering' => 2, 'published' => 1, 'access' => 1],
            ['id' => 2, 'title' => 'Main menu', 'module' => 'mod_menu', 'position' => 'banner', 'content' => '', 'ordering' => 1, 'published' => 1, 'access' => 1],
            ['id' => 3, 'title' => 'Old', 'module' => 'mod_custom', 'position' => 'banner', 'content' => '<p>Old text</p>', 'ordering' => 3, 'published' => 0, 'access' => 1],
            ['id' => 4, 'title' => 'Hidden', 'module' => 'mod_custom', 'position' => 'banner', 'content' => '<p>Hidden text</p>', 'ordering' => 4, 'published' => 1, 'access' => 3],
            ['id' => 5, 'title' => 'Note', 'module' => 'mod_custom', 'position' => 'other', 'content' => '<p>Note text</p>', 'ordering' => 1, 'published' => 1, 'access' => 1],
        ] );
        $db->table( 'content' )->where( 'id', 1 )->update( ['introtext' => '{emailcloak=off}<p>'
            . '<a href="index.php?option=com_content&amp;view=article&amp;id=4:nur-deutsch&amp;catid=2#top">Article</a> '
            . '<a href="https://example.com/index.php?Itemid=101">Item</a> '
            . "<a href='/index.php?option=com_content&view=category&id=2'>Category</a> "
            . '<a href="index.php?option=com_users&amp;view=login">Login</a> '
            . '<a href="index.php?option=com_content&amp;view=article&amp;id=99">Missing</a> '
            . '<a href="index.php?option=com_content&amp;view=featured&amp;Itemid=101">Featured</a></p>'
            . '<hr class="system-pagebreak" title="Part 2"><p>Second part</p><hr class="system-pagebreak" alt="Third">'
            . '<p>{loadposition banner}</p><p>Text {loadmoduleid 5} end</p><p>{loadmodule custom,Note}</p><hr class="system-pagebreak"><p>Last</p>'] );

        // the titles of page breaks without title are translated to the language of the article
        app( 'translator' )->addLines( ['*.Page :num' => 'Section :num'], 'en' );
        try {
            $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com', '--file-base' => 'https://example.com'] ) );
        } finally {
            app( 'translator' )->addLines( ['*.Page :num' => 'Page :num'], 'en' );
        }
        $output = Artisan::output();
        $json = stripslashes( (string) json_encode( PageView::where( 'name', 'About' )->firstOrFail()->content, JSON_UNESCAPED_UNICODE ) );

        // the links to Joomla pages use the URL paths of the imported pages, unknown links are kept
        $this->assertStringContainsString( '<a href="/blog/4-nur-deutsch#top">Article</a> <a href="/blog">Item</a> <a href="/blog">Category</a> '
            . '<a href="index.php?option=com_users&amp;view=login">Login</a> <a href="index.php?option=com_content&amp;view=article&amp;id=99">Missing</a> '
            . '<a href="index.php?option=com_content&amp;view=featured&amp;Itemid=101">Featured</a>', $json );

        // page breaks become headings using the alt text before the title, the shortcodes are replaced by the content of the custom modules of all languages without language filter
        $this->assertStringContainsString( '"title":"Part 2"', $json );
        $this->assertStringContainsString( '"title":"Third"', $json );
        $this->assertStringContainsString( '"title":"Section 4"', $json );
        $this->assertSame( 1, substr_count( $json, 'Banner text' ) );
        $this->assertSame( 2, substr_count( $json, 'Note text' ) );
        $this->assertStringNotContainsString( '{', strip_tags( implode( '', array_map( fn( $element ) => (string) ( $element->data->text ?? '' ), (array) PageView::where( 'name', 'About' )->firstOrFail()->content ) ) ) );

        foreach( ['Old text', 'Hidden text', 'emailcloak'] as $text ) {
            $this->assertStringNotContainsString( $text, $json );
        }

        $this->assertStringContainsString( 'Removed module [2] Main menu of shortcode {loadposition banner} because only custom modules are supported', $output );
    }


    public function testTeasersOfArticlesGuestsCantAccess(): void
    {
        $this->extraTables();
        $this->languageFilter();
        Schema::connection( 'joomla' )->table( 'content', function( Blueprint $table ) {
            $table->unsignedInteger( 'access' )->default( 1 );
            $table->string( 'publish_up' )->nullable();
            $table->string( 'publish_down' )->nullable();
        } );
        $db = DB::connection( 'joomla' );
        $db->table( 'extensions' )->insert( ['element' => 'com_content', 'params' => '{"show_noauth":"1","show_tags":"1","info_block_position":"1"}'] );
        $db->table( 'tags' )->insert( ['id' => 2, 'title' => 'Topic', 'published' => 1, 'access' => 1, 'lft' => 1] );
        $db->table( 'contentitem_tag_map' )->insert( [
            ['type_alias' => 'com_content.article', 'content_item_id' => 1, 'tag_id' => 2],
            ['type_alias' => 'com_content.article', 'content_item_id' => 5, 'tag_id' => 2],
            ['type_alias' => 'com_content.article', 'content_item_id' => 10, 'tag_id' => 2],
        ] );
        $db->table( 'content' )->where( 'id', 1 )->update( ['access' => 2, 'introtext' => '<p>Teaser part</p>', 'fulltext' => '<p>Secret part</p>',
            'images' => '{"image_intro":"images/intro.jpg","image_intro_caption":"Intro caption","image_fulltext":"images/full.jpg","image_fulltext_caption":"Full caption"}',
            'attribs' => '{"urls_position":"1"}', 'urls' => '{"urla":"https://example.org/below","urlatext":"Below link"}'] );
        $db->table( 'content' )->where( 'id', 2 )->update( ['access' => 2, 'introtext' => '<p>Anriss</p>', 'fulltext' => '<p>Geheim</p>'] );
        $article = ['language' => 'en-GB', 'access' => 2, 'introtext' => '<p>Intro</p>', 'fulltext' => '<p>Rest</p>', 'attribs' => '{}', 'urls' => '{}', 'publish_down' => null];
        $db->table( 'content' )->insert( [
            ['id' => 5, 'title' => 'Above', 'alias' => 'above', 'attribs' => '{"urls_position":"0","info_block_position":"0"}',
                'urls' => '{"urla":"https://example.org/above","urlatext":"Above link"}'] + $article,
            ['id' => 6, 'title' => 'Short', 'alias' => 'short', 'fulltext' => ''] + $article,
            ['id' => 7, 'title' => 'Expired', 'alias' => 'expired', 'publish_down' => '2000-01-01 00:00:00'] + $article,
            ['id' => 8, 'title' => 'Own', 'alias' => 'own', 'attribs' => '{"show_noauth":"0"}'] + $article,
            ['id' => 9, 'title' => 'Eigen', 'alias' => 'eigen', 'language' => 'de-DE'] + $article,
            ['id' => 10, 'title' => 'Split', 'alias' => 'split', 'access' => 1, 'attribs' => '{"info_block_position":"2"}'] + $article,
        ] );
        $db->table( 'content' )->insert( ['id' => 11, 'title' => 'Listed', 'alias' => 'listed', 'catid' => 2] + $article );
        $db->table( 'categories' )->insert( ['id' => 2, 'extension' => 'com_content', 'title' => 'News', 'alias' => 'news', 'published' => 1, 'parent_id' => 1, 'level' => 1, 'lft' => 1] );
        $db->table( 'menu' )->insert( ['id' => 101, 'menutype' => 'mainmenu', 'title' => 'News', 'alias' => 'news', 'link' => 'index.php?option=com_content&view=category&id=2',
            'type' => 'component', 'parent_id' => 1, 'level' => 1, 'lft' => 1, 'params' => '{"menu_show":"0"}'] );
        $db->table( 'associations' )->insert( [['id' => 8, 'context' => 'com_content.item', 'key' => 'own'], ['id' => 9, 'context' => 'com_content.item', 'key' => 'own']] );

        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com', '--language' => ['de-DE:de']] ) );
        $output = Artisan::output();
        $page = fn( $name ) => PageView::where( 'name', $name )->firstOrFail();
        $json = fn( $content ) => (string) json_encode( $content );

        // guests see the intro image and text of articles they can't access, the tags and links below the text are hidden
        $this->assertStringContainsString( "Imported only the intro text: About (/about) (it's restricted to Joomla access level 2)", $output );
        $this->assertSame( 1, $page( 'About' )->status );
        $this->assertStringContainsString( 'Teaser part', $json( $page( 'About' )->content ) );
        $this->assertStringContainsString( 'Intro caption', $json( $page( 'About' )->content ) );

        foreach( ['Secret part', 'Full caption', 'Below link', 'Topic'] as $text ) {
            $this->assertStringNotContainsString( $text, $json( $page( 'About' )->content ) );
        }

        $this->assertSame( ['images/intro.jpg'], File::pluck( 'path' )->all() );

        // the menu options of the category menu item aren't used for teasers of articles without their own menu item
        $this->assertStringContainsString( 'Imported only the intro text: Listed', $output );
        $this->assertSame( 1, $page( 'Listed' )->status );

        // translations of teasers are teasers too if guests can't access them
        $de = PageView::language( 'de' )->findOrFail( $page( 'About' )->id );
        $this->assertStringContainsString( 'Imported only the intro text of translation: Über uns (/ueber-uns) [de]', $output );
        $this->assertSame( 1, $de->status );
        $this->assertStringContainsString( 'Anriss', $json( $de->content ) );
        $this->assertStringNotContainsString( 'Geheim', $json( $de->content ) );

        // the links and tags above the text are shown
        $this->assertStringContainsString( 'Above link', $json( $page( 'Above' )->content ) );
        $this->assertStringContainsString( 'Topic', $json( $page( 'Above' )->content ) );
        $this->assertStringNotContainsString( 'Rest', $json( $page( 'Above' )->content ) );
        // the tags of info blocks split above and below the text are shown below
        $this->assertGreaterThan( strpos( $json( $page( 'Split' )->content ), 'Rest' ), strpos( $json( $page( 'Split' )->content ), 'Topic' ) );

        // articles without full text, expired articles, articles without the option and their translations are disabled
        foreach( ['Short', 'Expired', 'Own'] as $name ) {
            $this->assertSame( 0, $page( $name )->status, $name );
            $this->assertStringContainsString( "Disabled: {$name} (/" . strtolower( $name ) . ')', $output );
        }

        $this->assertSame( 0, PageView::language( 'de' )->findOrFail( $page( 'Own' )->id )->status );
    }


    public function testTeasersReplaceFieldShortcodesAndKeepPageBreaks(): void
    {
        $this->extraTables();
        Schema::connection( 'joomla' )->table( 'content', function( Blueprint $table ) {
            $table->unsignedInteger( 'access' )->default( 1 );
            $table->string( 'publish_up' )->nullable();
            $table->string( 'publish_down' )->nullable();
        } );
        $db = DB::connection( 'joomla' );
        $db->table( 'extensions' )->insert( ['type' => 'plugin', 'folder' => 'content', 'element' => 'pagebreak', 'params' => '{}'] );
        $db->table( 'extensions' )->insert( ['element' => 'com_content', 'params' => '{"show_noauth":"1"}'] );
        $db->table( 'modules' )->insert( ['id' => 1, 'title' => 'Banner', 'module' => 'mod_custom', 'position' => 'banner', 'content' => '<p>Banner text</p>',
            'ordering' => 1, 'published' => 1, 'access' => 1] );
        $db->table( 'fields' )->insert( [
            ['id' => 1, 'context' => 'com_content.article', 'title' => 'Color', 'label' => 'Color', 'type' => 'text', 'params' => '{"display":"0"}',
                'fieldparams' => '{}', 'state' => 1, 'ordering' => 1, 'access' => 1, 'language' => '*', 'group_id' => 0],
            ['id' => 2, 'context' => 'com_content.article', 'title' => 'Size', 'label' => 'Size', 'type' => 'text', 'params' => '{"display":"1"}',
                'fieldparams' => '{}', 'state' => 1, 'ordering' => 2, 'access' => 1, 'language' => '*', 'group_id' => 0],
        ] );
        $db->table( 'fields_values' )->insert( [['field_id' => 1, 'item_id' => '1', 'value' => 'Red'], ['field_id' => 2, 'item_id' => '1', 'value' => 'Large']] );
        $db->table( 'content' )->where( 'id', 1 )->update( ['access' => 2, 'fulltext' => '<p>Secret part</p>',
            'introtext' => '<p>Teaser {field 1}</p><hr class="system-pagebreak" title="Second"><p>After</p><p>{loadposition banner}</p>'] );
        // like Joomla's "strlen()" test, a full text of whitespace only is a full text, so guests see the teaser
        $db->table( 'content' )->insert( ['id' => 5, 'title' => 'Blank', 'alias' => 'blank', 'language' => 'en-GB', 'access' => 2,
            'introtext' => '<p>Blank intro</p>', 'fulltext' => "\n", 'attribs' => '{}', 'urls' => '{}'] );

        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com'] ) );
        $output = Artisan::output();
        $about = PageView::where( 'name', 'About' )->firstOrFail();
        $json = stripslashes( (string) json_encode( $about->content, JSON_UNESCAPED_UNICODE ) );

        // the "Content - Fields" plugin replaces the field shortcodes of the intro text too, the page break is kept and the modules are replaced
        $this->assertSame( 1, $about->status );
        $this->assertStringContainsString( 'Teaser <strong>Color: </strong>Red</p>', $json );
        $this->assertStringNotContainsString( '{field 1}', $json );
        $this->assertStringContainsString( '<hr class="system-pagebreak" title="Second"', $json );
        $this->assertStringNotContainsString( '"title":"Second"', $json );
        $this->assertStringContainsString( 'After', $json );
        $this->assertStringContainsString( 'Banner text', $json );
        $this->assertStringNotContainsString( 'Secret part', $json );
        // the custom fields shown at their positions are unaffected
        $this->assertStringContainsString( 'Large', $json );

        $this->assertStringContainsString( 'Imported only the intro text: Blank', $output );
        $this->assertSame( 1, PageView::where( 'name', 'Blank' )->firstOrFail()->status );
        $this->assertStringContainsString( 'Blank intro', (string) json_encode( PageView::where( 'name', 'Blank' )->firstOrFail()->content ) );
    }


    public function testTeasersReplaceModulesAndLinkEmailsOnce(): void
    {
        $this->extraTables();
        Schema::connection( 'joomla' )->table( 'content', function( Blueprint $table ) {
            $table->unsignedInteger( 'access' )->default( 1 );
            $table->string( 'publish_up' )->nullable();
            $table->string( 'publish_down' )->nullable();
        } );
        $db = DB::connection( 'joomla' );
        $db->table( 'extensions' )->insert( ['type' => 'plugin', 'folder' => 'content', 'element' => 'emailcloak', 'params' => '{"mode":"1"}'] );
        $db->table( 'extensions' )->insert( ['element' => 'com_content', 'params' => '{"show_noauth":"1"}'] );
        $db->table( 'modules' )->insert( [
            ['id' => 1, 'title' => 'Banner', 'module' => 'mod_custom', 'position' => 'banner', 'content' => '<p>Banner sales@example.com</p>', 'ordering' => 1, 'published' => 1, 'access' => 1],
            ['id' => 2, 'title' => 'Note', 'module' => 'mod_custom', 'position' => 'side', 'content' => '<p>Note text</p>', 'ordering' => 2, 'published' => 1, 'access' => 1],
        ] );
        $db->table( 'content' )->where( 'id', 1 )->update( ['access' => 2, 'fulltext' => '<p>Secret part</p>',
            'introtext' => '<p>Mail info@example.com</p><p>{loadposition banner}</p><p>{loadmodule custom,Note}</p>'] );

        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com'] ) );
        $json = stripslashes( (string) json_encode( PageView::where( 'name', 'About' )->firstOrFail()->content, JSON_UNESCAPED_UNICODE ) );

        // the modules of the teaser are replaced and the e-mail addresses of the teaser and the modules are linked once
        $this->assertStringContainsString( '<p>Mail <a href="mailto:info@example.com">info@example.com</a></p>', $json );
        $this->assertStringContainsString( 'Banner <a href="mailto:sales@example.com">sales@example.com</a>', $json );
        $this->assertStringContainsString( 'Note text', $json );
        $this->assertSame( 1, substr_count( $json, 'href="mailto:info@example.com"' ) );
        $this->assertSame( 1, substr_count( $json, 'Banner' ) );
        $this->assertStringNotContainsString( '{load', $json );
        $this->assertStringNotContainsString( 'Secret part', $json );
    }


    public function testTagsOfTheConfiguredLanguage(): void
    {
        $this->extraTables();
        $db = DB::connection( 'joomla' );
        $db->table( 'extensions' )->insert( ['element' => 'com_content', 'params' => '{"show_tags":"1"}'] );
        $db->table( 'tags' )->insert( [
            ['id' => 2, 'title' => 'English', 'published' => 1, 'access' => 1, 'lft' => 1, 'language' => 'en-GB'],
            ['id' => 3, 'title' => 'German', 'published' => 1, 'access' => 1, 'lft' => 2, 'language' => 'de-DE'],
            ['id' => 4, 'title' => 'Common', 'published' => 1, 'access' => 1, 'lft' => 3, 'language' => '*'],
        ] );
        $db->table( 'contentitem_tag_map' )->insert( array_merge( ...array_map( fn( $id ) => [
            ['type_alias' => 'com_content.article', 'content_item_id' => $id, 'tag_id' => 2],
            ['type_alias' => 'com_content.article', 'content_item_id' => $id, 'tag_id' => 3],
            ['type_alias' => 'com_content.article', 'content_item_id' => $id, 'tag_id' => 4],
        ], [1, 4, 5] ) ) );
        $db->table( 'content' )->insert( ['id' => 5, 'title' => 'All', 'alias' => 'all', 'language' => '*', 'introtext' => '<p>All</p>', 'fulltext' => ''] );
        $tags = fn( $name ) => array_values( array_filter( ['English', 'German', 'Common'], fn( $tag ) => str_contains( (string) json_encode( PageView::where( 'name', $name )->firstOrFail()->content ), '<li>' . $tag ) ) );
        $import = function( string $filter, bool $languages = false ) use ( $db ) {
            $db->table( 'extensions' )->where( 'element', 'com_tags' )->delete();
            $db->table( 'extensions' )->insert( ['element' => 'com_tags', 'params' => '{"tag_list_language_filter":"' . $filter . '"}'] );
            $this->languageFilter( $languages );

            $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com'] ) );
        };

        // all tags are shown by default
        $import( '' );
        $this->assertSame( ['English', 'German', 'Common'], $tags( 'Nur Deutsch' ) );

        // the tags of a configured language and the ones for all languages are shown
        $import( 'de-DE' );
        $this->assertSame( ['German', 'Common'], $tags( 'About' ) );

        // without language filter, the current language is the imported one
        $import( 'current_language' );
        $this->assertSame( ['English', 'Common'], $tags( 'Nur Deutsch' ) );

        // with language filter, the current language is the one of the article and the imported one for articles of all languages
        $import( 'current_language', true );
        $this->assertSame( ['English', 'Common'], $tags( 'About' ) );
        $this->assertSame( ['English', 'Common'], $tags( 'All' ) );
    }


    public function testModuleMenuAssignments(): void
    {
        $this->extraTables();
        $db = DB::connection( 'joomla' );
        Schema::connection( 'joomla' )->create( 'modules_menu', function( Blueprint $table ) {
            $table->unsignedInteger( 'moduleid' );
            $table->integer( 'menuid' );
        } );
        Schema::connection( 'joomla' )->table( 'categories', fn( Blueprint $table ) => $table->text( 'description' )->nullable() );
        $db->table( 'categories' )->insert( ['id' => 2, 'extension' => 'com_content', 'title' => 'News', 'alias' => 'news', 'parent_id' => 1, 'level' => 1, 'lft' => 1] );
        $db->table( 'content' )->whereIn( 'id', [1, 4] )->update( ['catid' => 2, 'introtext' => '<p>{loadposition p}</p>'] );
        $db->table( 'content' )->insert( ['id' => 5, 'title' => 'Loose', 'alias' => 'loose', 'language' => '*', 'introtext' => '<p>{loadposition p}</p>', 'fulltext' => ''] );
        $db->table( 'menu' )->insert( [
            ['id' => 101, 'menutype' => 'mainmenu', 'title' => 'Blog', 'alias' => 'blog', 'parent_id' => 1, 'level' => 1, 'lft' => 1,
                'type' => 'component', 'link' => 'index.php?option=com_content&view=category&layout=blog&id=2', 'params' => '{}'],
            ['id' => 102, 'menutype' => 'mainmenu', 'title' => 'Nur', 'alias' => 'nur', 'parent_id' => 1, 'level' => 1, 'lft' => 2,
                'type' => 'component', 'link' => 'index.php?option=com_content&view=article&id=4', 'params' => '{}'],
        ] );
        $modules = ['All' => [0], 'Blog only' => [101], 'Article only' => [102], 'Not blog' => [-101], 'Not article' => [-102], 'Nowhere' => [], 'Nested' => [0]];

        foreach( array_keys( $modules ) as $idx => $title )
        {
            // modules loaded by other modules are shown in the same menu item
            $content = $title === 'Nested' ? '<p>{loadposition q}</p>' : "<p>{$title} text</p>";
            $db->table( 'modules' )->insert( ['id' => $idx + 1, 'title' => $title, 'module' => 'mod_custom', 'position' => $title === 'Blog only' ? 'q' : 'p', 'content' => $content, 'ordering' => $idx] );
            $db->table( 'modules_menu' )->insert( array_map( fn( $menuId ) => ['moduleid' => $idx + 1, 'menuid' => $menuId], $modules[$title] ) );
        }

        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com'] ) );
        $texts = fn( string $name ) => array_values( array_filter( array_keys( $modules ), fn( $title ) => str_contains( (string) json_encode( PageView::where( 'name', $name )->firstOrFail()->content ), $title . ' text' ) ) );

        // articles without own menu item are shown in the menu item of their category
        $this->assertSame( ['All', 'Blog only', 'Not article'], $texts( 'About' ) );
        $this->assertSame( ['All', 'Article only', 'Not blog'], $texts( 'Nur' ) );
        // articles without menu item show the modules of all pages
        $this->assertSame( ['All', 'Not blog', 'Not article'], $texts( 'Loose' ) );

        // categories without own menu item are shown in the menu item of their parent category
        $db->table( 'categories' )->insert( ['id' => 3, 'extension' => 'com_content', 'title' => 'Local', 'alias' => 'local', 'parent_id' => 2, 'level' => 2, 'lft' => 2,
            'description' => '<p>{loadposition p}</p>'] );
        $db->table( 'content' )->where( 'id', 1 )->update( ['catid' => 3] );

        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com'] ) );
        $this->assertSame( ['All', 'Blog only', 'Not article'], $texts( 'Local' ) );
    }


    public function testModuleStylesAndTitles(): void
    {
        $this->extraTables();
        $db = DB::connection( 'joomla' );
        $db->table( 'extensions' )->where( 'element', 'loadmodule' )->update( ['params' => '{"style":"xhtml"}'] );
        $db->table( 'modules' )->insert( [
            ['id' => 1, 'title' => 'First', 'module' => 'mod_custom', 'position' => 'Banner', 'content' => '<p>One</p>', 'showtitle' => 1, 'ordering' => 1, 'params' => '{}'],
            ['id' => 2, 'title' => 'Second', 'module' => 'mod_custom', 'position' => 'banner', 'content' => '<p>Two</p>', 'showtitle' => 0, 'ordering' => 2, 'params' => '{}'],
            ['id' => 3, 'title' => 'Third', 'module' => 'mod_custom', 'position' => 'banner', 'content' => '<p>Three</p>', 'showtitle' => 1, 'ordering' => 3,
                'params' => '{"header_tag":"h4","style":"Cassiopeia-card"}'],
            ['id' => 4, 'title' => 'Empty', 'module' => 'mod_custom', 'position' => 'banner', 'content' => '', 'showtitle' => 1, 'ordering' => 4, 'params' => '{}'],
            ['id' => 5, 'title' => 'Fourth', 'module' => 'mod_custom', 'position' => 'plain', 'content' => '<p>Four</p>', 'showtitle' => 1, 'ordering' => 1,
                'params' => '{"style":"System-none","header_tag":"h4 onclick"}'],
            ['id' => 6, 'title' => 'Fifth', 'module' => 'mod_custom', 'position' => 'single', 'content' => '<p>Five</p>', 'showtitle' => 1, 'ordering' => 1,
                'params' => '{"style":"0","header_tag":"h4 onclick"}'],
            // "loadmodule" only shows the first module of the type and title
            ['id' => 7, 'title' => 'Fifth', 'module' => 'mod_custom', 'position' => 'twin', 'content' => '<p>Twin</p>', 'showtitle' => 1, 'ordering' => 1, 'params' => '{}'],
        ] );
        $db->table( 'content' )->where( 'id', 1 )->update( ['introtext' => '<p>{loadposition BANNER}</p><p>{loadposition plain}</p><p>{loadmoduleid 6}</p>'
            . '<p>{loadposition single,none}</p><p>{loadposition single,}</p><p>{loadmodule custom,Fifth}</p><p>{loadmodule mod_custom,Fifth,none}</p><p>{field 1}</p>'] );
        $text = function() {
            $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com'] ) );
            return implode( '', array_map( fn( $element ) => (string) ( $element->data->text ?? $element->data->title ?? '' ), (array) PageView::where( 'name', 'About' )->firstOrFail()->content ) );
        };

        // positions are compared case insensitive, modules styles show the titles of modules with content except "none" overwritten by the style of the module
        $this->assertSame( 'AboutFirst<p>One</p><p>Two</p>Third<p>Three</p><p>Four</p><p>Five</p><p>Five</p><p>Five</p>Fifth<p>Five</p><p>Five</p>', $text() );
        // valid header tags of the modules are used
        $levels = Collection::make( (array) PageView::where( 'name', 'About' )->firstOrFail()->content )->pluck( 'data.level', 'data.title' )->all();
        $this->assertSame( 4, $levels['Third'] ?? null );

        // shortcodes of disabled plugins are kept
        $db->table( 'extensions' )->where( 'type', 'plugin' )->update( ['enabled' => 0] );
        $this->assertSame( 'About<p>{loadposition BANNER}</p><p>{loadposition plain}</p><p>{loadmoduleid 6}</p><p>{loadposition single,none}</p><p>{loadposition single,}</p><p>{loadmodule custom,Fifth}</p><p>{loadmodule mod_custom,Fifth,none}</p><p>{field 1}</p>', $text() );

        // modules of disabled module types aren't shown
        $db->table( 'extensions' )->where( 'type', 'plugin' )->update( ['enabled' => 1] );
        $db->table( 'extensions' )->where( 'element', 'mod_custom' )->update( ['enabled' => 0] );
        $db->table( 'extensions' )->insert( ['type' => 'module', 'element' => 'mod_custom', 'client_id' => 1, 'params' => '{}'] );
        $this->assertSame( 'About', $text() );
    }


    public function testShortcodesInCustomModules(): void
    {
        $this->extraTables();
        $db = DB::connection( 'joomla' );
        $db->table( 'modules' )->insert( [
            ['id' => 1, 'title' => 'Outer', 'module' => 'mod_custom', 'position' => 'outer', 'content' => '<p>Outer text</p><p>{loadposition inner}</p><p>{field 1}</p><p>{loadposition outer}</p>', 'ordering' => 1, 'params' => null],
            ['id' => 2, 'title' => 'Inner', 'module' => 'mod_custom', 'position' => 'inner', 'content' => '<p>Inner text</p>', 'ordering' => 1, 'params' => '{}'],
            ['id' => 3, 'title' => 'Raw', 'module' => 'mod_custom', 'position' => 'raw', 'content' => '<p>Raw text {loadposition inner}</p>', 'ordering' => 1, 'params' => '{"prepare_content":"0"}'],
        ] );
        $db->table( 'fields' )->insert( ['id' => 1, 'context' => 'com_content.article', 'title' => 'Color', 'label' => 'Color', 'type' => 'text', 'params' => '{"display":"0"}',
            'fieldparams' => '{}', 'state' => 1, 'ordering' => 1, 'access' => 1, 'language' => '*', 'group_id' => 0] );
        $db->table( 'fields_values' )->insert( ['field_id' => 1, 'item_id' => '1', 'value' => 'Red'] );
        $db->table( 'content' )->where( 'id', 1 )->update( ['introtext' => '<p>{loadposition outer}</p><p>{loadposition raw}</p>'] );

        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com'] ) );
        $text = implode( '', array_map( fn( $element ) => (string) ( $element->data->text ?? '' ), (array) PageView::where( 'name', 'About' )->firstOrFail()->content ) );

        // custom modules replace their shortcodes by default, the fields of the article aren't available and
        // like in Joomla, the shortcodes of modules loading themselves are kept
        $this->assertSame( 1, substr_count( $text, 'Outer text' ) );
        $this->assertSame( 1, substr_count( $text, 'Inner text' ) );
        $this->assertStringNotContainsString( 'Red', $text );
        $this->assertStringNotContainsString( '{field 1}', $text );
        $this->assertSame( 1, substr_count( $text, '{loadposition outer}' ) );
        // modules whose content isn't prepared keep their shortcodes
        $this->assertStringContainsString( 'Raw text {loadposition inner}', $text );
    }


    public function testFieldVisibility(): void
    {
        $this->extraTables();
        $db = DB::connection( 'joomla' );
        $db->table( 'categories' )->insert( [
            ['id' => 10, 'extension' => 'com_content', 'title' => 'Parent', 'alias' => 'parent', 'parent_id' => 1, 'level' => 1],
            ['id' => 11, 'extension' => 'com_content', 'title' => 'Child', 'alias' => 'child', 'parent_id' => 10, 'level' => 2],
            ['id' => 12, 'extension' => 'com_content', 'title' => 'Other', 'alias' => 'other', 'parent_id' => 1, 'level' => 1],
        ] );
        $db->table( 'content' )->where( 'id', 1 )->update( ['catid' => 11, 'introtext' => '<p>{fieldgroup *}</p>'] );
        $db->table( 'content' )->insert( ['id' => 5, 'title' => 'All', 'alias' => 'all', 'language' => '*', 'catid' => 12, 'introtext' => '<p>{fieldgroup *}</p>', 'fulltext' => ''] );
        $field = ['context' => 'com_content.article', 'state' => 1, 'access' => 1, 'language' => '*', 'group_id' => 0, 'params' => '{"display":"0"}', 'fieldparams' => '{}', 'label' => '', 'type' => 'text',
            'default_value' => '', 'only_use_in_subform' => 0];
        $db->table( 'fields' )->insert( [
            ['id' => 1, 'title' => 'Inherited', 'ordering' => 1] + $field,
            ['id' => 2, 'title' => 'Assigned', 'ordering' => 2] + $field,
            ['id' => 3, 'title' => 'Default', 'ordering' => 3, 'default_value' => 'Fallback'] + $field,
            ['id' => 4, 'title' => 'Restricted', 'ordering' => 4, 'group_id' => 1] + $field,
            ['id' => 5, 'title' => 'Grouped', 'ordering' => 5, 'group_id' => 2] + $field,
            ['id' => 6, 'title' => 'Subform', 'ordering' => 6, 'only_use_in_subform' => 1] + $field,
            ['id' => 7, 'title' => 'German', 'ordering' => 7, 'language' => 'de-DE'] + $field,
            ['id' => 8, 'title' => 'Members', 'ordering' => 8, 'access' => 2] + $field,
            ['id' => 9, 'title' => 'Root', 'ordering' => 9] + $field,
        ] );
        $db->table( 'fields_groups' )->insert( [['id' => 1, 'state' => 1, 'access' => 3], ['id' => 2, 'state' => 1, 'access' => 1]] );
        $db->table( 'fields_categories' )->insert( [['field_id' => 1, 'category_id' => 10], ['field_id' => 2, 'category_id' => 12], ['field_id' => 9, 'category_id' => 1]] );
        $db->table( 'fields_values' )->insert( array_merge( ...array_map( fn( $id ) => array_map( fn( $field ) => ['field_id' => $field, 'item_id' => $id, 'value' => "Value {$field}"], [1, 2, 4, 5, 6, 7, 8, 9] ), ['1', '5'] ) ) );
        $db->table( 'fields_values' )->insert( ['field_id' => 3, 'item_id' => '1', 'value' => ''] );
        $shown = fn( $name ) => array_values( array_filter( ['Value 1', 'Value 2', 'Fallback', 'Value 4', 'Value 5', 'Value 6', 'Value 7', 'Value 8', 'Value 9'],
            fn( $text ) => str_contains( (string) json_encode( PageView::where( 'name', $name )->firstOrFail()->content ), $text ) ) );
        $import = function( bool $languages ) {
            $this->languageFilter( $languages );

            $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com'] ) );
        };

        // fields of the categories and their parents, default values for empty values, all languages without language filter,
        // no fields of restricted groups or levels and no fields only used in subforms, fields of the root category for all
        $import( false );
        $this->assertSame( ['Value 1', 'Fallback', 'Value 5', 'Value 7', 'Value 9'], $shown( 'About' ) );
        $this->assertSame( ['Value 2', 'Fallback', 'Value 5', 'Value 7', 'Value 9'], $shown( 'All' ) );

        // with language filter, only the fields of the article language and for all languages, all fields for articles of all languages
        $import( true );
        $this->assertSame( ['Value 1', 'Fallback', 'Value 5', 'Value 9'], $shown( 'About' ) );
        $this->assertSame( ['Value 2', 'Fallback', 'Value 5', 'Value 7', 'Value 9'], $shown( 'All' ) );
    }


    public function testFieldDisplayLanguages(): void
    {
        $this->extraTables();
        $db = DB::connection( 'joomla' );
        $db->table( 'content' )->insert( ['id' => 5, 'title' => 'All', 'alias' => 'all', 'language' => '*', 'introtext' => '<p>Text</p>', 'fulltext' => ''] );
        $field = ['context' => 'com_content.article', 'state' => 1, 'access' => 1, 'group_id' => 0, 'params' => '{"display":"2"}', 'fieldparams' => '{}', 'label' => '', 'type' => 'text'];
        $db->table( 'fields' )->insert( [
            ['id' => 1, 'title' => 'Any', 'ordering' => 1, 'language' => '*'] + $field,
            ['id' => 2, 'title' => 'English', 'ordering' => 2, 'language' => 'en-GB'] + $field,
            ['id' => 3, 'title' => 'German', 'ordering' => 3, 'language' => 'de-DE'] + $field,
            ['id' => 4, 'title' => 'Unpublished', 'ordering' => 4, 'language' => '*', 'state' => 0] + $field,
        ] );
        // values of unpublished fields are still stored but never shown
        $db->table( 'fields_values' )->insert( array_map( fn( $id ) => ['field_id' => $id, 'item_id' => '5', 'value' => "Value {$id}"], [1, 2, 3, 4] ) );
        $shown = function() {
            $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com'] ) );
            $json = (string) json_encode( PageView::where( 'name', 'All' )->firstOrFail()->content );
            return array_values( array_filter( ['Value 1', 'Value 2', 'Value 3', 'Value 4'], fn( $text ) => str_contains( $json, $text ) ) );
        };

        // without language filter, articles for all languages show all fields
        $this->assertSame( ['Value 1', 'Value 2', 'Value 3'], $shown() );

        // with language filter, they show the fields for all languages and the ones of the current language around the text
        $this->languageFilter();
        $this->assertSame( ['Value 1', 'Value 2'], $shown() );
    }


    public function testCategoryFields(): void
    {
        $this->extraTables();
        Schema::connection( 'joomla' )->table( 'categories', function( Blueprint $table ) {
            $table->text( 'description' )->nullable();
            $table->text( 'params' )->nullable();
        } );
        $db = DB::connection( 'joomla' );
        $db->table( 'categories' )->insert( [
            ['id' => 2, 'extension' => 'com_content', 'title' => 'News', 'alias' => 'news', 'parent_id' => 1, 'level' => 1, 'lft' => 1,
                'description' => '<p>Desc {field 11}</p><p>{field 1}</p><p>{field 16}</p>', 'params' => '{}'],
            ['id' => 3, 'extension' => 'com_content', 'title' => 'Local', 'alias' => 'local', 'parent_id' => 2, 'level' => 2, 'lft' => 2, 'description' => '', 'params' => '{}'],
        ] );
        $db->table( 'menu' )->insert( ['id' => 101, 'menutype' => 'mainmenu', 'title' => 'Blog', 'alias' => 'blog', 'parent_id' => 1, 'level' => 1, 'lft' => 1,
            'type' => 'component', 'link' => 'index.php?option=com_content&view=category&layout=blog&id=2', 'params' => '{}'] );
        $db->table( 'modules' )->insert( ['id' => 1, 'title' => 'Banner', 'module' => 'mod_custom', 'position' => 'banner', 'content' => '<p>Module</p>', 'showtitle' => 0, 'ordering' => 1] );
        $field = ['context' => 'com_content.categories', 'state' => 1, 'access' => 1, 'language' => '*', 'group_id' => 0, 'fieldparams' => '{}', 'label' => '', 'type' => 'text'];
        $db->table( 'fields' )->insert( [
            ['id' => 1, 'title' => 'Article', 'ordering' => 1, 'params' => '{"display":"2"}', 'context' => 'com_content.article'] + $field,
            ['id' => 11, 'title' => 'Mood', 'ordering' => 11, 'params' => '{"display":"0"}'] + $field,
            ['id' => 12, 'title' => 'Top', 'ordering' => 12, 'params' => '{"display":"1"}'] + $field,
            ['id' => 13, 'title' => 'Intro', 'ordering' => 13, 'params' => '{"display":"2"}'] + $field,
            ['id' => 14, 'title' => 'Outro', 'ordering' => 14, 'params' => '{"display":"3"}', 'type' => 'editor'] + $field,
            ['id' => 15, 'title' => 'Local', 'ordering' => 15, 'params' => '{"display":"2"}'] + $field,
            ['id' => 16, 'title' => 'Note', 'ordering' => 16, 'params' => '{"display":"0"}', 'type' => 'textarea'] + $field,
        ] );
        $db->table( 'tags' )->insert( ['id' => 2, 'title' => 'Topic', 'published' => 1, 'access' => 1, 'lft' => 1] );
        $db->table( 'contentitem_tag_map' )->insert( ['type_alias' => 'com_content.category', 'content_item_id' => 2, 'tag_id' => 2] );
        $db->table( 'content' )->where( 'id', 1 )->update( ['catid' => 3] );
        $db->table( 'fields_categories' )->insert( ['field_id' => 15, 'category_id' => 3] );
        $db->table( 'fields_values' )->insert( [
            ['field_id' => 1, 'item_id' => '2', 'value' => 'Article value'],
            ['field_id' => 11, 'item_id' => '2', 'value' => 'Happy'],
            ['field_id' => 12, 'item_id' => '2', 'value' => 'Above'],
            ['field_id' => 13, 'item_id' => '2', 'value' => 'Before'],
            ['field_id' => 14, 'item_id' => '2', 'value' => '<p>After {loadposition banner} {field 11}</p>'],
            ['field_id' => 15, 'item_id' => '2', 'value' => 'Hidden value'],
            ['field_id' => 15, 'item_id' => '3', 'value' => 'Local value'],
            ['field_id' => 16, 'item_id' => '2', 'value' => '<p>Note {loadposition banner}</p>'],
        ] );
        $json = function( string $name ) {
            $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com'] ) );
            return (string) json_encode( PageView::where( 'name', $name )->firstOrFail()->content );
        };

        // the fields of the categories are shown around the description in the order of Joomla, the field shortcodes use the category values
        $blog = $json( 'Blog' );
        $positions = array_map( fn( $text ) => strpos( $blog, $text ), ['News', 'Top: ', 'Above', 'Topic', 'Intro: ', 'Before', 'Desc', 'Mood: ', 'Happy', 'Note', 'After'] );
        $this->assertNotContains( false, $positions );
        $sorted = $positions;
        sort( $sorted );
        $this->assertSame( $sorted, $positions );
        // the content plugins replace the module shortcodes in editor and text area fields, but not the field shortcodes
        $this->assertStringContainsString( '{field 11}', $blog );
        $this->assertStringNotContainsString( '{loadposition', $blog );
        $this->assertSame( 2, substr_count( $blog, 'Module' ) );
        // fields of the articles and the ones assigned to other categories aren't shown
        $this->assertStringNotContainsString( 'Article value', $blog );
        $this->assertStringNotContainsString( 'Hidden value', $blog );
        // fields assigned to categories are shown for these categories
        $this->assertStringContainsString( 'Local value', $json( 'Local' ) );

        // the fields around the description are shown by the "System - Fields" plugin, the field shortcodes by the "Content - Fields" plugin
        $db->table( 'extensions' )->where( 'folder', 'system' )->where( 'element', 'fields' )->update( ['enabled' => 0] );
        $blog = $json( 'Blog' );
        $this->assertStringContainsString( 'Mood: <\\/strong>Happy', $blog );
        $this->assertStringNotContainsString( 'Above', $blog );

        // the fields are shown even if the description is hidden
        $db->table( 'extensions' )->where( 'folder', 'system' )->where( 'element', 'fields' )->update( ['enabled' => 1] );
        $db->table( 'menu' )->where( 'id', 101 )->update( ['params' => '{"show_description":"0"}'] );
        $db->table( 'fields_values' )->where( 'field_id', 13 )->update( ['value' => 'Changed'] );
        $blog = $json( 'Blog' );
        $this->assertStringNotContainsString( 'Desc', $blog );
        $this->assertStringNotContainsString( 'Mood', $blog );
        $this->assertStringContainsString( 'Changed', $blog );

        // the field shortcodes of the description use the fields assigned to other categories too
        $db->table( 'menu' )->where( 'id', 101 )->update( ['params' => '{}'] );
        $db->table( 'categories' )->where( 'id', 2 )->update( ['description' => '<p>Desc {field 15}</p>'] );
        $this->assertStringContainsString( 'Hidden value', $json( 'Blog' ) );

        // descriptions with markup only but without text or media are empty
        $db->table( 'categories' )->where( 'id', 2 )->update( ['description' => ''] );
        $empty = (string) preg_replace( '/"id":"[^"]+"/', '', $json( 'Blog' ) );
        $db->table( 'categories' )->where( 'id', 2 )->update( ['description' => "<p> </p>\n<hr>"] );
        $this->assertSame( $empty, preg_replace( '/"id":"[^"]+"/', '', $json( 'Blog' ) ) );
    }


    public function testJoomla3CategoryBlogSubheadingAndMediaDescriptions(): void
    {
        $this->extraTables();
        Schema::connection( 'joomla' )->table( 'categories', fn( Blueprint $table ) => $table->text( 'description' )->nullable() );
        $db = DB::connection( 'joomla' );
        $this->setJoomlaVersion( 3 );
        $db->table( 'categories' )->insert( [
            ['id' => 2, 'extension' => 'com_content', 'title' => 'News', 'alias' => 'news', 'parent_id' => 1, 'level' => 1, 'lft' => 1,
                'description' => '<p><iframe src="//www.youtube.com/watch?v=abcdefg" width="560" height="50em" title="Clip &amp; more"></iframe></p>'],
            ['id' => 3, 'extension' => 'com_content', 'title' => 'Sport', 'alias' => 'sport', 'parent_id' => 1, 'level' => 1, 'lft' => 2,
                'description' => '<p><iframe src="javascript:alert(1)"></iframe> </p>'],
        ] );
        $db->table( 'menu' )->insert( [
            ['id' => 101, 'menutype' => 'mainmenu', 'title' => 'Blog', 'alias' => 'blog', 'parent_id' => 1, 'level' => 1, 'lft' => 1,
                'type' => 'component', 'link' => 'index.php?option=com_content&view=category&layout=blog&id=2', 'params' => '{"page_subheading":"Latest"}'],
            ['id' => 102, 'menutype' => 'mainmenu', 'title' => 'List', 'alias' => 'list', 'parent_id' => 1, 'level' => 1, 'lft' => 2,
                'type' => 'component', 'link' => 'index.php?option=com_content&view=category&id=3', 'params' => '{"page_subheading":"Latest","show_category_title":"0"}'],
        ] );
        $headings = function( string $path ) {
            $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com'] ) );
            $content = (array) PageView::where( 'path', $path )->firstOrFail()->content;
            return array_values( array_map( fn( $element ) => $element->data->title ?? null, array_filter( $content, fn( $element ) => $element->type === 'heading' ) ) );
        };

        // the blog layout of Joomla 3 shows the subheading of the menu item before the category title, the list layout doesn't
        $this->assertSame( ['Latest News'], $headings( 'blog' ) );
        $this->assertSame( [], $headings( 'list' ) );
        // descriptions with iframes only are shown, iframes without web URL are removed
        $iframe = (array) PageView::where( 'path', 'blog' )->firstOrFail()->content;
        $this->assertSame( ['heading', 'html'], array_column( $iframe, 'type' ) );
        $this->assertStringContainsString( '<iframe src="https://www.youtube-nocookie.com/embed/abcdefg"', $iframe[1]->data->text ?? '' );
        $this->assertStringContainsString( 'title="Clip &amp; more"', $iframe[1]->data->text ?? '' );
        $this->assertStringContainsString( 'width="560"', $iframe[1]->data->text ?? '' );
        $this->assertStringNotContainsString( '50em', $iframe[1]->data->text ?? '' );
        $this->assertSame( [], array_filter( (array) PageView::where( 'path', 'list' )->firstOrFail()->content, fn( $element ) => $element->type !== 'heading' ) );

        // the subheading is shown without the category title if it's hidden
        $db->table( 'menu' )->where( 'id', 101 )->update( ['params' => '{"page_subheading":"Latest","show_category_title":"0"}'] );
        $this->assertSame( ['Latest'], $headings( 'blog' ) );
        $this->setJoomlaVersion( 4 );
        $this->assertSame( [], $headings( 'blog' ) );
    }


    public function testMetaDescriptionOfTheContentLanguage(): void
    {
        $db = $this->metaDescriptions();
        $description = fn( string $name ) => PageView::where( 'name', $name )->firstOrFail()->meta->{'meta-tags'}->data->description ?? null;

        // without language filter, the pages without meta description use the one of the default site language, British English by default
        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com'] ) );
        $this->assertSame( 'English site', $description( 'Nur Deutsch' ) );

        $db->table( 'extensions' )->insert( ['element' => 'com_languages', 'params' => '{"site":"de-DE"}'] );
        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com'] ) );
        $this->assertSame( 'Own', $description( 'About' ) );
        $this->assertSame( 'Deutsche Seite', $description( 'Any' ) );
        $this->assertSame( 'Deutsche Seite', $description( 'Nur Deutsch' ) );

        // languages whose site language isn't enabled have no meta description
        $db->table( 'extensions' )->insert( [
            ['type' => 'language', 'element' => 'en-GB', 'params' => '{}', 'enabled' => 1],
            ['type' => 'language', 'element' => 'de-DE', 'params' => '{}', 'enabled' => 0],
        ] );
        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com'] ) );
        $this->assertNull( $description( 'Any' ) );
    }


    public function testMetaDescriptionOfTheContentLanguageWithLanguageFilter(): void
    {
        $db = $this->metaDescriptions();
        $description = fn( string $name ) => PageView::where( 'name', $name )->firstOrFail()->meta->{'meta-tags'}->data->description ?? null;

        // with language filter, the one of their language or the import language for all languages
        $this->languageFilter();
        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com', '--language' => ['en-GB:en', 'de-DE:de']] ) );
        $this->assertSame( 'Own', $description( 'About' ) );
        $this->assertSame( 'English site', $description( 'Any' ) );
        $this->assertSame( 'Deutsche Seite', PageView::language( 'de' )->findOrFail( PageView::where( 'name', 'About' )->firstOrFail()->id )->meta->{'meta-tags'}->data->description ?? null );

        // unpublished languages have no meta description
        $db->table( 'languages' )->where( 'lang_code', 'en-GB' )->update( ['published' => 0] );
        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com', '--language' => ['en-GB:en', 'de-DE:de']] ) );
        $this->assertNull( $description( 'Any' ) );
    }


    public function testMetaDescriptionOfMenuTranslationsShowingCategoriesForAllLanguages(): void
    {
        $db = $this->metaDescriptions();
        $schema = Schema::connection( 'joomla' );
        $schema->table( 'menu', function( Blueprint $table ) {
            $table->boolean( 'home' )->default( false );
            $table->string( 'language' )->default( '*' );
        } );
        $schema->table( 'categories', fn( Blueprint $table ) => $table->string( 'language' )->default( '*' ) );
        $this->languageFilter();
        $db->table( 'categories' )->insert( ['id' => 2, 'extension' => 'com_content', 'title' => 'News', 'alias' => 'news', 'parent_id' => 1, 'level' => 1, 'lft' => 1] );
        $link = 'index.php?option=com_content&view=category&layout=blog&id=2';
        $db->table( 'menu' )->insert( [
            ['id' => 101, 'menutype' => 'mainmenu', 'title' => 'News', 'alias' => 'news', 'language' => 'en-GB', 'link' => $link, 'type' => 'component', 'lft' => 1, 'params' => '{}'],
            ['id' => 201, 'menutype' => 'mainmenu-de', 'title' => 'Neuigkeiten', 'alias' => 'neuigkeiten', 'language' => 'de-DE', 'link' => $link, 'type' => 'component', 'lft' => 2, 'params' => '{}'],
        ] );
        $db->table( 'associations' )->insert( [
            ['id' => 101, 'context' => 'com_menus.item', 'key' => 'news'],
            ['id' => 201, 'context' => 'com_menus.item', 'key' => 'news'],
        ] );

        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com', '--language' => ['de-DE:de']] ) );

        // categories for all languages are shown on the site of the language of the menu item
        $page = PageView::where( 'name', 'News' )->firstOrFail();
        $de = PageView::language( 'de' )->findOrFail( $page->id );
        $this->assertSame( 'English site', $page->meta->{'meta-tags'}->data->description ?? null );
        $this->assertSame( 'Deutsche Seite', $de->meta->{'meta-tags'}->data->description ?? null );
    }


    public function testTimezoneOptionConvertsTheDatesOfCalendarFields(): void
    {
        $this->extraTables();
        $db = DB::connection( 'joomla' );
        $db->table( 'fields' )->insert( ['id' => 1, 'context' => 'com_content.article', 'title' => 'Date', 'label' => 'Date', 'type' => 'calendar', 'params' => '{"showlabel":"0"}',
            'fieldparams' => '{"showtime":"1"}', 'state' => 1, 'ordering' => 1, 'access' => 1, 'language' => '*', 'group_id' => 0] );
        $db->table( 'fields_values' )->insert( ['field_id' => 1, 'item_id' => '1', 'value' => '2024-05-01 23:30:00'] );

        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com'] ) );
        $this->assertStringContainsString( '2024-05-01 23:30', (string) json_encode( PageView::where( 'name', 'About' )->firstOrFail()->content ) );

        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com', '--timezone' => 'Europe/Berlin'] ) );
        $this->assertStringContainsString( '2024-05-02 01:30', (string) json_encode( PageView::where( 'name', 'About' )->firstOrFail()->content ) );

        $this->assertSame( 1, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com', '--timezone' => 'Mars/Base'] ) );
        $this->assertStringContainsString( 'Invalid time zone for --timezone: Mars/Base', Artisan::output() );
    }


    public function testEmailCloakPluginLinksEmailAddresses(): void
    {
        $this->extraTables();
        $db = DB::connection( 'joomla' );
        $db->table( 'extensions' )->insert( ['type' => 'plugin', 'folder' => 'content', 'element' => 'emailcloak', 'params' => '{"mode":"1"}'] );
        $db->table( 'content' )->where( 'id', 1 )->update( ['introtext' => '<p>Mail info@example.com</p><hr class="system-pagebreak" title="Kept"><p>{loadmoduleid 7}</p>'] );
        $db->table( 'content' )->where( 'id', 4 )->update( ['introtext' => '{emailcloak=off}<p>Mail info@example.com</p>'] );
        $db->table( 'modules' )->insert( ['id' => 7, 'title' => 'Contact', 'module' => 'mod_custom', 'position' => 'side', 'content' => '{emailcloak=off}<p>Module text</p>',
            'showtitle' => 0, 'ordering' => 1, 'published' => 1, 'access' => 1] );
        $db->table( 'fields' )->insert( ['id' => 1, 'context' => 'com_content.article', 'title' => 'Contact', 'label' => 'Contact', 'type' => 'textarea', 'params' => '{"display":"2"}',
            'fieldparams' => '{}', 'state' => 1, 'ordering' => 1, 'access' => 1, 'language' => '*', 'group_id' => 0] );
        $db->table( 'fields_values' )->insert( ['field_id' => 1, 'item_id' => '1', 'value' => 'Ask sales@example.com'] );

        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com'] ) );

        // the page breaks are kept without the plugin and the e-mail addresses in the field values are linked too
        $json = stripslashes( (string) json_encode( PageView::where( 'name', 'About' )->firstOrFail()->content ) );
        $this->assertStringContainsString( '<p>Mail <a href="mailto:info@example.com">info@example.com</a></p><hr class="system-pagebreak"', $json );
        $this->assertStringContainsString( 'Ask <a href="mailto:sales@example.com">sales@example.com</a>', $json );
        // the modules prepared by the content plugins remove their own shortcodes
        $this->assertStringContainsString( '<p>Module text</p>', $json );
        $this->assertStringNotContainsString( 'emailcloak', $json );
        $this->assertStringNotContainsString( 'mailto', (string) json_encode( PageView::where( 'name', 'Nur Deutsch' )->firstOrFail()->content ) );

        // like Joomla, only the shortcode in lower case turns the plugin off
        $db->table( 'content' )->where( 'id', 4 )->update( ['introtext' => '{EMAILCLOAK=OFF}<p>Mail info@example.com</p>'] );
        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com'] ) );
        $json = stripslashes( (string) json_encode( PageView::where( 'name', 'Nur Deutsch' )->firstOrFail()->content ) );
        $this->assertStringContainsString( '{EMAILCLOAK=OFF}<p>Mail <a href="mailto:info@example.com">info@example.com</a></p>', $json );

        // the links are only added in mode 1
        $db->table( 'extensions' )->where( 'element', 'emailcloak' )->update( ['params' => '{"mode":"0"}'] );
        $db->table( 'content' )->where( 'id', 1 )->update( ['introtext' => '<p>Write to info@example.com <a href="mailto:x@example.com">Mail us</a></p>'] );
        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com'] ) );
        // existing links to e-mail addresses become text too
        $json = (string) json_encode( PageView::where( 'name', 'About' )->firstOrFail()->content );
        $this->assertStringNotContainsString( 'mailto', $json );
        $this->assertStringContainsString( 'Mail us', $json );

        // without the plugin, its shortcode is kept
        $db->table( 'extensions' )->where( 'element', 'emailcloak' )->delete();
        $db->table( 'content' )->where( 'id', 4 )->update( ['introtext' => '{emailcloak=off}<p>Mail info@example.com</p>'] );
        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com'] ) );
        $this->assertStringContainsString( '{emailcloak=off}', (string) json_encode( PageView::where( 'name', 'Nur Deutsch' )->firstOrFail()->content ) );
    }


    public function testUntitledPageBreaksUseTheLanguageOfThePage(): void
    {
        $this->extraTables();
        app( 'translator' )->addLines( ['*.Page :num' => 'Seite :num'], 'de' );
        $db = DB::connection( 'joomla' );
        $db->table( 'extensions' )->insert( ['type' => 'plugin', 'folder' => 'content', 'element' => 'pagebreak', 'params' => '{}'] );
        $db->table( 'content' )->where( 'id', 1 )->update( ['language' => '*', 'introtext' => '<p>A</p><hr class="system-pagebreak"><p>B</p>', 'fulltext' => ''] );

        // articles for all languages use the import language
        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com', '--lang' => 'de'] ) );

        $json = (string) json_encode( PageView::where( 'name', 'About' )->firstOrFail()->content );
        $this->assertStringContainsString( 'Seite 2', $json );
    }


    public function testPageBreakTitlesAreStoredDecoded(): void
    {
        $this->extraTables();
        $db = DB::connection( 'joomla' );
        $db->table( 'extensions' )->insert( ['type' => 'plugin', 'folder' => 'content', 'element' => 'pagebreak', 'params' => '{}'] );
        $db->table( 'content' )->where( 'id', 1 )->update( ['introtext' => '<p>A</p><hr class="system-pagebreak" title="Q&amp;A &lt;1&gt;"><p>B</p>', 'fulltext' => ''] );

        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com'] ) );

        // the content stores the raw text of the heading titles, the frontend escapes them
        $headings = array_values( array_filter( (array) PageView::where( 'name', 'About' )->firstOrFail()->content, fn( $element ) => ( $element->type ?? '' ) === 'heading' ) );
        $this->assertSame( ['About', 'Q&A <1>'], array_map( fn( $element ) => $element->data->title ?? null, $headings ) );
    }


    public function testOnlyPageBreaksOfArticlesBecomeHeadings(): void
    {
        $this->extraTables();
        Schema::connection( 'joomla' )->table( 'categories', fn( Blueprint $table ) => $table->text( 'description' )->nullable() );
        $db = DB::connection( 'joomla' );
        $db->table( 'extensions' )->insert( ['type' => 'plugin', 'folder' => 'content', 'element' => 'pagebreak', 'params' => '{}'] );
        $db->table( 'categories' )->insert( ['id' => 2, 'extension' => 'com_content', 'title' => 'News', 'alias' => 'news', 'parent_id' => 1, 'level' => 1, 'lft' => 1,
            'description' => '<p>All news</p><hr class="system-pagebreak" title="Kept">'] );
        $db->table( 'menu' )->insert( ['id' => 101, 'menutype' => 'mainmenu', 'title' => 'Blog', 'alias' => 'blog', 'parent_id' => 1, 'level' => 1, 'lft' => 1,
            'type' => 'component', 'link' => 'index.php?option=com_content&view=category&layout=blog&id=2', 'params' => '{}'] );

        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com'] ) );

        $json = stripslashes( (string) json_encode( PageView::where( 'path', 'blog' )->firstOrFail()->content ) );
        $this->assertStringContainsString( '<hr class="system-pagebreak" title="Kept"', $json );
        $this->assertStringNotContainsString( '"title":"Kept"', $json );
    }


    public function testFieldPluginsAndCategoriesOfTheFields(): void
    {
        $this->extraTables();
        $db = DB::connection( 'joomla' );
        $db->table( 'categories' )->insert( [
            ['id' => 10, 'extension' => 'com_content', 'title' => 'Parent', 'alias' => 'parent', 'parent_id' => 1, 'level' => 1, 'published' => 2],
            ['id' => 11, 'extension' => 'com_content', 'title' => 'Child', 'alias' => 'child', 'parent_id' => 10, 'level' => 2, 'published' => 1],
        ] );
        $db->table( 'content' )->where( 'id', 1 )->update( ['catid' => 11, 'introtext' => '<p>{fieldgroup *}</p>'] );
        $field = ['context' => 'com_content.article', 'state' => 1, 'access' => 1, 'language' => '*', 'group_id' => 0, 'params' => '{"display":"0"}', 'fieldparams' => '{}', 'label' => '',
            'type' => 'text', 'default_value' => '', 'only_use_in_subform' => 0];
        $db->table( 'fields' )->insert( [
            ['id' => 1, 'title' => 'Inherited', 'ordering' => 1] + $field,
            ['id' => 2, 'title' => 'Own', 'ordering' => 2] + $field,
            ['id' => 3, 'title' => 'Color', 'ordering' => 3, 'type' => 'color'] + $field,
        ] );
        $db->table( 'fields_categories' )->insert( [['field_id' => 1, 'category_id' => 10], ['field_id' => 2, 'category_id' => 11]] );
        $db->table( 'fields_values' )->insert( array_map( fn( $id ) => ['field_id' => $id, 'item_id' => '1', 'value' => "Value {$id}"], [1, 2, 3] ) );
        $db->table( 'extensions' )->where( 'folder', 'fields' )->where( 'element', 'color' )->update( ['enabled' => 0] );

        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com'] ) );
        $json = stripslashes( (string) json_encode( PageView::where( 'name', 'About' )->firstOrFail()->content ) );

        // Joomla doesn't know the parents of archived categories and doesn't show fields of disabled field plugins
        $this->assertStringNotContainsString( 'Value 1', $json );
        $this->assertStringContainsString( 'Value 2', $json );
        $this->assertStringNotContainsString( 'Value 3', $json );

        // the options of the field plugins are used if the fields don't set them
        $db->table( 'extensions' )->where( 'folder', 'fields' )->where( 'element', 'imagelist' )->update( ['params' => '{"directory":"photos"}'] );

        $field = (object) ['id' => 4, 'title' => 'Gallery', 'type' => 'imagelist', 'params' => '{"showlabel":"0"}', 'fieldparams' => '{"directory":""}'];
        $this->assertSame( '<p><img src="images/photos/a.jpg" alt=""></p>', $this->fields( [$field] )->field( 4, new Joomla\FieldContext( [4 => ['a.jpg']] ) ) );
        // options set by the fields are used before the ones of the field plugins
        $field->fieldparams = '{"directory":"logos"}';
        $this->assertSame( '<p><img src="images/logos/a.jpg" alt=""></p>', $this->fields( [$field] )->field( 4, new Joomla\FieldContext( [4 => ['a.jpg']] ) ) );
    }


    public function testCategoryDescriptionsArePreparedTwice(): void
    {
        $this->extraTables();
        Schema::connection( 'joomla' )->table( 'categories', fn( Blueprint $table ) => $table->text( 'description' )->nullable() );
        $db = DB::connection( 'joomla' );
        $db->table( 'modules' )->insert( [
            ['id' => 1, 'title' => 'Inner', 'module' => 'mod_custom', 'position' => 'inner', 'content' => '<p>Inner text</p>', 'ordering' => 1, 'params' => '{}'],
            ['id' => 2, 'title' => 'Raw', 'module' => 'mod_custom', 'position' => 'raw', 'content' => '<p>Raw text {loadposition inner}</p>', 'ordering' => 1, 'params' => '{"prepare_content":"0"}'],
        ] );
        $db->table( 'categories' )->insert( ['id' => 3, 'extension' => 'com_content', 'title' => 'News', 'alias' => 'news', 'parent_id' => 1, 'level' => 2, 'lft' => 1,
            'description' => '<p>{loadposition raw}</p>'] );
        $db->table( 'content' )->where( 'id', 1 )->update( ['catid' => 3, 'introtext' => '<p>{loadposition raw}</p>'] );

        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com'] ) );
        $text = fn( string $name ) => (string) json_encode( PageView::where( 'name', $name )->firstOrFail()->content );

        // the shortcodes in the output of the first run are replaced in the category description but not in the article
        $this->assertStringContainsString( 'Inner text', $text( 'News' ) );
        $this->assertStringNotContainsString( '{loadposition inner}', $text( 'News' ) );
        $this->assertStringContainsString( '{loadposition inner}', $text( 'About' ) );
    }
}
