<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\GraphQL\Mutations;

use Aimeos\Cms\Models\File;
use Aimeos\Prisma\Exceptions\PrismaException;
use GraphQL\Error\Error;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;


/**
 * Validates AI GraphQL uploads and converts AI provider errors into GraphQL errors.
 *
 * CMS exceptions are converted into client-safe errors by the global CmsExceptionDirective.
 */
trait ValidatesInputs
{
    /**
     * Calls the AI operation and converts provider and not found errors into GraphQL errors.
     *
     * @param \Closure $fn Function calling the AI operation
     * @return mixed Return value of the function
     */
    protected function ai( \Closure $fn ) : mixed
    {
        try
        {
            return $fn();
        }
        catch( PrismaException $e )
        {
            Log::error( 'AI service error', ['mutation' => class_basename( static::class ), 'message' => $e->getMessage(), 'trace' => $e->getTraceAsString()] );
            throw new Error( $e->getMessage() );
        }
        catch( ModelNotFoundException $e )
        {
            throw new Error( class_basename( $e->getModel() ) . ' not found' );
        }
    }


    /**
     * Validates an upload against the shared CMS policy and returns it as Prisma media object.
     *
     * @template T of \Aimeos\Prisma\Files\File
     * @param UploadedFile $value Uploaded file
     * @param class-string<T> $class Prisma file class, e.g. Image::class or Audio::class
     * @param string $label Name of the upload used in error messages
     * @return T Prisma media object
     */
    protected function upload( UploadedFile $value, string $class, string $label = 'file' ) : \Aimeos\Prisma\Files\File
    {
        File::checkUpload( $value );

        $type = strtolower( class_basename( $class ) );

        if( !str_starts_with( $mime = (string) $value->getMimeType(), $type . '/' ) ) {
            throw new Error( sprintf( '%s type "%s" is not allowed', ucfirst( $label ), $mime ) );
        }

        if( $type === 'image' ) {
            File::checkPixels( $value );
        }

        return $this->ai( fn() => $class::fromBinary( $value->getContent(), $mime ) );
    }
}
