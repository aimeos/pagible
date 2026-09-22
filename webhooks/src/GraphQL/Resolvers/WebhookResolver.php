<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\GraphQL\Resolvers;

use Aimeos\Cms\Models\Webhook;
use Aimeos\Cms\WebhookConfig;
use Aimeos\Cms\WebhookManager;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;


final class WebhookResolver
{
    public function __construct(
        private readonly WebhookManager $manager,
        private readonly WebhookConfig $config,
    ) {
    }


    /**
     * @param array{input: array{url: string, events: list<string>, status?: bool, name?: string|null}} $args
     * @return array<string, mixed>
     */
    public function add( mixed $root, array $args ) : array
    {
        return $this->manager->add(
            $args['input']['url'],
            $args['input']['events'],
            $args['input']['status'] ?? false,
            Auth::user(),
            $args['input']['name'] ?? '',
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
     * @param array{id: string} $args
     * @return array{success: bool, status: int|null, reason: string|null}
     */
    public function ping( mixed $root, array $args ) : array
    {
        return $this->manager->ping( $args['id'], Auth::user() );
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
     * @param array{id: string, input: array{events: list<string>, status: bool, name?: string|null}} $args
     */
    public function save( mixed $root, array $args ) : Webhook
    {
        return $this->manager->save(
            $args['id'],
            $args['input']['events'],
            $args['input']['status'],
            Auth::user(),
            // Omitted names are kept, empty ones are converted to NULL by Laravel's middleware
            array_key_exists( 'name', $args['input'] ) ? (string) $args['input']['name'] : null,
        );
    }


    /**
     * @return Collection<int, Webhook>
     */
    public function search() : Collection
    {
        // Not limited by the current total limit because subscriptions added before it was lowered
        // still receive events, their number is bounded by the limit when they were added
        return Webhook::query()
            ->orderByDesc( 'updated_at' )
            ->get();
    }


    /**
     * Returns if events are sent at all, so editors know why subscriptions don't receive any.
     *
     * @return array{enabled: bool, blocked: string|null}
     */
    public function server() : array
    {
        return [
            'enabled' => (bool) config( 'cms.webhooks.enabled', false ),
            'blocked' => $this->config->blocked(),
        ];
    }
}
