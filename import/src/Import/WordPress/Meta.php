<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Import\WordPress;

use Aimeos\Cms\Import\Date;
use Aimeos\Cms\Import\Elements;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;


/**
 * Builds the meta data of WordPress posts from the settings of SEO plugins, the excerpt and the categories and tags.
 *
 * WordPress core has no meta description, themes and SEO plugins use the excerpt. Yoast SEO and Rank Math store
 * their settings in the post meta, see Reader::SEO_KEYS. Their values can contain variables like
 * "%%title%% %%sep%% %%sitename%%" of Yoast or "%title%" of Rank Math, which are replaced like the plugins do
 * when the page is shown. Values with other variables, e.g. custom fields or the current date, are left out.
 * Posts without own title or description use the templates for posts of the active SEO plugin. The settings of
 * deactivated plugins aren't used, see Reader::seoPlugins(). Rank Math capitalizes the words of the titles if
 * "Capitalize Titles" is enabled.
 *
 * @phpstan-type PostVars array{id?: int|string, date?: string, modified?: string, author?: string, categories?: array<int, string>, tags?: array<int, string>, excerpt?: string, content?: string}
 */
class Meta
{
    /** @var array<string, string> Separators of Yoast SEO by their name in the "separator" setting */
    protected const YOAST_SEPARATORS = [
        'sc-dash' => '-', 'sc-ndash' => '–', 'sc-mdash' => '—', 'sc-colon' => ':', 'sc-middot' => '·', 'sc-bull' => '•', 'sc-star' => '*',
        'sc-smstar' => '⋆', 'sc-pipe' => '|', 'sc-tilde' => '~', 'sc-laquo' => '«', 'sc-raquo' => '»', 'sc-lt' => '<', 'sc-gt' => '>',
    ];

    /** @var array{yoast: string, rankMath: string} Separators of the plugins used for the "sep" variable */
    protected array $separators;
    /** @var array{yoast: array<int, string>, rankMath: array<int, string>} Robots settings of the posts without own settings */
    protected array $robots;
    /** @var array{title: array<string, string>, description: array<string, string>} Templates for posts of the active plugins by plugin */
    protected array $templates = ['title' => [], 'description' => []];
    /** TRUE if Rank Math capitalizes the words of the titles */
    protected bool $capitalize;

    /**
     * Initializes the object.
     *
     * @param array{name?: string, description?: string, dateFormat?: string, yoast?: array<mixed>, rankMath?: array<mixed>, plugins?: array<int, string>|null} $site
     *  Site title and tagline, date format, the title settings of the SEO plugins and the active plugins, see Reader::site()
     * @param bool $noindex TRUE if search engines are discouraged from indexing the whole site
     * @param (\Closure(string): ?string)|null $canonical Returns the canonical URL for the imported pages or NULL to leave it out
     * @param string $lang Language of the imported pages used for the names of months and days in dates
     */
    public function __construct( protected array $site = [], protected bool $noindex = false, protected ?\Closure $canonical = null, protected string $lang = 'en' )
    {
        $yoast = $site['yoast'] ?? [];
        $rankMath = $site['rankMath'] ?? [];

        $this->separators = [
            'yoast' => self::YOAST_SEPARATORS[is_string( $yoast['separator'] ?? null ) ? $yoast['separator'] : ''] ?? '-',
            'rankMath' => is_string( $rankMath['title_separator'] ?? null ) && trim( $rankMath['title_separator'] ) !== ''
                ? html_entity_decode( trim( $rankMath['title_separator'] ), ENT_QUOTES, 'UTF-8' ) : '-',
        ];

        // the settings of the plugins are only used if the plugin is active, the options stay after deactivating it
        $plugins = Reader::seoPlugins( $site['plugins'] ?? null );
        $active = ['yoast' => in_array( 'yoast', $plugins, true ), 'rankMath' => in_array( 'rankMath', $plugins, true )];

        $this->capitalize = $active['rankMath'] && ( $rankMath['capitalize_titles'] ?? '' ) === 'on';

        // Rank Math uses the robots of the post type if they are customized, otherwise the global ones
        $list = ( $rankMath['pt_post_custom_robots'] ?? '' ) === 'on' ? $rankMath['pt_post_robots'] ?? [] : $rankMath['robots_global'] ?? [];

        $this->robots = [
            'yoast' => $active['yoast'] && !empty( $yoast['noindex-post'] ) ? ['noindex'] : [],
            'rankMath' => $active['rankMath'] && is_array( $list ) ? array_values( array_filter( $list, 'is_string' ) ) : [],
        ];

        foreach( ['title' => ['title-post', 'pt_post_title'], 'description' => ['metadesc-post', 'pt_post_description']] as $type => [$yoastKey, $rankMathKey] )
        {
            if( $active['yoast'] && is_string( $yoast[$yoastKey] ?? null ) && trim( $yoast[$yoastKey] ) !== '' ) {
                $this->templates[$type]['yoast'] = $yoast[$yoastKey];
            }

            if( $active['rankMath'] && is_string( $rankMath[$rankMathKey] ?? null ) && trim( $rankMath[$rankMathKey] ) !== '' ) {
                $this->templates[$type]['rankMath'] = $rankMath[$rankMathKey];
            }
        }
    }


    /**
     * Checks if the values of the posts are required for the variables in the SEO settings.
     *
     * @param array<int|string, array<string, string>> $seo Values of the SEO plugins by post meta key by post ID
     * @return bool TRUE if templates are used or a value contains variables
     */
    public function variables( array $seo ): bool
    {
        return $this->templates['title'] !== [] || $this->templates['description'] !== []
            || array_filter( $seo, fn( $values ) => str_contains( implode( '', $values ), '%' ) );
    }


    /**
     * Returns the SEO title of the post or NULL if none is set.
     *
     * @param array<string, string> $seo Values of the SEO plugins by post meta key
     * @param string $title Title of the post replacing the "title" variable
     * @param string $excerpt Plain text introduction of the post replacing the "excerpt" variable
     * @param PostVars $post Values of the post replacing the other variables like "date" or "category"
     */
    public function title( array $seo, string $title, string $excerpt = '', array $post = [] ): ?string
    {
        return $this->value( $seo, ['_yoast_wpseo_title', 'rank_math_title'], $title, $excerpt, $post )
            ?? $this->template( 'title', $seo, $title, $excerpt, $post );
    }


    /**
     * Returns the meta data of the post.
     *
     * @param array<string, string> $seo Values of the SEO plugins by post meta key
     * @param array<int, string> $terms Names of the categories and tags of the post used as keywords
     * @param string $description Plain text introduction of the post used if no description is set
     * @param string $title Title of the post used for social media if no social media title is set
     * @param string|null $fileId ID of the featured image shown by social media
     * @param PostVars $post Values of the post replacing the variables like "date" or "category"
     * @return array<string, array{type: string, data: array<string, mixed>, files: array<int, string>}> Meta data keyed by type
     */
    public function build( array $seo, array $terms, string $description, string $title, ?string $fileId, array $post = [] ): array
    {
        $excerpt = $description;
        $description = $this->value( $seo, ['_yoast_wpseo_metadesc', 'rank_math_description'], $title, $excerpt, $post )
            ?? $this->template( 'description', $seo, $title, $excerpt, $post ) ?? $description;
        $meta = array_filter( ['meta-tags' => Elements::metaTags( $description, implode( ', ', $this->keywords( $seo, $terms ) ) )] );
        $socialTitle = $this->value( $seo, ['_yoast_wpseo_opengraph-title', 'rank_math_facebook_title'], $title, $excerpt, $post );

        if( $socialTitle !== null || $fileId )
        {
            $socialDescription = $this->value( $seo, ['_yoast_wpseo_opengraph-description', 'rank_math_facebook_description'], $title, $excerpt, $post ) ?? $description;

            $meta['social-media'] = ['type' => 'social-media', 'data' => array_filter( [
                'title' => Str::limit( $socialTitle ?? $this->title( $seo, $title, $excerpt, $post ) ?? $title, Elements::SOCIAL_TITLE_SIZE, '…', true ),
                'description' => Str::limit( (string) preg_replace( '/\s+/', ' ', $socialDescription ), Elements::DESCRIPTION_SIZE, '…', true ),
                'file' => $fileId ? Elements::file( $fileId ) : null,
            ] ), 'files' => $fileId ? [$fileId] : []];
        }

        if( ( $url = $this->canonical( $seo ) ) !== null ) {
            $meta['canonical'] = ['type' => 'canonical', 'data' => ['url' => $url], 'files' => []];
        }

        if( $robots = Elements::robots( $this->noindex ? 'noindex, nofollow' : $this->robots( $seo ) ) ) {
            $meta['robots'] = $robots;
        }

        return $meta;
    }


    /**
     * Returns the title with the first letter of each word in upper case if "Capitalize Titles" of Rank Math is enabled.
     *
     * Like Str::mb_ucwords() of Rank Math, which tests only the first byte of the words, words starting with other
     * characters than ASCII letters stay unchanged, e.g. "élan" or "(beta)".
     *
     * @param string $title Title from Rank Math
     */
    protected function capitalize( string $title ): string
    {
        if( !$this->capitalize ) {
            return $title;
        }

        return (string) preg_replace_callback( '/(?<!\S)[a-z]/u', fn( $m ) => strtoupper( $m[0] ), $title );
    }


    /**
     * Returns the canonical URL of the post for the imported pages.
     *
     * @param array<string, string> $seo Values of the SEO plugins by post meta key
     * @return string|null Absolute URL or path, NULL if none is set or it's invalid
     */
    protected function canonical( array $seo ): ?string
    {
        foreach( ['_yoast_wpseo_canonical', 'rank_math_canonical_url'] as $key )
        {
            $url = trim( html_entity_decode( strip_tags( $seo[$key] ?? '' ), ENT_QUOTES, 'UTF-8' ) );

            if( $url !== '' && ( str_starts_with( $url, '/' ) || filter_var( $url, FILTER_VALIDATE_URL ) ) ) {
                return $this->canonical ? ( $this->canonical )( $url ) : $url;
            }
        }

        return null;
    }


    /**
     * Returns the focus keyphrases of the SEO plugins and the categories and tags as keywords.
     *
     * Yoast SEO stores one focus keyphrase, Rank Math a comma separated list of focus keywords.
     *
     * @param array<string, string> $seo Values of the SEO plugins by post meta key
     * @param array<int, string> $terms Names of the categories and tags of the post
     * @return array<int, string> Keywords without duplicates ignoring the case
     */
    protected function keywords( array $seo, array $terms ): array
    {
        $list = [...explode( ',', $seo['_yoast_wpseo_focuskw'] ?? '' ), ...explode( ',', $seo['rank_math_focus_keyword'] ?? '' ), ...$terms];
        $keywords = [];

        foreach( $list as $keyword )
        {
            if( ( $keyword = trim( html_entity_decode( strip_tags( $keyword ), ENT_QUOTES, 'UTF-8' ) ) ) !== '' ) {
                $keywords[mb_strtolower( $keyword )] ??= $keyword;
            }
        }

        return array_values( $keywords );
    }


    /**
     * Returns the robots settings of the post from the SEO plugins.
     *
     * Yoast SEO stores "1" for "noindex" and "2" for "index" in "_yoast_wpseo_meta-robots-noindex" and "1" for
     * "nofollow" in "_yoast_wpseo_meta-robots-nofollow", Rank Math stores a serialized list like ["noindex", "nofollow"].
     * Posts without own settings use the default settings of the plugins for posts.
     *
     * @param array<string, string> $seo Values of the SEO plugins by post meta key
     * @return string Comma separated robots values like "noindex, nofollow"
     */
    protected function robots( array $seo ): string
    {
        $robots = match( $seo['_yoast_wpseo_meta-robots-noindex'] ?? '' ) {
            '1' => ['noindex'],
            '2' => ['index'],
            default => $this->robots['yoast'],
        };

        if( ( $seo['_yoast_wpseo_meta-robots-nofollow'] ?? '' ) === '1' ) {
            $robots[] = 'nofollow';
        }

        $list = isset( $seo['rank_math_robots'] ) ? @unserialize( $seo['rank_math_robots'], ['allowed_classes' => false] ) : $this->robots['rankMath'];

        foreach( is_array( $list ) ? $list : [] as $value )
        {
            if( is_string( $value ) ) {
                $robots[] = $value;
            }
        }

        return implode( ', ', $robots );
    }


    /**
     * Returns the template for posts of the active plugins with the variables replaced.
     *
     * @param string $type "title" or "description"
     * @param array<string, string> $seo Values of the SEO plugins by post meta key
     * @param string $title Title of the post replacing the "title" variable
     * @param string $excerpt Plain text introduction of the post replacing the "excerpt" variable
     * @param PostVars $post Values of the post replacing the other variables
     */
    protected function template( string $type, array $seo, string $title, string $excerpt, array $post ): ?string
    {
        foreach( $this->templates[$type] as $plugin => $template )
        {
            // Rank Math uses the manual excerpt before its description template
            if( $type === 'description' && $plugin === 'rankMath' && ( $post['excerpt'] ?? '' ) !== '' ) {
                return $post['excerpt'];
            }

            if( ( $value = $this->fill( $plugin, $template, $title, $excerpt, $seo, $post ) ) !== null ) {
                return $type === 'title' && $plugin === 'rankMath' ? $this->capitalize( $value ) : $value;
            }
        }

        return null;
    }


    /**
     * Returns the template of the plugin with the variables replaced.
     *
     * Rank Math replaces "%seo_title%" and "%seo_description%" in its templates by "%title%" and "%excerpt%",
     * see Paper::get_from_options(), so they don't refer to themselves.
     *
     * @param string $plugin "yoast" or "rankMath"
     * @param string $template Template like "%%title%% %%sep%% %%sitename%%"
     * @param string $title Title of the post replacing the "title" variable
     * @param string $excerpt Plain text introduction of the post replacing the "excerpt" variable
     * @param array<string, string> $seo Values of the SEO plugins by post meta key
     * @param PostVars $post Values of the post replacing the other variables
     * @return string|null Template with the variables replaced or NULL if it contains unknown variables or nothing is left
     */
    protected function fill( string $plugin, string $template, string $title, string $excerpt, array $seo, array $post ): ?string
    {
        $value = trim( html_entity_decode( strip_tags( $template ), ENT_QUOTES, 'UTF-8' ) );

        if( $plugin === 'rankMath' ) {
            $value = str_replace( ['%seo_title%', '%seo_description%'], ['%title%', '%excerpt%'], $value );
        }

        return $value !== '' ? $this->replace( $value, $plugin, $title, $excerpt, $seo, $post, false ) : null;
    }


    /**
     * Returns the first value of the keys as plain text with the variables of the plugins replaced.
     *
     * Values with variables which can't be replaced, e.g. custom fields, are skipped.
     *
     * @param array<string, string> $seo Values of the SEO plugins by post meta key
     * @param array<int, string> $keys Post meta keys in the order they are used
     * @param string $title Title of the post replacing the "title" variable
     * @param string $excerpt Plain text introduction of the post replacing the "excerpt" variable
     * @param PostVars $post Values of the post replacing the other variables
     */
    protected function value( array $seo, array $keys, string $title, string $excerpt, array $post = [] ): ?string
    {
        foreach( $keys as $key )
        {
            $value = trim( html_entity_decode( strip_tags( $seo[$key] ?? '' ), ENT_QUOTES, 'UTF-8' ) );

            if( $value !== '' && ( $value = $this->replace( $value, str_starts_with( $key, '_yoast' ) ? 'yoast' : 'rankMath', $title, $excerpt, $seo, $post ) ) !== null ) {
                return in_array( $key, ['rank_math_title', 'rank_math_facebook_title'], true ) ? $this->capitalize( $value ) : $value;
            }
        }

        return null;
    }


    /**
     * Returns the excerpt generated from the content like Yoast SEO does for the "excerpt" variable.
     *
     * The plain text of the whole content without shortcodes is shortened to 156 characters (80 for Japanese)
     * and then to the last space without ellipsis, see WPSEO_Replace_Vars::retrieve_excerpt().
     *
     * @param string $html Content of the post
     */
    protected function yoastExcerpt( string $html ): string
    {
        $limit = $this->lang === 'ja' ? 80 : 156;
        $text = Article::excerpt( $html );

        if( mb_strlen( $text ) <= $limit ) {
            return $text;
        }

        $cut = mb_substr( $text, 0, $limit );
        return ( $pos = mb_strrpos( $cut, ' ' ) ) ? rtrim( mb_substr( $cut, 0, $pos ) ) : $cut;
    }


    /**
     * Returns the excerpt generated from the content like Rank Math does for the "excerpt" variable.
     *
     * The first paragraph containing the first focus keyword is used, otherwise the first paragraph or the
     * whole content if it has no paragraphs. The text isn't shortened, see Post_Variables::get_post_content().
     *
     * @param string $html Content of the post
     * @param string $keyword First focus keyword of the post, empty if none
     */
    protected function rankMathExcerpt( string $html, string $keyword ): string
    {
        $html = (string) preg_replace( '/<!--[\s\S]*?-->/u', '', Shortcode::strip( $html ) );
        $paragraphs = [];

        // wpautop() wraps the texts separated by empty lines into paragraphs, other blocks don't become paragraphs
        foreach( preg_split( '/\n\s*\n/', $html ) ?: [] as $chunk )
        {
            if( !preg_match( '/<p\b/i', $chunk ) && !preg_match( '/^\s*<(?:h[1-6]|ul|ol|li|table|div|figure|blockquote|pre|hr|dl|form|section)\b/i', $chunk ) ) {
                $chunk = '<p>' . $chunk . '</p>';
            }

            preg_match_all( '/<p\b[^>]*>(.*?)<\/p>/is', $chunk, $matches );

            foreach( $matches[1] as $paragraph )
            {
                if( ( $text = Article::excerpt( $paragraph ) ) !== '' ) {
                    $paragraphs[] = $text;
                }
            }
        }

        // the spaces of the keyword match any character like in Rank Math
        $pattern = '/' . implode( '.', array_map( fn( $part ) => preg_quote( $part, '/' ), explode( ' ', $keyword ) ) ) . '/iu';

        foreach( $keyword !== '' ? $paragraphs : [] as $text )
        {
            if( preg_match( $pattern, $text ) ) {
                return $text;
            }
        }

        return $paragraphs[0] ?? Article::excerpt( $html );
    }


    /**
     * Returns the values of the variables of the plugin which depend on the post.
     *
     * Yoast SEO lists all categories and tags in "category" and "tag" and has the primary category chosen in the
     * plugin, which is empty if none of the categories of the post is chosen. Rank Math uses the first category and
     * tag by name in "category" and "tag" and all of them in "categories" and "tags", but has no primary category.
     * Its "modified" is the later one of the date and the modification date and "post_author" is the same as "name".
     * Values which aren't available are left out, so the variables are unknown.
     *
     * @param string $plugin "yoast" or "rankMath"
     * @param array<string, string> $seo Values of the SEO plugins by post meta key
     * @param PostVars $post Values of the post
     * @return array<string, string|\Closure(string): ?string> Values by variable name, dates are closures receiving the date format
     */
    protected function postVars( string $plugin, array $seo, array $post ): array
    {
        $vars = [];

        if( isset( $post['id'] ) ) {
            $vars['id'] = (string) $post['id'];
        }

        if( isset( $post['author'] ) ) {
            $vars['name'] = $post['author'];
        }

        // Rank Math uses the date of the post if it's later than the modification date, e.g. for scheduled posts
        if( $plugin === 'rankMath' && isset( $post['date'], $post['modified'] ) && strtotime( $post['date'] ) > strtotime( $post['modified'] ) ) {
            $post['modified'] = $post['date'];
        }

        foreach( ['date', 'modified'] as $name )
        {
            if( isset( $post[$name] ) ) {
                $vars[$name] = fn( string $format ) => $this->date( $post[$name], $format );
            }
        }

        $categories = $post['categories'] ?? null;
        $keywords = array_values( array_filter( array_map( 'trim', explode( ',', $seo[$plugin === 'yoast' ? '_yoast_wpseo_focuskw' : 'rank_math_focus_keyword'] ?? '' ) ) ) );

        // "excerpt_only" is only the manual excerpt, "excerpt" is generated from the content by each plugin in its own way without one
        if( isset( $post['excerpt'] ) )
        {
            $manual = $post['excerpt'];
            $content = $post['content'] ?? null;

            $vars['excerpt_only'] = $manual;
            $vars['excerpt'] = $manual !== '' || $content === null ? $manual
                : fn() => $plugin === 'yoast' ? $this->yoastExcerpt( $content ) : $this->rankMathExcerpt( $content, $keywords[0] ?? '' );
        }

        if( $plugin === 'yoast' )
        {
            if( isset( $post['date'] ) )
            {
                $vars['post_year'] = fn() => $this->date( $post['date'], 'Y' );
                $vars['post_month'] = fn() => $this->date( $post['date'], 'F' );
                $vars['post_day'] = fn() => $this->date( $post['date'], 'd' );
            }

            if( $categories !== null ) {
                $vars['category'] = implode( ', ', $categories );
            }

            if( isset( $post['tags'] ) ) {
                $vars['tag'] = implode( ', ', $post['tags'] );
            }

            $primary = $categories[(int) ( $seo['_yoast_wpseo_primary_category'] ?? 0 )] ?? '';

            return ['primary_category' => $primary, 'focuskw' => $seo['_yoast_wpseo_focuskw'] ?? ''] + $vars;
        }

        // "post_author" is the display name of the author like "name"
        if( isset( $vars['name'] ) ) {
            $vars['post_author'] = $vars['name'];
        }

        if( $categories !== null )
        {
            $vars['category'] = reset( $categories ) ?: '';
            $vars['categories'] = implode( ', ', $categories );
        }

        if( isset( $post['tags'] ) )
        {
            $vars['tag'] = $post['tags'][0] ?? '';
            $vars['tags'] = implode( ', ', $post['tags'] );
        }

        return ['focuskw' => $keywords[0] ?? '', 'keywords' => implode( ', ', $keywords )] + $vars;
    }


    /**
     * Returns the local date of the post in the format of WordPress and the language of the imported pages.
     *
     * @param string $date Date like "2026-01-31 12:00:00"
     * @param string $format PHP date format like "F j, Y", empty for the date format of the site
     * @return string|null Formatted date or NULL if it's invalid
     */
    protected function date( string $date, string $format ): ?string
    {
        if( !( $date = Date::first( [$date] ) ) ) {
            return null;
        }

        $format = $format !== '' ? $format : ( ( $this->site['dateFormat'] ?? '' ) ?: 'F j, Y' );

        try {
            return Carbon::parse( $date )->settings( ['locale' => $this->lang] )->translatedFormat( $format );
        } catch( \Exception $e ) {
            return null;
        }
    }


    /**
     * Replaces the variables of the SEO plugin in the value.
     *
     * Yoast SEO uses variables like "%%title%%", Rank Math like "%title%" or "%date(F j, Y)%" with arguments. The page
     * number is removed, repeated separators and separators left at the start or the end afterwards too.
     *
     * @param string $value Plain text value
     * @param string $plugin "yoast" or "rankMath"
     * @param string $title Title of the post replacing the "title" variable
     * @param string $excerpt Plain text introduction of the post replacing the "excerpt" variable
     * @param array<string, string> $seo Values of the SEO plugins by post meta key
     * @param PostVars $post Values of the post replacing the other variables
     * @param bool $own TRUE if the own SEO title and description of Rank Math can be used, FALSE while replacing them
     * @return string|null Value with the variables replaced or NULL if it contains unknown variables or nothing is left
     */
    protected function replace( string $value, string $plugin, string $title, string $excerpt, array $seo = [], array $post = [], bool $own = true ): ?string
    {
        $sep = $this->separators[$plugin];
        $vars = [
            'title' => $title,
            'sitename' => $this->site['name'] ?? '',
            'sitedesc' => $this->site['description'] ?? '',
            'sep' => $sep,
            'page' => '',
        ] + $this->postVars( $plugin, $seo, $post ) + ['excerpt' => $excerpt, 'excerpt_only' => $excerpt];

        // Rank Math uses the SEO title and description of the page like they are shown
        if( $plugin === 'rankMath' && $own )
        {
            $vars['seo_title'] = fn() => $this->seoTitle( $seo, $title, $excerpt, $post );
            $vars['seo_description'] = fn() => $this->seoDescription( $seo, $title, $excerpt, $post );
        }

        $pattern = $plugin === 'yoast' ? '/%%([a-z_]+)%%/i' : '/%([a-z_]+)(?:\(([^)]*)\))?%/i';
        $unknown = false;

        $value = (string) preg_replace_callback( $pattern, function( $m ) use ( $vars, &$unknown ) {
            $var = $vars[strtolower( $m[1] )] ?? null;
            $var = $var instanceof \Closure ? $var( $m[2] ?? '' ) : $var;
            $unknown = $unknown || $var === null;
            return $var ?? '';
        }, $value );

        $quoted = preg_quote( $sep, '/' );
        $value = (string) preg_replace( '/(' . $quoted . ')(?:\s*' . $quoted . ')+/u', '$1', (string) preg_replace( '/\s+/u', ' ', $value ) );
        $value = trim( (string) preg_replace( '/^(?:\s*' . $quoted . ')+\s*|(?:\s*' . $quoted . ')+\s*$/u', '', $value ) );

        return $unknown || $value === '' ? null : $value;
    }


    /**
     * Returns the SEO title of the page like Rank Math shows it for "%seo_title%".
     *
     * It's the own title of the post, the title template of Rank Math or the title of the post, see Paper::get_title().
     *
     * @param array<string, string> $seo Values of the SEO plugins by post meta key
     * @param string $title Title of the post
     * @param string $excerpt Plain text introduction of the post
     * @param PostVars $post Values of the post
     */
    protected function seoTitle( array $seo, string $title, string $excerpt, array $post ): string
    {
        $template = $this->templates['title']['rankMath'] ?? null;

        return $this->capitalize( $this->own( $seo, 'rank_math_title', $title, $excerpt, $post )
            ?? ( $template !== null ? $this->fill( 'rankMath', $template, $title, $excerpt, $seo, $post ) : null ) ?? $title );
    }


    /**
     * Returns the meta description of the page like Rank Math shows it for "%seo_description%".
     *
     * It's the own description of the post, the manual excerpt or the description template of Rank Math, see
     * Singular::get_post_description(). Without the values of the post, the introduction is used.
     *
     * @param array<string, string> $seo Values of the SEO plugins by post meta key
     * @param string $title Title of the post
     * @param string $excerpt Plain text introduction of the post
     * @param PostVars $post Values of the post
     */
    protected function seoDescription( array $seo, string $title, string $excerpt, array $post ): string
    {
        if( ( $value = $this->own( $seo, 'rank_math_description', $title, $excerpt, $post ) ) !== null ) {
            return $value;
        }

        if( ( $post['excerpt'] ?? '' ) !== '' ) {
            return $post['excerpt'];
        }

        $template = $this->templates['description']['rankMath'] ?? null;
        return ( $template !== null ? $this->fill( 'rankMath', $template, $title, $excerpt, $seo, $post ) : null ) ?? ( isset( $post['excerpt'] ) ? '' : $excerpt );
    }


    /**
     * Returns the own value of the post set in Rank Math with the variables replaced.
     *
     * @param array<string, string> $seo Values of the SEO plugins by post meta key
     * @param string $key Post meta key like "rank_math_title"
     * @param string $title Title of the post
     * @param string $excerpt Plain text introduction of the post
     * @param PostVars $post Values of the post
     */
    protected function own( array $seo, string $key, string $title, string $excerpt, array $post ): ?string
    {
        $value = trim( html_entity_decode( strip_tags( $seo[$key] ?? '' ), ENT_QUOTES, 'UTF-8' ) );
        return $value !== '' ? $this->replace( $value, 'rankMath', $title, $excerpt, $seo, $post, false ) : null;
    }
}
