<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\JsonApi\V1\Controllers;

use LaravelJsonApi\Core\Responses\DataResponse;
use LaravelJsonApi\Laravel\Http\Controllers\JsonApiController as Controller;
use Aimeos\Cms\FileStorage;
use Aimeos\Cms\JsonApi\V1\Pages\PageCollectionQuery;
use Aimeos\Cms\JsonApi\V1\Pages\PageQuery;
use Aimeos\Cms\Models\PageView;


/**
 * Custom controller to add meta data to responses.
 */
class JsonapiController extends Controller
{
    /**
     * Adds global meta data to single resource response.
     *
     * @param PageView|null $page Page model
     * @param PageQuery $query Page query
     * @return DataResponse
     */
    public function read( ?PageView $page, PageQuery $query ) : DataResponse
    {
        return DataResponse::make( $page )
            ->withMeta( ['baseurl' => FileStorage::disk( 'public' )->url( '' )] )
            ->withQueryParameters( $query );
    }


    /**
     * Adds global meta data to collection resource response.
     *
     * @param mixed $data Fetched data
     * @param PageCollectionQuery $query Page collection query
     * @return DataResponse
     */
    public function searched( $data, PageCollectionQuery $query ) : DataResponse
    {
        return DataResponse::make( $data )
            ->withMeta( ['baseurl' => FileStorage::disk( 'public' )->url( '' )] )
            ->withQueryParameters( $query );
    }
}
