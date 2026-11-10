<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests;

use Aimeos\Cms\AiClient;
use Aimeos\Cms\Tools as CmsTools;
use Illuminate\Support\Facades\Log;


class AiClientTest extends AiTestAbstract
{
    public function testDuration()
    {
        config( ['cms.ai.timeout' => 300, 'cms.ai.maxtime' => 900] );

        $this->assertSame( 300, AiClient::duration() );
        $this->assertSame( 900, AiClient::duration( 25 ) );

        config( ['cms.ai.maxtime' => 0] );
        $this->assertSame( 7500, AiClient::duration( 25 ) );

        config( ['cms.ai.timeout' => 0, 'cms.ai.maxtime' => 900] );
        $this->assertSame( 900, AiClient::duration( 25 ) );
    }


    public function testPrompt()
    {
        $prisma = AiClient::prompt( AiClient::WRITE, 'system', [CmsTools\GetPage::class, 'web_search'], 1, \Aimeos\Cms\Toolset::DISCOVERY, 'chat' );

        $this->assertEquals( ['get-locales', 'get-schemas', 'get-page'], $this->names( $prisma, 'tools' ) );
        $this->assertEquals( ['web_search'], $this->names( $prisma, 'providerTools' ) );
    }


    public function testPromptOnce()
    {
        $prisma = AiClient::prompt( AiClient::WRITE, 'system', [], 1, \Aimeos\Cms\Toolset::DISCOVERY, 'chat' );

        $this->assertEquals( ['get-locales', 'get-schemas'], $this->names( $prisma, 'tools' ) );
        $this->assertEquals( [], $this->names( $prisma, 'providerTools' ) );
    }


    public function testCheckInputInvalid()
    {
        $this->expectException( \Aimeos\Cms\Exception::class );
        $this->expectExceptionMessage( "Content can't be encoded" );

        AiClient::checkInput( "\xB1" );
    }


    public function testFailed()
    {
        Log::spy();

        // only CMS and validation errors are sent to the model, other errors are logged
        $this->assertEquals( 'Error: Page not found', \Aimeos\Cms\AiError::tool( new \Aimeos\Cms\Exception( 'Page not found' ) ) );
        $this->assertEquals( 'Error: The id field is required.', \Aimeos\Cms\AiError::tool( \Illuminate\Validation\ValidationException::withMessages( ['id' => 'The id field is required.'] ) ) );
        $this->assertEquals( 'Error: The tool failed', \Aimeos\Cms\AiError::tool( new \RuntimeException( 'SQLSTATE[HY000] internal' ) ) );

        Log::shouldHaveReceived( 'error' )->once()->with( 'AI tool error', \Mockery::on( fn( $ctx ) => $ctx['message'] === 'SQLSTATE[HY000] internal' ) );
    }


    public function testFileRequiresViewPermission()
    {
        $this->expectException( \Aimeos\Cms\Exceptions\PermissionException::class );
        \Aimeos\Cms\Media::load( ['00000000-0000-0000-0000-000000000000'], null );
    }


    public function testLifted()
    {
        config( ['cms.ai.timeout' => 100] );
        set_time_limit( 30 );

        $nested = AiClient::lifted( fn() => AiClient::lifted( fn() => ini_get( 'max_execution_time' ) ) );

        $this->assertEquals( '100', $nested );
        $this->assertEquals( '30', ini_get( 'max_execution_time' ) );
        $this->assertFalse( app()->bound( 'cms.ai.lifted' ) );
        set_time_limit( 0 );
    }


    public function testRedact()
    {
        $text = 'key sk-abcdefghijklmnopqrstuvwx, Authorization: Bearer abc.def-123 and https://x.test/?key=AIzaSyA1234567890abcdefghij&q=1';
        $result = \Aimeos\Cms\AiError::redact( $text );

        $this->assertStringNotContainsString( 'sk-abcdefghijklmnopqrstuvwx', $result );
        $this->assertStringNotContainsString( 'abc.def-123', $result );
        $this->assertStringNotContainsString( 'AIzaSyA1234567890abcdefghij', $result );
        $this->assertStringContainsString( 'q=1', $result );
    }


    public function testSystem()
    {
        $this->assertEquals( "instructions\ncontext", AiClient::system( "instructions\n", null, '', ' context ' ) );
    }


    public function testTools()
    {
        $tools = AiClient::tools( [CmsTools\GetPage::class, 'Aimeos\\Cms\\Tools\\Unknown', 'web_fetch'] );

        $this->assertEquals( ['get-page', 'web_fetch'], array_map( fn( $tool ) => $tool->name(), $tools ) );
    }


    public function testTranscribeEmpty()
    {
        \Aimeos\Prisma\Prisma::fake( [\Aimeos\Prisma\Responses\TextResponse::fromText( '' )->withStructured( [] )] );

        $this->expectException( \Aimeos\Cms\Exception::class );
        \Aimeos\Cms\Ai::transcribe( \Aimeos\Prisma\Files\Audio::fromBinary( 'x', 'audio/mpeg' ) );
    }


    public function testRefineLarge()
    {
        $refine = function( string $text ) {
            $fake = \Aimeos\Prisma\Prisma::fake( [\Aimeos\Prisma\Responses\TextResponse::fromText( '' )->withStructured( [
                'contents' => [['id' => 'content-1', 'type' => 'text', 'group' => 'main', 'data' => ['text' => 'Refined']]]
            ] )] );

            $content = [['id' => 'content-1', 'type' => 'text', 'group' => 'main', 'data' => ['text' => $text]]];
            $this->assertSame( 'Refined', \Aimeos\Cms\Refiner::content( 'Shorten the text', $content )[0]['data']['text'] ?? null );

            return ( new \ReflectionMethod( $fake, 'tools' ) )->invoke( $fake );
        };

        $this->assertNotEmpty( $refine( 'Short text' ) );
        // large content is sent once without tools
        $this->assertSame( [], $refine( str_repeat( 'x', 300000 ) ) );
    }


    public function testTranscribeInvalid()
    {
        \Aimeos\Prisma\Prisma::fake( [\Aimeos\Prisma\Responses\TextResponse::fromText( '' )->withStructured( [['start' => 'x']] )] );

        $this->expectException( \Aimeos\Cms\Exception::class );
        \Aimeos\Cms\Ai::transcribe( \Aimeos\Prisma\Files\Audio::fromBinary( 'x', 'audio/mpeg' ) );
    }


    public function testUncropRequiresSide()
    {
        $this->expectException( \Aimeos\Cms\Exception::class );
        \Aimeos\Cms\Ai::uncrop( \Aimeos\Prisma\Files\Image::fromBinary( 'x', 'image/png' ), 0, 0, 0, 0 );
    }


    public function testUncropTooLarge()
    {
        config( ['cms.upload.maxpixels' => 100] );
        $image = \Aimeos\Prisma\Files\Image::fromBinary( $this->pngBinary(), 'image/png' );

        $this->expectExceptionMessage( 'The edited image would exceed the maximum size of 100 pixels' );
        \Aimeos\Cms\Ai::uncrop( $image, 1, 2, 2, 1 );
    }


    public function testUpscaleInvalid()
    {
        $this->expectExceptionMessage( 'Invalid image' );
        \Aimeos\Cms\Ai::upscale( \Aimeos\Prisma\Files\Image::fromBinary( 'x', 'image/png' ), 2 );
    }


    public function testUpscaleTooLarge()
    {
        config( ['cms.upload.maxpixels' => 100] );
        $image = \Aimeos\Prisma\Files\Image::fromBinary( $this->pngBinary(), 'image/png' );

        $this->expectExceptionMessage( 'The edited image would exceed the maximum size of 100 pixels' );
        \Aimeos\Cms\Ai::upscale( $image, 2 );
    }


    /**
     * @return array<int, string>
     */
    protected function names( object $prisma, string $method ) : array
    {
        $provider = ( new \ReflectionProperty( $prisma, 'provider' ) )->getValue( $prisma );
        $tools = ( new \ReflectionMethod( $provider, $method ) )->invoke( $provider );
        return array_map( fn( $tool ) => $tool->name(), $tools );
    }
}
