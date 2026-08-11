<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests;

use Aimeos\Cms\Models\Page;
use Database\Seeders\TestSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;


class SecurityControllerTest extends ThemeTestAbstract
{
    use CmsWithMigrations;
    use RefreshDatabase;

    protected string $seeder = TestSeeder::class;


    public function testIndex() : void
    {
        $this->security( [
            'contact' => 'https://example.com/security',
            'email' => 'security@example.com',
            'expires' => '2027-08-11T00:00:00.000Z',
            'encryption' => 'https://example.com/pgp-key.txt',
            'acknowledgments' => 'https://example.com/hall-of-fame',
            'preferred-languages' => 'en,de-DE',
            'canonical' => 'https://example.com/.well-known/security.txt',
            'policy' => 'https://example.com/security-policy',
            'hiring' => 'https://example.com/jobs',
            'csaf' => 'https://example.com/.well-known/csaf/provider-metadata.json',
        ] );

        $response = $this->get( '/security.txt' );

        $response->assertOk();
        $response->assertHeader( 'Content-Type', 'text/plain; charset=utf-8' );
        $this->assertTrue( $response->headers->hasCacheControlDirective( 'public' ) );
        $this->assertSame( '300', $response->headers->getCacheControlDirective( 'max-age' ) );
        $this->assertSame( implode( "\n", [
            'Contact: https://example.com/security',
            'Contact: mailto:security@example.com',
            'Expires: 2027-08-11T23:59:59Z',
            'Encryption: https://example.com/pgp-key.txt',
            'Acknowledgments: https://example.com/hall-of-fame',
            'Preferred-Languages: en, de-DE',
            'Canonical: https://example.com/.well-known/security.txt',
            'Policy: https://example.com/security-policy',
            'Hiring: https://example.com/jobs',
            'CSAF: https://example.com/.well-known/csaf/provider-metadata.json',
            '',
        ] ), $response->getContent() );
    }


    public function testIndexIsNotAvailableWithoutConfig() : void
    {
        $this->get( '/security.txt' )->assertNotFound();
    }


    public function testIndexRequiresValidContactAndExpiration() : void
    {
        $this->security( [
            'contact' => 'https:missing-slashes',
            'expires' => 'tomorrow',
        ] );

        $this->get( '/security.txt' )->assertNotFound();
    }


    public function testIndexAllowsOptionalEmailToBeOmitted() : void
    {
        $this->security( [
            'contact' => 'https://example.com/security',
            'expires' => '2027-08-11',
        ] );

        $response = $this->get( '/security.txt' );

        $response->assertOk();
        $response->assertSeeText( 'Contact: https://example.com/security' );
        $response->assertDontSeeText( 'mailto:' );
        $response->assertSeeText( 'Expires: 2027-08-11T23:59:59Z' );
    }


    public function testRouteDoesNotStartWebSession() : void
    {
        $route = app( 'router' )->getRoutes()->getByName( 'cms.security' );

        $this->assertNotNull( $route );
        $this->assertNotContains( 'web', $route->gatherMiddleware() );
    }


    /**
     * Stores published root-page security information.
     *
     * @param array<string, string> $data Security config values
     */
    protected function security( array $data ) : void
    {
        Page::where( 'tag', 'root' )->firstOrFail()->forceFill( ['config' => [
            'security' => ['type' => 'security', 'data' => $data, 'files' => []],
        ]] )->saveQuietly();
    }
}
