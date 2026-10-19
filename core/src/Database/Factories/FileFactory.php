<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Database\Factories;

use Aimeos\Cms\Facades\Files;
use Aimeos\Cms\Models\File;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;


/**
 * Factory for files created by Files::add()
 *
 * The uploaded file or URL is passed as "file" attribute and stored on the "public"
 * disk unless "disk" is "private".
 *
 * @extends Factory<File>
 */
class FileFactory extends Factory
{
    /**
     * @var class-string<File>
     */
    protected $model = File::class;


    /**
     * Returns the default file input.
     *
     * @return array<string, mixed>
     */
    public function definition() : array
    {
        $name = Str::lower( Str::random( 10 ) );

        return [
            'name' => Str::ucfirst( $name ),
            'file' => fn() => UploadedFile::fake()->image( $name . '.png', 100, 100 ),
        ];
    }


    /**
     * Files stored on the private disk.
     *
     * @return static Factory for fluent interface
     */
    public function private() : static
    {
        return $this->state( ['disk' => 'private'] );
    }


    /**
     * Creates the file.
     *
     * @param array<string, mixed> $input File input with the uploaded file in "file"
     * @return File Created file
     */
    protected function add( array $input ) : Model
    {
        $file = $input['file'] ?? null;
        $disk = $input['disk'] ?? null;
        unset( $input['file'], $input['disk'] );

        if( !$file instanceof UploadedFile && !is_string( $file ) ) {
            throw new \InvalidArgumentException( 'The "file" attribute must be an uploaded file or a URL' );
        }

        return Files::add( $file, $input, disk: is_string( $disk ) ? $disk : null );
    }


    /**
     * Publishes the file.
     *
     * @param File $model Created file
     * @return File Published file
     */
    protected function publish( Model $model ) : Model
    {
        return Files::publish( (string) $model->getKey() );
    }
}
