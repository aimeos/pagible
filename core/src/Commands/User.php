<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Commands;

use Aimeos\Cms\Permission;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Hash;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;


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
        {--langs=* : Restrict writes to these language codes, e.g. "de" (can be used multiple times, replaces the current languages), "*" for all languages or empty for none}
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

        if( !is_string( $email = $this->argument( 'email' ) ) ) {
            $this->error( 'E-Mail address is required!' );
            return;
        }

        try {
            $langs = $this->langs();
        } catch( \InvalidArgumentException $e ) {
            $this->error( $e->getMessage() );
            return;
        }

        $model = $this->model();
        $user = $model::where( 'email', $email )->first();

        if( $this->option( 'list' ) ) {
            $this->list( $user );
            return;
        }

        // New users get their password in create(), so it isn't asked and saved again below
        if( $created = !$user ) {
            $user = $this->create( $email );
            $user->save();
        }

        // Compute the final permission set from all flags first, then apply it in ONE
        // Permission::set() — a single locked transaction and a single audit entry for
        // the intended end state, instead of one misleading intermediate audit line
        // per flag.
        $existing = Permission::assigned( $user );
        $assigned = $this->assigned( $existing );

        if( $assigned !== null || $langs !== false ) {
            Permission::set( $user, $assigned ?? $existing, $langs );
        }

        // Only save when the password actually changed; an unconditional save() would
        // re-issue an UPDATE (bumping updated_at / firing saved hooks) even for
        // pure-permission invocations, on top of set()'s own locked write.
        if( !$created && $this->input->hasParameterOption( '--password' ) ) {
            $user->password = Hash::make( $this->option( 'password' ) ?: $this->secret( 'Password' ) );
            $user->save();
        }

        if( !$this->option( 'quiet' ) ) {
            $this->list( $user );
        }
    }


    /**
     * Returns the permissions and roles resulting from the command options.
     *
     * @param array<string> $assigned Currently assigned permissions and roles
     * @return array<string>|null New permissions and roles or NULL if no option changes them
     */
    protected function assigned( array $assigned ) : ?array
    {
        $changed = false;

        if( $this->option( 'enable' ) ) {
            $assigned = $this->permissions( '*' );
            $changed = true;
        }

        if( $perms = $this->option( 'add' ) ) {
            $assigned = array_merge( $assigned, $this->permissions( $perms ) );
            $changed = true;
        }

        if( is_string( $role = $this->option( 'role' ) ) ) {
            $assigned = array_merge( $assigned, (array) $role );
            $changed = true;
        }

        if( $perms = $this->option( 'remove' ) ) {
            $assigned = array_diff( $assigned, $this->permissions( $perms ) );
            $changed = true;
        }

        if( $this->option( 'disable' ) ) {
            $assigned = [];
            $changed = true;
        }

        return $changed ? array_values( array_unique( $assigned ) ) : null;
    }


    /**
     * Returns the writable languages resulting from the command options.
     *
     * @return array<string>|null|false Language codes, NULL for unrestricted or FALSE if unchanged
     * @throws \InvalidArgumentException If "*" is combined with language codes
     */
    protected function langs() : array|null|false
    {
        // not passed: unchanged, "--langs=" (empty): no language, "--langs=*": all languages
        if( !( $values = (array) $this->option( 'langs' ) ) ) {
            return false;
        }

        $langs = array_values( array_unique( array_filter( $values, fn( $lang ) => is_string( $lang ) && $lang !== '' ) ) );

        if( in_array( '*', $langs, true ) )
        {
            if( count( $langs ) > 1 ) {
                throw new \InvalidArgumentException( 'Use either --langs=* or language codes!' );
            }

            return null;
        }

        return $langs;
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
        $model = $this->model();

        /** @var Model&Authenticatable $user */
        $user = new $model;

        return $user->forceFill( [
            'password' => Hash::make( $password ),
            'cmsperms' => [],
            'email' => $email,
            'name' => $email,
        ] );
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

        // same as the roles of the GraphQL user resolver, denied roles ("!role") are no roles of the user
        $roles = array_filter( Permission::assigned( $user ), fn( $entry ) => !str_contains( $entry, ':' ) && !str_starts_with( $entry, '!' ) );

        if( $roles ) {
            $this->info( 'roles:' );
            foreach( $roles as $role ) {
                $this->line( sprintf( '  %1$s', $role ) );
            }
        }

        $langs = Permission::langs( $user );
        $this->info( 'languages:' );
        $this->line( sprintf( '  %1$s', $langs === null ? '* (unrestricted)' : ( implode( ', ', $langs ) ?: '- (none)' ) ) );

        $groups = collect( Permission::all() )->sort()->groupBy( fn( $name ) => explode( ':', $name )[0] );

        foreach( $groups as $group => $names )
        {
            $this->info( sprintf( '%1$s:', $group ) );

            foreach( $names as $name ) {
                $this->line( sprintf( '  [%1$s] %2$s', Permission::can( $name, $user ) ? 'x' : ' ', $name ) );
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


    /**
     * Returns the class name of the user model.
     */
    protected function model() : mixed
    {
        return config( 'auth.providers.users.model', 'App\\Models\\User' );
    }
}
