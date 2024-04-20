<?php
declare(strict_types = 1);

namespace Innmind\Kalmiya\Command;

use Innmind\CLI\{
    Command,
    Console,
};
use Innmind\OperatingSystem\Filesystem;
use Innmind\Server\Control\Server;
use Innmind\Url\Path;
use Innmind\Immutable\{
    Map,
    Set,
    Str,
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

    public function __invoke(Console $console): Console
    {
        $console = $this->backups->reduce(
            $console,
            function(Console $console, Path $source, Path $target) {
                if (!$this->filesystem->contains($source)) {
                    return $console->output(Str::of("Backup source {$source->toString()} not accessible\n"));
                }

                if (!$this->filesystem->contains($target)) {
                    return $console->output(Str::of("Backup target {$target->toString()} not accessible\n"));
                }

                $console = $console->output(Str::of("Backuping {$source->toString()} to {$target->toString()}:\n"));
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
        $this->foldersToOpen->foreach(function(Path $folder): void {
            $this
                ->processes
                ->execute(
                    Server\Command::foreground('open')
                        ->withArgument($folder->toString()),
                )
                ->wait();
        });

        return $console;
    }

    /**
     * @psalm-mutation-free
     */
    public function usage(): string
    {
        return 'backup';
    }
}
