<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */

namespace Aimeos\Cms\Access;

use Aimeos\Cms\Exception;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;


/**
 * Base class of the adapters for third-party authorization packages.
 */
abstract class Provider
{
    /**
     * Returns the access adapter of the authorization package.
     *
     * @param (\Closure(Authenticatable): (iterable<mixed>|null))|null $grants Effective-grants resolver
     */
    abstract public static function adapter( ?\Closure $grants = null ) : Adapter;


    /**
     * Invokes a method on a service instance or object.
     */
    protected static function call( object|string $target, string $method, mixed ...$args ) : mixed
    {
        $target = is_string( $target ) ? app( $target ) : $target;
        return $target->{$method}( ...$args );
    }


    /**
     * Returns names from provider model collections.
     *
     * @return array<int, mixed>
     */
    protected static function itemNames( mixed $items ) : array
    {
        if( !is_iterable( $items ) ) {
            throw new Exception( 'Access provider returned an invalid assignment list.' );
        }

        $result = [];

        foreach( $items as $item ) {
            $result[] = data_get( $item, 'name' );
        }

        return $result;
    }


    /**
     * Resolves and validates a configured permission model.
     */
    protected static function model( mixed $model ) : Model
    {
        if( is_string( $model ) ) {
            $model = new $model();
        }

        if( !$model instanceof Model ) {
            throw new Exception( 'Configured permission model must be an Eloquent model.' );
        }

        return $model;
    }


    /**
     * Adds a value through the configured permission model.
     */
    protected static function modelAdd( mixed $model, string $value ) : void
    {
        self::model( $model )->newQuery()->create( ['name' => $value] );
    }


    /**
     * Deletes named values through the configured permission model.
     *
     * @param array<int, string> $values
     * @param array<string, mixed> $where
     */
    protected static function modelDelete( mixed $model, array $values, array $where = [] ) : void
    {
        $model = self::model( $model );

        $model->getConnection()->transaction( function() use ( $model, $values, $where ) {
            $model->newQuery()->where( $where )->whereIn( 'name', $values )->get()->each->delete();
        } );
    }


    /**
     * Returns an ordered list of configured permission names.
     *
     * @param array<string, mixed> $where
     * @return array<int, mixed>
     */
    protected static function modelNames( mixed $model, array $where = [] ) : array
    {
        return self::model( $model )->newQuery()->where( $where )
            ->distinct()->orderBy( 'name' )
            ->pluck( 'name' )
            ->all();
    }


    /**
     * Reads or replaces the values assigned directly to the user.
     *
     * @param Authenticatable $user User whose assignments are read or replaced
     * @param array<int, string>|null $values NULL reads; an array replaces the complete set
     * @param \Closure(Authenticatable): array<int, mixed> $assigned Returns the values assigned to the user
     * @param \Closure(array<int, string>, array<int, mixed>): void $change Assigns and removes the changed values
     * @return array<int, mixed> Values assigned to the user
     */
    protected static function replace( Authenticatable $user, ?array $values, \Closure $assigned, \Closure $change ) : array
    {
        $current = $assigned( $user );

        if( $values === null ) {
            return $current;
        }

        $assign = array_values( array_diff( $values, $current ) );
        $remove = array_values( array_diff( $current, $values ) );

        if( $assign || $remove ) {
            $change( $assign, $remove );
        }

        return $assigned( $user );
    }
}
