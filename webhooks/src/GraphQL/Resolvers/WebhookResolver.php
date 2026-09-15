<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\GraphQL\Resolvers;

use Aimeos\Cms\Models\Webhook;
use Aimeos\Cms\WebhookManager;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;


final class WebhookResolver
{
    public function __construct( private readonly WebhookManager $manager )
    {
    }


    /**
     * @param array{input: array{url: string, events: list<string>}} $args
     * @return array<string, mixed>
     */
    public function add( mixed $root, array $args ) : array
    {
        return $this->manager->add(
            $args['input']['url'],
            $args['input']['events'],
            Auth::user(),
        );
    }


    /**
     * @param array{id: list<string>} $args
     */
    public function drop( mixed $root, array $args ) : int
    {
        return $this->manager->drop( $args['id'], Auth::user() );
    }


    /**
     * @return list<string>
     */
    public function names() : array
    {
        return WebhookManager::EVENTS;
    }


    /**
     * @param array{id: string, url: string} $args
     * @return array<string, mixed>
     */
    public function replace( mixed $root, array $args ) : array
    {
        return $this->manager->replace(
            $args['id'],
            $args['url'],
            Auth::user(),
        );
    }


    /**
     * @param array{id: string} $args
     * @return array<string, mixed>
     */
    public function rotate( mixed $root, array $args ) : array
    {
        return $this->manager->rotate( $args['id'], Auth::user() );
    }


    /**
     * @param array{id: string, input: array{events: list<string>, status: bool}} $args
     */
    public function save( mixed $root, array $args ) : Webhook
    {
        return $this->manager->save(
            $args['id'],
            $args['input']['events'],
            $args['input']['status'],
            Auth::user(),
        );
    }


    /**
     * @return Collection<int, Webhook>
     */
    public function search() : Collection
    {
        return Webhook::query()
            ->orderByDesc( 'updated_at' )
            ->limit( max( 1, (int) config( 'cms.webhooks.limits.total', 100 ) ) )
            ->get();
    }


}
