<?php

namespace Tests\Integration;

use Aimeos\Prisma\Prisma;
use Aimeos\Prisma\Files\Image;
use PHPUnit\Framework\TestCase;
use GuzzleHttp\Client;
use Firebase\JWT\JWT;


class VertexaiTest extends TestCase
{
    protected function setUp() : void
    {
        \Dotenv\Dotenv::createImmutable( dirname( __DIR__, 2 ) )->load();

        if( empty( $_ENV['VERTEXAI_API_JSON'] ) ) {
            $this->markTestSkipped( 'VERTEXAI_API_JSON is not defined in the environment' );
        }
    }


    public function testImagine() : void
    {
        $image = Image::fromLocalPath( __DIR__ . '/assets/cat.png' );
        $response = Prisma::image()
            ->using( 'vertexai', [
                'access_token' => $this->token( base64_decode( $_ENV['VERTEXAI_API_JSON'] ) ),
                'project_id' => $_ENV['GOOGLE_PROJECT_ID']
            ] )
            ->ensure( 'imagine' )
            ->imagine( 'a cartoon dog', [$image] );

        $this->assertGreaterThan( 0, strlen( $response->binary() ) );

        file_put_contents( __DIR__ . '/results/vertexai_imagine.png', $response->binary() );
    }


    public function testInpaint() : void
    {
        $image = Image::fromLocalPath( __DIR__ . '/assets/cat.png' );
        $mask = Image::fromLocalPath( __DIR__ . '/assets/mask.png' );

        $response = Prisma::image()
            ->using( 'vertexai', [
                'access_token' => $this->token( base64_decode( $_ENV['VERTEXAI_API_JSON'] ) ),
                'project_id' => $_ENV['GOOGLE_PROJECT_ID']
            ] )
            ->ensure( 'inpaint' )
            ->inpaint( $image, $mask, 'add eye glasses' );

        $this->assertGreaterThan( 0, strlen( $response->binary() ) );

        file_put_contents( __DIR__ . '/results/vertexai_inpaint.png', $response->binary() );
    }


    public function testUpscale() : void
    {
        $image = Image::fromLocalPath( __DIR__ . '/assets/cat.png' );
        $response = Prisma::image()
            ->using( 'vertexai', [
                'access_token' => $this->token( base64_decode( $_ENV['VERTEXAI_API_JSON'] ) ),
                'project_id' => $_ENV['GOOGLE_PROJECT_ID']
            ] )
            ->ensure( 'upscale' )
            ->upscale( $image, 2 );

        $this->assertGreaterThan( 0, strlen( $response->binary() ) );

        file_put_contents( __DIR__ . '/results/vertexai_upscale.png', $response->binary() );
    }


    public function testVectorize() : void
    {
        $base64 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAIAAACQd1PeAAAADElEQVQI12NgYGAAAAAEAAEnNCcKAAAAAElFTkSuQmCC';
        $image = Image::fromBase64( $base64, 'image/png' );
        $response = Prisma::image()
            ->using( 'vertexai', [
                'access_token' => $this->token( base64_decode( $_ENV['VERTEXAI_API_JSON'] ) ),
                'project_id' => $_ENV['GOOGLE_PROJECT_ID']
            ] )
            ->ensure( 'vectorize' )
            ->vectorize( [$image] );

        $this->assertCount( 1, $response->vectors() );
        $this->assertCount( 512, $response->vectors()[0] );
    }


    protected function token( string $key ) : string
    {
        $serviceAccount = json_decode($key, true);

        $now = time();
        $jwtPayload = [
            'iss' => $serviceAccount['client_email'],
            'scope' => 'https://www.googleapis.com/auth/cloud-platform',
            'aud' => $serviceAccount['token_uri'],
            'iat' => $now,
            'exp' => $now + 3600,
        ];

        $jwt = JWT::encode($jwtPayload, $serviceAccount['private_key'], 'RS256');

        $client = new Client();
        $response = $client->post($serviceAccount['token_uri'], [
            'form_params' => [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $jwt,
            ],
        ]);

        $tokenData = json_decode($response->getBody()->getContents(), true);

        if (!isset($tokenData['access_token'])) {
            throw new \RuntimeException('Failed to obtain access token from Google OAuth 2.0');
        }

        return $tokenData['access_token'];
    }
}
