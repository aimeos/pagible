<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Aimeos\Cms\Actions;

use Aimeos\Cms\Models\Page;
use Illuminate\Http\Request;


class Toc
{
    /**
     * Returns a nested tree of heading elements after the TOC element.
     *
     * @param \Illuminate\Http\Request $request
     * @param \Aimeos\Cms\Models\Page $page
     * @param object $item
     * @return array<int, array{id: string, title: string, children: array<int, mixed>}> Nested heading tree
     */
    public function __invoke( Request $request, Page $page, object $item ): array
    {
        $headings = [];
        $found = false;

        foreach( (array) cms( $page, 'content' ) as $el )
        {
            $el = cmsref( $page, $el );

            if( !$found ) {
                $found = ($el->type ?? null) === 'toc' && ($el->id ?? null) === ($item->id ?? null);
                continue;
            }

            if( ($el->type ?? null) === 'heading' && ($el->data->title ?? null) ) {
                /** @phpstan-ignore property.notFound */
                $headings[] = ['id' => $el->id ?? '', 'level' => (int) ( $el->data->level ?? 1 ), 'title' => $el->data->title];
            }
        }

        return $this->tree( $headings );
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
