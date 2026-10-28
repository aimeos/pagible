<?php

$files = [
    dirname( __DIR__ ) . '/vendor/autoload.php',
    dirname( __DIR__, 2 ) . '/vendor/autoload.php',
];

foreach( $files as $file ) {
    if( is_file( $file ) ) {
        require $file;
        return;
    }
}

throw new RuntimeException( 'Composer autoloader not found.' );
