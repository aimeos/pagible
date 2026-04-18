<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Aimeos\Cms\Tools;

use Aimeos\Cms\Permission;
use Aimeos\Cms\Schema;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Response;
use Laravel\Mcp\Request;


#[IsReadOnly]
#[Name('get-schemas')]
#[Title('Get available content type schemas')]
#[Description('Returns the available content, meta, and config element types and their field definitions. Use this to understand what structures are valid when creating or saving pages.')]
class GetSchemas extends Tool
{
    /**
     * Handle the tool request.
     */
    public function handle(): \Laravel\Mcp\ResponseFactory
    {
        $result = [];

        foreach( ['content', 'meta', 'config'] as $section )
        {
            foreach( Schema::schemas( section: $section ) as $type => $schema )
            {
                $fields = [];

                foreach( $schema['fields'] ?? [] as $name => $field )
                {
                    $fields[$name] = [
                        'type' => $field['type'] ?? 'string',
                        'label' => $field['label'] ?? $name,
                        'required' => $field['required'] ?? false,
                    ];

                    if( !empty( $field['options'] ) ) {
                        $fields[$name]['options'] = array_column( $field['options'], 'value' );
                    }
                }

                $result[$section][$type] = [
                    'group' => $schema['group'] ?? 'basic',
                    'fields' => $fields,
                ];
            }
        }

        return Response::structured( $result );
    }


    /**
     * Determine if the tool should be registered.
     *
     * @param Request $request The incoming request to check permissions for.
     * @return bool TRUE if the tool should be registered, FALSE otherwise.
     */
    public function shouldRegister( Request $request ) : bool
    {
        return Permission::can( '*', $request->user() );
    }
}
