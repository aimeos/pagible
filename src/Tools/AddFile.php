<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Aimeos\Cms\Tools;

use Aimeos\Cms\Utils;
use Aimeos\Cms\Permission;
use Aimeos\Cms\Models\File;
use Aimeos\Cms\Models\Version;
use Illuminate\Support\Facades\DB;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Response;
use Laravel\Mcp\Request;


#[Name('add-file')]
#[Title('Add a media file from a URL')]
#[Description('Adds a new media file (image, video, audio, document) from a URL. Automatically generates preview images for image files. Returns the created file as a JSON object.')]
class AddFile extends Tool
{
    /**
     * Handle the tool request.
     */
    public function handle( Request $request ): \Laravel\Mcp\ResponseFactory
    {
        if( !Permission::can( 'file:add', $request->user() ) ) {
            throw new \Exception( 'Insufficient permissions' );
        }

        $validated = $request->validate([
            'url' => 'required|string|max:500',
            'name' => 'string|max:255',
            'lang' => 'string|max:5',
            'description' => 'array',
        ], [
            'url.required' => 'You must specify the URL of the file to add, e.g., "https://example.com/image.jpg".',
        ] );

        $url = $validated['url'];

        if( !str_starts_with( $url, 'http' ) ) {
            return Response::structured( ['error' => 'The URL must start with "http" or "https".'] );
        }

        return DB::connection( config( 'cms.db', 'sqlite' ) )->transaction( function() use ( $url, $validated, $request ) {

            $editor = (string) $request->user()?->name; // @phpstan-ignore-line property.notFound
            $versionId = ( new Version )->newUniqueId();

            $file = new File();
            $file->fill( array_intersect_key( $validated, array_flip( ['name', 'lang'] ) ) );

            if( isset( $validated['description'] ) ) {
                $file->description = $validated['description'];
            }

            $file->tenant_id = \Aimeos\Cms\Tenancy::value();
            $file->path = $url;
            $file->mime = Utils::mimetype( $url );
            $file->name = $file->name ?: substr( $url, 0, 255 );
            $file->latest_id = $versionId;
            $file->editor = $editor;

            try {
                if( str_starts_with( $file->mime, 'image/' ) ) {
                    $file->addPreviews( $url );
                }
            } catch( \Throwable $t ) {
                $file->removePreviews();
                throw $t;
            }

            $file->save();

            $file->versions()->forceCreate( [
                'id' => $versionId,
                'lang' => $validated['lang'] ?? null,
                'editor' => $editor,
                'data' => [
                    'lang' => $file->lang,
                    'name' => $file->name,
                    'mime' => $file->mime,
                    'path' => $file->path,
                    'previews' => $file->previews,
                    'description' => $file->description,
                    'transcription' => $file->transcription,
                ],
            ] );

            return Response::structured( $file->toArray() );
        }, 3 );
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
                ->description('The URL of the file to add, e.g., "https://example.com/photo.jpg".')
                ->required(),
            'name' => $schema->string()
                ->description('Display name for the file. If omitted, the URL is used as the name.'),
            'lang' => $schema->string()
                ->description('ISO language code, e.g., "en" or "de".'),
            'description' => $schema->object()
                ->description('Multilingual description object, e.g., {"en": "A sunset photo", "de": "Ein Sonnenuntergangsfoto"}. Used as alt text for images.'),
        ];
    }


    /**
     * Determine if the tool should be registered.
     *
     * @param Request $request The incoming request to check permissions for.
     * @return bool TRUE if the tool should be registered, FALSE otherwise.
     */
    public function shouldRegister( Request $request ) : bool
    {
        return Permission::can( 'file:add', $request->user() );
    }
}
