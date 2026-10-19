<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Database\Factories;

use Aimeos\Cms\Permission;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;


/**
 * Base factory creating CMS items through the facades.
 *
 * The items are created as system user like in seeders, so their versions, search
 * index entries and events are the same as for items created by editors.
 *
 * @template TModel of Model
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<TModel>
 */
abstract class Factory extends \Illuminate\Database\Eloquent\Factories\Factory
{
    /** @var \WeakMap<Model, array<string, mixed>>|null Uncasted input of the made models */
    private ?\WeakMap $inputs = null;


    /**
     * Creates the item from the input of the factory.
     *
     * @param array<string, mixed> $input Attributes of the definition and the states
     * @return TModel Created item
     */
    abstract protected function add( array $input ) : Model;


    /**
     * Publishes the latest version of the item.
     *
     * @param TModel $model Created item
     * @return TModel Published item
     */
    abstract protected function publish( Model $model ) : Model;


    /**
     * Get a new model instance and remembers the attributes for creating the item.
     *
     * @param array<string, mixed> $attributes
     * @return TModel
     */
    public function newModel( array $attributes = [] )
    {
        $model = parent::newModel( array_diff_key( $attributes, ['file' => null] ) );
        $this->inputs ??= new \WeakMap();
        $this->inputs[$model] = $attributes;

        return $model;
    }


    /**
     * Publishes the items after they have been created.
     *
     * @return static Factory for fluent interface
     */
    public function published() : static
    {
        return $this->afterCreating( function( Model $model ) {
            $this->sync( $model, Permission::system( fn() => $this->publish( $model ) ) );
        } );
    }


    /**
     * Creates the made models through the facades.
     *
     * @param Collection<int, TModel> $results Made models
     */
    protected function store( Collection $results ) : void
    {
        $results->each( function( Model $model ) {
            $input = $this->inputs[$model] ?? [];
            $this->sync( $model, Permission::system( fn() => $this->add( $input ) ) );
            $model->wasRecentlyCreated = true;
        } );
    }


    /**
     * Takes over the values and relations of the stored item.
     *
     * @param TModel $model Made model
     * @param TModel $item Stored item
     */
    protected function sync( Model $model, Model $item ) : void
    {
        $model->setRawAttributes( $item->getAttributes(), true );
        $model->setRelations( $item->getRelations() );
        $model->setConnection( $item->getConnectionName() );
        $model->exists = true;
    }
}
