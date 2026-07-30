<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */

namespace Aimeos\Cms;

use Aimeos\Cms\Models\Page;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;


/**
 * Resolves trusted payment data from a published pricing element.
 *
 * @phpstan-type ProductData array{
 *   access: string,
 *   currency: string,
 *   description: string,
 *   interval: int,
 *   kind: string,
 *   reference: string,
 *   url: string,
 * }
 */
class CashierProduct
{
    /**
     * Resolves one unambiguous price from trusted published page content.
     *
     * @return ProductData
     */
    public function find( Authenticatable $user, string $pageId, string $elementId, string $packageId, string $priceId ) : array
    {
        $tenant = Tenancy::value();

        if( !$user instanceof Model || $user->getAttribute( 'tenant_id' ) !== $tenant
            || !Tenancy::allows( $user, $tenant )
        ) {
            abort( 403 );
        }

        $page = Page::query()
            ->whereIn( 'status', [1, 2] )
            ->access( $user )
            ->findOrFail( $pageId );

        $data = $this->pricing( $page, $elementId );
        $packages = array_values( array_filter(
            (array) ( $data->items ?? [] ),
            fn( mixed $item ) => is_object( $item ) && ( $item->id ?? null ) === $packageId,
        ) );

        if( count( $packages ) !== 1 ) {
            abort( 404, __( 'Unknown product' ) );
        }

        $package = $packages[0];
        $role = $this->text( $package->access ?? null, 100 );
        $prices = (array) ( $package->prices ?? [] );

        if( !app( Access::class )->has( $role ) ) {
            abort( 404, __( 'Unknown product' ) );
        }

        if( count( $prices ) < 1 || count( $prices ) > 5 ) {
            abort( 404, __( 'Unknown product' ) );
        }

        $prices = array_values( array_filter(
            $prices,
            fn( mixed $item ) => is_object( $item ) && ( $item->id ?? null ) === $priceId,
        ) );

        if( count( $prices ) !== 1 ) {
            abort( 404, __( 'Unknown product' ) );
        }

        $price = $prices[0];
        $kind = $this->text( $price->kind ?? null, 32 );

        if( !in_array( $kind, ['once', 'subscription'], true ) ) {
            abort( 404, __( 'Unknown product' ) );
        }

        $reference = $this->text( $price->reference ?? null, 255 );
        $url = $package->url ?? null;
        $url = is_string( $url ) ? trim( $url ) : '';

        return [
            'access' => $role,
            'currency' => $this->currency( $price->currency ?? null ),
            'description' => is_string( $package->name ?? null ) ? trim( $package->name ) : '',
            'interval' => $this->interval( $price->interval ?? null ),
            'kind' => $kind,
            'reference' => $reference,
            'url' => str_starts_with( $url, '/' ) && Utils::isValidUrl( $url, false ) ? $url : '/',
        ];
    }


    /**
     * Validates an ISO-style three-letter currency code.
     */
    private function currency( mixed $value ) : string
    {
        if( !is_string( $value ) || !preg_match( '/^[A-Z]{3}$/D', $value ) ) {
            abort( 404, __( 'Unknown product' ) );
        }

        return $value;
    }


    /**
     * Validates the optional billing interval in days.
     */
    private function interval( mixed $value ) : int
    {
        if( $value === null ) {
            return 0;
        }

        if( !is_int( $value ) || $value < 0 || $value > 365 ) {
            abort( 404, __( 'Unknown product' ) );
        }

        return $value;
    }


    /**
     * Returns pricing data from an inline or referenced published element.
     */
    private function pricing( Page $page, string $elementId ) : object
    {
        foreach( (array) $page->content as $item )
        {
            if( ( $item->id ?? null ) === $elementId && ( $item->type ?? null ) === 'pricing' ) {
                return is_object( $item->data ?? null ) ? $item->data : (object) ( $item->data ?? [] );
            }

            if( ( $item->refid ?? null ) !== $elementId ) {
                continue;
            }

            $element = $page->elements->get( $elementId );

            if( $element && $element->type === 'pricing' ) {
                return $element->data;
            }
        }

        abort( 404, __( 'Unknown product' ) );
    }


    /**
     * Validates and normalizes a required product string.
     */
    private function text( mixed $value, int $max ) : string
    {
        if( !is_string( $value ) || ( $value = trim( $value ) ) === '' || mb_strlen( $value ) > $max ) {
            abort( 404, __( 'Unknown product' ) );
        }

        return $value;
    }
}
