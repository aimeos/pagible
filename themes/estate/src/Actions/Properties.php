<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */

namespace Aimeos\Cms\Actions;

use Aimeos\Cms\Models\File;
use Aimeos\Cms\Models\Page;
use Aimeos\Cms\Permission;
use Aimeos\Cms\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class Properties
{
    protected array $propertyCache = [];


    public function __invoke( Request $request, Page $page, object $item ) : object
    {
        $this->propertyCache = [];

        $editor = Permission::can( 'page:view', $request->user() );
        $enabled = (bool) ( $item->data->filters ?? true );
        $defaultSort = (string) ( $item->data->order ?? '-created_at' );
        $requestedSort = $enabled ? trim( (string) $request->query( 'sort', '' ) ) : '';
        $perPage = min( 100, max( 1, (int) ( $item->data->limit ?? 6 ) ) );
        $pageNo = max( 1, $request->integer( 'p' ) );
        $schema = Schema::schemas( section: 'content' )['estate::property']['fields'] ?? [];
        $options = (object) [
            'property_types' => $schema['property_type']['options'] ?? [],
            'offer_types' => $schema['offer_type']['options'] ?? [],
        ];
        $propertyTypes = collect( $options->property_types )->pluck( 'value' )
            ->map( fn( $value ) => strtolower( (string) $value ) )->all();
        $offerTypes = collect( $options->offer_types )->pluck( 'value' )
            ->map( fn( $value ) => strtolower( (string) $value ) )->all();

        [$sort, $sortBy, $sortDir] = match( $requestedSort !== '' ? $requestedSort : $defaultSort ) {
            '_lft' => ['_lft', '_lft', 'asc'],
            '-created_at' => ['-created_at', 'created_at', 'desc'],
            'created_at' => ['created_at', 'created_at', 'asc'],
            'updated_desc' => ['updated_desc', 'updated_at', 'desc'],
            'updated_asc' => ['updated_asc', 'updated_at', 'asc'],
            default => ['-created_at', 'created_at', 'desc'],
        };

        $city = $enabled ? trim( (string) $request->query( 'city', '' ) ) : '';
        $type = $enabled ? strtolower( trim( (string) $request->query( 'type', '' ) ) ) : '';
        $offer = $enabled ? strtolower( trim( (string) $request->query( 'offer', '' ) ) ) : '';
        $roomsMin = $enabled && is_numeric( $request->query( 'rooms_min' ) )
            ? (float) $request->query( 'rooms_min' )
            : null;
        $roomsMin = $roomsMin !== null && $roomsMin >= 1 && $roomsMin <= 999 ? $roomsMin : null;
        $availableBy = $enabled ? trim( (string) $request->query( 'available_by', '' ) ) : '';

        $city = mb_strlen( $city ) <= 255 ? $city : '';
        $type = in_array( $type, $propertyTypes, true ) ? $type : '';
        $offer = in_array( $offer, $offerTypes, true ) ? $offer : '';
        $availableDate = preg_match( '/^\d{4}-\d{2}-\d{2}$/', $availableBy )
            ? \DateTimeImmutable::createFromFormat( '!Y-m-d', $availableBy )
            : false;
        $availableBy = $availableDate instanceof \DateTimeImmutable && $availableDate->format( 'Y-m-d' ) === $availableBy
            ? $availableBy
            : '';

        $filters = [
            'city' => $city,
            'type' => $type,
            'offer' => $offer,
            'rooms_min' => $roomsMin,
            'available_by' => $availableBy,
            'sort' => $sort,
        ];
        $filtersActive = collect( $filters )->except( 'sort' )
            ->contains( fn( $value ) => $value !== null && $value !== '' );
        $columns = ['id', 'tenant_id', 'type', 'path', 'name', 'title', 'content', 'created_at', 'updated_at', 'latest_id', '_lft', 'status'];
        $builder = $this->query( $item, $editor, $sortBy, $sortDir );

        if( !$filtersActive )
        {
            $result = $builder->paginate( $perPage, $columns, 'p', $pageNo );
            $result->withPath( $request->url() );
        }
        else
        {
            $pages = $this->filter(
                $builder->get( $columns )
                    ->filter( fn( $pageItem ) => $this->property( $pageItem, $editor ) )
                    ->values(),
                $filters,
                $editor
            );
            $total = $pages->count();
            $offset = ( $pageNo - 1 ) * $perPage;

            $result = new LengthAwarePaginator(
                $pages->slice( $offset, $perPage )->values(),
                $total,
                $perPage,
                $pageNo,
                ['path' => $request->url()]
            );
        }

        $enabled ? $result->appends( array_filter( $filters, fn( $value ) => $value !== null && $value !== '' ) ) : null;
        $this->attachFiles( $result, $editor );

        return (object) [
            'items' => $result,
            'filters' => (object) $filters,
            'options' => $options,
        ];
    }


    protected function attachFiles( LengthAwarePaginator $pages, bool $editor ) : void
    {
        $fileIds = function( $pageItem ) use ( $editor ) {
            $property = $this->property( $pageItem, $editor );
            return collect( (array) ( $property?->files ?? [] ) )
                ->map( fn( $file ) => is_scalar( $file ) ? (string) $file : data_get( $file, 'id' ) )
                ->filter( fn( $id ) => is_string( $id ) && $id !== '' )
                ->all();
        };

        $ids = $pages->getCollection()
            ->flatMap( $fileIds )
            ->filter()
            ->unique()
            ->values()
            ->all();
        $files = $ids
            ? File::whereIn( 'cms_files.id', $ids )->get( ['cms_files.id', 'cms_files.tenant_id', 'name', 'mime', 'path', 'previews', 'description'] )->keyBy( 'id' )
            : collect();

        $pages->getCollection()->each( function( $pageItem ) use ( $files, $fileIds, $editor ) {
            $used = collect( $fileIds( $pageItem ) )
                ->mapWithKeys( fn( $id ) => [$id => $files->get( $id )] )
                ->filter();

            $pageItem->setRelation( 'files', $used );
            $editor && $pageItem->latest ? $pageItem->latest->setRelation( 'files', $used ) : null;
        } );
    }


    protected function filter( Collection $pages, array $filters, bool $editor ) : Collection
    {
        return $pages->filter( function( $pageItem ) use ( $editor, $filters ) {
            $property = $this->property( $pageItem, $editor );

            if( $property === null ) {
                return false;
            }
            if( $filters['city'] !== '' && !str_contains(
                strtolower( (string) ( $property->city ?? '' ) ),
                strtolower( $filters['city'] )
            ) ) {
                return false;
            }
            if( $filters['type'] !== '' && strtolower( (string) ( $property->property_type ?? '' ) ) !== $filters['type'] ) {
                return false;
            }
            if( $filters['offer'] !== '' && strtolower( (string) ( $property->offer_type ?? '' ) ) !== $filters['offer'] ) {
                return false;
            }
            if( $filters['rooms_min'] !== null && (
                ( $property->rooms ?? null ) === null || (float) $property->rooms < $filters['rooms_min']
            ) ) {
                return false;
            }
            if( $filters['available_by'] !== '' && (
                empty( $property->available_from ) || (string) $property->available_from > $filters['available_by']
            ) ) {
                return false;
            }

            return true;
        } );
    }


    protected function property( $item, bool $editor ) : ?object
    {
        $cache = (string) ( $item->id ?? '' );

        if( array_key_exists( $cache, $this->propertyCache ) ) {
            return $this->propertyCache[$cache];
        }

        $content = $editor
            ? ( $item->latest?->aux?->content ?? $item->latest?->data?->content ?? $item->content )
            : $item->content;

        $element = collect( (array) $content )
            ->first( fn( $element ) => ( $element->type ?? null ) === 'estate::property' );

        return $this->propertyCache[$cache] = $element ? (object) data_get( $element, 'data', [] ) : null;
    }


    protected function query( object $item, bool $editor, string $sort, string $direction ) : Builder
    {
        $with = $editor
            ? ['latest' => fn( $query ) => $query->select( 'id', 'tenant_id', 'versionable_id', 'aux' )]
            : [];
        $builder = Page::where( 'type', 'property' )->with( $with )->orderBy( $sort, $direction );

        if( $pid = $item->data->{'parent-page'}?->value ?? null ) {
            $builder->whereDescendantOf( $pid );
        }
        if( $editor ) {
            $builder->whereLatest( ['status' => 1] );
        } else {
            $builder->where( 'status', 1 );
        }

        return $builder;
    }
}
