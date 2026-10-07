<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Tools;

use Aimeos\Cms\Resource;
use Aimeos\Cms\Models\File;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Http\UploadedFile;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Response;
use Laravel\Mcp\Request;


#[Name('add-file')]
#[Title('Add a media file from a URL or content')]
#[Description('Adds a new media file (image, video, audio, document) from a URL or base64 encoded content. Prefer the URL for large files. Automatically generates preview images for image files. Returns the created file as a JSON object, including the latest_id to pass to save-file when editing it.')]
class AddFile extends Tool
{
    use Concerns\Upload;

    protected const PERMISSIONS = ['file:add'];


    /**
     * Validates, ingests, and stores the remote or uploaded File requested by the MCP client.
     *
     * @param Request $request Authorized MCP tool request
     * @return \Laravel\Mcp\ResponseFactory Structured File data or validation error
     */
    protected function run( Request $request ) : \Laravel\Mcp\ResponseFactory
    {
        $v = $request->validate([
            'url' => 'required_without:content|prohibits:content|string|max:500|url:http,https',
            'content' => 'string|max:8388608',
            'disk' => 'sometimes|string|in:public,private',
            'name' => 'string|max:255',
            'lang' => 'nullable|string|max:10',
            'description' => 'array',
        ], [
            'url.required_without' => 'You must specify the URL of the file to add, e.g., "https://example.com/image.jpg", or its base64 encoded content.',
            'content.max' => 'The file content must not exceed 8 MB, pass a URL instead.',
            'url.url' => 'The URL must be a valid "http" or "https" URL.',
        ] );

        $file = new File();
        $file->disk = $v['disk'] ?? 'public';
        $file->fill( array_intersect_key( $v, array_flip( ['name', 'lang'] ) ) );

        if( isset( $v['description'] ) ) {
            $file->description = $v['description'];
        }

        // Fetch the file and generate previews outside the transaction to keep
        // slow network and image work off the database connection.
        $this->source( $v, fn( UploadedFile|string $source ) => $file->ingest( $source ) );

        $file = Resource::addFile( $file, $request->user() );

        return Response::structured( Presenter::item( $file ) );
    }


    /**
     * Get the tool's input schema.
     *
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema( JsonSchema $schema ) : array
    {
        return [
            'url' => $schema->string()
                ->description('The URL of the file to add, e.g., "https://example.com/photo.jpg". Either "url" or "content" is required.'),
            'content' => $schema->string()
                ->description('Base64 encoded file content (optionally as data URI) if the file has no public URL. Up to 8 MB, only suitable for small files; pass "name" with file extension, e.g., "icon.svg".'),
            'disk' => $schema->string()
                ->enum( ['public', 'private'] )
                ->description('Storage visibility. Defaults to "public"; use "private" to protect it with page access.'),
            'name' => $schema->string()
                ->description('Display name for the file. If omitted, the URL or "file" is used as the name.'),
            'lang' => $schema->string()
                ->description('ISO language code, e.g., "en" or "de".'),
            'description' => $schema->object()
                ->description('Multilingual description object, e.g., {"en": "A sunset photo", "de": "Ein Sonnenuntergangsfoto"}. Used as alt text for images.'),
        ];
    }
}
