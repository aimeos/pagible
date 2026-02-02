<?php

declare(strict_types=1);

return (new \PhpCsFixer\Config())
    ->setUsingCache(false)
    ->setRules([
        'native_function_invocation' => [
            'include' => [
                '@all'
            ]
        ],
    ])
    ->setRiskyAllowed(true)
    ->setFinder(
        PhpCsFixer\Finder::create()
            ->in(__DIR__ . '/src')
            ->append([__FILE__])
    )
;
