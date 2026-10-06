<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests;

class MessageSelectorTest extends CoreTestAbstract
{
    public function testTranslatorUsesSelector()
    {
        $this->assertInstanceOf( \Aimeos\Cms\MessageSelector::class, app( 'translator' )->getSelector() );
    }


    public function testHyphenatedLocales()
    {
        $selector = new \Aimeos\Cms\MessageSelector();

        $this->assertEquals( 'items', $selector->choose( 'item|items', 2, 'pt-BR' ) );
        $this->assertEquals( 'item', $selector->choose( 'item|items', 1, 'pt-BR' ) );
        $this->assertEquals( 'few', $selector->choose( 'one|few|many', 3, 'sr-Latn' ) );
        $this->assertEquals( 'one', $selector->choose( 'one|other', 2, 'zh-TW' ) );
        $this->assertEquals( 'other', $selector->choose( 'one|other', 2, 'es-419' ) );
    }


    public function testUnderscoreLocales()
    {
        $selector = new \Aimeos\Cms\MessageSelector();

        $this->assertEquals( 'items', $selector->choose( 'item|items', 2, 'pt_BR' ) );
        $this->assertEquals( 'many', $selector->choose( 'one|few|many', 5, 'ru' ) );
    }
}
