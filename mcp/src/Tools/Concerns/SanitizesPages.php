<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Aimeos\Cms\Tools\Concerns;

use Aimeos\Cms\Utils;
use Aimeos\Cms\Permission;
use Aimeos\Cms\Validation;


trait SanitizesPages
{
    /**
     * Sanitizes the validated input for page mutations.
     *
     * @param array<string, mixed> $v Validated input
     * @param mixed $user Authenticated user
     * @return array<string, mixed> Sanitized input
     */
    protected function sanitize( array $v, mixed $user ) : array
    {
        if( !Utils::isValidUrl( $v['to'] ?? null, false ) ) {
            throw new \InvalidArgumentException( sprintf( 'Invalid URL "%s" in "to" field', $v['to'] ?? '' ) );
        }

        if( !Permission::can( 'config:page', $user ) ) {
            unset( $v['config'] );
        }

        if( isset( $v['content'] ) )
        {
            foreach( $v['content'] as &$content )
            {
                $content = (object) $content;

                if( ( $content->type ?? null ) === 'html' && isset( $content->data['text'] ) ) {
                    $content->data['text'] = Utils::html( (string) $content->data['text'] );
                }
            }

            Validation::content( $v['content'] );
            $v['content'] = $this->buildContent( $v['content'] );
        }

        if( isset( $v['meta'] ) ) {
            Validation::structured( (object) $v['meta'], 'meta' );
        }

        if( isset( $v['config'] ) ) {
            Validation::structured( (object) $v['config'], 'config' );
        }

        return $v;
    }


    /**
     * Builds content elements from the validated input.
     *
     * @param array<int, array<string, mixed>|object> $items Content element items
     * @return array<int, object> Structured content elements
     */
    protected function buildContent( array $items ) : array
    {
        $schemas = config( 'cms.schemas.content', [] );

        return array_values( array_map( function( array|object $item ) use ( $schemas ) {
            $item = (array) $item;
            $type = $item['type'];
            $group = $item['group'] ?? $schemas[$type]['group'] ?? 'main';

            return (object) [
                'id' => $item['id'] ?? Utils::uid(),
                'type' => $type,
                'group' => $group,
                'data' => (object) ( $item['data'] ?? [] ),
            ];
        }, $items ) );
    }


    /**
     * Builds structured meta or config objects from the validated input.
     *
     * @param array<string, array<string, mixed>> $items Keyed by type name, values are data fields
     * @param string $section Schema section ('meta' or 'config')
     * @param array<string, mixed>|object $existing Existing meta/config data
     * @return object Structured meta/config object
     */
    protected function buildStructured( array $items, string $section, array|object $existing ) : object
    {
        $schemas = config( "cms.schemas.{$section}", [] );
        $result = (object) ( (array) $existing );

        foreach( $items as $type => $data )
        {
            $group = $schemas[$type]['group'] ?? 'basic';
            $existingId = $result->{$type}->id ?? null;

            $result->{$type} = (object) [
                'id' => $existingId ?? Utils::uid(),
                'type' => $type,
                'group' => $group,
                'data' => (object) $data,
            ];
        }

        return $result;
    }
}
