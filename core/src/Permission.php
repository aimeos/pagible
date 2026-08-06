<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms;

use \Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;


/**
 * Permissions are checked via can()/get() and changed via set().
 * The "cmsperms" column is managed by this class.
 */
class Permission
{
    private const MAX_ASSIGNMENTS = 250;
    private const MAX_LENGTH = 100;

    /**
     * Available permission names
     *
     * @var array<int, string>
     */
    private static array $can = [
        'page:view',
        'page:save',
        'page:add',
        'page:drop',
        'page:keep',
        'page:purge',
        'page:publish',
        'page:move',
        'page:access',

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
     * Anonymous callback which allows or denies actions.
     */
    private static ?\Closure $canCallback = null;

    /**
     * Cached resolved permissions per user (Octane-safe).
     *
     * Entries are validated against the user's current assignment fingerprint, so
     * writes to the "cmsperms" attribute outside of set() re-resolve automatically
     * instead of serving stale permissions.
     *
     * @var \WeakMap<object, array{key: string, resolved: array<int, string>}>|null
     */
    private static ?\WeakMap $resolvedCache = null;

    /**
     * In-request assignment overrides for the current request scope only.
     *
     * Populated by set() so that other in-memory instances of the same user
     * row observe the new assignments within the same request.
     *
     * @var array<string, array<int, string>>
     */
    private static array $overrides = [];

    /** Last-seen request scope; a change clears $overrides (Octane-safe). */
    private static string $lastScope = '';


    /**
     * Returns the list of all available actions.
     *
     * @return array<int, string> List of action names
     */
    public static function all(): array
    {
        return self::$can;
    }


    /**
     * Returns the raw CMS roles and permissions assigned to the user,
     * including any request-local override from set().
     *
     * @return array<int, string>
     */
    public static function assigned( Authenticatable $user ) : array
    {
        $override = self::override( $user );

        if( $override !== null ) {
            return $override;
        }

        $entries = data_get( $user, 'cmsperms', [] );

        return is_array( $entries )
            ? array_values( array_filter( $entries, 'is_string' ) )
            : [];
    }


    /**
     * Reads or writes the request-local assignment override for a user.
     *
     * Pass $value to write; omit to read. Returns the current value.
     *
     * @param array<int, string>|null $value
     * @return array<int, string>|null
     */
    private static function override( Authenticatable $user, ?array $value = null ) : ?array
    {
        $id = $user->getAuthIdentifier();

        if( $id === null ) {
            return null;
        }

        $scope = app()->bound( 'request' ) ? (string) spl_object_id( app( 'request' ) ) : 'cli';

        if( $scope !== self::$lastScope ) {
            self::$lastScope = $scope;
            self::$overrides = [];
        }

        $key = Tenancy::value() . '|' . get_class( $user ) . '|' . (string) $id;

        if( $value !== null ) {
            self::$overrides[$key] = $value;
        }

        return self::$overrides[$key] ?? null;
    }


    /**
     * Tests whether a permission is available.
     */
    public static function has( string $action ) : bool
    {
        return in_array( $action, self::$can, true );
    }


    /**
     * Checks if the user belongs to the current tenant and has the requested permission.
     *
     * @param string $action Name of the requested action, e.g. "page:view"
     * @param Authenticatable|null $user Laravel user object
     * @return bool TRUE of the user is allowed to perform the action, FALSE if not
     */
    public static function can( string $action, ?Authenticatable $user ) : bool
    {
        if( !$user || !Tenancy::allows( $user, Tenancy::value() ) ) {
            return false;
        }

        // Unknown actions must never reach resolve()/the callback: legacy entries
        // on the user (e.g. a removed action name) would otherwise pass through.
        if( $action !== '*' && !self::has( $action ) ) {
            return false;
        }

        if( $closure = self::$canCallback ) {
            return $closure( $action, $user );
        }

        if( $action === '*' ) {
            return self::assigned( $user ) !== [];
        }

        self::$resolvedCache ??= new \WeakMap();

        $assignments = self::assigned( $user );
        // ALSO keyed on the current roles config: role entries resolve against
        // cms.roles, so a config change must re-resolve even when the raw
        // assignments are unchanged. INVALID_UTF8_SUBSTITUTE keeps the
        // fingerprint distinct for assignments containing invalid-UTF-8 bytes
        // (json_encode() would otherwise return false, collapsing the key to '').
        $key = (string) json_encode( $assignments, JSON_INVALID_UTF8_SUBSTITUTE )
            . '|' . md5( (string) json_encode( config( 'cms.roles', [] ), JSON_INVALID_UTF8_SUBSTITUTE ) );
        $entry = self::$resolvedCache[$user] ?? null;

        if( $entry === null || $entry['key'] !== $key ) {
            $entry = ['key' => $key, 'resolved' => self::resolve( $assignments )];
            self::$resolvedCache[$user] = $entry;
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
        self::$canCallback = $callback;
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

        foreach( self::$can as $action ) {
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
        foreach( (array) $actions as $action )
        {
            if( !self::has( $action ) ) {
                self::$can[] = $action;
            }
        }

        // Wildcard entries ("*" / "page:*") expand against self::$can at resolve
        // time, so changes to the action list invalidate cached resolutions.
        self::$resolvedCache = null;
    }


    /**
     * Unregisters permission names which are no longer available.
     *
     * @param array<string>|string $actions Permission name(s) to unregister
     */
    public static function unregister( array|string $actions ) : void
    {
        self::$can = array_values( array_diff( self::$can, (array) $actions ) );
        self::$resolvedCache = null;
    }


    /**
     * Returns the expanded permissions for a named role.
     *
     * @param string $name Role name
     * @return array<int, string> List of resolved permission names
     */
    public static function role( string $name ) : array
    {
        return self::resolve( config( "cms.roles.{$name}", [] ) );
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
     * This is the ONLY supported writer for the managed "cmsperms" attribute.
     * It enforces the current tenant, validates entries, and serializes concurrent
     * writers under a row lock. The resolution cache is fingerprinted against the
     * assignments, so the change takes effect on the next can()/get() call.
     *
     * @param iterable<mixed> $entries
     * @return array<int, string>
     */
    public static function set( Authenticatable $user, iterable $entries ) : array
    {
        $entries = self::normalize( $entries );

        if( !$user instanceof Model || !$user->exists || $user->getKey() === null ) {
            throw new Exception( 'CMS permission assignment requires a persisted Eloquent user.' );
        }

        $tenant = Tenancy::value();

        if( !Tenancy::allows( $user, $tenant ) ) {
            throw new Exception( 'CMS permissions can only be changed for users in the current tenant.' );
        }

        $result = $user->getConnection()->transaction( function() use ( $entries, $tenant, $user ) {
            /** @var Model&Authenticatable $locked */
            $locked = $user->newQuery()->whereKey( $user->getKey() )->lockForUpdate()->firstOrFail();

            if( !Tenancy::allows( $locked, $tenant ) ) {
                throw new Exception( 'CMS permissions can only be changed for users in the current tenant.' );
            }

            $existing = data_get( $locked, 'cmsperms', [] );
            $existing = is_array( $existing )
                ? array_values( array_filter( $existing, 'is_string' ) )
                : [];

            self::validate( $entries, $existing );
            $locked->forceFill( ['cmsperms' => $entries] )->save();

            return $entries;
        } );

        $user->forceFill( ['cmsperms' => $result] );
        self::override( $user, $result );
        self::audit( $user, $result, $tenant );

        return $result;
    }


    /**
     * Dispatches the permission-change audit event with the request context.
     *
     * @param array<int, string> $result
     */
    private static function audit( Authenticatable $user, array $result, string $tenant ) : void
    {
        Watch::dispatch( \Aimeos\Cms\Events\PermissionChanged::class, function() use ( $user, $result, $tenant ) {
            $actor = Auth::user();
            $request = app()->bound( 'request' ) ? app( 'request' ) : null;

            return new \Aimeos\Cms\Events\PermissionChanged(
                actorEmail: (string) data_get( $actor, 'email' ),
                targetEmail: (string) data_get( $user, 'email' ),
                targetId: (string) $user->getAuthIdentifier(),
                assignments: $result,
                ip: (string) ( $request?->ip() ?? '' ),
                userAgent: (string) ( $request?->userAgent() ?? '' ),
                tenant: $tenant,
            );
        } );
    }


    /**
     * Clears all request-local assignment overrides and cached resolutions.
     *
     * Registered automatically on queue/Octane lifecycle events; call it manually
     * before long-running loops that touch many users (imports, fixtures).
     */
    public static function flush() : void
    {
        self::$overrides = [];
        self::$lastScope = '';
        self::$resolvedCache = null;
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
                throw new Exception( 'CMS permissions must be non-empty strings.' );
            }

            if( mb_strlen( $entry ) > self::MAX_LENGTH ) {
                throw new Exception( sprintf( 'CMS permissions may not be longer than %d characters.', self::MAX_LENGTH ) );
            }

            if( !in_array( $entry, $result, true ) ) {
                $result[] = $entry;
            }

            if( count( $result ) > self::MAX_ASSIGNMENTS ) {
                throw new Exception( sprintf( 'No more than %d CMS permissions may be assigned at once.', self::MAX_ASSIGNMENTS ) );
            }
        }

        sort( $result, SORT_STRING );

        return $result;
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
                array_push( $perms, ...self::$can );
            } elseif( !str_contains( $entry, ':' ) ) {
                array_push( $perms, ...self::resolve( $roles[$entry] ?? [] ) );
            } elseif( str_contains( $entry, '*' ) ) {
                [$prefix, $suffix] = explode( ':', $entry, 2 );

                foreach( self::$can as $perm )
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
        if( str_starts_with( $entry, '!' ) ) {
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

        return $prefix !== '' && $suffix !== ''
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
                throw new Exception( sprintf( 'Unknown CMS role or permission "%s".', $entry ) );
            }
        }
    }
}
