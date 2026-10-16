<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Tools;

use Aimeos\Cms\Facades\Pages;
use Aimeos\Cms\Facades\Variants;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Collection;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;


#[Name('publish-page')]
#[Title('Publish one or more pages')]
#[Description('Publishes one or more pages by ID, in the language passed by lang or in their source language. Pass an array of up to 50 UUIDs. Optionally schedule for a future ISO 8601 datetime via "at". Returns published and skipped items with reasons.')]
class PublishPage extends PublishTool
{
    protected const PERMISSIONS = ['page:publish'];
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
        $fields = parent::schema( $schema );

        return [
            'id' => $fields['id'],
            'lang' => $schema->string()
                ->description('ISO language code of the language variants to publish, e.g., "de". Omit to publish the source language variants.'),
            'at' => $fields['at'],
        ];
    }


    /**
     * Publishes the pages or only their language variants if "lang" is passed.
     *
     * @param array<string, mixed> $v Validated input
     * @param Authenticatable|null $user Authenticated user
     * @return Collection<int, covariant \Aimeos\Cms\Models\Base> Published pages
     */
    protected function publish( array $v, ?Authenticatable $user ) : Collection
    {
        if( isset( $v['lang'] ) ) {
            return Variants::publishMany( $v['id'], $v['lang'], $user, $v['at'] ?? null );
        }

        return parent::publish( $v, $user );
    }
}
