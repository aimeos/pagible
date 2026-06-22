<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests;

abstract class BackupTestAbstract extends CmsTestAbstract
{
    protected function getPackageProviders( $app )
    {
        return array_merge( parent::getPackageProviders( $app ), [
            'Aimeos\Cms\BackupServiceProvider',
        ] );
    }
}
