<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Tools;

use Aimeos\Cms\Facades\Variants;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;


#[Name('drop-page-variant')]
#[Title('Soft-delete a language variant of a page')]
#[Description('Soft-deletes one language variant of a page, the source language can not be deleted. The variant can be restored within the retention period using restore-page-variant. Returns the page as a JSON object.')]
class DropPageVariant extends DropTool
{
    protected const PERMISSIONS = ['variant:drop'];
    protected const NOUN = 'page';
    protected const RULES = parent::RULES + ['lang' => 'required|string|max:10'];


    /**
     * Get the tool's input schema.
     *
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema( JsonSchema $schema ) : array
    {
        return parent::schema( $schema ) + [
            'lang' => $schema->string()
                ->description('ISO language code of the variant to delete, e.g., "de".')
                ->required(),
        ];
    }


    /**
     * Soft-deletes the language variant of the page.
     *
     * @param array<string, mixed> $v Validated input
     * @param Authenticatable|null $user Authenticated user
     * @return array<string, mixed> Page or error
     */
    protected function apply( array $v, ?Authenticatable $user ) : array
    {
        return Presenter::item( Variants::drop( $v['id'], $v['lang'], $user ) );
    }
}
