<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Tools;

use Aimeos\Cms\Ai;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Request;


#[Name('uncrop-image')]
#[Title('Extend an image (outpaint)')]
#[Description('Extends an existing image outwards by the given number of pixels on each side using AI (outpainting) and stores the result as a new draft version of the same file. Returns the updated file.')]
class UncropImage extends Tool
{
    use HandlesMedia;


    protected const PERMISSIONS = ['image:uncrop', 'file:save', 'file:view'];


    /**
     * Get the tool's input schema.
     *
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema( JsonSchema $schema ) : array
    {
        return [
            'file' => $schema->string()
                ->description( 'The UUID of the image file to extend.' )
                ->required(),
            'top' => $schema->integer()
                ->description( 'Pixels to add at the top.' ),
            'right' => $schema->integer()
                ->description( 'Pixels to add on the right.' ),
            'bottom' => $schema->integer()
                ->description( 'Pixels to add at the bottom.' ),
            'left' => $schema->integer()
                ->description( 'Pixels to add on the left.' ),
            'latest_id' => $this->latest( $schema ),
        ];
    }


    /**
     * Handle the tool request.
     */
    protected function run( Request $request ) : ResponseFactory
    {
        return $this->edit( $request, [
            'top' => 'integer|min:0|max:4096',
            'right' => 'integer|min:0|max:4096',
            'bottom' => 'integer|min:0|max:4096',
            'left' => 'integer|min:0|max:4096',
        ], [
            'file.required' => 'You must specify the UUID of the image file to extend.',
        ], fn( $image, $v ) => Ai::uncrop( $image, $v['top'] ?? 0, $v['right'] ?? 0, $v['bottom'] ?? 0, $v['left'] ?? 0 ) );
    }
}
