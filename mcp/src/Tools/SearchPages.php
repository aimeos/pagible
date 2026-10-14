<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Tools;

use Aimeos\Cms\Filter;
use Aimeos\Cms\Models\PageView;
use Aimeos\Nestedset\NestedSet;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Model;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Scout\Builder;


#[IsReadOnly]
#[Name('search-pages')]
#[Title('Search for pages by keywords')]
#[Description('Lists and searches pages. Parameters: term (full-text search), lang, domain, type, tag, theme, path, status, cache, to, trashed (without/with/only), publish (PUBLISHED/DRAFT/SCHEDULED), editor. Pages are returned in the language passed by lang, otherwise in their source language. Use term parameter if possible. Returns up to 25 matches.')]
class SearchPages extends SearchTool
{
    protected const MODEL = PageView::class;
    protected const COLUMNS = ['cms_page_view.id', 'cms_page_view.variant_id', 'cms_page_view.tenant_id', 'cms_page_view.lang', 'cms_page_view.source', 'cms_page_view.parent_id', 'cms_page_view.path', 'cms_page_view.created_at', 'cms_page_view.updated_at', 'cms_page_view.deleted_at', 'cms_page_view.latest_id', NestedSet::LFT, NestedSet::RGT];
    protected const KEY = 'pages';
    protected const PERMISSIONS = ['page:view'];
    protected const RULES = [
        'term' => 'string|max:255',
        'lang' => 'string|max:10',
        'status' => 'integer|in:0,1,2',
        'parent_id' => 'string|max:36',
        'type' => 'string|max:50',
    ] + parent::FILTERS + [
        'domain' => 'string|max:255',
        'tag' => 'string|max:50',
        'theme' => 'string|max:50',
        'path' => 'string|max:255',
        'cache' => 'integer|min:0|max:525600',
        'to' => 'string|max:2000',
    ];


    /**
     * Applies the page specific filters.
     *
     * @param Builder<Model> $search Scout search builder
     * @param array<string, mixed> $filter Validated filter values
     * @return Builder<Model> Filtered search builder
     */
    protected function filter( Builder $search, array $filter ) : Builder
    {
        return Filter::pages( $search, $filter );
    }


    /**
     * Returns the data of the found page.
     *
     * @param Model $item Found page
     * @return array<string, mixed> Page data
     */
    protected function present( Model $item ) : array
    {
        /** @var PageView $item */
        return ['has_children' => $item->has] + Presenter::page( $item, false );
    }


    /**
     * Get the tool's input schema.
     *
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema( JsonSchema $schema ) : array
    {
        return [
            'term' => $schema->string()
                ->description('Search keyword, e.g., "blog", "product", or "FAQ". One word or page path only.'),
            'lang' => $schema->string()
                ->description('ISO language code from the get-locales tool call, e.g., "en" or "en-US".'),
            'status' => $schema->integer()
                ->description('Filter by status: 0 = inactive, 1 = visible, 2 = hidden.'),
            'parent_id' => $schema->string()
                ->description('Filter by parent page ID to list children of a specific page.'),
            'type' => $schema->string()
                ->description('Filter by page type, e.g., "page", "blog", "docs".'),
            ...$this->filters( $schema ),
            'domain' => $schema->string()
                ->description('Filter by domain name.'),
            'tag' => $schema->string()
                ->description('Filter by page tag, e.g., "root".'),
            'theme' => $schema->string()
                ->description('Filter by theme name.'),
            'path' => $schema->string()
                ->description('Filter by page path.'),
            'cache' => $schema->integer()
                ->description('Filter by cache duration in minutes.'),
            'to' => $schema->string()
                ->description('Filter by redirect URL.'),
        ];
    }
}
