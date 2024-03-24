<?php
declare(strict_types = 1);

namespace Innmind\Kalmiya\Command\Music;

use Innmind\Kalmiya\Exception\AppleMusicNotConfigured;
use Innmind\CLI\{
    Command,
    Console,
    Question\Question,
};
use Innmind\Filesystem\{
    Adapter,
    Directory,
    Name,
    File,
    File\Content,
};
use Innmind\IPC\Server;
use Innmind\Server\Control\Server\{
    Processes,
    Command as ServerCommand,
    Signal,
};
use Innmind\Url\Path;
use Innmind\Immutable\{
    Str,
    Sequence,
    Predicate\Instance,
};

final class Authenticate implements Command
{
    private Command $attempt;
    private Adapter $config;
    private Processes $processes;
    private Server $listen;
    private Path $httpServer;

    public function __construct(
        Command $attempt,
        Adapter $config,
        Processes $processes,
        Server $listen,
        Path $httpServer,
    ) {
        $this->attempt = $attempt;
        $this->config = $config;
        $this->processes = $processes;
        $this->listen = $listen;
        $this->httpServer = $httpServer;
    }

    public function __invoke(Console $console): Console
    {
        try {
            return ($this->attempt)($console);
        } catch (AppleMusicNotConfigured $e) {
            $console = $this->configure($console);

            return ($this->attempt)($console);
        }
    }

    /**
     * @psalm-mutation-free
     */
    public function usage(): string
    {
        return $this->attempt->usage();
    }

    private function configure(Console $console): Console
    {
        $appleMusic = $this
            ->config
            ->get(Name::of('apple-music'))
            ->keep(Instance::of(Directory::class))
            ->match(
                static fn($appleMusic) => $appleMusic,
                static fn() => Directory::named('apple-music'),
            );

        if (!$appleMusic->contains(Name::of('id'))) {
            $ask = new Question('Id:');
            [$id, $console] = $ask($console);

            $id = $id->match(
                static fn($id) => $id,
                static fn() => throw new \LogicException('todo'),
            );
            $appleMusic = $appleMusic->add(File::named(
                'id',
                Content::ofString($id->toString()),
            ));
        }

        if (!$appleMusic->contains(Name::of('team-id'))) {
            $ask = new Question('Team id:');
            [$teamId, $console] = $ask($console);

            $teamId = $teamId->match(
                static fn($teamId) => $teamId,
                static fn() => throw new \LogicException('todo'),
            );
            $appleMusic = $appleMusic->add(File::named(
                'team-id',
                Content::ofString($teamId->toString()),
            ));
        }

        if (!$appleMusic->contains(Name::of('certificate'))) {
            /** @var Sequence<Str> */
            $certificate = Sequence::of();

            do {
                $ask = new Question('Certificate:');
                [$line, $console] = $ask($console);
                $certificate = $certificate->append($line->toSequence());
            } while (!$certificate->any(static fn($line) => $line->contains('END PRIVATE KEY')));

            $appleMusic = $appleMusic->add(File::named(
                'certificate',
                Content::ofLines($certificate->map(Content\Line::of(...))),
            ));
        }

        $this->config->add($appleMusic);

        $http = $this->processes->execute(
            ServerCommand::foreground('php')
                ->withShortOption('S', 'localhost:8080')
                ->withWorkingDirectory($this->httpServer),
        );
        $_ = $this
            ->processes
            ->execute(
                ServerCommand::foreground('open')
                    ->withArgument('http://localhost:8080'),
            )
            ->wait()
            ->match(
                static fn() => null,
                static fn() => null,
            );

        // the only message that we can receive is when the user token has
        // been persisted
        $console = ($this->listen)(
            null,
            static fn($_, $continuation) => $continuation->stop(null),
        )->match(
            static fn() => $console->output(Str::of("Apple Music token received\n")),
            static fn() => $console
                ->output(Str::of("Failed to receive the Apple Music token\n"))
                ->exit(1),
        );
        $console = $http
            ->pid()
            ->map(fn($pid) => $this->processes->kill($pid, Signal::terminate))
            ->match(
                static fn() => $console,
                static fn() => $console
                    ->output(Str::of("Failed to stop the HTTP server\n"))
                    ->exit(1),
            );

        return $console;
    }
}
