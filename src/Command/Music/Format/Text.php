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

final class Text implements Format
{
    private Environment $env;

    public function __construct(Environment $env)
    {
        $this->env = $env;
    }

    public function __invoke($album, Artist $artist): void
    {
        $this
            ->env
            ->output()
            ->write(Str::of("{$artist->name()->toString()} ||| {$album->name()->toString()}"));

        if ($album instanceof Catalog\Album) {
            $this->env->output()->write(Str::of(
                " ||| {$album->url()->toString()}",
            ));
        }

        $this->env->output()->write(Str::of("\n"));
    }
}
