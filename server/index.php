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


// Everything that can fail goes inside this try, including setup,
// so any error becomes a JSON response.
try {

    // Initialize config
    $config = AppConfig::fromEnv();

    // Initialize database
    $dbHost = AppConfig::mustGetEnv("DB_HOST");
    $dbPort = AppConfig::mustGetEnv("DB_PORT");
    $dbName = AppConfig::mustGetEnv("DB_NAME");
    $dbUser = AppConfig::mustGetEnv("DB_USER");
    $dbPassword = AppConfig::mustGetEnv("DB_PASSWORD");

    $dsn = "pgsql:host=$dbHost;port=$dbPort;dbname=$dbName";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ];

    $db = new PDO($dsn, $dbUser, $dbPassword, $options);

    // App handles all game logic
    $app = new \App($db, $config);

    $router = new \Bramus\Router\Router();

    // Register route handlers
    // Important! Right now these handlers are responsible for checking auth
    $router->get('/health', function () use ($app) {
        httpOk($app->healthCheck());
    });
    $router->post('/v1/auth/login', function () use ($app) {
        $request = new \Api\AuthRequest(readJsonBody());
        httpOk($app->handleAuth($request));
    });
    $router->get('/v1/me', function () use ($app) {
        $playerId = $app->validateToken(getAuthToken());
        httpOk($app->getMe($playerId));
    });
    $router->get('/v1/items', function () use ($app) {
        $app->validateToken(getAuthToken());
        httpOk($app->listShopItems());
    });
    $router->post('/v1/purchases', function () use ($app) {
        $playerId = $app->validateToken(getAuthToken());
        $request = new \Api\PurchaseRequest(readJsonBody());
        httpOk($app->purchase($playerId, $request));
    });
    $router->set404(function () {
        // throwing an error here, so it is handled in the catch block below
        throw HttpException::notFound();
    });

    // Handle the request.
    $router->run();
} catch (HttpException $e) {
    httpError($e->status, $e->getMessage());
} catch (\Throwable $e) {
    // Log the details server-side, never include in response
    error_log((string) $e);
    httpError(500, 'Internal server error');
}

function getAuthToken(): string
{
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';

    // "Bearer" followed by whitespace, then the token (one or more non-space characters).
    if (!preg_match('/^Bearer\s+(\S+)$/i', $authHeader, $matches)) {
        throw HttpException::unauthorized('missing or invalid Authorization header');
    }
    return $matches[1];
}

function httpOk($response): void
{
    sendJson(200, $response);
}

function httpError(int $status, string $message): void
{
    sendJson($status, ['error' => $message]);
}

function sendJson(int $status, $body): void
{
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($body);
}

// Decode the request body as a JSON object, or fail with a 400.
// Covers an empty body, invalid JSON, and valid JSON that isn't an object (e.g. "hi" or 42).
function readJsonBody(): array
{
    try {
        $data = json_decode(file_get_contents('php://input'), true, 512, JSON_THROW_ON_ERROR);
    } catch (\JsonException) {
        throw HttpException::badRequest('Request body must be valid JSON');
    }

    if (!is_array($data)) {
        throw HttpException::badRequest('Request body must be a JSON object');
    }

    return $data;
}
