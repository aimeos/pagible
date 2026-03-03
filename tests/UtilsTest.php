<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Tests;

use Aimeos\Cms\Utils;
use PHPUnit\Framework\Attributes\Group;


class UtilsTest extends TestAbstract
{
    public function testIsValidUrlNull()
    {
        $this->assertTrue( Utils::isValidUrl( null ) );
        $this->assertTrue( Utils::isValidUrl( '' ) );
    }


    public function testIsValidUrlLength()
    {
        $this->assertTrue( Utils::isValidUrl( 'https://example.com/' . str_repeat( 'a', 2028 ), false ) );
        $this->assertFalse( Utils::isValidUrl( 'https://example.com/' . str_repeat( 'a', 2029 ) ) );
    }


    public function testIsValidUrlControlChars()
    {
        $this->assertFalse( Utils::isValidUrl( "/file\x00name" ) );
        $this->assertFalse( Utils::isValidUrl( "/path\x1fname" ) );
        $this->assertFalse( Utils::isValidUrl( "/path\x7fname" ) );
    }


    public function testIsValidUrlProtocolRelative()
    {
        $this->assertFalse( Utils::isValidUrl( '//evil.com' ) );
        $this->assertFalse( Utils::isValidUrl( '//evil.com/path' ) );
    }


    public function testIsValidUrlPathTraversal()
    {
        $this->assertFalse( Utils::isValidUrl( '/path/../etc/passwd' ) );
        $this->assertFalse( Utils::isValidUrl( '../secret' ) );
        $this->assertFalse( Utils::isValidUrl( 'https://example.com/a/../b' ) );
    }


    public function testIsValidUrlScheme()
    {
        $this->assertFalse( Utils::isValidUrl( 'javascript:alert(1)' ) );
        $this->assertFalse( Utils::isValidUrl( 'javascript:void(0)' ) );

        $this->assertFalse( Utils::isValidUrl( 'data:text/html,<h1>hi</h1>' ) );
        $this->assertFalse( Utils::isValidUrl( 'data:image/png;base64,abc' ) );

        $this->assertFalse( Utils::isValidUrl( 'ftp://example.com' ) );
        $this->assertFalse( Utils::isValidUrl( 'file:///etc/passwd' ) );
        $this->assertFalse( Utils::isValidUrl( 'mailto:user@example.com' ) );
    }


    public function testIsValidUrlRelativePath()
    {
        $this->assertFalse( Utils::isValidUrl( 'relative/path' ) );
        $this->assertTrue( Utils::isValidUrl( 'relative/path', false ) );

        $this->assertFalse( Utils::isValidUrl( './images/photo.jpg' ) );
        $this->assertTrue( Utils::isValidUrl( './images/photo.jpg', false ) );

        $this->assertFalse( Utils::isValidUrl( 'page?q=search#section' ) );
        $this->assertTrue( Utils::isValidUrl( 'page?q=search#section', false ) );
    }


    public function testIsValidUrlAbsolutePath()
    {
        $this->assertFalse( Utils::isValidUrl( '/absolute/path' ) );
        $this->assertTrue( Utils::isValidUrl( '/absolute/path', false ) );

        $this->assertFalse( Utils::isValidUrl( '/path/to/page?foo=bar&baz=1#top' ) );
        $this->assertTrue( Utils::isValidUrl( '/path/to/page?foo=bar&baz=1#top', false ) );
    }


    public function testIsValidUrlInvalidHost()
    {
        $this->assertFalse( Utils::isValidUrl( 'http://', false ) );
        $this->assertFalse( Utils::isValidUrl( 'https://', false ) );

        $this->assertFalse( Utils::isValidUrl( 'http://invalid host with spaces', false ) );
        $this->assertFalse( Utils::isValidUrl( 'https://exam ple.com', false ) );
    }


    public function testIsValidUrlHttpNonStrict()
    {
        $this->assertTrue( Utils::isValidUrl( 'http://example.com', false ) );
        $this->assertTrue( Utils::isValidUrl( 'http://example.com/path', false ) );

        $this->assertTrue( Utils::isValidUrl( 'https://example.com', false ) );
        $this->assertTrue( Utils::isValidUrl( 'https://example.com/path?q=1#section', false ) );
    }


    public function testIsValidUrlStrict()
    {
        $this->assertFalse( Utils::isValidUrl( '/absolute/path', true ) );
        $this->assertFalse( Utils::isValidUrl( 'relative/path', true ) );

        $this->assertFalse( Utils::isValidUrl( 'javascript:alert(1)', true ) );
        $this->assertFalse( Utils::isValidUrl( '//evil.com', true ) );
        $this->assertFalse( Utils::isValidUrl( 'ftp://example.com', true ) );

        $this->assertFalse( Utils::isValidUrl( 'http://', true ) );
        $this->assertFalse( Utils::isValidUrl( 'https://', true ) );
        $this->assertFalse( Utils::isValidUrl( 'http://invalid host!', true ) );
    }


    #[Group('network')]
    public function testIsValidUrlStrictPublicDomain()
    {
        // example.com is maintained by IANA and always resolves to a public IP
        $this->assertTrue( Utils::isValidUrl( 'https://example.com', true ) );
        $this->assertTrue( Utils::isValidUrl( 'https://example.com/path?q=1', true ) );

        // .invalid TLD is guaranteed by RFC 2606 to never resolve
        $this->assertFalse( Utils::isValidUrl( 'https://nonexistent.invalid', true ) );
    }


    public function testHtmlStripsScript()
    {
        $result = Utils::html( '<p>Hello</p><script>alert(1)</script>' );

        $this->assertStringContainsString( '<p>Hello</p>', $result );
        $this->assertStringNotContainsString( '<script>', $result );
    }


    public function testHtmlStripsJavascriptHref()
    {
        $result = Utils::html( '<a href="javascript:void(0)">Link</a>' );

        $this->assertStringNotContainsString( 'javascript:', $result );
    }


    public function testHtmlPreservesAllowedTags()
    {
        $result = Utils::html( '<strong>Bold</strong> and <em>italic</em>' );

        $this->assertStringContainsString( '<strong>Bold</strong>', $result );
        $this->assertStringContainsString( '<em>italic</em>', $result );
    }


    public function testHtmlAllowsTargetBlank()
    {
        $result = Utils::html( '<a href="https://example.com" target="_blank">Link</a>' );

        $this->assertStringContainsString( 'target="_blank"', $result );
    }


    public function testHtmlNull()
    {
        $this->assertSame( '', Utils::html( null ) );
    }


    public function testSlugify()
    {
        $this->assertEquals( 'hello-world', Utils::slugify( 'Hello World' ) );
        $this->assertEquals( 'hello-world', Utils::slugify( 'HELLO WORLD' ) );
    }


    public function testSlugifySpecialChars()
    {
        $this->assertEquals( 'foo-bar', Utils::slugify( 'foo?bar' ) );
        $this->assertEquals( 'foo-bar', Utils::slugify( 'foo_bar' ) );
        $this->assertEquals( 'foo-bar', Utils::slugify( 'foo..bar' ) );
    }


    public function testSlugifyTrimsHyphens()
    {
        $this->assertEquals( 'test', Utils::slugify( '-test-' ) );
        $this->assertEquals( 'hello-world', Utils::slugify( '--Hello World--' ) );
    }


    public function testSlugifyUnicode()
    {
        $this->assertEquals( 'ällö', Utils::slugify( 'Ällö' ) );
    }


    public function testUidFormat()
    {
        $id = Utils::uid();

        $this->assertEquals( 6, strlen( $id ) );
        $this->assertMatchesRegularExpression( '/^[A-Za-z][A-Za-z0-9\-_]{5}$/', $id );
    }


    public function testUidUnique()
    {
        $ids = array_map( fn() => Utils::uid(), range( 1, 100 ) );

        $this->assertCount( 100, array_unique( $ids ) );
    }
}
