<?php
declare(strict_types = 1);

namespace Innmind\Kalmiya\Command\Music;

use Innmind\CLI\Console;
use MusicCompanion\AppleMusic\SDK\{
    Library,
    Library\Artist,
    Catalog,
};

interface Format
{
    public function __invoke(
        Console $console,
        Library\Album|Catalog\Album $album,
        Artist $artist,
    ): Console;
}
