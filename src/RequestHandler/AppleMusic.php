<?php
declare(strict_types = 1);

namespace Innmind\Kalmiya\RequestHandler;

use Innmind\Kalmiya\AppleMusic\SDKFactory;
use Innmind\Framework\Http\RequestHandler;
use Innmind\Filesystem\{
    Adapter,
    File,
    Name,
    Directory,
};
use Innmind\IPC\{
    IPC,
    Process,
    Message\Generic as Message,
};
use Innmind\Http\{
    ServerRequest,
    Response,
    Response\StatusCode,
};
use Innmind\Validation\Is;
use Innmind\Immutable\{
    Sequence,
    Predicate\Instance,
};

final class AppleMusic implements RequestHandler
{
    private SDKFactory $makeSDK;
    private Adapter $templates;
    private Adapter $config;
    private IPC $ipc;
    private Process\Name $cli;

    public function __construct(
        SDKFactory $makeSDK,
        Adapter $templates,
        Adapter $config,
        IPC $ipc,
        Process\Name $cli,
    ) {
        $this->makeSDK = $makeSDK;
        $this->templates = $templates;
        $this->config = $config;
        $this->ipc = $ipc;
        $this->cli = $cli;
    }

    public function __invoke(ServerRequest $request): Response
    {
        if (!$request->form()->contains('token')) {
            return $this->authorize($request);
        }

        return $this->save($request);
    }

    private function authorize(ServerRequest $request): Response
    {
        $sdk = ($this->makeSDK)();

        return Response::of(
            StatusCode::ok,
            $request->protocolVersion(),
            null,
            $this
                ->templates
                ->get(Name::of('appleMusic.html'))
                ->keep(Instance::of(File::class))
                ->match(
                    static fn($file) => $file->content(),
                    static fn() => throw new \LogicException('Template not found'),
                )
                ->map(static fn($line) => $line->map(
                    static fn($str) => $str->replace('{{ token }}', $sdk->jwt()),
                )),
        );
    }

    private function save(ServerRequest $request): Response
    {
        $config = $this
            ->config
            ->get(Name::of('apple-music'))
            ->keep(Instance::of(Directory::class))
            ->match(
                static fn($config) => $config,
                static fn() => Directory::named('apple-music'),
            );
        $request
            ->form()
            ->get('token')
            ->keep(Is::string()->asPredicate())
            ->map(File\Content::ofString(...))
            ->map(static fn($token) => File::named('user-token', $token))
            ->map($config->add(...))
            ->match(
                $this->config->add(...),
                static fn() => null,
            );

        $_ = $this
            ->ipc
            ->get($this->cli)
            ->flatMap(static fn($process) => $process->send(Sequence::of(
                Message::of('text/plain', 'ok'),
            )))
            ->match(
                static fn() => null,
                static fn() => null,
            );

        return Response::of(
            StatusCode::ok,
            $request->protocolVersion(),
        );
    }
}
