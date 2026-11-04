<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Import;

use Aimeos\Cms\Models\Element;
use Aimeos\Cms\Utils;


/**
 * Builds Pagible content and shared elements for the importers.
 */
class Elements
{
    /**
     * Returns a content element of the main group.
     *
     * @param string $type Element type
     * @param array<string, mixed> $data Element data
     * @return array<string, mixed> Content element
     */
    public static function create( string $type, array $data ): array
    {
        return [
            'id' => Utils::uid(),
            'type' => $type,
            'group' => 'main',
            'data' => $data,
        ];
    }


    /**
     * Returns a reference to a file for the element data.
     *
     * @param string $id File ID
     * @return array{id: string, type: string} File reference
     */
    public static function file( string $id ): array
    {
        return ['id' => $id, 'type' => 'file'];
    }


    /**
     * Returns an image element referencing the file.
     *
     * @param string $fileId File ID
     * @return array<string, mixed> Content element
     */
    public static function image( string $fileId ): array
    {
        return self::create( 'image', ['file' => self::file( $fileId )] );
    }


    /**
     * Returns the content of an image or image-text element for the file.
     *
     * @param string|null $fileId File ID or NULL if the image couldn't be imported
     * @param string $text Text next to the image or empty for an image element
     * @return array{elements: array<int, array<string, mixed>>, fileIds: array<int, string>}|null Content or NULL without file
     */
    public static function imageContent( ?string $fileId, string $text = '' ): ?array
    {
        if( !$fileId ) {
            return null;
        }

        $element = $text !== ''
            ? self::create( 'image-text', ['text' => $text, 'file' => self::file( $fileId )] )
            : self::image( $fileId );

        return ['elements' => [$element], 'fileIds' => [$fileId]];
    }


    /**
     * Merges content parts into one content in their order.
     *
     * @param array{elements?: array<int, array<string, mixed>>, fileIds?: array<int, string>}|null ...$contents Content parts, NULL parts are skipped
     * @return array{elements: array<int, array<string, mixed>>, fileIds: array<int, string>} Merged content with unique file IDs
     */
    public static function merge( ?array ...$contents ): array
    {
        $contents = array_filter( $contents );
        $elements = array_merge( ...array_map( fn( $content ) => $content['elements'] ?? [], $contents ) );
        $fileIds = array_merge( ...array_map( fn( $content ) => $content['fileIds'] ?? [], $contents ) );

        return ['elements' => $elements, 'fileIds' => array_values( array_unique( $fileIds ) )];
    }


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
    public static function shared( string $name, string $lang, array $element, array $fileIds, string $editor ): string
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
