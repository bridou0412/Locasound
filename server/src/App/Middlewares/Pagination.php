<?php
declare(strict_types=1);

namespace Robert2\API\Middlewares;

use Illuminate\Pagination\Paginator;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Http\ServerRequest as Request;

class Pagination
{
    public function __invoke(Request $request, RequestHandler $handler)
    {
        /** @var \Slim\Http\Response */
        $response = $handler->handle($request);

        Paginator::currentPageResolver(
            function () use ($request) {
                return $request->getParam('page');
            }
        );

        return $response;
    }
}
