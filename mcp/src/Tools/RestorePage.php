<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Tools;

use Aimeos\Cms\Facades\Pages;
use Aimeos\Cms\Models\PageView;
use Aimeos\Cms\Facades\Variants;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;


#[Name('restore-page')]
#[Title('Restore a soft-deleted page')]
#[Description('Restores a previously soft-deleted page, or only one deleted language variant if lang is passed. Returns the restored page as a JSON object.')]
class RestorePage extends RestoreTool
{
    protected const PERMISSIONS = ['variant:keep'];
    protected const MODEL = PageView::class;
    protected const RESOURCE = Pages::class;
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
                ->description('ISO language code to restore only that deleted language variant of the page, e.g., "de".'),
        ];
    }


    /**
     * Restores the page or only the language variant if "lang" is passed.
     *
     * @param array<string, mixed> $v Validated input
     * @param Authenticatable|null $user Authenticated user
     * @return array<string, mixed> Restored page or error
     */
    protected function apply( array $v, ?Authenticatable $user ) : array
    {
        if( isset( $v['lang'] ) ) {
            return Presenter::item( Variants::restore( $v['id'], $v['lang'], $user ), true );
        }

        return parent::apply( $v, $user );
    }
}
