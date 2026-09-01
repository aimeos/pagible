<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests;

use Aimeos\Cms\JsonSchema;
use Aimeos\Cms\Schema;


class JsonSchemaTest extends CoreTestAbstract
{
    public function testMap() : void
    {
        Schema::source( fn() => [
            'test' => [
                'content' => [
                    'map' => [
                        'fields' => [
                            'location' => ['type' => 'map', 'required' => true],
                        ],
                    ],
                ],
            ],
        ] );

        try
        {
            $schema = JsonSchema::build();
            $variants = $schema['properties']['contents']['items']['anyOf'];
            $variant = null;

            foreach( $variants as $item )
            {
                if( is_array( $item )
                    && ( $item['properties']['type']['enum'][0] ?? null ) === 'test::map'
                ) {
                    $variant = $item;
                    break;
                }
            }

            $location = $variant['properties']['data']['properties']['location'] ?? null;

            $this->assertIsArray( $location );
            $this->assertSame( 'object', $location['type'] );
            $this->assertSame( ['latitude', 'longitude', 'zoom'], $location['required'] );
            $this->assertSame( -90, $location['properties']['latitude']['minimum'] );
            $this->assertSame( 90, $location['properties']['latitude']['maximum'] );
            $this->assertSame( -180, $location['properties']['longitude']['minimum'] );
            $this->assertSame( 180, $location['properties']['longitude']['maximum'] );
            $this->assertSame( 'integer', $location['properties']['zoom']['type'] );
            $this->assertSame( 1, $location['properties']['zoom']['minimum'] );
            $this->assertSame( 19, $location['properties']['zoom']['maximum'] );
            $this->assertFalse( $location['additionalProperties'] );
        }
        finally
        {
            Schema::source( null );
        }
    }


    public function testMultipleCombobox() : void
    {
        Schema::source( fn() => [
            'test' => [
                'content' => [
                    'contact' => [
                        'fields' => [
                            'fields' => [
                                'type' => 'combobox',
                                'multiple' => true,
                                'max' => 20,
                            ],
                        ],
                    ],
                ],
            ],
        ] );

        try
        {
            $schema = JsonSchema::build();
            $variants = $schema['properties']['contents']['items']['anyOf'];
            $variant = null;

            foreach( $variants as $item )
            {
                if( is_array( $item )
                    && ( $item['properties']['type']['enum'][0] ?? null ) === 'test::contact'
                ) {
                    $variant = $item;
                    break;
                }
            }

            $fields = $variant['properties']['data']['properties']['fields'] ?? null;

            $this->assertIsArray( $fields );
            $this->assertSame( ['array', 'null'], $fields['type'] );
            $this->assertSame( ['type' => 'string'], $fields['items'] );
            $this->assertSame( 20, $fields['maxItems'] );
        }
        finally
        {
            Schema::source( null );
        }
    }


    public function testStringPattern() : void
    {
        Schema::source( fn() => [
            'test' => [
                'content' => [
                    'code' => [
                        'fields' => [
                            'value' => [
                                'type' => 'string',
                                'pattern' => '^[A-Z]{3}$',
                            ],
                        ],
                    ],
                ],
            ],
        ] );

        try
        {
            $schema = JsonSchema::build();
            $variants = $schema['properties']['contents']['items']['anyOf'];
            $variant = null;

            foreach( $variants as $item )
            {
                if( is_array( $item )
                    && ( $item['properties']['type']['enum'][0] ?? null ) === 'test::code'
                ) {
                    $variant = $item;
                    break;
                }
            }

            $this->assertIsArray( $variant );
            $this->assertSame(
                '^[A-Z]{3}$',
                $variant['properties']['data']['properties']['value']['pattern']
            );
        }
        finally
        {
            Schema::source( null );
        }
    }
}
