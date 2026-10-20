<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Database\Factories;

use Aimeos\Cms\Facades\Elements;
use Aimeos\Cms\Models\Element;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;


/**
 * Factory for shared elements created by Elements::add()
 *
 * @extends Factory<Element>
 */
class ElementFactory extends Factory
{
    /**
     * @var class-string<Element>
     */
    protected $model = Element::class;


    /**
     * Returns the default element input.
     *
     * @return array<string, mixed>
     */
    public function definition() : array
    {
        return [
            'type' => 'text',
            'name' => 'Element ' . Str::random( 6 ),
            'data' => ['type' => 'text', 'text' => Str::random( 20 )],
        ];
    }


    /**
     * Creates the element.
     *
     * @param array<string, mixed> $input Element input
     * @return Element Created element
     */
    protected function add( array $input ) : Model
    {
        return Elements::add( $input );
    }


    /**
     * Publishes the element.
     *
     * @param Element $model Created element
     * @return Element Published element
     */
    protected function publish( Model $model ) : Model
    {
        return Elements::publish( (string) $model->getKey() );
    }
}
