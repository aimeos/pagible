<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Import;


/**
 * Formats the values of simple Drupal fields as HTML like Drupal's default formatters show them.
 */
class DrupalFormatter
{
    /**
     * Initializes the object.
     *
     * @param DrupalData $data Configuration and entities of the Drupal database
     * @param DrupalUrls $urls URLs of the imported pages
     */
    public function __construct( protected DrupalData $data, protected DrupalUrls $urls )
    {
    }


    /**
     * Returns the HTML of a field value of a simple field type like Drupal's default formatters show it.
     *
     * @param string $entityType Entity type ID, e.g. "node"
     * @param string $bundle Bundle name or empty string if unknown
     * @param string $field Field name, e.g. "field_date"
     * @param array{type: string, settings?: array<string, mixed>} $storage Field storage
     * @param \Closure(string): mixed $value Returns the value of the field column, e.g. "value" or "target_id"
     * @param string $langcode Drupal language code
     * @return string|null HTML, empty string if there's no value or NULL if it's not a simple field type
     */
    public function text( string $entityType, string $bundle, string $field, array $storage, \Closure $value, string $langcode ): ?string
    {
        $text = (string) $value( 'value' );

        return match( $storage['type'] ) {
            'datetime' => e( $this->date( $text ) ),
            'daterange' => e( implode( ' – ', array_filter( [$this->date( $text ), $this->date( (string) $value( 'end_value' ) )] ) ) ),
            'timestamp', 'created', 'changed' => $text !== '' ? e( $this->date( '@' . $text ) ) : '',
            'integer', 'decimal', 'float', 'telephone' => e( $text ),
            'email' => $text !== '' ? '<a href="mailto:' . e( $text ) . '">' . e( $text ) . '</a>' : '',
            'boolean' => e( $this->booleanLabel( $entityType, $bundle, $field, $text !== '' && $text !== '0', $langcode ) ),
            'list_string', 'list_integer', 'list_float' => e( $this->allowedValue( $entityType, $field, $text, $langcode ) ),
            'entity_reference' => match( $storage['settings']['target_type'] ?? '' ) {
                'node' => $this->nodeLink( (int) $value( 'target_id' ), $langcode ),
                'taxonomy_term' => $this->termLink( (int) $value( 'target_id' ), $langcode ),
                'user' => e( (string) ( $this->data->entity( 'users_field_data', 'uid', (int) $value( 'target_id' ) )->name ?? '' ) ),
                default => null,
            },
            default => null,
        };
    }


    /**
     * Returns the label of a field as HTML prefix if the default view display shows it.
     *
     * @param string $entityType Entity type ID, e.g. "node"
     * @param string $bundle Bundle name or empty string if unknown
     * @param string $field Field name, e.g. "field_date"
     * @param string $langcode Drupal language code
     * @return string HTML like "<strong>Date:</strong> " or empty string
     */
    public function label( string $entityType, string $bundle, string $field, string $langcode ): string
    {
        if( $bundle === '' ) {
            return '';
        }

        $display = (array) ( $this->data->config( "core.entity_view_display.{$entityType}.{$bundle}.default" )['content'][$field] ?? [] );
        $label = trim( (string) ( $this->data->config( "field.field.{$entityType}.{$bundle}.{$field}", $langcode )['label'] ?? '' ) );

        if( $label === '' || in_array( $display['label'] ?? 'above', ['hidden', 'visually_hidden'], true ) ) {
            return '';
        }

        return '<strong>' . e( $label ) . ':</strong> ';
    }


    /**
     * Returns the link to the imported page of the node or its title if the node isn't imported.
     *
     * @return string HTML or empty string if the node doesn't exist or is unpublished
     */
    public function nodeLink( int $nid, string $langcode ): string
    {
        $node = $this->data->entity( 'node_field_data', 'nid', $nid, $langcode );

        if( !$node || empty( $node->status ) || ( $title = trim( (string) $node->title ) ) === '' ) {
            return '';
        }

        return ( $url = $this->urls->node( $nid, $langcode ) ) ? '<a href="' . e( $url ) . '">' . e( $title ) . '</a>' : e( $title );
    }


    /**
     * Returns the HTML link to the page of the taxonomy term.
     *
     * Only terms of the vocabularies from the --terms option have pages, others are skipped like
     * before because their links would lead nowhere.
     *
     * @param int $tid Term ID
     * @param string $langcode Drupal language code
     * @return string HTML link or empty string if the term has no page
     */
    protected function termLink( int $tid, string $langcode ): string
    {
        $term = $this->data->entity( 'taxonomy_term_field_data', 'tid', $tid, $langcode );

        if( !( $url = $this->urls->term( $tid, $langcode ) ) || ( $name = trim( (string) ( $term->name ?? '' ) ) ) === '' ) {
            return '';
        }

        return '<a href="' . e( $url ) . '">' . e( $name ) . '</a>';
    }


    /**
     * Returns the label of a list field value from the allowed values of the field storage.
     *
     * Supports the list of value/label pairs of Drupal 9.2+ and the map of values to labels used before.
     */
    protected function allowedValue( string $entityType, string $field, string $value, string $langcode ): string
    {
        $allowed = (array) ( $this->data->config( "field.storage.{$entityType}.{$field}", $langcode )['settings']['allowed_values'] ?? [] );

        foreach( $allowed as $key => $item )
        {
            if( is_array( $item ) && (string) ( $item['value'] ?? '' ) === $value ) {
                return (string) ( $item['label'] ?? $value );
            }

            if( !is_array( $item ) && (string) $key === $value ) {
                return (string) $item;
            }
        }

        return $value;
    }


    /**
     * Returns the label of a boolean field value from the field settings.
     */
    protected function booleanLabel( string $entityType, string $bundle, string $field, bool $value, string $langcode ): string
    {
        $settings = (array) ( $this->data->config( "field.field.{$entityType}.{$bundle}.{$field}", $langcode )['settings'] ?? [] );

        return (string) ( $value ? ( $settings['on_label'] ?? 'On' ) : ( $settings['off_label'] ?? 'Off' ) );
    }


    /**
     * Returns a Drupal date in the site time zone.
     *
     * Dates without time are returned as they are, dates with time are stored in UTC by Drupal.
     *
     * @param string $value Date like "2024-05-01", "2024-05-01T10:00:00" or Unix timestamp prefixed by "@"
     * @return string Date like "2024-05-01" or "2024-05-01 12:00"
     */
    protected function date( string $value ): string
    {
        if( $value === '' || $value === '@' || preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) ) {
            return ltrim( $value, '@' );
        }

        try
        {
            $tz = (string) ( $this->data->config( 'system.date' )['timezone']['default'] ?? '' );
            $date = new \DateTimeImmutable( $value, new \DateTimeZone( 'UTC' ) );

            return $date->setTimezone( new \DateTimeZone( $tz !== '' ? $tz : 'UTC' ) )->format( 'Y-m-d H:i' );
        }
        catch( \Exception )
        {
            return ltrim( $value, '@' );
        }
    }
}
