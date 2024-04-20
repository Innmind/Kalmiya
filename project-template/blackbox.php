<?php
declare(strict_types = 1);

require 'vendor/autoload.php';

use Innmind\BlackBox\{
    Application,
    Runner\Load,
    Runner\CodeCoverage,
};

Application::new($argv)
    ->when(
        \get_env('ENABLE_COVERAGE') !== false,
        static fn(Application $app) => $app 
            ->scenariiPerProof(1)
            ->codeCoverage(
                CodeCoverage::of(
                    __DIR__.'/src/',
                    __DIR__.'/proofs/',
                )
                    ->dumpTo('coverage.clover')
                    ->enableWhen(true),
            ),
    )
    ->tryToProve(Load::everythingIn(__DIR__.'/proofs/'))
    ->exit();
