<?php
declare(strict_types = 1);

namespace Innmind\Kalmiya\Command\Music\Format;

use Innmind\Kalmiya\Command\Music\Format;
use Innmind\CLI\Console;
use MusicCompanion\AppleMusic\SDK\{
    Library,
    Library\Artist,
    Catalog,
};
use Innmind\Url\Scheme;
use Innmind\Immutable\Str;

final class Text implements Format
{
    public function __invoke(
        Console $console,
        Library\Album|Catalog\Album $album,
        Artist $artist,
    ): Console {
        $console = $console->output(
            Str::of("{$artist->name()->toString()} ||| {$album->name()->toString()}"),
        );

        if ($album instanceof Catalog\Album) {
            $url = $album
                ->url()
                ->withScheme(Scheme::of('itmss'))
                ->toString();
            $console = $console->output(Str::of(
                " ||| $url",
            ));
        }

        return $console->output(Str::of("\n"));
    }
}
