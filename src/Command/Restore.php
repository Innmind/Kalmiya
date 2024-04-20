<?php
declare(strict_types = 1);

namespace Innmind\Kalmiya\Command;

use Innmind\CLI\{
    Command,
    Console,
};
use Innmind\OperatingSystem\Filesystem;
use Innmind\Url\Path;
use Innmind\Immutable\{
    Map,
    Str,
};

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
        $this->filesystem = $filesystem;
        $this->backups = $backups;
    }

    public function __invoke(Console $console): Console
    {
        return $this->backups->reduce(
            $console,
            function(Console $console, Path $target, Path $source) {
                if (!$this->filesystem->contains($source)) {
                    return $console->output(Str::of("Restore source {$source->toString()} not accessible\n"));
                }

                if (!$this->filesystem->contains($target)) {
                    return $console->output(Str::of("Restore target {$target->toString()} not accessible\n"));
                }

                $console = $console->output(Str::of("Restoring {$source->toString()} to {$target->toString()}:\n"));
                $target = $this->filesystem->mount($target);

                return $this
                    ->filesystem
                    ->mount($source)
                    ->root()
                    ->all()
                    ->reduce(
                        $console,
                        static function(Console $console, $file) use ($target) {
                            $console = $console->output(Str::of("{$file->name()->toString()}..."));
                            $target->add($file);

                            return $console->output(Str::of(" OK\n"));
                        },
                    );
            },
        );
    }

    /**
     * @psalm-mutation-free
     */
    public function usage(): string
    {
        return 'restore';
    }
}
