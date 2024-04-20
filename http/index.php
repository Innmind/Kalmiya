<?php
declare(strict_types = 1);

$paths = [
    __DIR__.'/../vendor/autoload.php',
    __DIR__ . '/../../../../vendor/autoload.php',
];

foreach ($paths as $file) {
    if (\file_exists($file)) {
        require $file;
        break;
    }
}

use Innmind\Framework\{
    Main\Http,
    Application,
};
use Innmind\Kalmiya\Kernel;

new class extends Http {
    protected function configure(Application $app): Application
    {
        return $app->map(new Kernel);
    }
};
