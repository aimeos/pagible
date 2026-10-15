<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Events;

use Aimeos\Cms\ContentAction;
use Aimeos\Cms\ContentType;
use Aimeos\Cms\Jobs\HandleChanges;
use Aimeos\Cms\LifecycleAction;
use Aimeos\Cms\VariantAction;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Event as Events;


/**
 * Change of a single page, element or file, regardless if it was announced by a single or bulk event.
 *
 * Operations on one item dispatch the per-action events (Saved, Published, etc.), operations on
 * several items dispatch one Bulk event instead. Both return their changed items by changes() and
 * listen() registers a listener which is called once for each changed item of both kinds of events:
 *
 * $stop = Change::listen( 'published', fn( Change $change ) => ... );
 * $stop(); // removes the listener again
 *
 * Use '*' to listen for all actions, including the page variant actions of VariantAction.
 *
 * Listener classes with a handle(Change $change) method are resolved from the container. If they
 * implement ShouldQueue, the changed items of each event are passed to them in one queued job.
 */
final readonly class Change
{
    /**
     * @param string $contentType Content type: 'page', 'element' or 'file'
     * @param string $action Action name, e.g. 'added', 'saved', 'moved', 'published', 'dropped', 'restored', 'purged' or a page variant action like 'translated'
     * @param string $id Content UUID
     * @param string $versionId ID of the latest version or of the published version if it was published
     * @param bool $published TRUE if the change made the version public immediately, FALSE if it's only scheduled or not published at all
     * @param string|null $lang Language of the changed page variant, NULL for elements, files and lifecycle changes of whole pages
     * @param string|null $path URL path of the page if known
     * @param string|null $domain Domain of the page if known
     * @param string $editor Editor name
     * @param string $tenant Tenant ID
     * @param string $source Originating interface: 'graphql', 'mcp' or 'cli'
     */
    public function __construct(
        public string $contentType,
        public string $action,
        public string $id,
        public string $versionId,
        public bool $published = false,
        public ?string $lang = null,
        public ?string $path = null,
        public ?string $domain = null,
        public string $editor = '',
        public string $tenant = '',
        public string $source = '',
    ) {}


    /**
     * Returns the type of the changed content.
     */
    public function type() : ContentType
    {
        return ContentType::from( $this->contentType );
    }


    /**
     * Registers a listener called once for each item changed by the given actions.
     *
     * @param ContentAction|LifecycleAction|VariantAction|string|array<ContentAction|LifecycleAction|VariantAction|string> $actions Actions to listen for or '*' for all
     * @param callable(Change): mixed|class-string $listener Listener or listener class receiving each changed item
     * @return \Closure(): void Function removing the listener again
     * @throws \InvalidArgumentException If an action name is unknown
     */
    public static function listen( ContentAction|LifecycleAction|VariantAction|string|array $actions, callable|string $listener ) : \Closure
    {
        $names = array_map( fn( $action ) => is_string( $action ) ? $action : $action->value, is_array( $actions ) ? $actions : [$actions] );
        $all = in_array( '*', $names, true );
        $classes = [Bulk::class];
        $state = (object) ['active' => true];

        foreach( $all ? [...ContentAction::cases(), ...LifecycleAction::cases()] : $names as $name )
        {
            $name = is_string( $name ) ? $name : $name->value;

            if( $action = ContentAction::tryFrom( $name ) ?? LifecycleAction::tryFrom( $name ) ) {
                $classes[] = $action->event();
            } elseif( !VariantAction::tryFrom( $name ) ) {
                throw new \InvalidArgumentException( sprintf( 'Unknown change action "%1$s"', $name ) );
            }
        }

        Events::listen( array_values( array_unique( $classes ) ), function( Event|Bulk $event ) use ( $names, $all, $listener, $state ) {
            $changes = array_values( array_filter( $event->changes(), fn( Change $change ) => $all || in_array( $change->action, $names, true ) ) );

            if( !$state->active || $changes === [] ) {
                return;
            }

            if( is_string( $listener ) && class_exists( $listener ) )
            {
                if( is_subclass_of( $listener, ShouldQueue::class ) ) {
                    HandleChanges::dispatch( $listener, $changes );
                    return;
                }

                $listener = [app( $listener ), 'handle'];
            }

            if( !is_callable( $listener ) ) {
                throw new \InvalidArgumentException( 'Change listener must be callable or a class with a handle() method' );
            }

            foreach( $changes as $change ) {
                $listener( $change );
            }
        } );

        return function() use ( $state ) {
            $state->active = false;
        };
    }
}
