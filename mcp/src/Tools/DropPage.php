<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Tools;

use Aimeos\Cms\Models\PageView;
use Aimeos\Cms\Resource\Variants;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;


#[Name('drop-page')]
#[Title('Soft-delete a page')]
#[Description('Soft-deletes a page with all its language variants, or only one language variant if lang is passed. The page or variant can be restored within the retention period using restore-page. Returns the deleted page as a JSON object.')]
class DropPage extends DropTool
{
    protected const PERMISSIONS = ['page:drop'];
    protected const MODEL = PageView::class;
    protected const NOUN = 'page';
    protected const RULES = parent::RULES + ['lang' => 'string|max:10'];


    /**
     * Get the tool's input schema.
     *
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema( JsonSchema $schema ) : array
    {
        return parent::schema( $schema ) + [
            'lang' => $schema->string()
                ->description('ISO language code to delete only that language variant of the page, e.g., "de". The source language can not be deleted.'),
        ];
    }


    /**
     * Soft-deletes the page or only the language variant if "lang" is passed.
     *
     * @param array<string, mixed> $v Validated input
     * @param Authenticatable|null $user Authenticated user
     * @return array<string, mixed> Deleted page or error
     */
    protected function apply( array $v, ?Authenticatable $user ) : array
    {
        if( isset( $v['lang'] ) ) {
            return Presenter::item( Variants::drop( $v['id'], $v['lang'], $user ) );
        }

        return parent::apply( $v, $user );
    }
}
