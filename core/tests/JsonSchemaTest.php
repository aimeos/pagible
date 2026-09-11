<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests;

use Aimeos\Cms\JsonSchema;
use Aimeos\Cms\Schema;


class JsonSchemaTest extends CoreTestAbstract
{
    public function testStrictBuild() : void
    {
        Schema::source( fn() => [
            'extension' => [
                'content' => ['text-trio' => ['fields' => ['text' => ['type' => 'string']]]],
                'config' => ['theme' => ['fields' => ['color' => ['type' => 'color']]]],
            ],
            'tenant' => [
                'content' => ['quote' => ['fields' => ['text' => ['type' => 'string']]]],
                'meta' => ['social' => ['fields' => ['title' => ['type' => 'string']]]],
            ],
        ] );

        try
        {
            $content = JsonSchema::build( 'content', strict: true );
            $meta = JsonSchema::build( 'meta', strict: true );
            $config = JsonSchema::build( 'config', strict: true );
            $variants = $content['items']['anyOf'];
            $types = array_column( array_column( array_column( $variants, 'properties' ), 'type' ), 'enum' );

            $this->assertSame( 'array', $content['type'] );
            $this->assertContains( ['extension::text-trio'], $types );
            $this->assertContains( ['tenant::quote'], $types );
            $this->assertArrayHasKey( 'tenant::social', $meta['properties'] );
            $this->assertArrayHasKey( 'extension::theme', $config['properties'] );
            $this->assertSame(
                'tenant::social',
                $meta['properties']['tenant::social']['properties']['type']['const']
            );
            $this->assertSame(
                ['type', 'data', 'files'],
                $config['properties']['extension::theme']['required']
            );
        }
        finally
        {
            Schema::source( null );
        }
    }


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
            $location = $this->fields( JsonSchema::build(), 'test::map' )['location'] ?? null;

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
            $fields = $this->fields( JsonSchema::build(), 'test::contact' )['fields'] ?? null;

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
            $value = $this->fields( JsonSchema::build(), 'test::code' )['value'] ?? null;

            $this->assertIsArray( $value );
            $this->assertSame( '^[A-Z]{3}$', $value['pattern'] );
        }
        finally
        {
            Schema::source( null );
        }
    }


    public function testStrictMode() : void
    {
        Schema::source( fn() => [
            'test' => ['content' => ['settings' => ['fields' => [
                'active' => ['type' => 'boolean', 'default' => true],
                'country' => ['type' => 'string', 'uppercase' => true],
                'date' => ['type' => 'date'],
                'link' => ['type' => 'url', 'absolute' => true, 'allowed' => ['https']],
                'location' => ['type' => 'map', 'zoom' => 16],
                'price' => ['type' => 'number', 'precision' => 2],
                'tags' => ['type' => 'combobox', 'multiple' => true, 'options' => [
                    ['value' => 'one'],
                    ['value' => 'two'],
                ]],
                'rows' => ['type' => 'items', 'identity' => 'id', 'item' => [
                    'name' => ['type' => 'string'],
                ]],
            ]]]],
        ] );

        try
        {
            $strict = $this->fields( JsonSchema::build( strict: true ), 'test::settings' );
            $ai = $this->fields( JsonSchema::build(), 'test::settings' );

            $this->assertSame( 'boolean', $strict['active']['type'] );
            $this->assertTrue( $strict['active']['default'] );
            $this->assertSame( ['boolean', 'null'], $ai['active']['type'] );
            $this->assertArrayNotHasKey( 'default', $ai['active'] );
            $this->assertSame( '^[^a-z]*$', $strict['country']['pattern'] );
            $this->assertSame( 'date', $strict['date']['format'] );
            $this->assertArrayNotHasKey( 'format', $ai['date'] );
            $this->assertSame( 'uri', $strict['link']['format'] );
            $this->assertSame( '^(?:https)://', $strict['link']['pattern'] );
            $this->assertSame( 16, $strict['location']['properties']['zoom']['default'] );
            $this->assertSame( 0.01, $strict['price']['multipleOf'] );
            $this->assertSame( ['one', 'two'], $strict['tags']['items']['enum'] );
            $this->assertSame( '^[A-Za-z][A-Za-z0-9_-]{5}$', $strict['rows']['items']['properties']['id']['pattern'] );
            $this->assertArrayNotHasKey( 'id', $ai['rows']['items']['properties'] );
        }
        finally
        {
            Schema::source( null );
        }
    }


    public function testStrictModeRejectsUnknownType() : void
    {
        Schema::source( fn() => [
            'test' => ['content' => ['custom' => ['fields' => [
                'value' => ['type' => 'unknown'],
            ]]]],
        ] );

        try
        {
            $this->expectException( \Aimeos\Cms\Exception::class );
            $this->expectExceptionMessage( 'Unsupported schema field type "unknown"' );

            JsonSchema::build( strict: true );
        }
        finally
        {
            Schema::source( null );
        }
    }


    /**
     * @param array<string, mixed> $schema JSON Schema content definition
     * @return array<string, mixed> Element data field schemas
     */
    private function fields( array $schema, string $type ) : array
    {
        $variants = $schema['type'] === 'array'
            ? $schema['items']['anyOf']
            : $schema['properties']['contents']['items']['anyOf'];

        foreach( $variants as $variant )
        {
            if( ( $variant['properties']['type']['enum'][0] ?? null ) === $type ) {
                return $variant['properties']['data']['properties'];
            }
        }

        $this->fail( sprintf( 'Missing schema variant "%s"', $type ) );
    }
}
