<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Tools;

use Aimeos\Cms\Publication;
use Aimeos\Cms\Permission;
use Aimeos\Cms\Models\File;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Response;
use Laravel\Mcp\Request;


#[Name('publish-file')]
#[Title('Publish one or more media files')]
#[Description('Publishes one or more files by ID. Pass an array of up to 50 UUIDs. Optionally schedule via "at". Returns published and skipped items.')]
class PublishFile extends Tool
{
    /**
     * Handle the tool request.
     */
    public function handle( Request $request ): \Laravel\Mcp\ResponseFactory
    {
        if( !Permission::can( 'file:publish', $request->user() )
            || !Permission::can( 'file:view', $request->user() ) ) {
            throw new \Aimeos\Cms\Exception( 'Insufficient permissions' );
        }

        $v = $request->validate([
            'id' => 'required|array|max:50',
            'id.*' => 'string|max:36',
            'at' => 'date',
        ], [
            'id.required' => 'You must specify the ID (string) or IDs (array of up to 50) of the files to publish.',
        ] );

        $ids = (array) $v['id'];
        $at = $v['at'] ?? null;
        $items = Publication::publish( File::class, $ids, $request->user(), $at );

        $published = [];
        $skipped = [];

        foreach( $items as $item )
        {
            /** @var File $item */
            if( !$item->latest ) {
                $skipped[] = ['id' => $item->id, 'reason' => 'No draft version'];
            } elseif( $at && $item->latest->published ) {
                $skipped[] = ['id' => $item->id, 'reason' => 'Already published'];
            } elseif( $at ) {
                $published[] = ['id' => $item->id, 'name' => $item->name, 'scheduled_at' => $at];
            } else {
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
                ->items( $schema->string() )
                ->description('An array of up to 50 file UUIDs to publish.')
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
        return Permission::can( 'file:publish', $request->user() )
            && Permission::can( 'file:view', $request->user() );
    }
}
