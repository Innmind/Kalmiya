<?php
declare(strict_types = 1);

namespace Innmind\Kalmiya\Gene;

use Innmind\Genome\{
    Gene,
    History,
    Exception\PreConditionFailed,
    Exception\ExpressionFailed,
};
use Innmind\OperatingSystem\OperatingSystem;
use Innmind\Server\Control\{
    Server,
    Server\Script,
    Server\Command,
    Exception\ScriptFailed,
};

final class GenerateSshKey implements Gene
{
    public function name(): string
    {
        return 'Generate new ssh key';
    }

    public function express(
        OperatingSystem $local,
        Server $target,
        History $history
    ): History {
        try {
            $check = new Script(
                Command::foreground('which')->withArgument('ssh-keygen'),
            );
            $check($target);
        } catch (ScriptFailed $e) {
            throw new PreConditionFailed('ssh-keygen is missing');
        }

        try {
            $generate = new Script(
                Command::foreground('ssh-keygen')
                    ->withShortOption('t', 'rsa')
                    ->withShortOption('b', '4096')
                    ->withShortOption('C', 'baptouuuu@gmail.com')
                    ->withShortOption('f', '.ssh/id_rsa'),
                Command::foreground('open')
                    ->withShortOption('a', 'TextEdit')
                    ->withArgument('.ssh/id_rsa.pub'),
            );
            $generate($target);
        } catch (ScriptFailed $e) {
            throw new ExpressionFailed($this->name());
        }

        return $history;
    }
}
