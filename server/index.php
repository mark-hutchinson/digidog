<?php
require __DIR__ . '/vendor/autoload.php';

// index.php is the only public entrypoint, and handles all routing, json encoding and http status
// application logic should be handled by the app

use Api\HttpException;

// Turn PHP warnings/notices into exceptions so they're caught below
// instead of leaking into the response body.
set_error_handler(function (int $severity, string $message, string $file, int $line) {
    if (!(error_reporting() & $severity)) {
        return false;
    }
    throw new \ErrorException($message, 0, $severity, $file, $line);
});

// TODO: initialize database connection, and pass to app

$app = new \App();

$router = new \Bramus\Router\Router();

// Register routes
$router->get('/health', function () use ($app) {
    httpOk($app->healthCheck());
});
$router->set404(function () {
    // throwing an error here, so it is handled in the catch block below
    throw HttpException::notFound();
});

// Route the request
try {
    $router->run();
} catch (HttpException $e) {
    httpError($e->status, $e->getMessage());
} catch (\Throwable $e) {
    // Log the details server-side, never include in response
    error_log((string) $e);
    httpError(500, 'Internal server error');
}

function httpOk($response): void {
    sendJson(200, $response);
}

function httpError(int $status, string $message): void {
    sendJson($status, ['error' => $message]);
}

function sendJson(int $status, $body): void {
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($body);
}
