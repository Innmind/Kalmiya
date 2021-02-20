<?php
declare(strict_types = 1);

namespace Innmind\Kalmiya\Command\Music;

use Innmind\Kalmiya\Exception\AppleMusicNotConfigured;
use Innmind\CLI\{
    Command,
    Command\Arguments,
    Command\Options,
    Environment,
    Question\Question,
};
use Innmind\Filesystem\{
    Adapter,
    Directory\Directory,
    Name,
    File\File,
};
use Innmind\OperatingSystem\Sockets;
use Innmind\IPC\{
    Server,
    Exception\Stop,
};
use Innmind\Server\Control\Server\{
    Processes,
    Command as ServerCommand,
    Signal,
};
use Innmind\Stream\Readable\Stream;
use Innmind\Url\Path;
use Innmind\Immutable\Str;
use MusicCompanion\AppleMusic\Exception\{
    InvalidToken,
    InvalidUserToken,
};

final class Authenticate implements Command
{
    private Command $attempt;
    private Adapter $config;
    private Sockets $sockets;
    private Processes $processes;
    private Server $listen;
    private Path $httpServer;

    public function __construct(
        Command $attempt,
        Adapter $config,
        Sockets $sockets,
        Processes $processes,
        Server $listen,
        Path $httpServer
    ) {
        $this->attempt = $attempt;
        $this->config = $config;
        $this->sockets = $sockets;
        $this->processes = $processes;
        $this->listen = $listen;
        $this->httpServer = $httpServer;
    }

    public function __invoke(Environment $env, Arguments $arguments, Options $options): void
    {
        try {
            ($this->attempt)($env, $arguments, $options);
        } catch (AppleMusicNotConfigured | InvalidToken | InvalidUserToken $e) {
            $this->configure($env);
            ($this->attempt)($env, $arguments, $options);
        }
    }

    public function toString(): string
    {
        return $this->attempt->toString();
    }

    private function configure(Environment $env): void
    {
        if ($this->config->contains(new Name('apple-music'))) {
            /** @var Directory */
            $appleMusic = $this->config->get(new Name('apple-music'));
        } else {
            $appleMusic = Directory::named('apple-music');
        }

        if (!$appleMusic->contains(new Name('id'))) {
            $ask = new Question('Id:');
            $id = $ask($env, $this->sockets);

            $appleMusic = $appleMusic->add(File::named(
                'id',
                Stream::ofContent($id->toString()),
            ));
        }

        if (!$appleMusic->contains(new Name('team-id'))) {
            $ask = new Question('Team id:');
            $teamId = $ask($env, $this->sockets);

            $appleMusic = $appleMusic->add(File::named(
                'team-id',
                Stream::ofContent($teamId->toString()),
            ));
        }

        if (!$appleMusic->contains(new Name('certificate'))) {
            $certificate = Str::of('');

            do {
                $ask = new Question('Certificate:');
                $line = $ask($env, $this->sockets);
                $certificate = $certificate
                    ->append($line->toString())
                    ->append("\n");
            } while (!$certificate->contains('END PRIVATE KEY'));

            $appleMusic = $appleMusic->add(File::named(
                'certificate',
                Stream::ofContent($certificate->toString()),
            ));
        }

        $this->config->add($appleMusic);

        $http = $this->processes->execute(
            ServerCommand::foreground('php')
                ->withShortOption('S', 'localhost:8080')
                ->withWorkingDirectory($this->httpServer),
        );
        $this
            ->processes
            ->execute(
                ServerCommand::foreground('open')
                    ->withArgument('http://localhost:8080')
            )
            ->wait();

        ($this->listen)(static function(): void {
            // the only message that we can receive is when the user token has
            // been persisted
            throw new Stop;
        });
        $this->processes->kill($http->pid(), Signal::terminate());
    }
}
