<?php
declare(strict_types = 1);

namespace Innmind\Kalmiya;

use Innmind\OperatingSystem\Sockets;
use Innmind\Filesystem\{
    Adapter,
    Name,
    Directory,
};
use Innmind\HttpTransport\Transport;
use Innmind\TimeContinuum\Clock;
use Innmind\CLI;

/**
 * @return list<CLI\Command>
 */
function bootstrap(
    Adapter $config,
    Transport $http,
    Clock $clock,
    Sockets $sockets
): array {
    if (!$config->contains(new Name('apple-music'))) {
        return [
            new Command\Music\Authenticate($config, $sockets),
        ];
    }

    $sdkFactory = new AppleMusic\SDKFactory($config, $http, $clock);

    return [
        new Command\Music\Library($sdkFactory, $config),
        new Command\Music\Releases($sdkFactory, $config, $clock, $http),
    ];
}
