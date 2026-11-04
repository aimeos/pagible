<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Import;


/**
 * Reads the domains of the Drupal Domain Access module.
 */
class DrupalDomains
{
    /**
     * @param DrupalData $data Configuration and tables of the Drupal database
     */
    public function __construct( protected DrupalData $data )
    {
    }


    /**
     * Returns the hostname of the Domain Access domain.
     *
     * @param string $id Domain Access domain ID from the --drupal-domain option
     * @return string Hostname or empty string if the domain has none
     * @throws \RuntimeException If the Domain Access module isn't used or the domain doesn't exist
     */
    public function hostname( string $id ): string
    {
        if( !$this->used() ) {
            throw new \RuntimeException( "The --drupal-domain option requires the Domain Access module but its table \"node__field_domain_access\" doesn't exist." );
        }

        if( !( $record = $this->data->config( "domain.record.{$id}" ) ) ) {
            throw new \RuntimeException( "Domain Access domain \"{$id}\" not found." );
        }

        return (string) ( $record['hostname'] ?? '' );
    }


    /**
     * Tests if the Drupal site uses the Domain Access module.
     */
    public function used(): bool
    {
        return $this->data->hasTable( 'node__field_domain_access' );
    }
}
