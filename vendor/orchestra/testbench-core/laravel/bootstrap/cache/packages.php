<?php return array (
  'aimeos/laravel-analytics-bridge' => 
  array (
    'aliases' => 
    array (
      'Analytics' => 'Aimeos\\AnalyticsBridge\\Facades\\Analytics',
    ),
    'providers' => 
    array (
      0 => 'Aimeos\\AnalyticsBridge\\ServiceProvider',
    ),
  ),
  'aimeos/laravel-nestedset' => 
  array (
    'providers' => 
    array (
      0 => 'Aimeos\\Nestedset\\NestedSetServiceProvider',
    ),
  ),
  'laravel-json-api/encoder-neomerx' => 
  array (
    'providers' => 
    array (
      0 => 'LaravelJsonApi\\Encoder\\Neomerx\\ServiceProvider',
    ),
  ),
  'laravel-json-api/laravel' => 
  array (
    'aliases' => 
    array (
      'JsonApi' => 'LaravelJsonApi\\Core\\Facades\\JsonApi',
      'JsonApiRoute' => 'LaravelJsonApi\\Laravel\\Facades\\JsonApiRoute',
    ),
    'providers' => 
    array (
      0 => 'LaravelJsonApi\\Laravel\\ServiceProvider',
    ),
  ),
  'laravel-json-api/spec' => 
  array (
    'providers' => 
    array (
      0 => 'LaravelJsonApi\\Spec\\ServiceProvider',
    ),
  ),
  'laravel-json-api/validation' => 
  array (
    'providers' => 
    array (
      0 => 'LaravelJsonApi\\Validation\\ServiceProvider',
    ),
  ),
  'laravel/pail' => 
  array (
    'providers' => 
    array (
      0 => 'Laravel\\Pail\\PailServiceProvider',
    ),
  ),
  'laravel/scout' => 
  array (
    'providers' => 
    array (
      0 => 'Laravel\\Scout\\ScoutServiceProvider',
    ),
  ),
  'laravel/tinker' => 
  array (
    'providers' => 
    array (
      0 => 'Laravel\\Tinker\\TinkerServiceProvider',
    ),
  ),
  'nesbot/carbon' => 
  array (
    'providers' => 
    array (
      0 => 'Carbon\\Laravel\\ServiceProvider',
    ),
  ),
  'nunomaduro/collision' => 
  array (
    'providers' => 
    array (
      0 => 'NunoMaduro\\Collision\\Adapters\\Laravel\\CollisionServiceProvider',
    ),
  ),
  'nunomaduro/termwind' => 
  array (
    'providers' => 
    array (
      0 => 'Termwind\\Laravel\\TermwindServiceProvider',
    ),
  ),
  'nuwave/lighthouse' => 
  array (
    'providers' => 
    array (
      0 => 'Nuwave\\Lighthouse\\LighthouseServiceProvider',
      1 => 'Nuwave\\Lighthouse\\Async\\AsyncServiceProvider',
      2 => 'Nuwave\\Lighthouse\\Auth\\AuthServiceProvider',
      3 => 'Nuwave\\Lighthouse\\Bind\\BindServiceProvider',
      4 => 'Nuwave\\Lighthouse\\Cache\\CacheServiceProvider',
      5 => 'Nuwave\\Lighthouse\\GlobalId\\GlobalIdServiceProvider',
      6 => 'Nuwave\\Lighthouse\\OrderBy\\OrderByServiceProvider',
      7 => 'Nuwave\\Lighthouse\\Pagination\\PaginationServiceProvider',
      8 => 'Nuwave\\Lighthouse\\SoftDeletes\\SoftDeletesServiceProvider',
      9 => 'Nuwave\\Lighthouse\\Testing\\TestingServiceProvider',
      10 => 'Nuwave\\Lighthouse\\Validation\\ValidationServiceProvider',
    ),
  ),
  'orchestra/canvas' => 
  array (
    'providers' => 
    array (
      0 => 'Orchestra\\Canvas\\LaravelServiceProvider',
    ),
  ),
  'orchestra/canvas-core' => 
  array (
    'providers' => 
    array (
      0 => 'Orchestra\\Canvas\\Core\\LaravelServiceProvider',
    ),
  ),
  'prism-php/prism' => 
  array (
    'aliases' => 
    array (
      'PrismServer' => 'Prism\\Prism\\Facades\\PrismServer',
    ),
    'providers' => 
    array (
      0 => 'Prism\\Prism\\PrismServiceProvider',
    ),
  ),
  'aimeos/pagible' => 
  array (
    'providers' => 
    array (
      0 => 'Aimeos\\Cms\\ServiceProvider',
    ),
  ),
);