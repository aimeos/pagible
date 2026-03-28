<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Aimeos\Cms\JsonApi\V1\Controllers;

use Illuminate\Support\Facades\Storage;
use LaravelJsonApi\Core\Responses\DataResponse;
use LaravelJsonApi\Core\Responses\RelatedResponse;
use LaravelJsonApi\Eloquent\Fields\Relations\Relation;
use LaravelJsonApi\Laravel\Http\Requests\ResourceQuery;
use LaravelJsonApi\Laravel\Http\Controllers\JsonApiController as Controller;
use Aimeos\Cms\JsonApi\V1\Pages\PageCollectionQuery;
use Aimeos\Cms\JsonApi\V1\Pages\PageQuery;
use Aimeos\Cms\Models\Page;


/**
 * Custom controller to add meta data to responses.
 */
class JsonapiController extends Controller
{
    /**
     * Adds global meta data to single resource response.
     *
     * @param Page|null $page Page model
     * @param PageQuery $query Page query
     * @return DataResponse
     */
    public function read( ?Page $page, PageQuery $query ) : DataResponse
    {
        return DataResponse::make( $page )
            ->withMeta( ['baseurl' => Storage::disk( config( 'cms.disk', 'public' ) )->url( '' )] )
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
            ->withMeta( ['baseurl' => Storage::disk( config( 'cms.disk', 'public' ) )->url( '' )] )
            ->withQueryParameters( $query );
    }
}