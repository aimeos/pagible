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
     * Prices and currencies may differ per language, so the price is read from the
     * published content of the page variant in that language (source variant if NULL).
     *
     * @return ProductData
     */
    public function find( Authenticatable $user, string $pageId, string $elementId, string $packageId, string $priceId,
        ?string $lang = null ) : array
    {
        $tenant = Tenancy::value();

        if( !$user instanceof Model || !Tenancy::allows( $user, $tenant ) ) {
            abort( 403 );
        }

        $page = Page::query()
            ->when( $lang, fn( $query ) => $query->language( (string) $lang ) )
            ->whereIn( 'status', [1, 2] )
            ->access( $user )
            ->findOrFail( $pageId );

        $find = function( mixed $items, string $id ) : ?object {
            $items = array_filter( (array) $items, fn( mixed $item ) => is_object( $item ) && ( $item->id ?? null ) === $id );
            return count( $items ) === 1 ? reset( $items ) : null;
        };

        if( !$package = $find( $this->pricing( $page, $elementId )->items ?? [], $packageId ) ) {
            abort( 404, __( 'Unknown product' ) );
        }

        $role = $this->text( $package->access ?? null, 100 );
        $prices = (array) ( $package->prices ?? [] );
        $price = count( $prices ) <= 5 ? $find( $prices, $priceId ) : null;
        $kind = $price ? $this->text( $price->kind ?? null, 32 ) : '';
        $currency = $price->currency ?? null;
        $interval = $price->interval ?? 0;

        if( !$price || !app( Access::class )->has( $role ) || !in_array( $kind, ['once', 'subscription'], true )
            || !is_string( $currency ) || !preg_match( '/^[A-Z]{3}$/D', $currency )
            || !is_int( $interval ) || $interval < 0 || $interval > 365
        ) {
            abort( 404, __( 'Unknown product' ) );
        }

        $url = $package->url ?? null;
        $url = is_string( $url ) ? trim( $url ) : '';

        return [
            'access' => $role,
            'currency' => $currency,
            'description' => is_string( $package->name ?? null ) ? trim( $package->name ) : '',
            'interval' => $interval,
            'kind' => $kind,
            'reference' => $this->text( $price->reference ?? null, 255 ),
            'url' => str_starts_with( $url, '/' ) && Utils::isValidUrl( $url, false ) ? $url : '/',
        ];
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

            $element = $page->getElementsAttribute()->get( $elementId );

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
