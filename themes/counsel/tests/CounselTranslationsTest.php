<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests;


class CounselTranslationsTest extends ThemeTestAbstract
{
    use ChecksTranslations;


    public function testTranslations() : void
    {
        $dir = dirname( __DIR__ );
        $shared = array_keys( (array) json_decode( (string) file_get_contents( dirname( $dir, 2 ) . '/theme/lang/en.json' ), true ) );

        $this->assertTranslations( $dir . '/lang', [$dir . '/views', $dir . '/src'], $shared );
    }
}
