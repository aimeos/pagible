<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Aimeos\Cms\Tools;

use Aimeos\Cms\Permission;
use Aimeos\Cms\Validation;
use Aimeos\Cms\Models\Element;
use Illuminate\Support\Facades\DB;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Response;
use Laravel\Mcp\Request;


#[Name('publish-element')]
#[Title('Publish one or more shared content elements')]
#[Description('Publishes one or more shared elements by ID. Pass an array of up to 50 UUIDs. Optionally schedule via "at". Returns published and skipped items.')]
class PublishElement extends Tool
{
    /**
     * Handle the tool request.
     */
    public function handle( Request $request ): \Laravel\Mcp\ResponseFactory
    {
        if( !Permission::can( 'element:publish', $request->user() ) ) {
            throw new \Exception( 'Insufficient permissions' );
        }

        $v = $request->validate([
            'id' => 'required|array|max:50',
            'id.*' => 'string|max:36',
            'at' => 'date',
        ], [
            'id.required' => 'You must specify the ID (string) or IDs (array of up to 50) of the elements to publish.',
        ] );

        Validation::publishAt( $v['at'] ?? null );

        return DB::connection( config( 'cms.db', 'sqlite' ) )->transaction( function() use ( $v, $request ) {

            $ids = (array) $v['id'];

            $items = Element::with( 'latest.files' )->whereIn( 'id', $ids )->get();
            $editor = $request->user()?->email ?? request()->ip(); // @phpstan-ignore-line property.notFound
            $published = [];
            $skipped = [];

            foreach( $items as $item )
            {
                /** @var Element $item */
                $latest = $item->latest;

                if( !$latest ) {
                    $skipped[] = ['id' => $item->id, 'reason' => 'No draft version'];
                    continue;
                }

                if( !empty( $v['at'] ) )
                {
                    $latest->publish_at = $v['at'];
                    $latest->editor = $editor;
                    $latest->save();

                    $published[] = ['id' => $item->id, 'name' => $item->name, 'scheduled_at' => $v['at']];
                }
                else
                {
                    $item->publish( $latest );
                    $published[] = ['id' => $item->id, 'name' => $item->name];
                }
            }

            $notFound = array_diff( $ids, $items->pluck( 'id' )->all() );

            foreach( $notFound as $id ) {
                $skipped[] = ['id' => $id, 'reason' => 'Not found'];
            }

            return Response::structured( [
                'published' => $published,
                'skipped' => $skipped,
            ] );
        }, 3 );
    }


    /**
     * Get the tool's input schema.
     *
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema( JsonSchema $schema ) : array
    {
        return [
            'id' => $schema->array()
                ->description('An array of up to 50 element UUIDs to publish.')
                ->required(),
            'at' => $schema->string()
                ->description('Schedule publication for a future date/time in ISO 8601 format, e.g., "2026-04-01 12:00:00". Omit to publish immediately.'),
        ];
    }


    /**
     * Determine if the tool should be registered.
     *
     * @param Request $request The incoming request to check permissions for.
     * @return bool TRUE if the tool should be registered, FALSE otherwise.
     */
    public function shouldRegister( Request $request ) : bool
    {
        return Permission::can( 'element:publish', $request->user() );
    }
}
