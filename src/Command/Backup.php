<?php
declare(strict_types = 1);

namespace Innmind\Kalmiya\Command;

use Innmind\CLI\{
    Command,
    Command\Usage,
    Console,
};
use Innmind\OperatingSystem\Filesystem;
use Innmind\Server\Control\Server;
use Innmind\Url\Path;
use Innmind\Immutable\{
    Map,
    Set,
    Str,
    Attempt,
    SideEffect,
};

final class Backup implements Command
{
    private Filesystem $filesystem;
    private Server\Processes $processes;
    /** @var Map<Path, Path> */
    private Map $backups;
    /** @var Set<Path> */
    private Set $foldersToOpen;

    /**
     * @param Map<Path, Path> $backups
     * @param Set<Path> $foldersToOpen
     */
    public function __construct(
        Filesystem $filesystem,
        Server\Processes $processes,
        Map $backups,
        Set $foldersToOpen,
    ) {
        $this->filesystem = $filesystem;
        $this->processes = $processes;
        $this->backups = $backups;
        $this->foldersToOpen = $foldersToOpen;
    }

    #[\Override]
    public function __invoke(Console $console): Attempt
    {
        $console = $this->backups->toSequence()->sink($console)->attempt(
            function(Console $console, $pair) {
                $source = $pair->key();
                $target = $pair->value();

                if (!$this->filesystem->contains($source)) {
                    return $console->output(Str::of("Backup source {$source->toString()} not accessible\n"));
                }

                if (!$this->filesystem->contains($target)) {
                    return $console->output(Str::of("Backup target {$target->toString()} not accessible\n"));
                }

                return $console
                    ->output(Str::of("Backuping {$source->toString()} to {$target->toString()}:\n"))
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

        return $console->flatMap(
            fn($console) => $this
                ->foldersToOpen
                ->unsorted()
                ->sink(SideEffect::identity)
                ->attempt(
                    fn($_, $folder) => $this
                        ->processes
                        ->execute(
                            Server\Command::foreground('open')
                                ->withArgument($folder->toString()),
                        )
                        ->flatMap(
                            static fn($process) => $process
                                ->wait()
                                ->map(SideEffect::identity(...))
                                ->attempt(static fn() => new \RuntimeException),
                        ),
                )
                ->map(static fn() => $console),
        );
    }

    /**
     * @psalm-mutation-free
     */
    #[\Override]
    public function usage(): Usage
    {
        return Usage::of('backup');
    }
}
