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
use Innmind\TimeContinuum\Earth\Period\Hour;
use MusicCompanion\AppleMusic\{
    SDK\SDK,
    Key,
};
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

    /** @var Directory */
    $appleMusic = $config->get(new Name('apple-music'));

    $sdk = new SDK(
        $clock,
        $http,
        new Key(
            \trim($appleMusic->get(new Name('id'))->content()->toString()),
            \trim($appleMusic->get(new Name('team-id'))->content()->toString()),
            $appleMusic
                ->get(new Name('certificate'))
                ->content(),
        ),
        new Hour(24),
    );

    return [
        new Command\Music\Library($sdk, $config),
        new Command\Music\Releases($sdk, $config, $clock, $http),
    ];
}
