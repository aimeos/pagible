<?php

/**
 * @license MIT, http://opensource.org/licenses/MIT
 */


namespace Aimeos\Cms\Models;

use Illuminate\Database\Eloquent\Model;


/**
 * Nav model
 */
class Nav extends Model
{
    /**
     * The model's default values for attributes.
     *
     * @var array
     */
    protected $attributes = [
        'lang' => '',
        'path' => '',
        'domain' => '',
        'to' => '',
        'name' => '',
        'title' => '',
        'status' => 0,
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'lang',
        'path',
        'domain',
        'to',
        'name',
        'title',
        'status',
    ];

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'cms_pages';


    /**
     * Get the connection name for the model.
     *
     * @return string Name of the database connection to use
     */
    public function getConnectionName() : string
    {
        return config( 'cms.db', 'sqlite' );
    }


    /**
     * Tests if node has children.
     *
     * @return bool TRUE if node has children, FALSE if not
     */
    public function getHasAttribute() : bool
    {
        return $this->_rgt > $this->_lft + 1;
    }


    public static function boot()
    {
        parent::boot();

        static::saving(function () {
            throw new \Exception('This model is read-only.');
        });

        static::deleting(function () {
            throw new \Exception('This model is read-only.');
        });
    }


    public static function create(array $attributes = [])
    {
        throw new \Exception('This model is read-only.');
    }


    public function delete()
    {
        throw new \Exception('This model is read-only.');
    }


    public function restore()
    {
        throw new \Exception('This model is read-only.');
    }


    public function save(array $options = [])
    {
        throw new \Exception('This model is read-only.');
    }


    public function update(array $attributes = [], array $options = [])
    {
        throw new \Exception('This model is read-only.');
    }
}
