<?php
declare(strict_types = 1);

namespace Innmind\Kalmiya\Command;

use Innmind\CLI\{
    Command,
    Command\Arguments,
    Command\Options,
    Environment,
};
use Innmind\OperatingSystem\Filesystem;
use Innmind\Filesystem\File;
use Innmind\Server\Control\Server;
use Innmind\Url\Path;
use Innmind\Immutable\{
    Map,
    Set,
    Str,
};
use function Innmind\Immutable\assertMap;

final class Restore implements Command
{
    private Filesystem $filesystem;
    /** @var Map<Path, Path> */
    private Map $backups;

    /**
     * @param Map<Path, Path> $backups
     */
    public function __construct(Filesystem $filesystem, Map $backups)
    {
        assertMap(Path::class, Path::class, $backups, 2);

        $this->filesystem = $filesystem;
        $this->backups = $backups;
    }

    public function __invoke(Environment $env, Arguments $arguments, Options $options): void
    {
        $this->backups->foreach(function(Path $target, Path $source) use ($env): void {
            if (!$this->filesystem->contains($source)) {
                $env->output()->write(Str::of("Restore source {$source->toString()} not accessible\n"));

                return;
            }

            if (!$this->filesystem->contains($target)) {
                $env->output()->write(Str::of("Restore target {$target->toString()} not accessible\n"));

                return;
            }

            $env->output()->write(Str::of("Restoring {$source->toString()} to {$target->toString()}:\n"));
            $target = $this->filesystem->mount($target);
            $this
                ->filesystem
                ->mount($source)
                ->all()
                ->foreach(function(File $file) use ($env, $target): void {
                    $env->output()->write(Str::of("{$file->name()->toString()}..."));
                    $target->add($file);
                    $env->output()->write(Str::of(" OK\n"));
                });
        });
    }

    public function toString(): string
    {
        return 'restore';
    }
}
