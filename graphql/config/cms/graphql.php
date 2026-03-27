<?php

return [
    'filesize' => env( 'CMS_GRAPHQL_FILESIZE', 50 ),
    'mimetypes' => explode( ',', env( 'CMS_GRAPHQL_MIMETYPES', 'application/gzip,application/pdf,application/vnd.,application/zip,audio/,image/,text/,video/' ) ),
];
