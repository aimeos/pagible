<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Database\Factories;

use Aimeos\Cms\Facades\Pages;
use Aimeos\Cms\Models\PageView;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;


/**
 * Factory for pages created by Pages::add()
 *
 * Use "parent_id" or for( $page, 'parent' ) to add the page below another one.
 *
 * @extends Factory<PageView>
 */
class PageFactory extends Factory
{
    /**
     * @var class-string<PageView>
     */
    protected $model = PageView::class;


    /**
     * Returns the default page input.
     *
     * @return array<string, mixed>
     */
    public function definition() : array
    {
        $name = Str::lower( Str::random( 10 ) );

        return [
            'lang' => app()->getLocale(),
            'name' => Str::ucfirst( $name ),
            'title' => Str::ucfirst( $name ),
            'path' => $name,
            'status' => 1,
            'content' => [],
        ];
    }


    /**
     * Pages hidden in the navigation.
     *
     * @return static Factory for fluent interface
     */
    public function hidden() : static
    {
        return $this->state( ['status' => 2] );
    }


    /**
     * Inactive pages.
     *
     * @return static Factory for fluent interface
     */
    public function inactive() : static
    {
        return $this->state( ['status' => 0] );
    }


    /**
     * Creates the page.
     *
     * @param array<string, mixed> $input Page input
     * @return PageView Created page
     */
    protected function add( array $input ) : Model
    {
        $parent = $input['parent_id'] ?? null;
        unset( $input['parent_id'] );

        return Pages::add( $input, parent: $parent );
    }


    /**
     * Publishes the page.
     *
     * @param PageView $model Created page
     * @return PageView Published page
     */
    protected function publish( Model $model ) : Model
    {
        return Pages::publish( (string) $model->getKey() );
    }
}
