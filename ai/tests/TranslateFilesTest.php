<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests;

use Aimeos\Cms\Models\File;
use Aimeos\Cms\Tenancy;
use Aimeos\Prisma\Prisma;
use Aimeos\Prisma\Responses\TextResponse;
use Illuminate\Support\Facades\RateLimiter;


class TranslateFilesTest extends TranslateTestAbstract
{
    public function testGraphqlTranslateFiles()
    {
        $file = File::firstOrFail();
        $file->forceFill( ['description' => ['en' => 'A test image']] )->saveQuietly();
        $file->latest?->forceFill( ['aux' => ['description' => ['en' => 'A test image']]] )->saveQuietly();

        $fake = Prisma::fake( [TextResponse::fromTexts( ['Ein Testbild'] )] );

        $this->actingAs( $this->user )->graphQL( '
            mutation($id: [ID!]!) { translateFiles(id: $id, lang: ["de", "en"]) { id } }
        ', ['id' => [$file->id]] )->assertJson( ['data' => ['translateFiles' => [['id' => $file->id]]]] );

        $file = File::with( 'latest' )->findOrFail( $file->id );

        $this->assertCount( 1, $fake->calls() );
        $this->assertEquals( 'Ein Testbild', $file->latest->aux->description->de );
        $this->assertEquals( 'A test image', $file->latest->aux->description->en );
    }


    public function testGraphqlTranslateFilesNumeric()
    {
        $file = File::firstOrFail();
        $file->latest?->forceFill( ['aux' => ['description' => ['en' => '2024']]] )->saveQuietly();

        Prisma::fake( [TextResponse::fromTexts( ['2024'] )] );

        $this->actingAs( $this->user )->graphQL( '
            mutation($id: [ID!]!) { translateFiles(id: $id, lang: ["de"]) { id } }
        ', ['id' => [$file->id]] )->assertJson( ['data' => ['translateFiles' => [['id' => $file->id]]]] );

        $this->assertSame( '2024', File::with( 'latest' )->findOrFail( $file->id )->latest->aux->description->de );
    }


    public function testGraphqlTranslateFilesBatched()
    {
        config( ['cms.locales' => ['en', 'de', 'fr']] );

        $files = File::with( 'latest' )->take( 2 )->get();

        foreach( $files as $file )
        {
            $file->forceFill( ['lang' => 'en', 'description' => ['en' => 'A test image']] )->saveQuietly();
            $file->latest?->forceFill( ['aux' => ['description' => ['en' => 'A test image']]] )->saveQuietly();
        }

        $fake = Prisma::fake( [TextResponse::fromTexts( ['Ein Testbild'] ), TextResponse::fromTexts( ['Une image de test'] )] );

        $this->actingAs( $this->user )->graphQL( '
            mutation($id: [ID!]!) { translateFiles(id: $id, lang: ["de", "fr"]) { id } }
        ', ['id' => $files->pluck( 'id' )->all()] )->assertGraphQLErrorFree();

        // one call per language, identical descriptions are sent once
        $this->assertCount( 2, $fake->calls() );
        $this->assertSame( 2, RateLimiter::attempts( 'cms-translate:' . sha1( (string) Tenancy::value() ) ) );

        foreach( $files as $file )
        {
            $file = File::with( 'latest' )->findOrFail( $file->id );

            $this->assertEquals( 'Ein Testbild', $file->latest->aux->description->de );
            $this->assertEquals( 'Une image de test', $file->latest->aux->description->fr );
        }
    }


    public function testGraphqlTranslateFilesMaximum()
    {
        $langs = array_map( fn( $i ) => 'l' . chr( 97 + $i ), range( 0, 10 ) );
        config( ['cms.locales' => ['en', ...$langs]] );

        $fake = Prisma::fake( [] );

        $this->actingAs( $this->user )->graphQL( '
            mutation($id: [ID!]!, $lang: [String!]!) { translateFiles(id: $id, lang: $lang) { id } }
        ', ['id' => File::take( 1 )->pluck( 'id' )->all(), 'lang' => $langs] )
            ->assertGraphQLValidationKeys( ['lang'] );

        $this->assertCount( 0, $fake->calls() );
    }


    public function testGraphqlTranslateFilesThrottled()
    {
        config( ['cms.ai.ratelimit' => 2] );

        $key = 'cms-translate:' . sha1( (string) Tenancy::value() );
        $file = File::firstOrFail();
        $file->forceFill( ['lang' => 'en', 'description' => ['en' => 'A test image']] )->saveQuietly();
        $file->latest?->forceFill( ['aux' => ['description' => ['en' => 'A test image']]] )->saveQuietly();
        $latestId = $file->latest_id;

        RateLimiter::hit( $key, 60 );
        RateLimiter::hit( $key, 60 );

        $fake = Prisma::fake( [TextResponse::fromTexts( ['Ein Testbild'] )] );

        $this->actingAs( $this->user )->graphQL( '
            mutation($id: [ID!]!) { translateFiles(id: $id, lang: ["de"]) { id } }
        ', ['id' => [$file->id]] )->assertGraphQLErrorMessage( 'Too many translations, please try again later' );

        // the reservation is given back and nothing is translated or saved
        $this->assertCount( 0, $fake->calls() );
        $this->assertSame( 2, RateLimiter::attempts( $key ) );
        $this->assertSame( $latestId, File::findOrFail( $file->id )->latest_id );

        RateLimiter::clear( $key );

        $this->actingAs( $this->user )->graphQL( '
            mutation($id: [ID!]!) { translateFiles(id: $id, lang: ["de"]) { id } }
        ', ['id' => [$file->id]] )->assertJson( ['data' => ['translateFiles' => [['id' => $file->id]]]] );

        $this->assertCount( 1, $fake->calls() );
        $this->assertSame( 1, RateLimiter::attempts( $key ) );
        $this->assertEquals( 'Ein Testbild', File::with( 'latest' )->findOrFail( $file->id )->latest->aux->description->de );
    }


    public function testGraphqlTranslateFilesThrottledKeepsTranslated()
    {
        config( ['cms.ai.ratelimit' => 2, 'cms.locales' => ['en', 'de', 'fr']] );

        $key = 'cms-translate:' . sha1( (string) Tenancy::value() );
        $file = File::firstOrFail();
        $file->forceFill( ['lang' => 'en', 'description' => ['en' => 'A test image']] )->saveQuietly();
        $file->latest?->forceFill( ['aux' => ['description' => ['en' => 'A test image']]] )->saveQuietly();

        RateLimiter::hit( $key, 60 );

        $fake = Prisma::fake( [TextResponse::fromTexts( ['Ein Testbild'] ), TextResponse::fromTexts( ['Une image de test'] )] );

        // two languages need two calls but only one is left, so only the first one is sent
        $this->actingAs( $this->user )->graphQL( '
            mutation($id: [ID!]!) { translateFiles(id: $id, lang: ["de", "fr"]) { id } }
        ', ['id' => [$file->id]] )->assertGraphQLErrorMessage( 'Too many translations, please try again later' );

        $this->assertCount( 1, $fake->calls() );
        $this->assertSame( 2, RateLimiter::attempts( $key ) );

        $description = File::with( 'latest' )->findOrFail( $file->id )->latest->aux->description;
        $this->assertEquals( 'Ein Testbild', $description->de ?? null );
        $this->assertObjectNotHasProperty( 'fr', $description );

        RateLimiter::clear( $key );
    }


    public function testGraphqlTranslateFilesThrottledKeepsSourceGroup()
    {
        config( ['cms.ai.ratelimit' => 2, 'cms.locales' => ['en', 'de', 'fr']] );

        $key = 'cms-translate:' . sha1( (string) Tenancy::value() );
        [$en, $de] = File::with( 'latest' )->take( 2 )->get()->all();

        $en->forceFill( ['lang' => 'en', 'description' => ['en' => 'A test image']] )->saveQuietly();
        $en->latest?->forceFill( ['aux' => ['description' => ['en' => 'A test image']]] )->saveQuietly();
        $de->forceFill( ['lang' => 'de', 'description' => ['de' => 'Ein Testbild']] )->saveQuietly();
        $de->latest?->forceFill( ['aux' => ['description' => ['de' => 'Ein Testbild']]] )->saveQuietly();

        RateLimiter::hit( $key, 60 );

        $fake = Prisma::fake( [TextResponse::fromTexts( ['Une image de test'] ), TextResponse::fromTexts( ['Une autre image'] )] );

        // both source languages need a call for French but only one is left
        $this->actingAs( $this->user )->graphQL( '
            mutation($id: [ID!]!) { translateFiles(id: $id, lang: ["fr"]) { id } }
        ', ['id' => [$en->id, $de->id]] )->assertGraphQLErrorMessage( 'Too many translations, please try again later' );

        $this->assertCount( 1, $fake->calls() );

        // the translations of the first source language are saved
        $this->assertEquals( 'Une image de test', File::with( 'latest' )->findOrFail( $en->id )->latest->aux->description->fr ?? null );
        $this->assertObjectNotHasProperty( 'fr', File::with( 'latest' )->findOrFail( $de->id )->latest->aux->description );

        RateLimiter::clear( $key );
    }
}
