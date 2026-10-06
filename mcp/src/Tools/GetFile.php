<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Tools;

use Aimeos\Cms\Models\File;
use Aimeos\Cms\Models\Version;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Response;
use Laravel\Mcp\Request;


#[IsReadOnly]
#[Name('get-file')]
#[Title('Get file details by ID')]
#[Description('Retrieves full details for a media file including name, MIME type, path, preview URLs, descriptions, and transcription data. Returns the file as a JSON object. The returned latest_id identifies the version you read — pass it back to save-file so concurrent edits are merged instead of overwritten.')]
class GetFile extends Tool
{
    protected const PERMISSIONS = ['file:view'];


    /**
     * Handle the tool request.
     */
    protected function run( Request $request ) : \Laravel\Mcp\ResponseFactory
    {
        $v = $request->validate([
            'id' => 'required|string|max:36',
        ], [
            'id.required' => 'You must specify the file ID.',
        ] );

        /** @var File $file */
        $file = File::withTrashed()->with( [
            'latest' => fn( $q ) => $q->select( [...Version::SELECT_COLUMNS, 'aux', 'publish_at', 'created_at'] )
        ] )->findOrFail( $v['id'] );

        $data = Presenter::file( $file ) + [
            'used_by_elements' => Presenter::rows( $file->byelements(), 'cms_elements.id', 'cms_elements.type', 'cms_elements.name' ),
            'used_by_pages' => Presenter::rows( $file->bypages(), 'cms_pages.id', 'cms_pages.name', 'cms_pages.path' ),
        ];

        return Response::structured( $data );
    }


    /**
     * Get the tool's input schema.
     *
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema( JsonSchema $schema ) : array
    {
        return [
            'id' => $schema->string()
                ->description('The UUID of the file to retrieve.')
                ->required(),
        ];
    }
}
