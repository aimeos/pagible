<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */

namespace Aimeos\Cms\Access;

use Aimeos\Cms\Exception;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;


/**
 * Access adapter for silber/bouncer 1.0.2 or newer.
 */
class Bouncer extends Provider
{
    private const SERVICE = 'Silber\\Bouncer\\Bouncer';


    /**
     * Returns the access adapter using Bouncer abilities.
     *
     * @param (\Closure(Authenticatable): (iterable<mixed>|null))|null $grants Effective-grants resolver
     */
    public static function adapter( ?\Closure $grants = null ) : Adapter
    {
        return new Adapter(
            list: fn() => self::modelNames( self::call( self::SERVICE, 'ability' ), ['entity_type' => null] ),
            activate: fn( string $tenant ) => self::call( self::call( self::SERVICE, 'scope' ), 'to', $tenant ),
            add: function( string $value ) {
                self::modelAdd( self::call( self::SERVICE, 'ability' ), $value );
                self::call( self::SERVICE, 'refresh' );
            },
            delete: function( array $values ) {
                self::modelDelete( self::call( self::SERVICE, 'ability' ), $values, ['entity_type' => null] );
                self::call( self::SERVICE, 'refresh' );
            },
            grants: $grants,
            userAccess: fn( Authenticatable $user, ?array $values ) => self::userAccess( $user, $values ),
        );
    }


    /**
     * Returns Bouncer abilities assigned directly in the active scope.
     *
     * @return array<int, mixed>
     */
    private static function assigned( Authenticatable $user ) : array
    {
        $relation = self::call( $user, 'abilities' );

        if( !is_object( $relation ) ) {
            throw new Exception( 'Bouncer user abilities relation is not available.' );
        }

        $related = self::call( $relation, 'getRelated' );

        if( !$related instanceof Model ) {
            throw new Exception( 'Bouncer ability model is not available.' );
        }

        self::call( $relation, 'whereNull', $related->qualifyColumn( 'entity_type' ) );
        self::call( $relation, 'wherePivot', 'forbidden', false );

        return self::itemNames( self::call( $relation, 'get', [$related->qualifyColumn( 'name' )] ) );
    }


    /**
     * Reads or replaces the abilities assigned directly to the user.
     *
     * @param array<int, string>|null $values NULL reads; an array replaces the complete set
     * @return array<int, mixed>
     */
    private static function userAccess( Authenticatable $user, ?array $values ) : array
    {
        return self::replace( $user, $values, self::assigned( ... ), function( array $assign, array $remove ) use ( $user ) {
            if( $assign ) {
                self::call( self::call( self::SERVICE, 'allow', $user ), 'to', $assign );
            }

            if( $remove ) {
                self::call( self::call( self::SERVICE, 'disallow', $user ), 'to', $remove );
            }

            self::call( self::SERVICE, 'refreshFor', $user );
        } );
    }
}
