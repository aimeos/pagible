<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests;


class TranslationsTest extends ThemeTestAbstract
{
    use ChecksTranslations;


    public function testTranslations() : void
    {
        $dir = dirname( __DIR__ );

        $this->assertTranslations( $dir . '/lang', [$dir . '/views', $dir . '/src'] );
    }
}
