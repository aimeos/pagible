<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests;

use Aimeos\Cms\Plugin;
use PHPUnit\Framework\Attributes\DataProvider;


class PluginTest extends AdminTestAbstract
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->reset();
    }


    protected function tearDown(): void
    {
        $this->reset();

        parent::tearDown();
    }


    public function testAllEmpty()
    {
        $this->assertSame( ['i18n' => [], 'panels' => [], 'subpanels' => []], Plugin::all() );
    }


    public function testI18n()
    {
        Plugin::i18n( 'commerce', '/vendor/cms/commerce/i18n/{locale}.json' );

        $this->assertSame(
            ['commerce' => '/vendor/cms/commerce/i18n/{locale}.json'],
            Plugin::all()['i18n'],
        );
    }


    public function testI18nConflictingDefinition()
    {
        Plugin::i18n( 'commerce', '/vendor/cms/commerce/i18n/{locale}.json' );

        $this->expectException( \LogicException::class );

        Plugin::i18n( 'commerce', '/vendor/cms/other/i18n/{locale}.json' );
    }


    public function testI18nDuplicateDefinition()
    {
        Plugin::i18n( 'commerce', '/vendor/cms/commerce/i18n/{locale}.json' );
        Plugin::i18n( 'commerce', '/vendor/cms/commerce/i18n/{locale}.json' );

        $this->assertCount( 1, Plugin::all()['i18n'] );
    }


    #[DataProvider( 'invalidI18nDefinitions' )]
    public function testI18nInvalidDefinition( string $key, string $url )
    {
        $this->expectException( \InvalidArgumentException::class );

        Plugin::i18n( $key, $url );
    }


    public static function invalidI18nDefinitions() : iterable
    {
        yield 'invalid key' => ['Commerce!', '/vendor/cms/commerce/i18n/{locale}.json'];
        yield 'missing locale' => ['commerce', '/vendor/cms/commerce/i18n/de.json'];
        yield 'multiple locales' => ['commerce', '/vendor/{locale}/commerce/{locale}.json'];
        yield 'protocol relative' => ['commerce', '//evil.example/i18n/{locale}.json'];
        yield 'traversal' => ['commerce', '/vendor/%2e%2e/i18n/{locale}.json'];
    }


    public function testRegisterPanel()
    {
        Plugin::register( 'products', [
            'label' => 'Products',
            'icon' => '<svg></svg>',
            'permission' => 'product:view',
            'component' => '/vendor/cms/extensions/commerce/product.js',
        ] );

        $all = Plugin::all();

        $this->assertSame( [
            'label' => 'Products',
            'permission' => 'product:view',
            'component' => '/vendor/cms/extensions/commerce/product.js',
            'icon' => '<svg></svg>',
        ], $all['panels']['products'] );
        $this->assertSame( [], $all['subpanels'] );
    }


    public function testRegisterPanelWithoutIcon()
    {
        Plugin::register( 'products', [
            'label' => 'Products',
            'permission' => 'product:view',
            'component' => '/vendor/cms/extensions/commerce/product.js',
        ] );

        $this->assertArrayNotHasKey( 'icon', Plugin::all()['panels']['products'] );
    }


    public function testRegisterPanelWithI18n()
    {
        Plugin::i18n( 'commerce', '/vendor/cms/commerce/i18n/{locale}.json' );
        Plugin::register( 'products', [
            'label' => 'Products',
            'i18n' => 'commerce',
            'permission' => 'product:view',
            'component' => '/vendor/cms/extensions/commerce/product.js',
        ] );

        $this->assertSame( 'commerce', Plugin::all()['panels']['products']['i18n'] );
    }


    public function testRegisterPanelWithUnknownI18n()
    {
        $this->expectException( \InvalidArgumentException::class );

        Plugin::register( 'products', [
            'label' => 'Products',
            'i18n' => 'commerce',
            'permission' => 'product:view',
            'component' => '/vendor/cms/extensions/commerce/product.js',
        ] );
    }


    public function testRegisterSubpanel()
    {
        Plugin::register( 'page:settings', [
            'label' => 'Settings',
            'component' => '/vendor/cms/extensions/commerce/pageSettings.js',
        ] );

        $all = Plugin::all();

        $this->assertSame( [
            'label' => 'Settings',
            'component' => '/vendor/cms/extensions/commerce/pageSettings.js',
        ], $all['subpanels']['page']['settings'] );
        $this->assertSame( [], $all['panels'] );
    }


    public function testRegisterSubpanelWithI18n()
    {
        Plugin::i18n( 'commerce', '/vendor/cms/commerce/i18n/{locale}.json' );
        Plugin::register( 'page:settings', [
            'label' => 'Settings',
            'i18n' => 'commerce',
            'component' => '/vendor/cms/extensions/commerce/pageSettings.js',
        ] );

        $this->assertSame( 'commerce', Plugin::all()['subpanels']['page']['settings']['i18n'] );
    }


    public function testRegisterOrder()
    {
        Plugin::register( 'products', [
            'label' => 'Products', 'permission' => 'product:view', 'component' => '/a.js',
        ] );
        Plugin::register( 'orders', [
            'label' => 'Orders', 'permission' => 'order:view', 'component' => '/b.js',
        ] );

        $this->assertSame( ['products', 'orders'], array_keys( Plugin::all()['panels'] ) );
    }


    public function testRegisterDuplicatePanel()
    {
        Plugin::register( 'products', [
            'label' => 'Products', 'permission' => 'product:view', 'component' => '/a.js',
        ] );

        $this->expectException( \LogicException::class );

        Plugin::register( 'products', [
            'label' => 'Products', 'permission' => 'product:view', 'component' => '/b.js',
        ] );
    }


    public function testRegisterIdenticalDefinitionsAgain()
    {
        $panel = ['label' => 'Products', 'permission' => 'product:view', 'component' => '/a.js'];
        $subpanel = ['label' => 'Settings', 'component' => '/b.js'];

        Plugin::register( 'products', $panel );
        Plugin::register( 'products', $panel );
        Plugin::register( 'page:settings', $subpanel );
        Plugin::register( 'page:settings', $subpanel );

        $this->assertSame( $panel, Plugin::all()['panels']['products'] );
        $this->assertSame( $subpanel, Plugin::all()['subpanels']['page']['settings'] );
    }


    public function testRegisterDuplicateSubpanel()
    {
        Plugin::register( 'page:settings', [
            'label' => 'Settings', 'component' => '/a.js',
        ] );

        $this->expectException( \LogicException::class );

        Plugin::register( 'page:settings', [
            'label' => 'Settings', 'component' => '/b.js',
        ] );
    }


    public function testRegisterMissingLabel()
    {
        $this->expectException( \InvalidArgumentException::class );

        Plugin::register( 'products', [
            'permission' => 'product:view', 'component' => '/a.js',
        ] );
    }


    public function testRegisterMissingComponent()
    {
        $this->expectException( \InvalidArgumentException::class );

        Plugin::register( 'products', [
            'label' => 'Products', 'permission' => 'product:view',
        ] );
    }


    public function testRegisterMissingPermission()
    {
        $this->expectException( \InvalidArgumentException::class );

        Plugin::register( 'products', [
            'label' => 'Products', 'component' => '/a.js',
        ] );
    }


    public function testRegisterInvalidKey()
    {
        $this->expectException( \InvalidArgumentException::class );

        Plugin::register( 'Products!', [
            'label' => 'Products', 'permission' => 'product:view', 'component' => '/a.js',
        ] );
    }


    public function testRegisterInvalidHost()
    {
        $this->expectException( \InvalidArgumentException::class );

        Plugin::register( 'user:settings', [
            'label' => 'Settings', 'component' => '/a.js',
        ] );
    }


    public function testRegisterProtocolRelativeUrl()
    {
        $this->expectException( \InvalidArgumentException::class );

        Plugin::register( 'products', [
            'label' => 'Products', 'permission' => 'product:view', 'component' => '//evil.example/a.js',
        ] );
    }


    public function testRegisterTraversalUrl()
    {
        $this->expectException( \InvalidArgumentException::class );

        Plugin::register( 'products', [
            'label' => 'Products', 'permission' => 'product:view', 'component' => '/vendor/../../etc/a.js',
        ] );
    }


    public function testRegisterRelativeUrl()
    {
        $this->expectException( \InvalidArgumentException::class );

        Plugin::register( 'products', [
            'label' => 'Products', 'permission' => 'product:view', 'component' => 'product.js',
        ] );
    }


    /**
     * Resets the private static registry between tests.
     */
    protected function reset(): void
    {
        $class = new \ReflectionClass( Plugin::class );

        foreach( ['i18n', 'panels', 'subpanels'] as $name ) {
            $prop = $class->getProperty( $name );
            $prop->setAccessible( true );
            $prop->setValue( null, [] );
        }
    }
}
