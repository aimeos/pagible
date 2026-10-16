<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Tools;

use Aimeos\Cms\Query\Filter;
use Aimeos\Cms\Models\Element;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Model;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Scout\Builder;


#[IsReadOnly]
#[Name('search-elements')]
#[Title('Search shared content elements')]
#[Description('Lists and searches shared content elements. Optional: term (full-text search), type, lang, trashed, publish, editor. Without term, returns all matching elements. Returns up to 25 results.')]
class SearchElements extends SearchTool
{
    protected const MODEL = Element::class;
    protected const COLUMNS = ['cms_elements.id', 'cms_elements.tenant_id', 'cms_elements.created_at', 'cms_elements.updated_at', 'cms_elements.deleted_at', 'cms_elements.latest_id'];
    protected const KEY = 'elements';
    protected const PERMISSIONS = ['element:view'];
    protected const RULES = [
        'term' => 'string|max:255',
        'type' => 'string|max:50',
        'lang' => 'nullable|string|max:10',
    ] + parent::FILTERS;


    /**
     * Applies the element specific filters.
     *
     * @param Builder<Model> $search Scout search builder
     * @param array<string, mixed> $filter Validated filter values
     * @return Builder<Model> Filtered search builder
     */
    protected function filter( Builder $search, array $filter ) : Builder
    {
        return Filter::elements( $search, $filter );
    }


    /**
     * Returns the data of the found element.
     *
     * @param Model $item Found element
     * @return array<string, mixed> Element data
     */
    protected function present( Model $item ) : array
    {
        /** @var Element $item */
        return Presenter::element( $item, false );
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
                ->description('Search keyword to match against element name or data content.'),
            'type' => $schema->string()
                ->description('Filter by element type, e.g., "heading", "text", "image", "contact".'),
            'lang' => $schema->string()
                ->description('Filter by ISO language code, e.g., "en" or "de".'),
            ...$this->filters( $schema ),
        ];
    }
}
