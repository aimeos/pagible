<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */

namespace Aimeos\Cms\Access;

use Aimeos\Cms\Exception;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;


/**
 * Access adapter for spatie/laravel-permission 6.2.0 or newer.
 */
class Spatie extends Provider
{
    /**
     * Returns the access adapter using Spatie permissions.
     *
     * @param (\Closure(Authenticatable): (iterable<mixed>|null))|null $grants Effective-grants resolver
     */
    public static function adapter( ?\Closure $grants = null ) : Adapter
    {
        $registrar = 'Spatie\\Permission\\PermissionRegistrar';
        $model = config( 'permission.models.permission', 'Spatie\\Permission\\Models\\Permission' );
        $guard = config( 'auth.defaults.guard', 'web' );

        return new Adapter(
            list: fn() => self::modelNames( $model, ['guard_name' => $guard] ),
            activate: fn( string $tenant ) => self::call( $registrar, 'setPermissionsTeamId', $tenant ),
            prepare: fn( Authenticatable $user ) => self::prepare( $user ),
            add: fn( string $value ) => self::call( self::model( $model ), 'findOrCreate', $value, $guard ),
            delete: fn( array $values ) => self::modelDelete( $model, $values, ['guard_name' => $guard] ),
            grants: $grants,
            userAccess: function( Authenticatable $user, ?array $values ) {
                self::prepare( $user );

                if( $values !== null ) {
                    self::call( $user, 'syncPermissions', $values );
                    self::prepare( $user );
                }

                return self::itemNames( self::call( $user, 'getDirectPermissions' ) );
            },
        );
    }


    /**
     * Clears Spatie relations which vary by active team.
     */
    private static function prepare( Authenticatable $user ) : void
    {
        if( !$user instanceof Model ) {
            throw new Exception( 'Spatie access requires an Eloquent user model.' );
        }

        $user->unsetRelation( 'roles' );
        $user->unsetRelation( 'permissions' );
    }
}
