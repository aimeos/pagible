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


#[Name('restore-page-variant')]
#[Title('Restore a soft-deleted language variant of a page')]
#[Description('Restores one previously soft-deleted language variant of a page. Returns the page as a JSON object.')]
class RestorePageVariant extends RestoreTool
{
    protected const PERMISSIONS = ['variant:keep'];
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
                ->description('ISO language code of the deleted variant to restore, e.g., "de".')
                ->required(),
        ];
    }


    /**
     * Restores the language variant of the page.
     *
     * @param array<string, mixed> $v Validated input
     * @param Authenticatable|null $user Authenticated user
     * @return array<string, mixed> Page or error
     */
    protected function apply( array $v, ?Authenticatable $user ) : array
    {
        return Presenter::item( Variants::restore( $v['id'], $v['lang'], $user ), true );
    }
}
