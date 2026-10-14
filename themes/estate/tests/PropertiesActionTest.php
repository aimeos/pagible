<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests;

use Aimeos\Cms\Models\Page;
use Aimeos\Cms\Actions\Properties;
use Aimeos\Cms\Models\File;
use Aimeos\Cms\Models\PageView;
use Aimeos\Cms\Navigation;
use Aimeos\Cms\Tenancy;
use Carbon\CarbonImmutable;
use Database\Seeders\EstateDemo;
use Database\Seeders\TestSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;


class PropertiesActionTest extends ThemeTestAbstract
{
    use CmsWithMigrations;
    use RefreshDatabase;

    protected $seeder = TestSeeder::class;


    public function testArticleRendersJsonLd(): void
    {
        $page = ( new PageView() )->forceFill( [
            'created_at' => CarbonImmutable::parse( '2026-08-23 12:00:00' ),
            'lang' => 'en',
            'title' => 'Article',
            'type' => 'news',
            'updated_at' => CarbonImmutable::parse( '2026-08-24 12:00:00' ),
        ] );
        $data = (object) [
            'author-name' => 'Jane Doe',
            'author-url' => 'https://example.com/authors/jane-doe',
            'files' => [
                (object) ['id' => 'image-1x1', 'type' => 'file'],
                (object) ['id' => 'image-4x3', 'type' => 'file'],
                (object) ['id' => 'image-16x9', 'type' => 'file'],
            ],
            'text' => 'Article introduction',
        ];
        $files = collect( [
            'image-1x1' => (object) [
                'id' => 'image-1x1',
                'path' => 'https://example.com/article-1x1.jpg',
                'previews' => [],
            ],
            'image-4x3' => (object) [
                'id' => 'image-4x3',
                'path' => 'https://example.com/article-4x3.jpg',
                'previews' => [],
            ],
            'image-16x9' => (object) [
                'id' => 'image-16x9',
                'path' => 'https://example.com/article-16x9.jpg',
                'previews' => [],
            ],
        ] );

        $html = view( 'estate::article', compact( 'data', 'files', 'page' ) )->render();

        $this->assertSame( 1, preg_match( '/<script type="application\/ld\+json">(.*?)<\/script>/s', $html, $matches ) );
        $json = json_decode( $matches[1], true, flags: JSON_THROW_ON_ERROR );
        $this->assertSame( 'NewsArticle', $json['@type'] );
        $this->assertSame( [
            '@type' => 'Person',
            'name' => 'Jane Doe',
            'url' => 'https://example.com/authors/jane-doe',
        ], $json['author'] );
        $this->assertSame( [
            'https://example.com/article-1x1.jpg',
            'https://example.com/article-4x3.jpg',
            'https://example.com/article-16x9.jpg',
        ], $json['image'] );
    }


    public function testDemoGroupsHiddenPagesAndUsesNewsRedirect(): void
    {
        ( new EstateDemo( 'estate', 'estate' ) )->seed();
        Tenancy::$callback = fn() => 'estate';
        app()->forgetInstance( Tenancy::class );

        $home = PageView::where( 'tag', 'root' )->firstOrFail();
        $properties = PageView::where( 'path', 'properties' )->firstOrFail();
        $exposes = PageView::where( 'path', 'exposes' )->firstOrFail();
        $news = PageView::where( 'path', 'news' )->firstOrFail();
        $items = PageView::where( 'type', 'property' )->defaultOrder()->get();

        $this->assertTrue( $properties->parent()->firstOrFail()->is( $home ) );
        $this->assertTrue( $exposes->parent()->firstOrFail()->is( $home ) );
        $this->assertSame( 2, $exposes->status );
        $this->assertSame( '/news/why-portfolio-diversification-matters', $news->to );
        $this->assertSame( [], (array) $news->content );
        $this->assertSame(
            [
                'news/why-portfolio-diversification-matters',
                'news/renovation-as-a-leverage-point',
            ],
            $news->children()->defaultOrder()->pluck( 'path' )->all()
        );
        $this->assertCount( 0, $properties->children()->get() );
        $this->assertCount( 3, $items );
        $this->assertNotContains( $exposes->id, ( new Navigation( $home, null ) )->items()->pluck( 'id' ) );

        foreach( $items as $item ) {
            $this->assertTrue( $item->parent()->firstOrFail()->is( $exposes ) );
            $this->assertStringStartsWith( 'exposes/', $item->path );
        }

        $lists = collect( [$home, $properties] )->map(
            fn( $page ) => collect( (array) $page->content )
                ->first( fn( $item ) => ( $item->type ?? null ) === 'estate::properties' )
        );
        $this->assertSame(
            [$exposes->id, $exposes->id],
            $lists->map( fn( $item ) => $item->data->{'parent-page'}->value )->all()
        );

        $list = $lists->last();
        $request = Request::create( '/properties', 'GET' );
        $request->setUserResolver( fn() => null );
        $result = ( new Properties() )( $request, $properties, $list );

        $this->assertSame( 3, $result->items->total() );
    }


    public function testPropertyListUsesNativeListSemantics(): void
    {
        ( new EstateDemo( 'estate', 'estate' ) )->seed();
        Tenancy::$callback = fn() => 'estate';
        app()->forgetInstance( Tenancy::class );

        $response = $this->get( '/properties' );

        $response->assertOk();
        $response->assertSee( '<ul class="list-items list-cards"', false );
        $response->assertSee( '<li class="property-list-item">', false );
        $response->assertDontSee( 'role="listitem"', false );
        $response->assertSee( '10 rooms' );
        $response->assertSee( 'September 1, 2026' );
        $this->assertMatchesRegularExpression( '/\b1 bathroom\b|\b\d+ bathrooms\b/', $response->getContent() );
    }


    public function testDemoPropertyEntriesRenderMaps(): void
    {
        config( ['app.name' => 'Laravel'] );

        ( new EstateDemo( 'estate', 'estate' ) )->seed();
        Tenancy::$callback = fn() => 'estate';
        app()->forgetInstance( Tenancy::class );

        $maps = PageView::where( 'type', 'property' )->defaultOrder()->get()
            ->map( fn( $page ) => collect( (array) $page->content )
                ->first( fn( $item ) => ( $item->type ?? null ) === 'estate::property' )?->data?->map
            );

        $this->assertCount( 3, $maps );
        $this->assertTrue( $maps->every( fn( $map ) =>
            is_numeric( $map?->latitude )
            && is_numeric( $map?->longitude )
            && $map?->zoom === 16
        ) );

        $response = $this->get( '/exposes/urban-penthouse-berlin' );

        $response->assertOk();
        $response->assertSee( 'class="property-location-description property-map map"', false );
        $response->assertSee( 'https://www.openstreetmap.org/export/embed.html?', false );
        $response->assertSee( 'marker=52.547914%2C13.413557', false );
        $response->assertSee( '© OpenStreetMap contributors', false );
        $response->assertDontSee( 'GeoCoordinates', false );

        preg_match_all( '/<script type="application\/ld\+json">(.*?)<\/script>/s', (string) $response->getContent(), $matches );
        $listing = collect( $matches[1] )
            ->map( fn( string $json ) => json_decode( $json, true, flags: JSON_THROW_ON_ERROR ) )
            ->first( fn( array $json ) => ( $json['@type'] ?? null ) === 'RealEstateListing' );

        $this->assertSame( 'Estate', data_get( $listing, 'offers.seller.name' ) );
    }


    public function testFiltersByRequestedTypeAndCity()
    {
        $root = PageView::where( 'tag', 'root' )->firstOrFail();
        $list = $this->addListPage( $root );
        $this->addProperty( $list, [
            'path' => 'seaside-loft',
            'title' => 'Seaside Loft',
            'property_type' => 'apartment',
            'city' => 'Berlin',
            'status' => 'available',
            'price' => 1250,
        ] );
        $this->addProperty( $list, [
            'path' => 'lake-house',
            'title' => 'Lakeside House',
            'property_type' => 'house',
            'city' => 'Paris',
            'status' => 'available',
            'price' => 780,
        ] );

        $request = Request::create( '/properties', 'GET', ['type' => 'apartment', 'city' => 'berlin'] );
        $request->setUserResolver( fn() => null );

        $result = ( new Properties() )( $request, $list, (object) [
            'data' => (object) [
                'limit' => 10,
                'order' => '-created_at',
                'parent-page' => (object) [ 'value' => $list->id ],
            ],
        ] );

        $this->assertEquals( 1, $result->items->total() );
        $this->assertEquals( 'Seaside Loft', $result->items->first()->title );
        $this->assertSame( 'apartment', $result->filters->type );
        $this->assertContains( 'apartment', array_column( $result->options->property_types, 'value' ) );
    }


    public function testFiltersByMinimumRooms()
    {
        $root = PageView::where( 'tag', 'root' )->firstOrFail();
        $list = $this->addListPage( $root );
        $this->addProperty( $list, [
            'path' => 'three-room-property',
            'title' => 'Three Room Property',
            'rooms' => 3,
        ] );
        $this->addProperty( $list, [
            'path' => 'two-room-property',
            'title' => 'Two Room Property',
            'rooms' => 2,
        ] );

        $request = Request::create( '/properties', 'GET', ['rooms_min' => 3] );
        $request->setUserResolver( fn() => null );

        $result = ( new Properties() )( $request, $list, (object) [
            'data' => (object) [
                'limit' => 10,
                'order' => '-created_at',
                'parent-page' => (object) [ 'value' => $list->id ],
            ],
        ] );

        $this->assertSame( 1, $result->items->total() );
        $this->assertSame( 'Three Room Property', $result->items->first()->title );
    }


    public function testIgnoresOutOfRangeMinimumRooms()
    {
        $root = PageView::where( 'tag', 'root' )->firstOrFail();
        $list = $this->addListPage( $root );
        $item = (object) [
            'data' => (object) [
                'limit' => 10,
                'order' => '-created_at',
                'parent-page' => (object) [ 'value' => $list->id ],
            ],
        ];

        foreach( [0, 1000] as $value ) {
            $request = Request::create( '/properties', 'GET', ['rooms_min' => $value] );
            $request->setUserResolver( fn() => null );

            $result = ( new Properties() )( $request, $list, $item );

            $this->assertNull( $result->filters->rooms_min );
        }
    }


    public function testIncludesPropertiesFromNestedCategories()
    {
        $root = PageView::where( 'tag', 'root' )->firstOrFail();
        $list = $this->addListPage( $root );
        $category = $this->addCategory( $list );
        $this->addProperty( $category, [
            'path' => 'nested-property',
            'title' => 'Nested Property',
            'property_type' => 'house',
            'price' => 980,
        ] );

        $request = Request::create( '/properties', 'GET' );
        $request->setUserResolver( fn() => null );

        $result = ( new Properties() )( $request, $list, (object) [
            'data' => (object) [
                'limit' => 10,
                'order' => '-created_at',
                'parent-page' => (object) [ 'value' => $list->id ],
            ],
        ] );

        $this->assertSame( 1, $result->items->total() );
        $this->assertSame( 'Nested Property', $result->items->first()->title );
    }


    public function testKeepsPrivateDiskForAttachedFiles()
    {
        $root = PageView::where( 'tag', 'root' )->firstOrFail();
        $list = $this->addListPage( $root );
        $file = File::firstOrFail();
        $file->forceFill( ['disk' => 'private'] )->saveQuietly();
        $this->addProperty( $list, [
            'path' => 'private-file-property',
            'title' => 'Private File Property',
            'files' => [$file->id],
        ] );
        $request = Request::create( '/properties', 'GET' );
        $request->setUserResolver( fn() => null );

        $result = ( new Properties() )( $request, $list, (object) [
            'data' => (object) [
                'limit' => 10,
                'order' => '-created_at',
                'parent-page' => (object) ['value' => $list->id],
            ],
        ] );

        $this->assertSame( 'private', $result->items->first()->files->first()->disk );
    }


    public function testIgnoresRemovedStatusFilter()
    {
        $root = PageView::where( 'tag', 'root' )->firstOrFail();
        $list = $this->addListPage( $root );
        $this->addProperty( $list, [
            'path' => 'available-property',
            'title' => 'Available Property',
            'status' => 'available',
        ] );
        $this->addProperty( $list, [
            'path' => 'sold-property',
            'title' => 'Sold Property',
            'status' => 'sold',
        ] );

        $request = Request::create( '/properties', 'GET', ['status' => 'sold'] );
        $request->setUserResolver( fn() => null );

        $result = ( new Properties() )( $request, $list, (object) [
            'data' => (object) [
                'limit' => 10,
                'order' => '-created_at',
                'parent-page' => (object) ['value' => $list->id],
            ],
        ] );

        $this->assertSame( 2, $result->items->total() );
        $this->assertFalse( property_exists( $result->filters, 'status' ) );
        $this->assertFalse( property_exists( $result->options, 'statuses' ) );
    }


    public function testIgnoresFiltersWhenDisabled()
    {
        $root = PageView::where( 'tag', 'root' )->firstOrFail();
        $list = $this->addListPage( $root );
        $this->addProperty( $list, [
            'path' => 'apartment',
            'title' => 'Apartment',
            'property_type' => 'apartment',
        ] );
        $this->addProperty( $list, [
            'path' => 'house',
            'title' => 'House',
            'property_type' => 'house',
        ] );

        $request = Request::create( '/properties', 'GET', ['type' => 'apartment'] );
        $request->setUserResolver( fn() => null );

        $result = ( new Properties() )( $request, $list, (object) [
            'data' => (object) [
                'filters' => false,
                'limit' => 10,
                'order' => '-created_at',
                'parent-page' => (object) [ 'value' => $list->id ],
            ],
        ] );

        $this->assertSame( 2, $result->items->total() );
    }


    public function testFiltersByAvailability()
    {
        $root = PageView::where( 'tag', 'root' )->firstOrFail();
        $list = $this->addListPage( $root );
        $this->addProperty( $list, [
            'path' => 'available-later',
            'title' => 'Available Later',
            'available_from' => '2026-09-01',
        ] );
        $this->addProperty( $list, [
            'path' => 'available-sooner',
            'title' => 'Available Sooner',
            'available_from' => '2026-08-01',
        ] );
        $this->addProperty( $list, [
            'path' => 'availability-unspecified',
            'title' => 'Availability Unspecified',
            'available_from' => null,
        ] );

        $request = Request::create( '/properties', 'GET', ['available_by' => '2026-08-15'] );
        $request->setUserResolver( fn() => null );
        $item = (object) ['data' => (object) [
            'limit' => 10,
            'order' => '-created_at',
            'parent-page' => (object) ['value' => $list->id],
        ]];
        $result = ( new Properties() )( $request, $list, $item );

        $this->assertSame( 1, $result->items->total() );
        $this->assertSame( 'Available Sooner', $result->items->first()->title );
    }


    public function testSortsByUpdatedDate()
    {
        $root = PageView::where( 'tag', 'root' )->firstOrFail();
        $list = $this->addListPage( $root );
        $older = $this->addProperty( $list, [
            'path' => 'older-update',
            'title' => 'Older Update',
        ] );
        $newer = $this->addProperty( $list, [
            'path' => 'newer-update',
            'title' => 'Newer Update',
        ] );

        $older->variant->forceFill( ['updated_at' => CarbonImmutable::parse( '2026-01-01' )] )->save();
        $newer->variant->forceFill( ['updated_at' => CarbonImmutable::parse( '2026-02-01' )] )->save();

        $request = Request::create( '/properties', 'GET', ['sort' => 'updated_desc'] );
        $request->setUserResolver( fn() => null );
        $result = ( new Properties() )( $request, $list, (object) [
            'data' => (object) [
                'limit' => 10,
                'order' => '-created_at',
                'parent-page' => (object) ['value' => $list->id],
            ],
        ] );

        $this->assertSame( ['Newer Update', 'Older Update'], $result->items->getCollection()->pluck( 'title' )->all() );
    }


    public function testUsesSixItemDefaultLimit()
    {
        $root = PageView::where( 'tag', 'root' )->firstOrFail();
        $list = $this->addListPage( $root );

        foreach( range( 1, 7 ) as $idx ) {
            $this->addProperty( $list, [
                'path' => 'property-' . $idx,
                'title' => 'Property ' . $idx,
            ] );
        }

        $request = Request::create( '/properties', 'GET' );
        $request->setUserResolver( fn() => null );
        $result = ( new Properties() )( $request, $list, (object) [
            'data' => (object) [
                'order' => '-created_at',
                'parent-page' => (object) ['value' => $list->id],
            ],
        ] );

        $this->assertSame( 7, $result->items->total() );
        $this->assertSame( 6, $result->items->count() );
        $this->assertSame( 6, $result->items->perPage() );
    }


    public function testStoresCurrentPaginationPageOnRequest()
    {
        $root = PageView::where( 'tag', 'root' )->firstOrFail();
        $list = $this->addListPage( $root );
        $this->addProperty( $list, [
            'path' => 'first-property',
            'title' => 'First Property',
        ] );
        $this->addProperty( $list, [
            'path' => 'second-property',
            'title' => 'Second Property',
        ] );

        $request = Request::create( '/properties', 'GET', ['p' => 2] );
        $request->setUserResolver( fn() => null );
        $result = ( new Properties() )( $request, $list, (object) [
            'data' => (object) [
                'limit' => 1,
                'order' => '-created_at',
                'parent-page' => (object) ['value' => $list->id],
            ],
        ] );

        $this->assertSame( 2, $result->items->currentPage() );
        $this->assertSame( 2, $request->attributes->get( 'cms.pagination' ) );
    }


    protected function addCategory( PageView $parent ) : PageView
    {
        $node = new Page( ['source' => 'en'] );
        $node->appendToNode( Page::findOrFail( $parent->id ) )->save();

        $page = $node->variants()->create( [
            'lang' => 'en',
            'name' => 'Residential',
            'title' => 'Residential',
            'path' => 'residential-' . mt_rand( 1000, 9999 ),
            'tag' => 'category',
            'type' => 'page',
            'status' => 1,
            'editor' => 'seeder',
        ] )->view;

        return $page;
    }


    protected function addListPage( PageView $root ) : PageView
    {
        $node = new Page( ['source' => 'en'] );
        $node->appendToNode( Page::findOrFail( $root->id ) )->save();

        $page = $node->variants()->create( [
            'lang' => 'en',
            'name' => 'Properties',
            'title' => 'Properties',
            'path' => 'properties-' . mt_rand( 1000, 9999 ),
            'tag' => 'properties',
            'type' => 'properties',
            'status' => 1,
            'editor' => 'seeder',
        ] )->view;

        return $page;
    }


    protected function addProperty( PageView $parent, array $data ) : PageView
    {
        $property = array_merge( [
            'lang' => 'en',
            'name' => $data['title'] ?? 'Property',
            'title' => $data['title'] ?? 'Property',
            'type' => 'property',
            'tag' => 'property',
            'status' => 1,
            'editor' => 'seeder',
            'content' => [
                [
                    'id' => (string) mt_rand( 100000, 999999 ),
                    'type' => 'estate::property',
                    'group' => 'main',
                    'data' => array_diff_key( $data, ['path' => true, 'title' => true] ),
                ],
            ],
        ], [
            'path' => $data['path'],
        ] );

        $node = new Page( ['source' => 'en'] );
        $node->appendToNode( Page::findOrFail( $parent->id ) )->save();

        return $node->variants()->create( $property )->view;
    }


    protected function getPackageProviders( $app )
    {
        return array_merge( parent::getPackageProviders( $app ), [
            'Aimeos\Cms\EstateServiceProvider',
        ] );
    }
}
