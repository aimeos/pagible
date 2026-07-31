<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\GraphQL\Mutations;

use Aimeos\Cms\Models\File;
use Aimeos\Cms\Resource;
use Aimeos\Cms\Utils;
use GraphQL\Error\Error;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;


final class AddFile
{
    /**
     * Validates, ingests, and stores a File supplied through the GraphQL mutation.
     *
     * @param null $rootValue Unused GraphQL root value
     * @param array<string, mixed> $args Mutation input, upload, and optional preview
     * @return File Stored draft File
     */
    public function __invoke( $rootValue, array $args ) : File
    {
        if( empty( $args['input']['path'] ) && empty( $args['file'] ) ) {
            throw new Error( 'Either input "path" or "file" argument must be provided' );
        }

        $source = $args['file'] ?? $args['input']['path'] ?? null;
        $preview = $args['preview'] ?? null;

        if( !$source instanceof UploadedFile && !is_string( $source ) ) {
            throw new Error( 'Invalid file upload' );
        }

        if( is_string( $source ) && ( !str_starts_with( $source, 'http' ) || !Utils::isValidUrl( $source ) ) ) {
            throw new Error( sprintf( 'Invalid URL "%s"', $source ) );
        }

        if( $preview !== null && !$preview instanceof UploadedFile ) {
            throw new Error( 'Invalid preview upload' );
        }

        $file = new File();
        $file->disk = $args['disk'] ?? 'public';
        $file->fill( $args['input'] ?? [] );
        $file->ingest( $source, $preview );

        return Resource::addFile( $file, Auth::user() );
    }
}
