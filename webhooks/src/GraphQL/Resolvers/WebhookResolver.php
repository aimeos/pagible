<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\GraphQL\Resolvers;

use Aimeos\Cms\Exception;
use Aimeos\Cms\Models\Webhook;
use Aimeos\Cms\Permission;
use Aimeos\Cms\Tenancy;
use Aimeos\Cms\WebhookManager;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;


final class WebhookResolver
{
    public function __construct( private readonly WebhookManager $manager )
    {
    }


    /**
     * @param array<string, mixed> $args
     * @return array<string, mixed>
     */
    public function add( mixed $root, array $args ) : array
    {
        return $this->manager->add( (array) ( $args['input'] ?? [] ), Auth::user() );
    }


    /**
     * @param array<string, mixed> $args
     */
    public function drop( mixed $root, array $args ) : int
    {
        $ids = $args['id'] ?? null;

        if( !is_array( $ids ) || !array_is_list( $ids ) || array_filter( $ids, 'is_string' ) !== $ids ) {
            throw new Exception( 'Invalid webhook selection.' );
        }

        return $this->manager->drop( $ids, Auth::user() );
    }


    /**
     * @param array<string, mixed> $args
     */
    public function find( mixed $root, array $args ) : ?Webhook
    {
        $tenant = $this->tenant();

        return Webhook::withoutTenancy()
            ->where( 'tenant_id', $tenant )
            ->where( 'id', (string) ( $args['id'] ?? '' ) )
            ->first();
    }


    /**
     * @return list<string>
     */
    public function names() : array
    {
        $this->tenant();
        return WebhookManager::EVENTS;
    }


    /**
     * @param array<string, mixed> $args
     * @return array<string, mixed>
     */
    public function replace( mixed $root, array $args ) : array
    {
        return $this->manager->replace(
            (string) ( $args['id'] ?? '' ),
            (string) ( $args['url'] ?? '' ),
            Auth::user(),
        );
    }


    /**
     * @param array<string, mixed> $args
     * @return array<string, mixed>
     */
    public function rotate( mixed $root, array $args ) : array
    {
        return $this->manager->rotate( (string) ( $args['id'] ?? '' ), Auth::user() );
    }


    /**
     * @param array<string, mixed> $args
     */
    public function save( mixed $root, array $args ) : Webhook
    {
        return $this->manager->save(
            (string) ( $args['id'] ?? '' ),
            (array) ( $args['input'] ?? [] ),
            Auth::user(),
        );
    }


    /**
     * @return Collection<int, Webhook>
     */
    public function search() : Collection
    {
        $tenant = $this->tenant();

        return Webhook::withoutTenancy()
            ->where( 'tenant_id', $tenant )
            ->orderByDesc( 'updated_at' )
            ->limit( max( 1, (int) config( 'cms.webhooks.limits.total', 100 ) ) )
            ->get();
    }


    private function tenant() : string
    {
        if( !Permission::can( 'config:webhook', Auth::user() ) ) {
            throw new Exception( 'Permission denied.' );
        }

        $tenant = Tenancy::value();

        if( $tenant === '' && Tenancy::$callback !== null ) {
            throw new Exception( 'No tenant is active.' );
        }

        return $tenant;
    }
}
