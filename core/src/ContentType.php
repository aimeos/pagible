<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms;


/**
 * Types of the content managed by the CMS.
 *
 * The values are used as "contentType" in events, as broadcast name prefixes and as permission prefixes.
 */
enum ContentType : string
{
    case Element = 'element';
    case File = 'file';
    case Page = 'page';
}
