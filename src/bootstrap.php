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
use Innmind\Url\Path;
use Innmind\CLI;
use Innmind\HttpFramework;
use function Innmind\Templating\bootstrap as templating;

/**
 * @return list<CLI\Command>
 */
function cli(
    Adapter $config,
    Transport $http,
    Clock $clock,
    Sockets $sockets
): array {
    $sdkFactory = new AppleMusic\SDKFactory($config, $http, $clock);

    return [
        new Command\Music\Authenticate(
            new Command\Music\Library($sdkFactory, $config),
            $config,
            $sockets,
        ),
        new Command\Music\Authenticate(
            new Command\Music\Releases($sdkFactory, $config, $clock, $http),
            $config,
            $sockets,
        ),
    ];
}

function http(
    Adapter $config,
    Transport $http,
    Clock $clock,
    Path $templates
): HttpFramework\RequestHandler {
    return new RequestHandler\AppleMusic(
        new AppleMusic\SDKFactory($config, $http, $clock),
        templating($templates),
        $config,
    );
}
