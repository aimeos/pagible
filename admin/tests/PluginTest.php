<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests;

use Aimeos\Cms\Plugin;


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
        $this->assertSame( ['panels' => [], 'subpanels' => []], Plugin::all() );
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

        foreach( ['panels', 'subpanels'] as $name ) {
            $prop = $class->getProperty( $name );
            $prop->setAccessible( true );
            $prop->setValue( null, [] );
        }
    }
}
