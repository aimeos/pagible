<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests;

use Aimeos\Cms\Facades;
use Aimeos\Cms\Models\PageView;
use Aimeos\Cms\Resource\BulkResult;
use Database\Seeders\TestSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;


class FacadeTest extends CoreTestAbstract
{
    use CmsWithMigrations;
    use RefreshDatabase;

    protected $seeder = TestSeeder::class;


    protected function tearDown() : void
    {
        // the mocks replace the shared instances, the next tests must use the real resources again
        foreach( ['Pages', 'Elements', 'Files', 'Variants'] as $name )
        {
            $this->app?->forgetInstance( 'Aimeos\\Cms\\Resource\\' . $name );
            Facades\Pages::clearResolvedInstance( 'Aimeos\\Cms\\Resource\\' . $name );
        }

        parent::tearDown();
    }


    public function testForwardsToResource() : void
    {
        $root = PageView::where( 'tag', 'root' )->firstOrFail();
        $page = Facades\Pages::add( ['lang' => 'en', 'name' => 'Facade', 'path' => 'facade'], parent: $root->id );

        $this->assertInstanceOf( PageView::class, $page );
        $this->assertEquals( 'Facade', Facades\Pages::save( $page->id, ['name' => 'Facade'] )->name );
        $this->assertInstanceOf( BulkResult::class, Facades\Pages::bulk( [$page->id], ['title' => 'Bulk'] ) );

        $variant = Facades\Variants::add( $page->id, 'de' );
        $this->assertEquals( 'de', $variant->lang );
    }


    public function testShouldReceive() : void
    {
        $page = new PageView( ['name' => 'Mocked'] );

        Facades\Pages::shouldReceive( 'publish' )->once()->with( 'page-id', null )->andReturn( $page );

        $this->assertSame( $page, Facades\Pages::publish( 'page-id', null ) );
    }


    public function testSpy() : void
    {
        $spy = Facades\Elements::spy();

        Facades\Elements::drop( 'element-id' );

        $spy->shouldHaveReceived( 'drop' )->once()->with( 'element-id' );
    }


    public function testOverride() : void
    {
        $this->app->singleton( \Aimeos\Cms\Resource\Elements::class, function() {
            return new class extends \Aimeos\Cms\Resource\Elements {
                public array $dropped = [];

                public function dropMany( array $ids, ?\Illuminate\Contracts\Auth\Authenticatable $user = null, array $fields = [] ) : \Illuminate\Support\Collection
                {
                    $this->dropped = $ids;
                    return parent::dropMany( $ids, $user, $fields );
                }
            };
        } );

        $element = Facades\Elements::add( ['type' => 'text', 'name' => 'Override', 'data' => ['type' => 'text', 'text' => 'Test']] );

        // drop() calls dropMany() on the bound instance, so overriding one method changes all callers
        $this->assertNotNull( Facades\Elements::drop( $element->id )->deleted_at );
        $this->assertSame( [$element->id], app( \Aimeos\Cms\Resource\Elements::class )->dropped );
    }


    public function testDeprecatedClassAliases() : void
    {
        $this->assertSame( \Aimeos\Cms\Events\Channel::class, ( new \ReflectionClass( 'Aimeos\Cms\Channel' ) )->getName() );
        $this->assertSame( \Aimeos\Cms\Query\Filter::class, ( new \ReflectionClass( 'Aimeos\Cms\Filter' ) )->getName() );
        $this->assertSame( \Aimeos\Cms\Query\SearchBuilder::class, ( new \ReflectionClass( 'Aimeos\Cms\SearchBuilder' ) )->getName() );

        try {
            throw new \Aimeos\Cms\Exceptions\InvalidInputException( 'test' );
        } catch( \Aimeos\Cms\InvalidException $e ) {
            $this->assertSame( 'test', $e->getMessage() );
        }
    }


    /**
     * The @method tags of the facades are written by hand and must match the resource classes.
     */
    public function testDocblocksMatchResources() : void
    {
        foreach( ['Pages', 'Elements', 'Files', 'Variants'] as $name )
        {
            $expected = [];
            $resource = new \ReflectionClass( 'Aimeos\\Cms\\Resource\\' . $name );

            foreach( $resource->getMethods( \ReflectionMethod::IS_PUBLIC ) as $method )
            {
                if( !$method->isStatic() && $method->getDeclaringClass()->getName() === $resource->getName()
                    && !str_contains( (string) $method->getDocComment(), '@internal' )
                ) {
                    $expected[$method->getName()] = array_map( fn( $param ) => $param->getName(), $method->getParameters() );
                }
            }

            $actual = [];
            $doc = (string) ( new \ReflectionClass( 'Aimeos\\Cms\\Facades\\' . $name ) )->getDocComment();
            preg_match_all( '/@method static .+? (\w+)\( (.*) \)$/m', $doc, $matches, PREG_SET_ORDER );

            foreach( $matches as $match )
            {
                preg_match_all( '/\$(\w+)/', $match[2], $params );
                $actual[$match[1]] = $params[1];
            }

            ksort( $expected );
            ksort( $actual );

            $this->assertEquals( $expected, $actual, 'Facade ' . $name . ' differs from Aimeos\\Cms\\Resource\\' . $name );
        }
    }
}
