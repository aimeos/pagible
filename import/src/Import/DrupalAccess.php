<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Import;

use Aimeos\Cms\Access;
use Aimeos\Cms\Permission;


/**
 * Returns the access values of the pages from the view grants of Drupal's node access modules.
 */
class DrupalAccess
{
    /** @var array<int, array<int, \stdClass>>|false|null View grants of the node access modules by node ID of the current window, FALSE if none is used */
    protected array|false|null $grants = null;
    /** @var array<int, true> IDs of the nodes whose grants and term roles are fetched together */
    protected array $window = [];
    /** @var array<int, string>|null Pagible access values of the Drupal admin roles */
    protected ?array $admins = null;
    /** @var array<string, array<int, string>>|null Drupal role IDs by grant realm and grant ID */
    protected ?array $realms = null;
    /** @var array<int, array<string, array<int, array<int, string>>>>|null Role IDs of the Permissions by Term module by node ID, language code and term */
    protected ?array $termRoleMap = null;


    /**
     * Initializes the object.
     *
     * @param DrupalReader $reader Reader for the Drupal database
     * @param DrupalData $data Configuration of the Drupal database
     * @param Access $access Access values known in Pagible
     * @param array<string, string> $roles Pagible access values by Drupal role ID
     * @param \Closure(int): array<int, int> $nids Returns the IDs of the node and the next imported nodes whose grants are fetched at once
     * @param \Closure(object{nid: int, title: string, langcode?: string}): mixed $restrict Called for restricted nodes before their access values are checked
     * @param \Closure(string): void $warn Shows a warning
     * @param \Closure(string, string): void $warnOnce Shows the warning of the key only once
     */
    public function __construct(
        protected DrupalReader $reader,
        protected DrupalData $data,
        protected Access $access,
        protected array $roles,
        protected \Closure $nids,
        protected \Closure $restrict,
        protected \Closure $warn,
        protected \Closure $warnOnce
    ) {
    }


    /**
     * Returns the access values of the node from the view grants of Drupal's node access modules.
     *
     * Supports the role grants of the Content Access and Nodeaccess modules and the role permissions
     * of the taxonomy terms of the Permissions by Term module. Grants for the anonymous
     * role make the page public and grants for the authenticated role allow all logged in users. The
     * other roles are imported as access values together with the admin roles because users with
     * the "bypass node access" permission can see all nodes in Drupal. Grants for single users and
     * of other modules can't be imported, so only the roles of the other grants can see the page.
     * Restricted nodes aren't imported if frontend access restrictions aren't set up in Pagible or
     * their roles don't exist there. Admin roles which don't exist in Pagible are skipped.
     *
     * @param object{nid: int, title: string, langcode?: string} $node
     * @return array<int, string>|false|null Access values, NULL for public pages or FALSE if no node access module is used
     */
    public function node( object $node ): array|false|null
    {
        if( ( $grants = $this->fetch( (int) $node->nid ) ) === false ) {
            return false;
        }

        if( ( $values = $this->granted( $node, $grants[$node->nid] ?? [] ) ) === null ) {
            return null;
        }

        // pages of restricted nodes which fail to import are disabled
        ( $this->restrict )( $node );

        // restricted nodes must not become public pages
        if( !Permission::has( 'access:view' ) ) {
            throw new \RuntimeException( 'Access is restricted in Drupal but frontend access restrictions aren\'t set up in Pagible, see Access::using()' );
        }

        if( in_array( 'authenticated', $values, true ) ) {
            return [];
        }

        $values = array_values( array_unique( array_map( fn( $role ) => $this->roles[$role] ?? $role, $values ) ) );

        if( $unknown = array_diff( $values, $this->access->known( $values ) ) ) {
            throw new \RuntimeException( 'Unknown access values "' . implode( '", "', $unknown ) . '", add them in Pagible or use --role=<drupalrole>:<value>' );
        }

        // admin roles which don't exist in Pagible are skipped
        $admins = $this->admins ??= $this->access->known( array_map( fn( $role ) => $this->roles[$role] ?? $role, $this->adminRoles() ) );

        if( !$values && !$admins ) {
            throw new \RuntimeException( "Access is only granted to single users in Drupal and the admin roles don't exist in Pagible" );
        }

        if( !$values ) {
            ( $this->warn )( "  Restricted [{$node->nid}] {$node->title} to admin roles, its node access grants aren't for roles" );
        }

        return array_values( array_unique( [...$values, ...$admins] ) );
    }


    /**
     * Returns the grants of the nodes of the window containing the node.
     *
     * The grants and term roles of the node and the next imported nodes are fetched together if the
     * node isn't in the current window, so not all grants are kept in memory.
     *
     * @return array<int, array<int, \stdClass>>|false Grants by node ID or FALSE if no node access module is used
     */
    protected function fetch( int $nid ): array|false
    {
        if( $this->grants === false || $this->grants !== null && isset( $this->window[$nid] ) ) {
            return $this->grants;
        }

        $nids = array_values( array_unique( [$nid, ...( $this->nids )( $nid )] ) );
        $this->window = array_fill_keys( $nids, true );
        $this->termRoleMap = null;

        return $this->grants = $this->reader->grants( $nids );
    }


    /**
     * Returns the Drupal roles which are granted access to the node.
     *
     * @param object{nid: int, title: string, langcode?: string} $node
     * @param array<int, \stdClass> $rows Node access grants of the node
     * @return array<int, string>|null Drupal role names or NULL if everyone has access
     */
    protected function granted( object $node, array $rows ): ?array
    {
        $rows = array_filter( $rows, fn( $row ) => (string) $row->langcode === (string) ( $node->langcode ?? '' ) ) ?: $rows;
        $realms = $this->realms ??= $this->grantRoles();
        $terms = false;
        $roles = [];

        foreach( $rows as $row )
        {
            $realm = (string) $row->realm;

            if( $realm === 'all' ) {
                return null;
            }

            if( str_starts_with( $realm, 'permissions_by_term' ) ) {
                $terms = true;
            } elseif( isset( $realms[$realm][(int) $row->gid] ) ) {
                $roles[] = $realms[$realm][(int) $row->gid];
            } elseif( !preg_match( '/_(author|uid)$/', $realm ) ) {
                ( $this->warnOnce )( "realm:{$realm}", "  Skipped node access grants of the \"{$realm}\" realm, not supported" );
            }
        }

        $roles = [...$roles, ...( $terms ? $this->termRoles( $node ) : [] )];

        return in_array( 'anonymous', $roles, true ) ? null : $roles;
    }


    /**
     * Returns the Drupal roles allowed to view the node by the taxonomy terms of the Permissions by Term module.
     *
     * Terms without role permissions don't restrict the access. If the "require_all_terms_granted"
     * setting is enabled, only roles allowed to view all restricted terms are returned.
     *
     * @param object{nid: int, langcode?: string} $node
     * @return array<int, string> Drupal role IDs
     */
    protected function termRoles( object $node ): array
    {
        // the roles of the nodes of the current window are fetched at once to avoid one query per node
        $this->termRoleMap ??= $this->reader->termRoles( array_keys( $this->window ) );
        $map = $this->termRoleMap[(int) $node->nid] ?? [];
        $roles = $map[(string) ( $node->langcode ?? '' )] ?? $map[''] ?? [];

        if( count( $roles ) > 1 && !empty( $this->data->config( 'permissions_by_term.settings' )['require_all_terms_granted'] ) ) {
            return array_values( array_intersect( ...$roles ) );
        }

        return array_values( array_unique( array_merge( ...$roles ) ) );
    }


    /**
     * Returns the Drupal role IDs by grant realm and grant ID of the supported node access modules.
     *
     * @return array<string, array<int, string>>
     */
    protected function grantRoles(): array
    {
        $gids = fn( mixed $map ) => array_flip( array_map( 'intval', array_filter( (array) $map, 'is_numeric' ) ) );
        return [
            'content_access_rid' => $gids( $this->reader->contentAccessGids() ),
            'nodeaccess_rid' => $gids( $this->data->config( 'nodeaccess.settings' )['role_map'] ?? [] ),
        ];
    }


    /**
     * Returns the IDs of the Drupal roles whose users can see all nodes.
     *
     * @return array<int, string>
     */
    protected function adminRoles(): array
    {
        $roles = [];

        foreach( $this->data->configs() as $name => $config )
        {
            if( str_starts_with( $name, 'user.role.' ) && ( !empty( $config['is_admin'] )
                || in_array( 'bypass node access', (array) ( $config['permissions'] ?? [] ), true ) )
            ) {
                $roles[] = (string) ( $config['id'] ?? substr( $name, 10 ) );
            }
        }

        return $roles;
    }
}
