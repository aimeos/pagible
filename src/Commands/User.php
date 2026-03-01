<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Aimeos\Cms\Commands;

use Aimeos\Cms\Permission;
use Illuminate\Foundation\Auth\User as BaseUser;
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
        {email : E-Mail of the user}';

    /**
     * Command description
     */
    protected $description = 'Authorization for CMS users';


    /**
     * Execute command
     */
    public function handle(): void
    {
        // @phpstan-ignore-next-line cast.string
        $email = (string) $this->argument( 'email' );
        $user = BaseUser::where( 'email', $email )->first();

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

        if( $this->option( 'add' ) ) {
            $user = Permission::add( $this->permissions( $this->option( 'add' ) ), $user );
        }

        if( $this->option( 'remove' ) ) {
            $user = Permission::del( $this->permissions( $this->option( 'remove' ) ), $user );
        }

        if( $this->option( 'disable' ) ) {
            $user = Permission::del( $this->permissions( '*' ), $user );
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
     * @return BaseUser Created user object
     */
    protected function create( string $email ) : BaseUser
    {
        $password = $this->option( 'password' ) ?: $this->secret( 'Password' );

        return (new BaseUser())->forceFill( [
            'password' => Hash::make( $password ),
            'cmseditor' => 0,
            'email' => $email,
            'name' => $email,
        ] );
    }


    /**
     * Lists the permissions of the given user.
     *
     * @param BaseUser|null $user Laravel user object or NULL if the user was not found
     */
    protected function list( ?BaseUser $user ) : void
    {
        if( !$user ) {
            $this->error( 'User not found!' );
            return;
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
     * Returns the actions for the given names or patterns.
     *
     * @param array|string $action Name(s) or pattern(s) of the requested action(s), e.g. "page:view", "page:*" or "*:view"
     * @return array List of action names
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
