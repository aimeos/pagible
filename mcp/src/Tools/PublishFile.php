<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Aimeos\Cms\Tools;

use Aimeos\Cms\Utils;
use Aimeos\Cms\Resource;
use Aimeos\Cms\Permission;
use Aimeos\Cms\Validation;
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
        if( !Permission::can( 'file:publish', $request->user() ) ) {
            throw new \Exception( 'Insufficient permissions' );
        }

        $v = $request->validate([
            'id' => 'required|array|max:50',
            'id.*' => 'string|max:36',
            'at' => 'date',
        ], [
            'id.required' => 'You must specify the ID (string) or IDs (array of up to 50) of the files to publish.',
        ] );

        Validation::publishAt( $v['at'] ?? null );

        $ids = (array) $v['id'];
        $editor = Utils::editor( $request->user() );
        $items = Resource::publish( File::class, $ids, $editor, $v['at'] ?? null );

        $published = [];
        $skipped = [];

        foreach( $items as $item )
        {
            /** @var File $item */
            if( !$item->latest ) {
                $skipped[] = ['id' => $item->id, 'reason' => 'No draft version'];
            } elseif( !empty( $v['at'] ) ) {
                $published[] = ['id' => $item->id, 'name' => $item->name, 'scheduled_at' => $v['at']];
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
        return Permission::can( 'file:publish', $request->user() );
    }
}
