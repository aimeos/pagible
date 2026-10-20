<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Commands;

use Aimeos\Cms\Models\PageView;
use Aimeos\Cms\Schema;
use Aimeos\Cms\Theme;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\View;


class CheckSchema extends Command
{
    /**
     * Field types available in the admin panel (admin/js/fields/*.vue)
     */
    public const TYPES = [
        'audio', 'autocomplete', 'checkbox', 'color', 'combobox', 'date', 'file', 'hidden', 'html',
        'image', 'images', 'items', 'map', 'markdown', 'media', 'number', 'plaintext', 'radio',
        'range', 'select', 'slider', 'string', 'switch', 'table', 'text', 'url', 'video',
    ];

    /**
     * Command name
     */
    protected $signature = 'cms:schema:check
        {theme?* : Names of the themes to check (all registered if empty)}';

    /**
     * Command description
     */
    protected $description = 'Check the schema.json files of the registered themes for errors';


    /**
     * Execute command
     */
    public function handle() : int
    {
        $names = (array) $this->argument( 'theme' );
        $count = 0;

        foreach( Schema::all() as $theme => $data )
        {
            if( !empty( $names ) && !in_array( $theme, $names, true ) ) {
                continue;
            }

            $errors = $this->errors( $theme, $data );
            $count += count( $errors );

            foreach( $errors as $error ) {
                $this->error( $theme . ': ' . $error );
            }
        }

        if( $count ) {
            return 1;
        }

        $this->info( 'No errors found' );
        return 0;
    }


    /**
     * Returns the errors of the action field of a content element.
     *
     * @param array<string, mixed> $field Action field definition
     * @return array<int, string> Error messages
     */
    protected function action( string $key, array $field ) : array
    {
        $value = $field['value'] ?? null;

        if( !is_string( $value ) || $value === '' ) {
            return [sprintf( '"%s" has an action field without value', $key )];
        }

        [$class, $method] = array_pad( explode( '@', $value, 2 ), 2, '__invoke' );

        if( !class_exists( $class ) || !method_exists( $class, $method ) ) {
            return [sprintf( '"%s" uses the action "%s" which doesn\'t exist', $key, $value )];
        }

        return [];
    }


    /**
     * Returns the errors of all schemas of a theme.
     *
     * @param array<string, mixed> $data Theme data
     * @return array<int, string> Error messages
     */
    protected function errors( string $theme, array $data ) : array
    {
        $errors = [];

        foreach( ['content', 'meta', 'config'] as $section )
        {
            foreach( (array) ( $data[$section] ?? [] ) as $key => $schema )
            {
                $fields = (array) ( $schema['fields'] ?? [] );
                $errors = array_merge( $errors, $this->fields( $key, $fields ) );

                if( $section === 'content' && isset( $fields['action'] ) ) {
                    $errors = array_merge( $errors, $this->action( $key, (array) $fields['action'] ) );
                }

                // themes from the storage disk resolve their views at runtime
                if( $section !== 'config' && isset( $data['path'] ) && !$this->view( $theme, $key ) ) {
                    $errors[] = sprintf( '"%s" has no view', $key );
                }
            }
        }

        return $errors;
    }


    /**
     * Returns the errors of the fields of a schema including nested items.
     *
     * @param array<mixed> $fields Field definitions
     * @return array<int, string> Error messages
     */
    protected function fields( string $path, array $fields ) : array
    {
        $errors = [];

        foreach( $fields as $name => $field )
        {
            $code = $path . '.' . $name;
            $type = is_array( $field ) ? ( $field['type'] ?? null ) : null;

            if( !is_string( $type ) || !in_array( $type, self::TYPES, true ) )
            {
                $errors[] = sprintf( '"%s" has the unknown type "%s"', $code, is_string( $type ) ? $type : '' );
                continue;
            }

            if( in_array( $type, ['radio', 'select'] ) && empty( $field['options'] ) ) {
                $errors[] = sprintf( '"%s" has no options', $code );
            }

            if( $type === 'autocomplete' && empty( $field['options'] ) && empty( $field['query'] ) && empty( $field['url'] ) ) {
                $errors[] = sprintf( '"%s" needs options, a query or an URL', $code );
            }

            if( $type === 'items' ) {
                $errors = array_merge( $errors, $this->fields( $code, (array) ( $field['item'] ?? [] ) ) );
            }
        }

        return $errors;
    }


    /**
     * Tests if one of the views Theme::names() resolves for the schema exists.
     */
    protected function view( string $theme, string $key ) : bool
    {
        $names = Theme::names( new PageView( ['theme' => $theme] ), (object) ['type' => $key] );

        return collect( $names )->contains( fn( $name ) => View::exists( $name ) );
    }
}
