<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms;

use Aimeos\Cms\Exceptions\InvalidInputException;
use Aimeos\Cms\Exceptions\PermissionException;
use \Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Auth\GenericUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Aimeos\Cms\Events\PermissionChanged;


/**
 * Permissions are checked via can()/get() and changed via set().
 * The "cmsperms" and "cmslangs" columns are managed by this class.
 *
 * The writable languages in "cmslangs" restrict the write actions of a user in addition to
 * the action permissions: database NULL is unrestricted, a list allows writes in these languages only.
 */
class Permission
{
    private const MAX_ASSIGNMENTS = 250;
    private const MAX_LANGS = 100;
    private const MAX_LENGTH = 100;

    /**
     * Permission names available by default, packages register more via register()
     *
     * @var array<int, string>
     */
    public const ACTIONS = [
        'page:view',
        'page:save',
        'page:add',
        'page:drop',
        'page:keep',
        'page:purge',
        'page:publish',
        'page:move',
        'page:access',

        'variant:add',
        'variant:drop',
        'variant:keep',
        'variant:purge',

        'element:view',
        'element:save',
        'element:add',
        'element:drop',
        'element:keep',
        'element:purge',
        'element:publish',

        'file:view',
        'file:save',
        'file:add',
        'file:drop',
        'file:keep',
        'file:purge',
        'file:publish',
        'file:relocate',

        'page:config',
    ];

    /**
     * Returns the list of all available actions.
     *
     * @return array<int, string> List of action names
     */
    public static function all(): array
    {
        return Hooks::get()->actions;
    }


    /**
     * Checks if the action is allowed for the user or for calls without user inside system().
     *
     * Use it for optional parts of write operations, e.g. fields requiring an additional permission.
     * For read decisions like previews, use can() because calls without user must be treated as anonymous.
     *
     * @param string $action Name of the requested action, e.g. "page:config"
     * @param Authenticatable|null $user Laravel user object or NULL for anonymous users
     * @return bool TRUE if the action is allowed, FALSE if not
     */
    public static function allowed( string $action, ?Authenticatable $user ) : bool
    {
        return $user ? self::can( $action, $user ) : Hooks::get()->system;
    }


    /**
     * Returns the raw CMS roles and permissions assigned to the user.
     *
     * @return array<int, string>
     */
    public static function assigned( Authenticatable $user ) : array
    {
        $entries = data_get( $user, 'cmsperms', [] );

        return is_array( $entries )
            ? array_values( array_filter( $entries, 'is_string' ) )
            : [];
    }


    /**
     * Tests whether a permission is available.
     */
    public static function has( string $action ) : bool
    {
        return in_array( $action, Hooks::get()->actions, true );
    }


    /**
     * Checks if the user belongs to the current tenant and has the requested permission.
     *
     * Variant actions are also granted by the page action of the same name,
     * e.g. "page:drop" allows "variant:drop" too.
     *
     * @param string $action Name of the requested action, e.g. "page:view"
     * @param Authenticatable|null $user Laravel user object
     * @return bool TRUE of the user is allowed to perform the action, FALSE if not
     */
    public static function can( string $action, ?Authenticatable $user ) : bool
    {
        if( self::grants( $action, $user ) ) {
            return true;
        }

        return str_starts_with( $action, 'variant:' ) && self::grants( 'page:' . substr( $action, 8 ), $user );
    }


    /**
     * Throws an exception if the user isn't allowed to write in the language.
     *
     * Items without a language (NULL) can only be written by users with an unrestricted scope.
     * A NULL user is unrestricted inside system() only.
     *
     * @param string|null $lang Language of the written item or NULL for language-neutral items
     * @param Authenticatable|null $user Laravel user object or NULL for anonymous users
     * @param bool $needed FALSE to skip the check
     * @throws PermissionException If the language is outside the writable languages of the user
     */
    public static function checkLang( ?string $lang, ?Authenticatable $user, bool $needed = true ) : void
    {
        if( $needed && !self::writable( $lang, $user ) ) {
            throw new PermissionException( sprintf( 'Insufficient permissions for language "%1$s"', $lang ?? '' ) );
        }
    }


    /**
     * Throws an exception if the writable languages of the user are restricted.
     *
     * Required for changes affecting the identity of several languages, e.g. language or source changes.
     *
     * @param Authenticatable|null $user Laravel user object or NULL for anonymous users
     * @param bool $needed FALSE to skip the check
     * @throws PermissionException If the user has a restricted language scope
     */
    public static function checkUnrestricted( ?Authenticatable $user, bool $needed = true ) : void
    {
        if( $needed && self::langs( $user ) !== null ) {
            throw new PermissionException( 'Insufficient permissions for all languages' );
        }
    }


    /**
     * Returns the languages the user is allowed to write.
     *
     * Only database NULL is unrestricted. Missing attributes (e.g. partially selected users),
     * JSON null, malformed values and invalid language codes allow no writes at all.
     *
     * @param Authenticatable|null $user Laravel user object or NULL for anonymous users
     * @return array<int, string>|null List of language codes or NULL if unrestricted
     */
    public static function langs( ?Authenticatable $user ) : ?array
    {
        if( !$user ) {
            return Hooks::get()->system ? null : [];
        }

        $attributes = match( true ) {
            $user instanceof Model => $user->getAttributes(),
            $user instanceof GenericUser => ( fn() => $this->attributes )->call( $user ),
            default => get_object_vars( $user ),
        };

        if( !array_key_exists( 'cmslangs', $attributes ) ) {
            return [];
        }

        return $attributes['cmslangs'] === null ? null : self::parseLangs( $attributes['cmslangs'] );
    }


    /**
     * Checks if the user may write in the language.
     *
     * @param string|null $lang Language of the written item or NULL for language-neutral items
     * @param Authenticatable|null $user Laravel user object or NULL for anonymous users
     * @return bool TRUE if the language is writable, FALSE if not
     */
    public static function writable( ?string $lang, ?Authenticatable $user ) : bool
    {
        $langs = self::langs( $user );
        return $langs === null || $lang !== null && in_array( $lang, $langs, true );
    }


    /**
     * Checks if the user has the permission without the implied page permissions.
     *
     * @param string $action Name of the requested action, e.g. "page:view"
     * @param Authenticatable|null $user Laravel user object
     * @return bool TRUE of the user is allowed to perform the action, FALSE if not
     */
    private static function grants( string $action, ?Authenticatable $user ) : bool
    {
        if( !$user || !Tenancy::allows( $user, Tenancy::value() ) ) {
            return false;
        }

        // Unknown actions must never reach resolve()/the callback: legacy entries
        // on the user (e.g. a removed action name) would otherwise pass through.
        if( $action !== '*' && !self::has( $action ) ) {
            return false;
        }

        if( $closure = Hooks::get()->can ) {
            return $closure( $action, $user );
        }

        if( $action === '*' ) {
            return self::assigned( $user ) !== [];
        }

        // Cached per user and validated against the current assignment fingerprint, so writes to
        // the "cmsperms" attribute outside of set() re-resolve instead of serving stale permissions
        $cache = Hooks::get()->resolved;
        $assignments = self::assigned( $user );
        $entry = $cache[$user] ?? null;

        $key = (string) json_encode( $assignments, JSON_INVALID_UTF8_SUBSTITUTE )
            . '|' . md5( (string) json_encode( config( 'cms.roles', [] ), JSON_INVALID_UTF8_SUBSTITUTE ) );

        if( $entry === null || $entry['key'] !== $key )
        {
            $entry = ['key' => $key, 'resolved' => self::resolve( $assignments )];
            $cache[$user] = $entry;
        }

        return in_array( $action, $entry['resolved'] );
    }


    /**
     * Sets the callback for checking permissions.
     *
     * @param \Closure|null $callback Anonymous function or NULL to reset
     */
    public static function canUsing( ?\Closure $callback ) : void
    {
        Hooks::get()->can = $callback;
    }


    /**
     * Throws an exception if the user isn't allowed to perform the action.
     *
     * A NULL user is anonymous and only allowed inside system().
     *
     * @param string $action Action name, e.g. "page:publish"
     * @param Authenticatable|null $user Laravel user object or NULL for anonymous users
     * @param bool $needed FALSE to skip the check, e.g. if there are no related items
     * @throws PermissionException If the user lacks the permission
     */
    public static function check( string $action, ?Authenticatable $user, bool $needed = true ) : void
    {
        if( $user ? $needed && !self::can( $action, $user ) : !Hooks::get()->system ) {
            throw new PermissionException( 'Insufficient permissions' );
        }
    }


    /**
     * Returns the available actions and their permissions.
     *
     * @param Authenticatable|null $user Laravel user object
     * @return array<string, bool> List of actions as keys and booleans as values indicating if the user has permission for the action
     */
    public static function get( ?Authenticatable $user ) : array
    {
        $map = [];

        foreach( self::all() as $action ) {
            $map[$action] = self::can( $action, $user );
        }

        return $map;
    }


    /**
     * Registers additional permission names.
     *
     * @param array<string>|string $actions Permission name(s) to register
     */
    public static function register( array|string $actions ) : void
    {
        $hooks = Hooks::get();
        $hooks->actions = array_values( array_unique( array_merge( $hooks->actions, (array) $actions ) ) );

        // Wildcard entries ("*" / "page:*") expand against the actions at resolve
        // time, so changes to the action list invalidate cached resolutions.
        $hooks->resolved = new \WeakMap();
    }


    /**
     * Unregisters permission names which are no longer available.
     *
     * @param array<string>|string $actions Permission name(s) to unregister
     */
    public static function unregister( array|string $actions ) : void
    {
        $hooks = Hooks::get();
        $hooks->actions = array_values( array_diff( $hooks->actions, (array) $actions ) );
        $hooks->resolved = new \WeakMap();
    }


    /**
     * Returns the available role names from config.
     *
     * @return array<int, string> List of role names
     */
    public static function roles() : array
    {
        /** @var array<string, mixed> $roles */
        $roles = config( 'cms.roles', [] );
        return array_keys( $roles );
    }


    /**
     * Atomically replaces the raw CMS roles and permissions assigned to a persisted user.
     *
     * This is the ONLY supported writer for the managed "cmsperms" and "cmslangs" attributes.
     * It enforces the current tenant, validates entries, and serializes concurrent
     * writers under a row lock. The resolution cache is fingerprinted against the
     * assignments, so the change takes effect on the next can()/get() call.
     *
     * The acting user isn't checked, callers must authorize the change themselves. Checks depending
     * on the stored assignments belong into the $check callback, which receives the locked user.
     *
     * @param iterable<mixed> $entries
     * @param array<mixed>|null|false $langs Writable languages, NULL for unrestricted or FALSE to keep the stored ones
     * @param (\Closure(Authenticatable&Model): void)|null $check Authorizes the change of the locked user, throws to abort
     * @return array<int, string>
     */
    public static function set( Authenticatable $user, iterable $entries, array|null|false $langs = false, ?\Closure $check = null ) : array
    {
        $entries = self::normalize( $entries );
        $langs = is_array( $langs ) ? self::normalizeLangs( $langs ) : $langs;

        if( !$user instanceof Model || !$user->exists || $user->getKey() === null ) {
            throw new Exception( 'CMS permission assignment requires a persisted Eloquent user.' );
        }

        $tenant = Tenancy::value();
        $error = 'CMS permissions can only be changed for users in the current tenant.';

        if( !Tenancy::allows( $user, $tenant ) ) {
            throw new Exception( $error );
        }

        [$result, $raw] = Tenancy::lock( $user, $tenant, $error, function( Authenticatable&Model $locked ) use ( $entries, $langs, $check ) {
            if( $check ) {
                $check( $locked );
            }

            $existing = self::assigned( $locked );

            self::validate( $entries, $existing );

            // no cast for "cmslangs" because it would decode database NULL and JSON null to the same value
            $raw = $langs === false ? $locked->getAttributes()['cmslangs'] ?? null : ( $langs === null ? null : json_encode( $langs ) );
            $locked->forceFill( ['cmsperms' => $entries, 'cmslangs' => $raw] )->save();

            return [$entries, $raw];
        } );

        $user->forceFill( ['cmsperms' => $result, 'cmslangs' => $raw] );
        self::audit( $user, $result, self::langs( $user ), $tenant );

        return $result;
    }


    /**
     * Executes the callback without permission checks for calls without user.
     *
     * Use it for system changes, e.g. in commands, jobs, seeders or payment webhooks.
     * Explicitly passed users are still checked.
     *
     * @template T
     * @param \Closure(): T $callback Function calling the CMS operations
     * @return T Result of the callback
     */
    public static function system( \Closure $callback ) : mixed
    {
        $hooks = Hooks::get();
        $system = $hooks->system;
        $hooks->system = true;

        try {
            return $callback();
        } finally {
            $hooks->system = $system;
        }
    }


    /**
     * Dispatches the permission-change audit event with the request context.
     *
     * @param array<int, string> $result Resulting assignments
     * @param array<int, string>|null $langs Resulting writable languages, NULL for unrestricted
     */
    private static function audit( Authenticatable $user, array $result, ?array $langs, string $tenant ) : void
    {
        Watch::dispatch( PermissionChanged::class, function() use ( $user, $result, $langs, $tenant ) {
            $actor = Auth::user();
            $request = app()->bound( 'request' ) ? app( 'request' ) : null;

            return new PermissionChanged(
                actorEmail: (string) data_get( $actor, 'email' ),
                targetEmail: (string) data_get( $user, 'email' ),
                targetId: (string) $user->getAuthIdentifier(),
                assignments: $result,
                ip: (string) ( $request?->ip() ?? '' ),
                userAgent: (string) ( $request?->userAgent() ?? '' ),
                tenant: $tenant,
                langs: $langs,
            );
        } );
    }


    /**
     * Clears resolved-permission caches.
     *
     * Entries are fingerprinted and released with their user objects, so this is only
     * needed to free memory before long-running loops that touch many users.
     */
    public static function flush() : void
    {
        Hooks::get()->resolved = new \WeakMap();
    }


    /**
     * Returns raw assignments deduplicated, normalized, and sorted.
     *
     * @param iterable<mixed> $entries
     * @return array<int, string>
     */
    private static function normalize( iterable $entries ) : array
    {
        $result = [];

        foreach( $entries as $entry )
        {
            if( !is_string( $entry ) || ( $entry = trim( $entry ) ) === '' ) {
                throw new InvalidInputException( 'CMS permissions must be non-empty strings.' );
            }

            if( mb_strlen( $entry ) > self::MAX_LENGTH ) {
                throw new InvalidInputException( sprintf( 'CMS permissions may not be longer than %d characters.', self::MAX_LENGTH ) );
            }

            if( !in_array( $entry, $result, true ) ) {
                $result[] = $entry;
            }

            if( count( $result ) > self::MAX_ASSIGNMENTS ) {
                throw new InvalidInputException( sprintf( 'No more than %d CMS permissions may be assigned at once.', self::MAX_ASSIGNMENTS ) );
            }
        }

        sort( $result, SORT_STRING );

        return $result;
    }


    /**
     * Returns the writable languages validated, deduplicated, and sorted.
     *
     * @param array<mixed> $langs Language codes
     * @return array<int, string> Normalized language codes
     * @throws InvalidInputException If a language code is invalid
     */
    private static function normalizeLangs( array $langs ) : array
    {
        $result = [];

        foreach( $langs as $lang )
        {
            if( !is_string( $lang ) || !Utils::isValidLang( $lang ) ) {
                throw new InvalidInputException( sprintf( 'Invalid language code "%1$s"', is_scalar( $lang ) ? $lang : gettype( $lang ) ) );
            }

            $result[$lang] = $lang;
        }

        if( count( $result ) > self::MAX_LANGS ) {
            throw new InvalidInputException( sprintf( 'No more than %d languages may be assigned.', self::MAX_LANGS ) );
        }

        sort( $result, SORT_STRING );

        return $result;
    }


    /**
     * Returns the writable languages stored in the raw attribute value.
     *
     * @param mixed $raw JSON encoded list or list of language codes
     * @return array<int, string> Language codes or an empty list if the value is invalid
     */
    private static function parseLangs( mixed $raw ) : array
    {
        $langs = is_string( $raw ) ? json_decode( $raw, true ) : $raw;

        if( !is_array( $langs ) || !array_is_list( $langs ) ) {
            return [];
        }

        foreach( $langs as $lang )
        {
            if( !is_string( $lang ) || !Utils::isValidLang( $lang ) ) {
                return [];
            }
        }

        return $langs;
    }


    /**
     * Resolves roles and wildcards to concrete permission strings.
     *
     * @param array<int, string> $entries Permission and/or role entries
     * @return array<int, string> Resolved permission names
     */
    private static function resolve( array $entries ) : array
    {
        $roles = config( "cms.roles", [] );
        $perms = $deny = [];

        foreach( $entries as $entry )
        {
            if( str_starts_with( $entry, '!' ) ) {
                array_push( $deny, ...self::resolve( [substr( $entry, 1 )] ) );
            } elseif( $entry === '*' ) {
                array_push( $perms, ...self::all() );
            } elseif( !str_contains( $entry, ':' ) ) {
                array_push( $perms, ...self::resolve( $roles[$entry] ?? [] ) );
            } elseif( str_contains( $entry, '*' ) ) {
                [$prefix, $suffix] = explode( ':', $entry, 2 );

                foreach( self::all() as $perm )
                {
                    [$p, $s] = explode( ':', $perm, 2 );

                    if( ( $prefix === '*' || $p === $prefix ) && ( $suffix === '*' || $s === $suffix ) ) {
                        $perms[] = $perm;
                    }
                }
            } else {
                $perms[] = $entry;
            }
        }

        return $deny ? array_values( array_diff( $perms, $deny ) ) : $perms;
    }


    /**
     * Tests whether a raw role, permission, wildcard or deny entry is supported.
     */
    private static function valid( string $entry ) : bool
    {
        if( str_starts_with( $entry, '!' ) )
        {
            $entry = substr( $entry, 1 );

            if( $entry === '' || str_starts_with( $entry, '!' ) ) {
                return false;
            }
        }

        if( $entry === '*' || self::has( $entry ) || in_array( $entry, self::roles(), true ) ) {
            return true;
        }

        if( substr_count( $entry, ':' ) !== 1 || !str_contains( $entry, '*' ) ) {
            return false;
        }

        [$prefix, $suffix] = explode( ':', $entry, 2 );

        return $prefix && $suffix
            && ( $prefix === '*' || $suffix === '*' )
            && self::resolve( [$entry] ) !== [];
    }


    /**
     * Rejects unsupported new values while allowing existing legacy assignments to be retained.
     *
     * @param array<int, string> $entries
     * @param array<int, string> $existing
     */
    private static function validate( array $entries, array $existing ) : void
    {
        foreach( $entries as $entry )
        {
            if( !self::valid( $entry ) && !in_array( $entry, $existing, true ) ) {
                throw new InvalidInputException( sprintf( 'Unknown CMS role or permission "%s".', $entry ) );
            }
        }
    }
}
