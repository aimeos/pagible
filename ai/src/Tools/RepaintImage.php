<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Tools;

use Aimeos\Cms\Ai;
use Aimeos\Prisma\Files\Image;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Request;


#[Name('repaint-image')]
#[Title('Edit an image using a prompt')]
#[Description('Edits an existing image based on a text prompt using AI and stores the result as a new draft version of the same file. Returns the updated file.')]
class RepaintImage extends Tool
{
    use Concerns\HandlesMedia;


    protected const PERMISSIONS = ['image:repaint', 'file:save', 'file:view'];


    /**
     * Get the tool's input schema.
     *
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema( JsonSchema $schema ) : array
    {
        return $this->media( $schema, [
            'prompt' => $schema->string()
                ->description( 'Describe the changes, e.g., "Make the sky look like a sunset".' )
                ->required(),
        ] );
    }


    /**
     * Handle the tool request.
     */
    protected function run( Request $request ) : ResponseFactory
    {
        return $this->edit( $request, [
            'prompt' => 'required|string|max:2000',
        ], [
            'prompt.required' => 'You must provide a prompt describing the desired changes.',
        ], fn( Image $image, array $v ) => Ai::repaint( $image, $v['prompt'] ) );
    }
}
