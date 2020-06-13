<?php
declare(strict_types = 1);

namespace Innmind\Kalmiya\RequestHandler;

use Innmind\Kalmiya\AppleMusic\SDKFactory;
use Innmind\HttpFramework\RequestHandler;
use Innmind\Templating\{
    Engine,
    Name,
};
use Innmind\Filesystem\{
    Adapter,
    File\File,
    Name as FileName,
    Directory,
};
use Innmind\Http\Message\{
    ServerRequest,
    Response,
    StatusCode,
};
use Innmind\Stream\Readable\Stream;
use Innmind\Immutable\Map;

final class AppleMusic implements RequestHandler
{
    private SDKFactory $makeSDK;
    private Engine $render;
    private Adapter $config;

    public function __construct(
        SDKFactory $makeSDK,
        Engine $render,
        Adapter $config
    ) {
        $this->makeSDK = $makeSDK;
        $this->render = $render;
        $this->config = $config;
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
        /** @var Map<string, mixed> */
        $parameters = Map::of('string', 'mixed');

        return new Response\Response(
            $code = StatusCode::of('OK'),
            $code->associatedReasonPhrase(),
            $request->protocolVersion(),
            null,
            ($this->render)(
                new Name('appleMusic.html.twig'),
                ($parameters)('token', $sdk->jwt()),
            ),
        );
    }

    private function save(ServerRequest $request): Response
    {
        /** @var Directory */
        $config = $this->config->get(new FileName('apple-music'));
        /** @psalm-suppress PossiblyInvalidArgument */
        $this->config->add($config->add(File::named(
            'user-token',
            Stream::ofContent($request->form()->get('token')->value()),
        )));

        return new Response\Response(
            $code = StatusCode::of('OK'),
            $code->associatedReasonPhrase(),
            $request->protocolVersion(),
        );
    }
}
