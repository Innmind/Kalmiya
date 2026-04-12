<?php
declare(strict_types = 1);

namespace Innmind\Kalmiya\Command;

use Innmind\CLI\{
    Command,
    Command\Usage,
    Console,
};
use Innmind\OperatingSystem\Filesystem;
use Innmind\Url\Path;
use Innmind\Immutable\{
    Map,
    Str,
    Attempt,
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

    #[\Override]
    public function __invoke(Console $console): Attempt
    {
        return $this->backups->toSequence()->sink($console)->attempt(
            function(Console $console, $pair) {
                $target = $pair->key();
                $source = $pair->value();

                if (!$this->filesystem->contains($source)) {
                    return $console->output(Str::of("Restore source {$source->toString()} not accessible\n"));
                }

                if (!$this->filesystem->contains($target)) {
                    return $console->output(Str::of("Restore target {$target->toString()} not accessible\n"));
                }

                return $console
                    ->output(Str::of("Restoring {$source->toString()} to {$target->toString()}:\n"))
                    ->flatMap(
                        fn($console) => $this
                            ->filesystem
                            ->mount($target)
                            ->flatMap(
                                fn($target) => $this
                                    ->filesystem
                                    ->mount($source)
                                    ->flatMap(
                                        static fn($source) => $source
                                            ->root()
                                            ->all()
                                            ->sink($console)
                                            ->attempt(
                                                static fn($console, $file) => $console
                                                    ->output(Str::of("{$file->name()->toString()}..."))
                                                    ->flatMap(
                                                        static fn($console) => $target
                                                            ->add($file)
                                                            ->map(static fn() => $console),
                                                    )
                                                    ->flatMap(static fn($console) => $console->output(Str::of(" OK\n"))),
                                            ),
                                    ),
                            ),
                    );
            },
        );
    }

    /**
     * @psalm-mutation-free
     */
    #[\Override]
    public function usage(): Usage
    {
        return Usage::of('restore');
    }
}
