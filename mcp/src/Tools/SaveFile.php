<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Tools;

use Aimeos\Cms\Resource;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Http\UploadedFile;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Response;
use Laravel\Mcp\Request;


#[Name('save-file')]
#[Title('Save file metadata or content')]
#[Description('Saves the name, description, language, or content of an existing media file. Creates a new draft version. Returns the updated file as a JSON object.')]
class SaveFile extends Tool
{
    use Concerns\Upload;

    protected const PERMISSIONS = ['file:save'];


    /**
     * Handle the tool request.
     */
    protected function run( Request $request ) : \Laravel\Mcp\ResponseFactory
    {
        $v = $request->validate([
            'id' => 'required|string|max:36',
            'name' => 'string|max:255',
            'lang' => 'nullable|string|max:5',
            'description' => 'array',
            'latest_id' => 'required|string|max:36',
            'content' => 'string|max:8388608',
        ], [
            'id.required' => 'You must specify the ID of the file to save.',
            'latest_id.required' => 'You must pass the latest_id returned by get-file, add-file, or a previous save-file so concurrent edits are detected.',
            'content.max' => 'The file content must not exceed 8 MB.',
        ] );

        $input = array_diff_key( $v, array_flip( ['id', 'latest_id', 'content'] ) );

        $file = $this->source( $v, fn( ?UploadedFile $upload ) =>
            Resource::saveFile( $v['id'], $input, $request->user(), $v['latest_id'], $upload )
        );

        return Response::structured( Presenter::saved( Presenter::file( $file ), $file ) );
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
                ->description( 'The UUID of the file to save. Use search-files to find the ID.' )
                ->required(),
            'name' => $schema->string()
                ->description( 'New display name for the file.' ),
            'lang' => $schema->string()
                ->description( 'ISO language code for the file, e.g., "en" or "de".' ),
            'description' => $schema->object()
                ->description( 'Multilingual description object, e.g., {"en": "A sunset photo", "de": "Ein Sonnenuntergangsfoto"}. Used as alt text for images.' ),
            'content' => $schema->string()
                ->description( 'Base64 encoded file content (optionally as data URI) replacing the current file. Up to 8 MB, only suitable for small files. Previews are regenerated for images.' ),
            'latest_id' => $schema->string()
                ->description( 'Required. The latest_id value returned by get-file, add-file, or your previous save-file for this file. Ensures edits made by another editor in the meantime are merged instead of overwritten.' )
                ->required(),
        ];
    }
}
