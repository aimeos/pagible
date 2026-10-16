<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\GraphQL\Mutations;

use Aimeos\Cms\Models\File;
use Aimeos\Cms\Facades\Files;
use GraphQL\Error\Error;
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

        $source = $args['file'] ?? $args['input']['path'];

        if( is_string( $source ) && !str_starts_with( $source, 'http' ) ) {
            throw new Error( sprintf( 'Invalid URL "%s"', $source ) );
        }

        return Files::add( $source, $args['input'] ?? [], Auth::user(), $args['disk'] ?? null, $args['preview'] ?? null );
    }
}
