<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */

namespace Aimeos\Cms\Access;

use Aimeos\Cms\Exception;
use Aimeos\Cms\Tenancy;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Gate;


/**
 * Access adapter for santigarcor/laratrust 8.3.0 or newer.
 */
class Laratrust extends Provider
{
    /**
     * Returns the access adapter using Laratrust permissions.
     *
     * @param (\Closure(Authenticatable): (iterable<mixed>|null))|null $grants Effective-grants resolver
     */
    public static function adapter( ?\Closure $grants = null ) : Adapter
    {
        $model = config( 'laratrust.models.permission' );

        return new Adapter(
            list: fn() => self::gates( self::modelNames( $model ) ),
            add: fn( string $value ) => self::modelAdd( $model, $value ),
            delete: fn( array $values ) => self::modelDelete( $model, $values ),
            grants: $grants,
            userAccess: fn( Authenticatable $user, ?array $values ) => self::userAccess( $user, $values ),
        );
    }


    /**
     * Returns Laratrust permissions assigned directly in the active team.
     *
     * @return array<int, mixed>
     */
    private static function assigned( Authenticatable $user ) : array
    {
        $relation = self::call( $user, 'permissions' );

        if( !is_object( $relation ) ) {
            throw new Exception( 'Laratrust user permissions relation is not available.' );
        }

        if( config( 'laratrust.teams.enabled', false ) )
        {
            $helper = 'Laratrust\\Helper';
            $key = (string) config( 'laratrust.foreign_keys.team', 'team_id' );
            $team = $helper::getIdFor( Tenancy::value(), 'team' );

            self::call( $relation, 'wherePivot', $key, $team );
        }

        return self::itemNames( self::call( $relation, 'get', ['name'] ) );
    }


    /**
     * Registers tenant-aware Laratrust gates for catalog values loaded on demand.
     *
     * @param array<int, mixed> $values
     * @return array<int, mixed>
     */
    private static function gates( array $values ) : array
    {
        foreach( $values as $value )
        {
            if( !is_string( $value ) || trim( $value ) === ''
                || ( Gate::has( $value ) && !config( 'laratrust.permissions_as_gates', false ) )
            ) {
                continue;
            }

            Gate::define( $value, function( Authenticatable $user ) use ( $value ) {
                return (bool) self::call( $user, 'isAbleTo', $value, self::team() );
            } );
        }

        return $values;
    }


    /**
     * Returns the active team if Laratrust teams are enabled.
     */
    private static function team() : ?string
    {
        return config( 'laratrust.teams.enabled', false ) ? Tenancy::value() : null;
    }


    /**
     * Reads or replaces the permissions assigned directly to the user.
     *
     * @param array<int, string>|null $values NULL reads; an array replaces the complete set
     * @return array<int, mixed>
     */
    private static function userAccess( Authenticatable $user, ?array $values ) : array
    {
        return self::replace( $user, $values, self::assigned( ... ), function( array $assign, array $remove ) use ( $user ) {
            if( $assign ) {
                self::call( $user, 'givePermissions', $assign, self::team() );
            }

            if( $remove ) {
                self::call( $user, 'removePermissions', $remove, self::team() );
            }
        } );
    }
}
