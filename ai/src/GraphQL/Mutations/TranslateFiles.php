<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\GraphQL\Mutations;

use Aimeos\Cms\Models\File;
use Aimeos\Cms\Translation;
use Illuminate\Support\Facades\Auth;


final class TranslateFiles
{
    /**
     * Translates the missing file descriptions, the schema limits the request to 100 files and 5 languages.
     *
     * @param  null  $rootValue
     * @param  array<string, mixed>  $args
     * @return array<int, File> Files with new drafts
     */
    public function __invoke( $rootValue, array $args ) : array
    {
        return Translation\Descriptions::translate( $args['id'], $args['lang'], Auth::user() );
    }
}
