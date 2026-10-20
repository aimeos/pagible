<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Actions;

use Aimeos\Cms\Models\PageView;
use Aimeos\Cms\Theme;
use Illuminate\Http\Request;


class Toc
{
    /**
     * Returns a nested tree of heading elements after the TOC element.
     *
     * @param \Illuminate\Http\Request $request
     * @param \Aimeos\Cms\Models\PageView $page
     * @param object $item
     * @return array<int, array{id: string, title: string, children: array<int, mixed>}> Nested heading tree
     */
    public function __invoke( Request $request, PageView $page, object $item ): array
    {
        $content = array_values( (array) cms( $page, 'content' ) );

        foreach( $content as $idx => $el )
        {
            $el = cmsref( $page, $el );

            if( ( $el->type ?? null ) === 'toc' && ( $el->id ?? null ) === ( $item->id ?? null ) ) {
                return $this->tree( Theme::headings( $page, array_slice( $content, $idx + 1 ) ) );
            }
        }

        return [];
    }


    /**
     * Builds a nested tree from a flat list of headings.
     *
     * @param array<int, array{id: string, level: int, title: string}> $headings Flat heading list
     * @param int $idx Current index in the list
     * @param int|null $base Base heading level for the current nesting
     * @return array<int, array{id: string, title: string, children: array<int, mixed>}> Nested tree
     */
    protected function tree( array $headings, int &$idx = 0, ?int $base = null ): array
    {
        $items = [];
        $base = $base ?? ( $headings[0]['level'] ?? 1 );

        while( $idx < count( $headings ) )
        {
            $h = $headings[$idx];

            if( $h['level'] < $base ) {
                break;
            }

            $idx++;
            $children = $h['level'] < 6 ? $this->tree( $headings, $idx, $h['level'] + 1 ) : [];
            $items[] = ['id' => $h['id'], 'title' => $h['title'], 'children' => $children];
        }

        return $items;
    }
}
