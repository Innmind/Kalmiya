<?php
declare(strict_types = 1);

namespace Innmind\Kalmiya\Command\Music;

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
use Innmind\Stream\Readable\Stream;
use Innmind\Immutable\Str;

final class Authenticate implements Command
{
    private Adapter $config;
    private Sockets $sockets;

    public function __construct(Adapter $config, Sockets $sockets)
    {
        $this->config = $config;
        $this->sockets = $sockets;
    }

    public function __invoke(Environment $env, Arguments $arguments, Options $options): void
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

        if (!$appleMusic->contains(new Name('Certificate'))) {
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

        // always ask for the user token as this token can expire
        $ask = new Question('User token:');
        $userToken = $ask($env, $this->sockets);

        $appleMusic = $appleMusic->add(File::named(
            'user-token',
            Stream::ofContent($userToken->toString()),
        ));

        $this->config->add($appleMusic);
    }

    public function toString(): string
    {
        return <<<USAGE
            music:authenticate

            Provide the information to communicate with Apple Music
            USAGE;
    }
}
