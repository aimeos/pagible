<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Import\Joomla;

use Aimeos\Cms\Import\Elements;
use Aimeos\Cms\Import\Pages as BasePages;


/**
 * Builds the page data and the content of the pages of the Joomla articles, categories and menu items.
 *
 * There's one builder per page kind, used for the pages in the import language and their translations, so the
 * import commands only save the pages. Joomla shows the articles and categories depending on the options of the
 * menu items, the articles component and the articles or categories themselves and restricts them by their access
 * levels. The options are combined by Options, the headings are built by Headings and the browser
 * titles and meta data by PageMeta, which are created here.
 *
 * PageContent: content elements, IDs of the used files and meta data of a page
 *
 * @phpstan-import-type ContentElements from Elements
 * @phpstan-type PageContent array{elements: array<int, array<string, mixed>>, fileIds: array<int, string>, meta: array<string, mixed>}
 */
class Pages
{
    /** Tags of embedded media which are shown in category descriptions without text */
    protected const MEDIA_TAGS = '<img><iframe>';
    /** Position of the info block with the tags above the text */
    protected const INFO_ABOVE = '0';
    /** Position of the info block with the tags below the text */
    protected const INFO_BELOW = '1';
    /** Position of the info block split above and below the text, the tags are below */
    protected const INFO_SPLIT = '2';

    /** Options of the articles, categories and menu items */
    protected const CATEGORY_IMAGE = 'image';
    protected const CATEGORY_IMAGE_ALT = 'image_alt';
    protected const INFO_POSITION = 'info_block_position';
    protected const MENU_SHOW = 'menu_show';
    protected const PAGE_SUBHEADING = 'page_subheading';
    protected const SHOW_CATEGORY_TAGS = 'show_cat_tags';
    protected const SHOW_DESCRIPTION = 'show_description';
    protected const SHOW_DESCRIPTION_IMAGE = 'show_description_image';
    protected const SHOW_INTRO = 'show_intro';
    protected const SHOW_NOAUTH = 'show_noauth';
    protected const SHOW_TAGS = 'show_tags';

    /** Suffixes of the keys of the alternative text and the caption of an article image, e.g. "image_intro_alt" */
    protected const IMAGE_ALT_SUFFIX = '_alt';
    protected const IMAGE_CAPTION_SUFFIX = '_caption';

    /** Combines the options of the articles, categories, menu items and the articles component */
    protected Options $options;
    /** Builds the headings above the articles and categories */
    protected Headings $headings;
    /** Builds the browser titles and the meta data of the pages */
    protected PageMeta $pageMeta;


    /**
     * Initializes the object.
     *
     * @param Site $site Settings of the Joomla site with the access checks of the articles, categories and menu items
     * @param Content $content Builds the content elements of the articles and categories
     * @param Articles $articles Imported articles with the menu items whose URL, title and options Joomla uses for them
     * @param Links $links Returns the targets of the URL and alias menu items
     * @param Texts $texts Prefetched texts of the articles
     * @param Host $host Functions of the import command importing the images and returning the page data
     */
    public function __construct(
        protected Site $site,
        protected Content $content,
        protected Articles $articles,
        protected Links $links,
        protected Texts $texts,
        protected Host $host
    ) {
        $this->options = new Options( $site->defaults );
        $this->headings = new Headings();
        $this->pageMeta = new PageMeta( $site );
    }


    /**
     * Returns the page data and the content of the article or translation and why guests can't see the article.
     *
     * Teasers of restricted articles are only built without given status because their pages are published, a given
     * status like BasePages::STATUS_DISABLED of the page in the import language would hide them anyway.
     *
     * @param Routes $routes Routes of the language of the article
     * @param int|null $status Status of the page regardless of the article, e.g. BasePages::STATUS_DISABLED if the page
     *  in the import language is disabled, or NULL to use the status of the article and allow teasers
     * @return Page Page data, content, if it's a teaser and why guests can't see the article
     */
    public function articlePage( \stdClass $article, string $slug, Routes $routes, ?int $status = null ): Page
    {
        $display = $this->articleDisplay( $article, $routes );
        return $this->article( $article, $slug, $status, $status === null, BasePages::TAG_PAGE, $display, Row::language( $article ) );
    }


    /**
     * Returns the name of the page or variant of the article or translation built by articlePage().
     *
     * The import keeps the pages of unchanged articles only if their names are still the same, so it compares them by this name.
     *
     * @param \stdClass $article Article in the import language or a translation
     * @return string Title of the menu item Joomla uses for the article or the title of the article
     */
    public function articleName( \stdClass $article ): string
    {
        return self::pageName( $article, $this->ownItem( $article ) );
    }


    /**
     * Returns the page data and the content of the variant of the root page showing the article of the home menu item of another language.
     *
     * Articles for all languages are shown in the language of the home menu item, the same article can be shown by several ones.
     *
     * @param \stdClass $article Article shown by the home menu item
     * @param string $slug URL path of the variant
     * @param Routes $routes Routes of the language of the home menu item
     * @param \stdClass $item Home menu item of the other language showing the article
     * @param string $language Joomla language tag of the home menu item the article is shown in
     * @return Page Page data, content, if it's a teaser and why guests can't see the article
     */
    public function homeTranslation( \stdClass $article, string $slug, Routes $routes, \stdClass $item, string $language ): Page
    {
        return $this->article( $article, $slug, null, true, BasePages::TAG_PAGE, $this->articleDisplay( $article, $routes, $item ), $language );
    }


    /**
     * Returns the page data and the content of the root page showing the article of the home menu item.
     *
     * @param \stdClass $article Article shown by the home menu item
     * @param \stdClass $menuItem Home menu item showing the article
     * @return Page Page data and content of the root page
     */
    public function homeArticlePage( \stdClass $article, \stdClass $menuItem ): Page
    {
        return $this->article( $article, '', BasePages::STATUS_PUBLISHED, false, BasePages::TAG_ROOT, Display::menu( $menuItem ), Row::language( $article ) );
    }


    /**
     * Returns the page data and the content of the root page for a home menu item which doesn't show an article, see home().
     *
     * @param array<string, mixed> $data Page data of the root page
     * @param \stdClass|null $category Category of the home menu item or NULL if it shows no category
     * @return Page Page data and content of the root page
     */
    public function homePage( \stdClass $menuItem, array $data, ?\stdClass $category = null ): Page
    {
        return $this->home( Display::menu( $menuItem ), $data, $category );
    }


    /**
     * Returns the page data and content of a category without menu item or of its translation.
     *
     * Like Joomla, the category and its parents are checked also if a menu item links to it.
     *
     * @param \stdClass $category Category in the import language or a translation
     * @param string $slug URL path of the page
     * @param Routes $routes Routes of the language of the category
     * @param \stdClass|null $item Menu item linking to the category, e.g. one of another menu or the own one of the translation
     * @param int|null $status Status of the page regardless of the category, e.g. BasePages::STATUS_DISABLED if the page
     *  in the import language is disabled, or NULL to use the status of the category
     * @return Page Page data, content and why guests can't see the category
     */
    public function categoryPage( \stdClass $category, string $slug, Routes $routes, ?\stdClass $item, ?int $status = null ): Page
    {
        $reason = $this->site->access->categoryRestriction( $category );
        $display = Display::shown( $item, $reason, $routes, (int) $category->id );

        $data = $this->folderData( Row::title( $category ), $slug, $display, $status );
        return new Page( $data, $this->categoryContent( $category, $display ), reason: $reason );
    }


    /**
     * Returns the page data and content of a menu heading or the page of a menu item showing a category.
     *
     * @param string $slug URL path of the page
     * @param string|null $reason Why guests can't see the menu item or NULL if they can
     * @param \stdClass|null $category Category shown by the menu item or NULL for menu headings
     * @param int|null $status Status of the page regardless of the reason, e.g. BasePages::STATUS_DISABLED if the page
     *  in the import language is disabled, or NULL to use the reason and the menu item
     * @return Page Page data, content and why guests can't see the menu item
     */
    public function menuPage( \stdClass $menuItem, string $slug, ?string $reason, ?\stdClass $category = null, ?int $status = null ): Page
    {
        $display = Display::menu( $menuItem, $reason );
        $data = $this->folderData( Row::menuTitle( $menuItem ), $slug, $display, $status );

        return new Page( $data, $this->categoryContent( $category, $display ), reason: $reason );
    }


    /**
     * Returns the page data of the link page of a URL or alias menu item.
     *
     * Link pages have no content like the link pages saved by Commands::saveLink().
     *
     * @param string $slug URL path of the page
     * @param string $to Target of the link page
     * @param string|null $reason Why guests can't see the menu item or NULL if they can
     * @param int|null $status Status of the page regardless of the reason, e.g. BasePages::STATUS_DISABLED if the page
     *  in the import language is disabled, or NULL to use the reason and the menu item
     * @return Page Page data including the link target, empty content and why guests can't see the menu item
     */
    public function linkPage( \stdClass $menuItem, string $slug, string $to, ?string $reason, ?int $status = null ): Page
    {
        $data = ['to' => $to] + $this->folderData( Row::menuTitle( $menuItem ), $slug, Display::menu( $menuItem, $reason ), $status );
        return new Page( $data, Elements::content() + ['meta' => []], reason: $reason );
    }


    /**
     * Returns the page data and content of the translation of a menu item.
     *
     * The translations are restricted like the menu items in the import language. Home menu items of other languages
     * and the translations of the home menu item are root page variants. Translations without supported link target or
     * whose category isn't shown aren't imported, see CategoryItems::translationPaged().
     *
     * @param \stdClass $menuItem Associated menu item in another language
     * @param string $slug URL path of the translation
     * @param int|null $status Status of the page regardless of the menu item, e.g. BasePages::STATUS_DISABLED if the page
     *  in the import language is disabled, or NULL to use the status of the menu item
     * @return Page|null Page data, content and why guests can't see the menu item or NULL if the translation isn't imported
     */
    public function menuTranslation( \stdClass $menuItem, string $slug, ?int $status = null ): ?Page
    {
        // the same check decides which translations claim URL paths, so links never point to translations without variant
        if( !$this->links->menuTranslated( $menuItem ) ) {
            return null;
        }

        if( MenuLink::of( $menuItem )->kind === MenuLink::KIND_LINK )
        {
            // aliases to articles or categories which aren't imported still have no target
            $to = $this->links->menuItemLink( $menuItem );
            return $to !== null ? $this->linkPage( $menuItem, $slug, $to, $this->site->access->restriction( $menuItem ), $status ) : null;
        }

        $catId = MenuItem::category( $menuItem );
        // menu items of categories show the category in their language, unpublished or trashed categories are ruled out before
        $category = $catId ? $this->site->category( $catId ) : null;

        $reason = $this->site->access->categoryItemRestriction( $menuItem, $catId );

        if( !MenuItem::home( $menuItem ) ) {
            return $this->menuPage( $menuItem, $slug, $reason, $category, $status );
        }

        // the browser title is set by home() like for the root page
        $display = Display::menu( $menuItem, $reason );
        return $this->home( $display, $this->folderData( Row::menuTitle( $menuItem ), $slug, $display, $status ), $category );
    }


    /**
     * Returns the page data of a redirect imported as hidden link page.
     *
     * @param string $path URL path of the redirect, also used as title
     * @param string $to Target of the redirect
     * @return array<string, mixed> Page data including the link target
     */
    public function redirectData( string $path, string $to ): array
    {
        return ['to' => $to] + ( $this->host->pageData )( $path, $path, BasePages::STATUS_HIDDEN, BasePages::TAG_PAGE );
    }


    /**
     * Returns the page data and the content of the article.
     *
     * Joomla uses the title of the menu item linking to the article and the options of the menu item and the articles
     * component before the ones of the article, the options of other menu items, e.g. of the category, only if the
     * article doesn't set them. The meta data of the article is used before the one of the menu item.
     *
     * Guests see the intro text of articles they can't access if the "show_noauth" option is enabled and the article has a full text.
     * The page of the teaser is published then, so teasers are only built if they are allowed and the status isn't given.
     *
     * @param int|null $status Status of the page or NULL to use the status of the article and its menu item
     * @param bool $teasers TRUE to build the teaser of an article guests can't access, FALSE to always build the whole article
     * @param string $pageTag Tag of the page, i.e. BasePages::TAG_PAGE or BasePages::TAG_ROOT
     * @param Display $display Menu item showing the article, why guests can't see it and the menu item of its category
     * @param string $language Joomla language tag the article is shown in
     * @return Page Page data, content, if it's a teaser and why guests can't see the article
     */
    protected function article( \stdClass $article, string $slug, ?int $status, bool $teasers, string $pageTag, Display $display,
        string $language ): Page
    {
        $text = $this->texts->text( $article->id );
        // the options of the menu item linking to the article are used for the title, the ones of the category menu item too otherwise
        $options = $display->item ? $this->options->view( $text->attribs, $display->menuParams ) : $this->options->inherited( $text->attribs, $display->menuParams );

        if( $teasers && $display->reason !== null && $this->teaser( $article, $text, $options ) ) {
            $text = $text->asTeaser();
        }

        // the teasers of restricted articles are shown to guests, so they are published like other pages
        $status ??= $this->status( $text->teaser ? null : $display->reason, $display->params );
        $articleTitle = Row::title( $article );
        $data = array_replace( ( $this->host->pageData )( $articleTitle, $slug, $status, $pageTag ), [
            'name' => self::pageName( $article, $display->item ),
            'title' => $this->pageMeta->articleTitle( $articleTitle, $text->attribs, $display ),
        ] );

        $content = $this->articleContent( $article, $text, $options, $display, $language )
            + ['meta' => $this->pageMeta->data( $article, $display->menuParams, $language )];

        return new Page( $data, $content, $text->teaser, $display->reason );
    }


    /**
     * Returns the page data of a menu heading or category page.
     *
     * @param string $title Title of the category or menu item, it's trimmed because category titles aren't, see Row::title()
     * @param Display $display Menu item of the page and why guests can't see it
     * @param int|null $status Status of the page regardless of the reason, e.g. BasePages::STATUS_DISABLED if the page
     *  in the import language is disabled, or NULL to use the reason and the menu item
     * @return array<string, mixed> Page data
     */
    protected function folderData( string $title, string $slug, Display $display, ?int $status ): array
    {
        $title = trim( $title );
        $data = ( $this->host->pageData )( $title, $slug, $status ?? $this->status( $display->reason, $display->params ), BasePages::TAG_PAGE );
        $data['title'] = $this->pageMeta->title( $display->params, $title );

        return $data;
    }


    /**
     * Returns the content of a category or folder page with the headings, the tags, the image and the description of the category
     * and the meta data of the category and the menu item.
     *
     * The options of the menu item decide what is shown like in a Joomla category blog. The meta data
     * of the category is used before the one of the menu item.
     *
     * @param \stdClass|null $category Category or NULL for menu headings
     * @param Display $display Menu item of the page or the one of the parent category Joomla uses if the page has none
     * @return PageContent
     */
    protected function categoryContent( ?\stdClass $category, Display $display ): array
    {
        $content = $category ? $this->categoryElements( $category, $display ) : Elements::content();
        // pages are shown on the site of the language of their category, menu headings and categories for all languages on the one of the menu item
        $language = $category && !Row::allLanguages( $category ) ? Row::language( $category ) : Row::language( $display->menuItem() );

        return $content + ['meta' => $this->pageMeta->data( $category, $display->menuParams, $language )];
    }


    /**
     * Returns the root page or its variant for a home menu item which doesn't show an article.
     *
     * Like Joomla, the title of the menu item is the browser title if the menu item has none. Category blogs and
     * lists show the category, other views like the featured articles the page heading of the menu item.
     *
     * @param Display $display Home menu item and why guests can't see it
     * @param array<string, mixed> $data Page data of the root page or its variant
     * @param \stdClass|null $category Category of the home menu item or NULL if it shows no category
     * @return Page Page data, content and why guests can't see the menu item
     */
    protected function home( Display $display, array $data, ?\stdClass $category ): Page
    {
        $data['title'] = $this->pageMeta->title( $display->menuParams, Row::menuTitle( $display->item ), $data['title'] ?? '' );
        $content = $this->categoryContent( $category, $display );

        if( !$category && $display->item )
        {
            // the headings are shown above the content and the meta data is kept
            $headings = $this->headings->home( $this->options->view( [], $display->menuParams ), $display->item );
            $content = Elements::merge( ...[...$headings, $content] ) + $content;
        }

        return new Page( $data, $content, reason: $display->reason );
    }


    /**
     * Returns the content elements of a category page in the order Joomla shows them.
     *
     * @param Display $display Menu item of the page or the one of the parent category Joomla uses if the page has none
     * @return ContentElements
     */
    protected function categoryElements( \stdClass $category, Display $display ): array
    {
        $options = $this->options->view( [], $display->menuParams );
        $itemId = $display->itemId();
        $categoryParams = Row::params( $category );
        // the blog and list layouts show the image by default
        $image = Value::enabled( $options, self::SHOW_DESCRIPTION_IMAGE ) ? Value::string( $categoryParams[self::CATEGORY_IMAGE] ?? null ) : '';

        $tags = Value::enabled( $options, self::SHOW_CATEGORY_TAGS ) ? $this->content->categoryTags( $category ) : null;
        $rendering = $this->content->categoryRendering( $category, $itemId );
        $fields = fn( int $position ) => $this->content->fieldContent( $position, $rendering );
        $title = Row::title( $category );

        // like the blog and list layouts, the fields are shown around the description even if the description is hidden
        return Elements::merge( ...[
            ...$this->headings->category( $title, $this->subheading( $display ), $options, $display ),
            $fields( Fields::AFTER_TITLE ),
            $tags,
            Elements::imageContent( ( $this->host->image )( $this->texts->imageSource( $image ), Value::string( $categoryParams[self::CATEGORY_IMAGE_ALT] ?? null ) ) ),
            $fields( Fields::BEFORE_CONTENT ),
            $this->categoryDescription( $category, $options, $itemId ),
            $fields( Fields::AFTER_CONTENT ),
        ] );
    }


    /**
     * Returns the content of the category description if it's shown and contains text or media.
     *
     * @param array<string, mixed> $options Options of the category page
     * @param int $itemId ID of the menu item Joomla shows the category in, 0 for none
     * @return ContentElements|null Content elements or NULL if there's no description
     */
    protected function categoryDescription( \stdClass $category, array $options, int $itemId ): ?array
    {
        $html = Value::enabled( $options, self::SHOW_DESCRIPTION ) ? Value::text( $category->description ?? null ) : '';

        if( trim( strip_tags( $html, self::MEDIA_TAGS ) ) === '' ) {
            return null;
        }

        // Joomla replaces the field shortcodes of the description without checking the categories of the fields
        return $this->content->content( $html, $this->content->descriptionRendering( $category, $itemId ) );
    }


    /**
     * Returns the subheading of the menu item the blog layout of Joomla 3 shows before the category title.
     *
     * @param Display $display Menu item of the page or the one of the parent category Joomla uses if the page has none
     * @return string Subheading or an empty string if none is shown
     */
    protected function subheading( Display $display ): string
    {
        if( !$this->site->version->upTo( Version::JOOMLA_3 ) || MenuItem::layout( $display->item ) !== MenuItem::LAYOUT_BLOG ) {
            return '';
        }

        return Value::string( $display->menuParams[self::PAGE_SUBHEADING] ?? null );
    }


    /**
     * Returns the name of the page of the article, the title of the menu item showing it or the title of the article.
     *
     * @param \stdClass $article Article or translation
     * @param \stdClass|null $item Menu item showing the article or NULL if there's none
     */
    protected static function pageName( \stdClass $article, ?\stdClass $item ): string
    {
        return Row::menuTitle( $item ) ?: Row::title( $article );
    }


    /**
     * Returns the own menu item Joomla uses for the article or translation.
     *
     * @param \stdClass $article Article or translation
     * @return \stdClass|null Menu item or NULL if the article has none
     */
    protected function ownItem( \stdClass $article ): ?\stdClass
    {
        return $this->articles->items[$article->id] ?? null;
    }


    /**
     * Returns the menu item showing the article, why it's restricted and the active menu item of its category.
     *
     * @param \stdClass $article Article in the import language or a translation
     * @param Routes $routes Routes of the language of the article
     * @param \stdClass|null $showing Menu item showing the article instead of its own one, e.g. the home menu item of its language
     * @return Display Menu item, reason or NULL if guests can see the article and active menu item if it has no own one
     */
    protected function articleDisplay( \stdClass $article, Routes $routes, ?\stdClass $showing = null ): Display
    {
        $own = $this->ownItem( $article );
        $item = $showing ?? $own;
        $reason = $this->site->access->articleRestriction( $article, $own !== null, $showing );

        return Display::shown( $item, $reason, $routes, Article::catId( $article ) );
    }


    /**
     * Builds the content elements of the article page in the order Joomla shows them.
     *
     * These are the page heading of the menu item and the title, the custom fields, tags and links, the full text image
     * and the text. The intro image is only shown in the article lists. The intro text is only added if it's
     * shown on the article page by the options or if there's no full text like Joomla does. Joomla joins the
     * intro and full text before rendering, so elements split by the "Read more" break are complete again.
     *
     * Teasers of articles guests can't access contain the intro image and text instead of the full text image
     * and text, the tags and links below the text are hidden then. Joomla replaces the field shortcodes, modules and
     * e-mail addresses in the intro text of teasers but keeps their page breaks (the "Content - Fields" plugin also
     * prepares $item->introtext, the "Content - Page Break" plugin only $item->text), see Rendering::teaser().
     *
     * @param Text $text Texts, images, links and options of the article or its teaser
     * @param array<string, mixed> $options Options of the article page
     * @param Display $display Menu item linking to the article or the one of its category Joomla uses otherwise
     * @param string $language Joomla language tag the article is shown in
     * @return ContentElements
     */
    protected function articleContent( \stdClass $article, Text $text, array $options, Display $display, string $language ): array
    {
        $info = Value::string( $options[self::INFO_POSITION] ?? null ) ?: self::INFO_ABOVE;
        $rendering = $this->content->articleRendering( $article, $language, $display->itemId() );
        $fields = fn( int $position ) => $this->content->fieldContent( $position, $rendering );
        $tags = Value::enabled( $options, self::SHOW_TAGS ) ? $this->content->articleTags( $article, $language ) : null;
        $positions = LinkPositions::of( $text->urls, $options );
        $links = $positions->shown() ? $this->content->linkContent( $text->urls, $options, $language ) : null;
        $teaser = $text->teaser;

        return Elements::merge( ...[
            ...$this->headings->article( Row::title( $article ), $options, $display ),
            $fields( Fields::AFTER_TITLE ),
            $info === self::INFO_ABOVE ? $tags : null,
            $fields( Fields::BEFORE_CONTENT ),
            $positions->above ? $links : null,
            $this->articleImage( $text->images, $teaser ? Article::INTRO_IMAGE : Article::ARTICLE_IMAGE ),
            $teaser
                ? $this->content->content( $text->introtext, $rendering->teaser() )
                : $this->content->content( $this->articleHtml( $text, $options ), $rendering ),
            !$teaser && in_array( $info, [self::INFO_BELOW, self::INFO_SPLIT], true ) ? $tags : null,
            !$teaser && $positions->below ? $links : null,
            $fields( Fields::AFTER_CONTENT ),
        ] );
    }


    /**
     * Tests if guests see the intro text of an article they can't access.
     *
     * Joomla redirects guests to the login page instead if there's no full text, expired and scheduled articles aren't shown at all.
     * Like Joomla's "!strlen( $item->fulltext )" test, a full text of whitespace only is a full text here, so guests see the
     * teaser. This differs from articleHtml() on purpose, which shows the intro text if the full text is empty or whitespace.
     *
     * @param Text $text Texts of the article
     * @param array<string, mixed> $options Options of the article page
     */
    protected function teaser( \stdClass $article, Text $text, array $options ): bool
    {
        return $text->fulltext !== ''
            && $this->site->access->dateRestriction( $article ) === null
            && Value::enabled( $options, self::SHOW_NOAUTH, false );
    }


    /**
     * Returns the image of the article page.
     *
     * @param array<string, mixed> $images Images of the article
     * @param string $image Name of the image, i.e. the intro or the full text image
     * @return ContentElements|null Content or NULL without image
     */
    protected function articleImage( array $images, string $image ): ?array
    {
        $source = $this->texts->imageSource( Value::string( $images[$image] ?? null ) );
        $fileId = ( $this->host->image )( $source, Value::string( $images[$image . self::IMAGE_ALT_SUFFIX] ?? null ) );
        return Elements::imageContent( $fileId, self::markdown( Value::string( $images[$image . self::IMAGE_CAPTION_SUFFIX] ?? null ) ) );
    }


    /**
     * Escapes the plain text for Markdown, so it's shown as it is.
     *
     * The captions of the article images are plain text, Joomla escapes their HTML special characters too.
     *
     * @param string $text Plain text like "*Photo* by [A & B]"
     * @return string Markdown text like "\*Photo\* by \[A \& B\]"
     */
    protected static function markdown( string $text ): string
    {
        $text = addcslashes( trim( $text ), '\\`*_[]<>#|~!&' );
        // list items, headings and ordered lists at the start of lines
        $text = (string) preg_replace( '/^(\s*)([-+=])/m', '$1\\\\$2', $text );

        return (string) preg_replace( '/^(\s*\d+)([.)])/m', '$1\\\\$2', $text );
    }


    /**
     * Returns the text of the article page guests can access.
     *
     * The intro text is only added if it's shown by the options or if there's no full text. Teasers use the intro
     * text instead, see articleContent().
     *
     * @param Text $text Texts of the article
     * @param array<string, mixed> $options Options of the article page
     * @return string HTML of the article text
     */
    protected function articleHtml( Text $text, array $options ): string
    {
        if( Value::enabled( $options, self::SHOW_INTRO ) || trim( $text->fulltext ) === '' ) {
            return $text->introtext . ' ' . $text->fulltext;
        }

        return $text->fulltext;
    }


    /**
     * Returns the status of the page.
     *
     * Pages of menu items which Joomla doesn't show in the menu are hidden in the navigation.
     *
     * @param string|null $reason Why the page is disabled or NULL to publish it
     * @param array<string, mixed> $params Options of the menu item of the page, empty if there's none
     * @return int One of the status constants BasePages::STATUS_DISABLED, BasePages::STATUS_PUBLISHED or BasePages::STATUS_HIDDEN
     */
    protected function status( ?string $reason, array $params ): int
    {
        if( $reason !== null ) {
            return BasePages::STATUS_DISABLED;
        }

        return Value::enabled( $params, self::MENU_SHOW ) ? BasePages::STATUS_PUBLISHED : BasePages::STATUS_HIDDEN;
    }
}
