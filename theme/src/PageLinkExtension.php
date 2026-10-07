<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms;

use League\CommonMark\Environment\EnvironmentBuilderInterface;
use League\CommonMark\Event\DocumentParsedEvent;
use League\CommonMark\Extension\CommonMark\Node\Inline\Link;
use League\CommonMark\Extension\ExtensionInterface;


/**
 * Rewrites "page:<id>" links in Markdown to the page URLs in the current language.
 *
 * Links to pages which aren't available are replaced by their link text.
 */
class PageLinkExtension implements ExtensionInterface
{
    public function register( EnvironmentBuilderInterface $environment ) : void
    {
        $environment->addEventListener( DocumentParsedEvent::class, [$this, 'onDocumentParsed'] );
    }


    public function onDocumentParsed( DocumentParsedEvent $event ) : void
    {
        $links = [];

        foreach( $event->getDocument()->iterator() as $node )
        {
            if( $node instanceof Link && PageLinks::is( $node->getUrl() ) ) {
                $links[] = $node;
            }
        }

        if( empty( $links ) ) {
            return;
        }

        $urls = app( PageLinks::class )->resolve( array_map( fn( $link ) => substr( trim( $link->getUrl() ), 5 ), $links ) );

        foreach( $links as $link )
        {
            if( $url = $urls[substr( trim( $link->getUrl() ), 5 )] ?? '' ) {
                $link->setUrl( $url );
                continue;
            }

            foreach( $link->children() as $child ) {
                $link->insertBefore( $child );
            }

            $link->detach();
        }
    }
}
