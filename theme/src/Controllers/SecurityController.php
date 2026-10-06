<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Controllers;

use Aimeos\Cms\Models\Nav;
use Illuminate\Routing\Controller;
use Symfony\Component\HttpFoundation\Response;


class SecurityController extends Controller
{
    /**
     * Returns the RFC 9116 security.txt information configured on a published root page.
     *
     * @param string $domain Requested domain
     * @return Response Plain-text security policy discovery response
     */
    public function index( string $domain = '' ) : Response
    {
        $data = $this->data( $domain );
        $contact = $this->url( $data['contact'] ?? null );
        $expires = $this->expires( $data['expires'] ?? null );

        if( !$contact || !$expires ) {
            abort( 404 );
        }

        $email = $this->email( $data['email'] ?? null );
        $lines = [];

        foreach( [
            ['Contact', $contact],
            ['Contact', $email ? 'mailto:' . $email : null],
            ['Expires', $expires],
            ['Encryption', $this->url( $data['encryption'] ?? null )],
            ['Acknowledgments', $this->url( $data['acknowledgments'] ?? null )],
            ['Preferred-Languages', $this->languages( $data['preferred-languages'] ?? null )],
            ['Canonical', $this->url( $data['canonical'] ?? null )],
            ['Policy', $this->url( $data['policy'] ?? null )],
            ['Hiring', $this->url( $data['hiring'] ?? null )],
            ['CSAF', $this->url( $data['csaf'] ?? null )],
        ] as [$name, $value] ) {
            if( $value ) {
                $lines[] = $name . ': ' . $value;
            }
        }

        return response( implode( "\n", $lines ) . "\n", 200, [
            'Content-Type' => 'text/plain; charset=utf-8',
            'Cache-Control' => 'public, max-age=300',
        ] );
    }


    /**
     * Returns the published security config from the first matching root page.
     *
     * @param string $domain Requested domain, empty when multi-domain routing is disabled
     * @return array<string, mixed>|null Security config data
     */
    protected function data( string $domain ) : ?array
    {
        return Nav::rootConfig( $domain, fn( $page ) => is_object( $data = $page->config->security->data ?? null ) ? (array) $data : null );
    }


    /**
     * Returns a valid contact email address.
     */
    protected function email( mixed $value ) : ?string
    {
        $value = trim( is_scalar( $value ) ? (string) $value : '' );

        return $value !== '' && strlen( $value ) <= 254 && filter_var( $value, FILTER_VALIDATE_EMAIL )
            ? $value
            : null;
    }


    /**
     * Formats a date field as an RFC 3339 timestamp at the end of the selected day.
     */
    protected function expires( mixed $value ) : ?string
    {
        $value = trim( is_scalar( $value ) ? (string) $value : '' );

        if( !preg_match( '/^(\d{4}-\d{2}-\d{2})(?:T\d{2}:\d{2}:\d{2}(?:\.\d+)?Z)?$/D', $value, $matches ) ) {
            return null;
        }

        $date = \DateTimeImmutable::createFromFormat(
            '!Y-m-d',
            $matches[1],
            new \DateTimeZone( 'UTC' ),
        );
        $errors = \DateTimeImmutable::getLastErrors();

        if( !$date || ( $errors !== false && ( $errors['warning_count'] || $errors['error_count'] ) ) ) {
            return null;
        }

        return $date->setTime( 23, 59, 59 )->format( 'Y-m-d\TH:i:s\Z' );
    }


    /**
     * Normalizes a comma-separated list of BCP 47 language tags.
     */
    protected function languages( mixed $value ) : ?string
    {
        $values = preg_split( '/\s*,\s*/', trim( (string) $value ), -1, PREG_SPLIT_NO_EMPTY ) ?: [];

        if( !$values || array_filter( $values, fn( string $item ) =>
            !preg_match( '/^[A-Za-z0-9]{1,8}(?:-[A-Za-z0-9]{1,8})*$/D', $item )
        ) ) {
            return null;
        }

        return implode( ', ', $values );
    }


    /**
     * Returns a valid absolute HTTPS URL.
     */
    protected function url( mixed $value ) : ?string
    {
        $value = trim( is_scalar( $value ) ? (string) $value : '' );

        return strlen( $value ) <= 2048
            && filter_var( $value, FILTER_VALIDATE_URL )
            && strtolower( (string) parse_url( $value, PHP_URL_SCHEME ) ) === 'https'
            && parse_url( $value, PHP_URL_HOST )
                ? $value
                : null;
    }
}
