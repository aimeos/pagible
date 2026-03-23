<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Aimeos\Cms\Commands;

use Aimeos\Cms\Permission;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Hash;
use Illuminate\Console\Command;


class User extends Command
{
    /**
     * Command name
     */
    protected $signature = 'cms:user
        {--a|add= : Add permissions for the user by permission names, patterns like "page:*", "*:view", or "*" for all permissions (can be used multiple times)}
        {--d|disable : Disables the user}
        {--e|enable : Enables the user}
        {--l|list : Lists all permissions of the CMS user}
        {--p|password= : Secret password of the account (will ask if user will be created)}
        {--q|--quiet : Do not output any message}
        {--r|remove= : Remove permissions for the user by permission names, patterns like "page:*", "*:view", or "*" for all permissions (can be used multiple times)}
        {--role= : Add a named role to the user (e.g. "editor", "publisher", "admin")}
        {--roles : Lists all available roles and their permissions}
        {email? : E-Mail of the user}';

    /**
     * Command description
     */
    protected $description = 'Authorization for CMS users';


    /**
     * Execute command
     */
    public function handle(): void
    {
        if( $this->option( 'roles' ) ) {
            $this->listRoles();
            return;
        }

        // @phpstan-ignore-next-line cast.string
        $email = (string) $this->argument( 'email' );
        $model = config( 'auth.providers.users.model', 'App\\Models\\User' );
        $user = $model::where( 'email', $email )->first();

        if( $this->option( 'list' ) ) {
            $this->list( $user );
            return;
        }

        if( !$user ) {
            $user = $this->create( $email );
        }

        if( $this->option( 'enable' ) ) {
            $user = Permission::add( $this->permissions( '*' ), $user );
        }

        if( $perms = $this->option( 'add' ) ) {
            $user = Permission::add( $this->permissions( $perms ), $user ); // @phpstan-ignore-line argument.type
        }

        if( $role = $this->option( 'role' ) ) {
            $user = Permission::add( (string) $role, $user );
        }

        if( $perms = $this->option( 'remove' ) ) {
            $user = Permission::remove( $this->permissions( $perms ), $user ); // @phpstan-ignore-line argument.type
        }

        if( $this->option( 'disable' ) ) {
            $user = Permission::remove( $this->permissions( '*' ), $user );
        }

        if( $this->input->hasParameterOption( '--password' ) ) {
            $user->password = Hash::make( $this->option( 'password' ) ?: $this->secret( 'Password' ) );
        }

        $user->save();

        if( !$this->option( 'quiet' ) ) {
            $this->list( $user );
        }
    }


    /**
     * Creates a new user with the given email.
     *
     * @param string $email E-Mail of the user
     * @return Authenticatable Created user object
     */
    protected function create( string $email ) : Authenticatable
    {
        $password = $this->option( 'password' ) ?: $this->secret( 'Password' );
        $model = config( 'auth.providers.users.model', 'App\\Models\\User' );

        /** @var Authenticatable $user */
        $user = new $model;
        $user->password = Hash::make( $password ); // @phpstan-ignore-line property.notFound
        $user->cmsperms = []; // @phpstan-ignore-line property.notFound
        $user->email = $email; // @phpstan-ignore-line property.notFound
        $user->name = $email; // @phpstan-ignore-line property.notFound

        return $user;
    }


    /**
     * Lists the permissions of the given user.
     *
     * @param Authenticatable|null $user Laravel user object or NULL if the user was not found
     */
    protected function list( ?Authenticatable $user ) : void
    {
        if( !$user ) {
            $this->error( 'User not found!' );
            return;
        }

        $roles = array_filter( $user->cmsperms ?? [], fn( $entry ) => !str_contains( $entry, ':' ) );

        if( $roles ) {
            $this->info( 'roles:' );
            foreach( $roles as $role ) {
                $this->line( sprintf( '  %1$s', $role ) );
            }
        }

        $groups = collect( Permission::all() )->sort()->groupBy( fn( $name ) => explode( ':', $name )[0] );

        foreach( $groups as $group => $names )
        {
            $this->info( sprintf( '%1$s:', $group ) );

            foreach( $names as $name )
            {
                if( Permission::can( $name, $user ) ) {
                    $this->line( sprintf( '  [x] %1$s', $name ) );
                } else {
                    $this->line( sprintf( '  [ ] %1$s', $name ) );
                }
            }
        }
    }


    /**
     * Lists all available roles and their permissions.
     */
    protected function listRoles() : void
    {
        $roles = config( 'cms.roles', [] );

        if( empty( $roles ) ) {
            $this->info( 'No roles defined in config/cms.php' );
            return;
        }

        foreach( $roles as $name => $perms )
        {
            $this->info( sprintf( '%1$s:', $name ) );

            foreach( $perms as $perm ) {
                $this->line( sprintf( '  %1$s', $perm ) );
            }
        }
    }


    /**
     * Returns the actions for the given names or patterns.
     *
     * @param array<string>|string $action Name(s) or pattern(s) of the requested action(s), e.g. "page:view", "page:*" or "*:view"
     * @return array<string> List of action names
     */
    protected function permissions( array|string $action ) : array
    {
        $list = [];
        $perms = Permission::all();

        foreach( (array) $action as $name )
        {
            if( str_contains( $name, '*' ) )
            {
                $pattern = str_replace( '*', '.*', $name );

                foreach( $perms as $perm )
                {
                    if( preg_match( sprintf( '#^%1$s$#', $pattern ), $perm ) ) {
                        $list[] = $perm;
                    }
                }
            }
            else
            {
                $list[] = $name;
            }
        }

        return $list;
    }
}
