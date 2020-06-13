<?php
declare(strict_types = 1);

namespace Innmind\Kalmiya\Command\Music\Format;

use Innmind\Kalmiya\Command\Music\Format;
use Innmind\CLI\Environment;
use MusicCompanion\AppleMusic\SDK\{
    Library,
    Library\Artist,
    Catalog,
};
use Innmind\Immutable\Str;

final class Markdown implements Format
{
    private Environment $env;

    public function __construct(Environment $env)
    {
        $this->env = $env;
        $output = $env->output();
        $output->write(Str::of("|Artist|Album|Artwork|\n"));
        $output->write(Str::of("|---|---|---|\n"));
    }

    public function __invoke($album, Artist $artist): void
    {
        $artwork = '';
        $albumName = $album->name()->toString();

        if ($album instanceof Library\Album) {
            $widthClass = Library\Album\Artwork\Width::class;
            $heightClass = Library\Album\Artwork\Height::class;
        } else {
            $widthClass = Catalog\Artwork\Width::class;
            $heightClass = Catalog\Artwork\Height::class;
        }

        if ($album->hasArtwork()) {
            /** @psalm-suppress PossiblyInvalidArgument */
            $artwork = $album->artwork()->ofSize(
                new $widthClass(200),
                new $heightClass(200),
            )->toString();
            $artwork = "![]($artwork)";
        }

        if ($album instanceof Catalog\Album) {
            $url = $album->url()->toString();
            $albumName = "[$albumName]($url)";
        }

        $this
            ->env
            ->output()
            ->write(Str::of("| {$artist->name()->toString()} | $albumName | $artwork |\n"));
    }
}
