<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
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
