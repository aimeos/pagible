<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\GraphQL\Resolvers;

use Aimeos\Cms\Access;
use Aimeos\Cms\Events\UserChanged;
use Aimeos\Cms\Permission;
use Aimeos\Cms\Tenancy;
use GraphQL\Error\Error;
use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;


class UserResolver
{
    /**
     * Returns direct frontend access assigned to an administrative lookup result.
     *
     * @return array<int, string>
     */
    public function access( Authenticatable $user ) : array
    {
        return app( Access::class )->assigned( $user );
    }


    /**
     * Returns raw CMS assignments for an administrative lookup result.
     *
     * @return array<int, string>
     */
    public function assigned( Authenticatable $user ) : array
    {
        return Permission::assigned( $user );
    }


    /**
     * Returns the available CMS roles and permission names.
     *
     * @return array{roles: array<int, string>, permissions: array<int, string>}
     */
    public function catalog() : array
    {
        $roles = Permission::roles();
        $permissions = Permission::all();
        sort( $roles, SORT_STRING );
        sort( $permissions, SORT_STRING );

        return ['roles' => $roles, 'permissions' => $permissions];
    }


    /**
     * Finds a tenant user by exact email address for assignment administration.
     *
     * @param array{email: string} $args
     * @return Authenticatable|null
     */
    public function cmsUser( mixed $root, array $args ) : ?Authenticatable
    {
        $email = $this->email( (string) ( $args['email'] ?? '' ) );

        return $this->tenantUser( $this->provider()->retrieveByCredentials( ['email' => $email] ) );
    }


    /**
     * Creates a tenant user with a random password and no authorization assignments.
     *
     * @param array{email: string} $args
     */
    public function create( mixed $root, array $args ) : Authenticatable
    {
        $email = $this->email( (string) ( $args['email'] ?? '' ) );
        $provider = $this->provider();

        if( !$provider instanceof EloquentUserProvider ) {
            throw new \LogicException( 'User creation requires an Eloquent authentication provider.' );
        }

        $user = $provider->createModel();
        $attributes = [
            'cmsperms' => [],
            'email' => $email,
            'name' => $email,
            'password' => Hash::make( Str::password( 32 ) ),
        ];
        $tenant = Tenancy::value();

        if( $tenant !== '' && $user->getConnection()->getSchemaBuilder()->hasColumn( $user->getTable(), 'tenant_id' ) ) {
            $attributes['tenant_id'] = $tenant;
        }

        try {
            $user->getConnection()->transaction( function() use ( $attributes, $tenant, $user ) {
                $user->forceFill( $attributes )->save();

                if( !Tenancy::allows( $user, $tenant ) ) {
                    throw new Error( 'Created user does not belong to the current tenant' );
                }
            } );
        }
        catch( UniqueConstraintViolationException ) {
            throw new Error( 'User already exists' );
        }

        UserChanged::fire( 'create', $user );

        return $user;
    }


    /**
     * Returns the opaque authentication identifier for an administrative lookup result.
     */
    public function id( Authenticatable $user ) : string
    {
        return (string) $user->getAuthIdentifier();
    }


    /**
     * Returns the effective CMS permissions of the user.
     *
     * @return array<string, mixed>
     */
    public function permission( Authenticatable $user ) : array
    {
        return Permission::get( $user );
    }


    /**
     * Returns the CMS roles directly assigned to the user.
     *
     * @return array<int, string>
     */
    public function roles( Authenticatable $user ) : array
    {
        return array_values( array_filter(
            Permission::assigned( $user ),
            fn( $entry ) => !str_contains( $entry, ':' ) && !str_starts_with( $entry, '!' ),
        ) );
    }


    /**
     * Replaces direct frontend access assigned to a tenant user.
     *
     * @param array{id: string, access: array<int, mixed>} $args
     * @return array<int, string>
     */
    public function setAccess( mixed $root, array $args ) : array
    {
        $user = $this->findId( $args['id'] ?? null ) ?? throw new Error( 'User not found' );
        $result = app( Access::class )->set( $user, $args['access'] ?? [] );

        UserChanged::fire( 'access', $user, $result );

        return $result;
    }


    /**
     * Replaces raw CMS roles and permissions assigned to a tenant user.
     *
     * @param array{id: string, permissions: array<int, mixed>} $args
     * @return array<int, string>
     */
    public function setPermissions( mixed $root, array $args ) : array
    {
        $user = $this->findId( $args['id'] ?? null ) ?? throw new Error( 'User not found' );

        // Users may never change their own permissions: granting would be a
        // privilege-escalation path, and even shedding could lock the last
        // privileged user out of the account. Another actor must do it.
        $actor = Auth::user();

        if( $actor && (string) $actor->getAuthIdentifier() === (string) $user->getAuthIdentifier() ) {
            throw new Error( 'You cannot change your own permissions' );
        }

        // Audited via the PermissionChanged event dispatched by Permission::set().
        return Permission::set( $user, $args['permissions'] ?? [] );
    }


    /**
     * Returns the stored admin settings of the user.
     *
     * @return array<string, mixed>|null
     */
    public function settings( Authenticatable $user ) : ?array
    {
        return json_decode( (string) data_get( $user, 'cmsdata', '' ), true ) ?: null;
    }


    /**
     * Validates and normalizes an exact email address.
     */
    private function email( string $email ) : string
    {
        $email = mb_strtolower( trim( $email ) );

        Validator::make( ['email' => $email], [
            'email' => ['required', 'string', 'email', 'max:255'],
        ] )->validate();

        return $email;
    }


    /**
     * Resolves one tenant user by opaque authentication identifier.
     */
    private function findId( mixed $id ) : ?Authenticatable
    {
        if( !( is_int( $id ) || is_string( $id ) ) || ( $id = (string) $id ) === '' || mb_strlen( $id ) > 255 ) {
            return null;
        }

        return $this->tenantUser( $this->provider()->retrieveById( $id ) );
    }


    /**
     * Returns the active guard's authentication provider.
     */
    private function provider() : UserProvider
    {
        $guard = Auth::guard();
        $provider = method_exists( $guard, 'getProvider' ) ? $guard->getProvider() : null;

        if( !$provider instanceof UserProvider ) {
            throw new \LogicException( 'The authentication guard has no user provider.' );
        }

        return $provider;
    }


    /**
     * Returns a user only when it belongs to the active tenant.
     */
    private function tenantUser( ?Authenticatable $user ) : ?Authenticatable
    {
        return $user && Tenancy::allows( $user, Tenancy::value() ) ? $user : null;
    }


}
