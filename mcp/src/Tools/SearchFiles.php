<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Tools;

use Aimeos\Cms\Query\Filter;
use Aimeos\Cms\Models\File;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Model;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Scout\Builder;


#[IsReadOnly]
#[Name('search-files')]
#[Title('Search files by keyword')]
#[Description('Lists and searches media files. Optional: term (full-text search), mime, lang, trashed, publish, editor. Without term, returns all matching files. Returns up to 25 results.')]
class SearchFiles extends SearchTool
{
    protected const MODEL = File::class;
    protected const COLUMNS = ['cms_files.id', 'cms_files.tenant_id', 'cms_files.created_at', 'cms_files.updated_at', 'cms_files.deleted_at', 'cms_files.latest_id'];
    protected const KEY = 'files';
    protected const PERMISSIONS = ['file:view'];
    protected const RULES = [
        'term' => 'string|max:255',
        'mime' => 'string|max:50',
        'lang' => 'nullable|string|max:10',
    ] + parent::FILTERS;


    /**
     * Applies the file specific filters.
     *
     * @param Builder<Model> $search Scout search builder
     * @param array<string, mixed> $filter Validated filter values
     * @return Builder<Model> Filtered search builder
     */
    protected function filter( Builder $search, array $filter ) : Builder
    {
        return Filter::files( $search, $filter );
    }


    /**
     * Returns the data of the found file.
     *
     * @param Model $item Found file
     * @return array<string, mixed> File data
     */
    protected function present( Model $item ) : array
    {
        /** @var File $item */
        return Presenter::file( $item, false );
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
                ->description('Search keyword to match against file name, description, and transcription.'),
            'mime' => $schema->string()
                ->description('Filter by MIME type prefix, e.g., "image" for all images, "video" for all videos.'),
            'lang' => $schema->string()
                ->description('Filter by ISO language code, e.g., "en" or "de".'),
            ...$this->filters( $schema ),
        ];
    }
}
