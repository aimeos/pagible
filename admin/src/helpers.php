<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


if( !function_exists( 'cmsadmin' ) )
{
    /**
     * Reads and decodes the CMS admin Vite manifest, memoized per path for the process lifetime.
     *
     * The manifest is immutable for a given build, so caching it avoids re-reading and re-parsing
     * the large JSON on every render (the admin shell looks up both the entry chunk and, via
     * cmsimports(), the plugin import map against the same file). In Octane the cache lives for the
     * worker's lifetime; deploys that republish assets restart the workers.
     *
     * @param string $path The path to the manifest file, e.g. "vendor/cms/admin/.vite/manifest.json"
     * @return array<string, mixed> The decoded manifest or an empty array if not found
     */
    function cmsadmin( string $path ) : array
    {
        static $cache = [];

        return $cache[$path] ??= json_decode( @file_get_contents( public_path( $path ) ) ?: '', true ) ?: [];
    }
}


if( !function_exists( 'cmsimports' ) )
{
    /**
     * Builds the import map of shared dependencies for admin plugins.
     *
     * Resolves each re-export shim entry in the Vite manifest to its hashed asset
     * URL so plugin modules import the host's single Vue/Vuetify/router runtime
     * instead of bundling their own. Returns an empty array (no import map) unless
     * every shim is present, e.g. during local development without a build.
     *
     * @param string $path The path to the manifest file, e.g. "vendor/cms/admin/.vite/manifest.json"
     * @return array<string, string> Map of bare specifier to asset URL, empty if any shim is missing
     */
    function cmsimports( string $path ) : array
    {
        $shims = [
            'vue'         => 'js/shims/vue.js',
            'vue-router'  => 'js/shims/vue-router.js',
            'vuetify'     => 'js/shims/vuetify.js',
            'graphql-tag' => 'js/shims/graphql-tag.js',
        ];

        $base = dirname( $path, 2 );
        $manifest = cmsadmin( $path );
        $imports = [];

        foreach( $shims as $spec => $src )
        {
            if( empty( $manifest[$src]['file'] ) ) {
                return [];
            }

            $imports[$spec] = asset( $base . '/' . $manifest[$src]['file'] );
        }

        return $imports;
    }
}
