<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Commands;

use Aimeos\Cms\Import\Status;
use Aimeos\Cms\Models\PageView;


/**
 * Imports the Drupal front page node into the root page of the imported site.
 */
trait ImportsDrupalFront
{
    /** @var int|null Node ID of the Drupal front page imported into the root page or NULL if none is imported */
    protected ?int $front = null;


    /**
     * Imports the Drupal front page node into the root page.
     *
     * The front page is configured in "system.site" as node path (e.g. "/node/1") or alias.
     * Root pages which weren't created by the import or were changed since aren't overwritten,
     * the front page is imported as normal page then.
     */
    protected function importFront( PageView $root ): void
    {
        /** @var object{title: string, status: int, nid: int, created: int, changed?: int, type: string, langcode?: string}|null $node */
        $node = ( $nid = $this->urls->frontNode() ) ? $this->nodes->get( $nid ) : null;

        if( !$node ) {
            return;
        }

        if( $root->editor !== $this->editor ) {
            $this->warn( "  Imported front page [{$node->nid}] {$node->title} as normal page because the root page already exists" );
            return;
        }

        $page = $this->transaction( "[{$node->nid}] {$node->title}", function() use ( $node, $root ) {
            $access = empty( $node->status ) ? false : $this->access->node( $node );

            $content = $this->nodeContent( $node, $node->langcode ?? '', true );
            $data = array_replace( $this->nodePageData( $node, '/' ), ['tag' => 'root', 'status' => 1] );

            $status = $this->updateVersion( $root, $data, $content );
            $this->touch( $root, $node );
            $this->setAccess( $root, $access );
            $this->pending( $root, $node, $data, $content, true );
            $this->saveTranslations( $node, $root );

            return $this->imported( $node->nid, $root, $node->title, '', $this->newRoot ? Status::Imported : $status );
        } );
        $this->data->forget( $node->nid );

        if( !$page )
        {
            $this->front = null;

            if( isset( $this->restricted[$node->nid] ) ) {
                $this->warn( "  Check the access of the root page, the access of the front page [{$node->nid}] {$node->title} is restricted in Drupal" );
            }

            return;
        }

        $this->importChildren( 'node:' . $node->nid, $root );
    }
}
