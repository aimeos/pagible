<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests;

use Aimeos\Cms\Translation\Translator;
use Aimeos\Cms\Tenancy;
use Aimeos\Prisma\Exceptions\RateLimitException;
use Aimeos\Prisma\Prisma;
use Aimeos\Prisma\Responses\TextResponse;
use Illuminate\Support\Facades\RateLimiter;


class TranslatorTest extends TranslateTestAbstract
{
    public function testTranslateEscapedChunks()
    {
        $texts = [str_repeat( '&', 20000 ), str_repeat( '&', 20000 )];
        $fake = Prisma::fake( [TextResponse::fromTexts( ['a'] ), TextResponse::fromTexts( ['b'] )] );

        // escaped texts are five times longer, so DeepL needs one request per text
        $this->assertSame( ['a', 'b'], Translator::translate( $texts, 'de', 'en' ) );
        $this->assertCount( 2, $fake->calls() );
    }


    public function testChunks()
    {
        $chunks = Translator::chunks( array_fill( 0, 120, 'text' ) );

        $this->assertSame( [50, 50, 20], array_map( 'count', $chunks ) );
        $this->assertSame( [2, 1], array_map( 'count', Translator::chunks( ['aa', 'bb', 'cc'], 50, 4 ) ) );
        $this->assertSame( [1, 1], array_map( 'count', Translator::chunks( ['aaaaa', 'b'], 50, 4 ) ) );
        $this->assertSame( [], Translator::chunks( [] ) );
    }


    public function testTranslateChunked()
    {
        $texts = array_map( fn( $i ) => 'text ' . $i, range( 1, 120 ) );
        $fake = Prisma::fake( array_map( fn( $chunk ) => TextResponse::fromTexts( array_map( 'strtoupper', $chunk ) ), Translator::chunks( $texts ) ) );

        $result = Translator::translate( $texts, 'de', 'en' );

        $this->assertCount( 3, $fake->calls() );
        $this->assertSame( array_map( 'strtoupper', $texts ), $result );
    }


    public function testTranslateEscaped()
    {
        $fake = Prisma::fake( [TextResponse::fromTexts( ['Rollen &amp; Rechte &lt;b&gt;', '<x>00:01 --&gt; 00:02</x> Hallo'] )] );

        $result = Translator::translate( ['Roles & permissions <b>', '<x>00:01 --> 00:02</x> Hello'], 'de', 'en', 'Page: AI & CMS' );

        $this->assertSame( ['Roles &amp; permissions &lt;b&gt;', '<x>00:01 --&gt; 00:02</x> Hello'], $fake->calls()[0]['arguments'][0] );
        $this->assertSame( 'Page: AI &amp; CMS', $fake->calls()[0]['arguments'][3] );
        $this->assertSame( 'xml', $fake->calls()[0]['arguments'][4]['tag_handling'] );
        $this->assertSame( ['Rollen & Rechte <b>', '<x>00:01 --> 00:02</x> Hallo'], $result );
    }


    public function testTranslateNotEscaped()
    {
        config( ['cms.ai.translate.provider' => 'google'] );
        $fake = Prisma::fake( [TextResponse::fromTexts( ['Rollen & Rechte'] )] );

        $result = Translator::translate( ['Roles & permissions'], 'de', 'en' );

        $this->assertSame( ['Roles & permissions'], $fake->calls()[0]['arguments'][0] );
        $this->assertArrayNotHasKey( 'tag_handling', $fake->calls()[0]['arguments'][4] );
        $this->assertSame( ['Rollen & Rechte'], $result );
    }


    public function testTranslateLimited()
    {
        $texts = array_map( fn( $i ) => 'retry ' . $i, range( 1, 120 ) );
        $responses = array_map( fn( $chunk ) => TextResponse::fromTexts( array_map( 'strtoupper', $chunk ) ), Translator::chunks( $texts ) );
        $key = 'cms-translate:' . sha1( (string) Tenancy::value() );

        $fake = Prisma::fake( [$responses[0], new RateLimitException( 'Too many requests' )] );

        try {
            Translator::translate( $texts, 'de', 'en', null, true );
            $this->fail( 'Rate limit error not thrown' );
        } catch( RateLimitException $e ) {
            $this->assertCount( 2, $fake->calls() );
            $this->assertSame( 3, RateLimiter::attempts( $key ) );
        }

        // the retry sends all chunks again
        $fake = Prisma::fake( $responses );
        $result = Translator::translate( $texts, 'de', 'en', null, true );

        $this->assertSame( array_map( 'strtoupper', $texts ), $result );
        $this->assertCount( 3, $fake->calls() );
        $this->assertSame( 6, RateLimiter::attempts( $key ) );
    }


    public function testTranslator()
    {
        $this->assertInstanceOf( \Closure::class, Translator::for( $this->user ) );
        $this->assertNull( Translator::for( $this->user( ['page:save', 'page:add'] ) ) );
        $this->assertNull( Translator::for( null ) );
        $this->assertInstanceOf( \Closure::class, \Aimeos\Cms\Permission::system( fn() => Translator::for( null ) ) );

        config( ['cms.ai.translate.provider' => null] );
        $this->assertNull( Translator::for( $this->user ) );
        config( ['cms.ai.translate.provider' => 'deepl'] );
    }
}
