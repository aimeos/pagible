<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Import;

use Aimeos\Cms\Models\Element;


/**
 * Saves the shared elements of the importers.
 */
class SharedElements
{
    /**
     * Creates the shared element or publishes a new version of the one imported before if it changed.
     *
     * Elements changed by other editors since are kept as they are.
     *
     * @param string $name Unique name of the shared element in the language
     * @param string $lang Pagible language code
     * @param array<string, mixed> $element Content element
     * @param array<int, string> $fileIds IDs of the files which may be used by the element
     * @param string $editor Editor name of the importer
     * @return string ID of the shared element
     */
    public static function save( string $name, string $lang, array $element, array $fileIds, string $editor ): string
    {
        $type = (string) ( $element['type'] ?? '' );
        $data = (array) ( $element['data'] ?? [] );
        $shared = Element::withTrashed()->where( 'lang', $lang )->where( 'name', $name )->first();

        if( $shared?->trashed() ) {
            $shared->restore();
        }

        if( $shared && ( $shared->editor !== $editor
            || $shared->type === $type && Pages::normalize( $shared->data ) === Pages::normalize( $data ) )
        ) {
            return (string) $shared->id;
        }

        $shared ??= Element::forceCreate( ['lang' => $lang, 'type' => $type, 'name' => $name, 'data' => $data, 'editor' => $editor] );

        $version = $shared->versions()->forceCreate( [
            'lang' => $lang,
            'data' => ['lang' => $lang, 'type' => $type, 'name' => $name, 'data' => $data],
            'editor' => $editor,
        ] );

        // only the files referenced by the element are attached
        $json = (string) json_encode( $data );
        $ids = array_values( array_unique( array_filter( $fileIds, fn( $id ) => str_contains( $json, '"' . $id . '"' ) ) ) );

        if( $ids ) {
            $version->files()->attach( $ids );
        }

        // publishing saves the latest version ID together with the published values
        $shared->forceFill( ['latest_id' => $version->id] );
        $shared->publish( $version );

        return (string) $shared->id;
    }
}
