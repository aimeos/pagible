<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Import;

use Illuminate\Support\Collection;


/**
 * Fetches the public tags, the shown custom fields and their values of the Joomla articles and categories.
 *
 * The ones of the articles are kept per chunk by JoomlaChunkCache like the texts of JoomlaTexts, the ones of other
 * articles are fetched on first use and kept until the next chunk is prefetched. The ones of the categories are
 * fetched all at once on first use because there are only a few categories. The readers split the IDs into chunks,
 * so the IN lists of the queries stay small.
 */
class JoomlaExtras
{
    /** Message shown if the tags and custom field values of an article which wasn't prefetched are fetched on their own */
    protected const UNFETCHED = '  Fetched the tags and custom field values of article "%s" on their own because they weren\'t prefetched';

    /** @var JoomlaChunkCache<array{tags: list<\stdClass>, values: array<int|string, list<string>>}> Tags and custom field values of the articles imported next */
    protected JoomlaChunkCache $articles;
    /** @var array<int|string, array<int|string, list<string>>>|null Custom field values of the categories by category ID and field ID, fetched on first use */
    protected ?array $categoryValueMap = null;
    /** @var array<int|string, list<\stdClass>>|null Tags of the categories by category ID, fetched on first use */
    protected ?array $categoryTagMap = null;
    /** @var array<string, Collection<int, \stdClass>> Custom fields Joomla shows by context, fetched on first use */
    protected array $fields = [];
    /** @var array<int|string, list<int>>|null Category IDs by field ID, fetched on first use */
    protected ?array $fieldCategories = null;


    /**
     * Initializes the object.
     *
     * @param JoomlaReader $reader Reader for the tags, custom fields and their values
     * @param JoomlaAccess $access Access checks of the tags and custom fields
     * @param JoomlaPlugins $plugins Enabled plugins and their options, e.g. the field plugins
     * @param Collection<int, \stdClass> $categories Categories of all states by ID
     * @param \Closure(string): void|null $debug Shows a debug message, e.g. if the values of an article are fetched on their own, or NULL to ignore them
     */
    public function __construct(
        protected JoomlaReader $reader,
        protected JoomlaAccess $access,
        protected JoomlaPlugins $plugins,
        protected Collection $categories,
        ?\Closure $debug = null
    ) {
        $this->articles = new JoomlaChunkCache( fn( array $ids ) => $this->fetch( $ids ), self::UNFETCHED, $debug );
    }


    /**
     * Returns the custom fields Joomla shows.
     *
     * Fields which aren't rendered, whose field plugin is disabled, which are not public or only used in subforms aren't shown.
     *
     * @param string $context Context of the fields, JoomlaFields::ARTICLE_CONTEXT or JoomlaFields::CATEGORY_CONTEXT
     * @return Collection<int, \stdClass> Custom fields with the access level of their field group
     */
    public function shownFields( string $context ): Collection
    {
        return $this->fields[$context] ??= $this->reader->fields( $context )->filter( function( $field ) {
            $restricted = $this->access->restricted( $field->access ?? null ) || $this->access->restricted( $field->group_access ?? null );
            return empty( $field->only_use_in_subform ) && !$restricted && JoomlaFields::renderable( $field )
                && $this->plugins->fieldParams( JoomlaFields::type( $field ) ) !== null;
        } );
    }


    /**
     * Returns the categories the custom fields are assigned to.
     *
     * @return array<int|string, list<int>> Category IDs by field ID, fields without categories are missing
     */
    public function fieldCategories(): array
    {
        return $this->fieldCategories ??= $this->reader->fieldCategories();
    }


    /**
     * Fetches the tags and custom field values of the articles imported next at once.
     *
     * The ones of the former chunk are discarded, the ones of these articles fetched before are kept.
     *
     * @param array<int|string> $ids IDs of the articles and translations
     */
    public function prefetch( array $ids ): void
    {
        $this->articles->prefetch( $ids );
    }


    /**
     * Returns the stored custom field values of the article.
     *
     * Articles which weren't prefetched are fetched on their own, which needs extra queries per article.
     *
     * @return array<int|string, list<string>> Values by field ID
     */
    public function articleValues( int|string $id ): array
    {
        return $this->articles->get( $id )['values'] ?? [];
    }


    /**
     * Returns the public tags of the article.
     *
     * Articles which weren't prefetched are fetched on their own, which needs extra queries per article.
     *
     * @return list<\stdClass> Tags with title, access level and language
     */
    public function articleTags( int|string $id ): array
    {
        return $this->public( $this->articles->get( $id )['tags'] ?? [] );
    }


    /**
     * Returns the stored custom field values of the category.
     *
     * @return array<int|string, list<string>> Values by field ID
     */
    public function categoryValues( int|string $catId ): array
    {
        // the values are only fetched if there are custom fields for categories
        $this->categoryValueMap ??= $this->hasFields( JoomlaFields::CATEGORY_CONTEXT )
            ? $this->reader->fieldValues( $this->categoryIds(), JoomlaFields::CATEGORY_CONTEXT ) : [];

        return $this->categoryValueMap[$catId] ?? [];
    }


    /**
     * Returns the public tags of the category.
     *
     * @return list<\stdClass> Tags with title, access level and language
     */
    public function categoryTags( int|string $catId ): array
    {
        $this->categoryTagMap ??= $this->reader->tags( $this->categoryIds(), JoomlaReader::CATEGORY_TAGS );

        return $this->public( $this->categoryTagMap[$catId] ?? [] );
    }


    /**
     * Fetches the tags and custom field values of the articles.
     *
     * @param list<int|string> $ids IDs of the articles and translations
     * @return array<int|string, array{tags: list<\stdClass>, values: array<int|string, list<string>>}> Tags and values by article ID
     */
    protected function fetch( array $ids ): array
    {
        $tags = $this->reader->tags( $ids, JoomlaReader::ARTICLE_TAGS );

        // the values are only fetched if there are custom fields for articles
        $values = $this->hasFields( JoomlaFields::ARTICLE_CONTEXT ) ? $this->reader->fieldValues( $ids, JoomlaFields::ARTICLE_CONTEXT ) : [];

        $result = [];

        foreach( $ids as $id ) {
            $result[$id] = ['tags' => $tags[$id] ?? [], 'values' => $values[$id] ?? []];
        }

        return $result;
    }


    /**
     * Tests if there are custom fields Joomla shows for the context, the values of the others aren't needed.
     *
     * @param string $context Context of the fields, JoomlaFields::ARTICLE_CONTEXT or JoomlaFields::CATEGORY_CONTEXT
     * @return bool TRUE if there are shown custom fields, FALSE if not
     */
    protected function hasFields( string $context ): bool
    {
        return $this->shownFields( $context )->isNotEmpty();
    }


    /**
     * Returns the tags guests can see.
     *
     * @param list<\stdClass> $tags Joomla tags with title, access level and language
     * @return list<\stdClass> Public tags
     */
    protected function public( array $tags ): array
    {
        return array_values( array_filter( $tags, fn( $tag ) => !$this->access->restricted( $tag->access ?? null ) ) );
    }


    /**
     * Returns the IDs of the categories of all states.
     *
     * @return array<int|string> Category IDs
     */
    protected function categoryIds(): array
    {
        return $this->categories->keys()->all();
    }
}
