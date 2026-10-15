<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms;


/**
 * Unversioned changes of page variants besides the content and lifecycle actions.
 *
 * They are announced by Bulk events only, using the values as action names.
 */
enum VariantAction : string
{
    case Ignored = 'ignored';
    case Source = 'source';
    case Translated = 'translated';
}
