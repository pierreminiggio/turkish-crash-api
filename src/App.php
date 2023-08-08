<?php

namespace App;

use PierreMiniggio\DatabaseConnection\DatabaseConnection;
use PierreMiniggio\DatabaseFetcher\DatabaseFetcher;
use RuntimeException;

class App
{
    public function run(
        string $path,
        ?string $queryParameters,
        ?string $authHeader,
        ?string $origin,
        ?string $accessControlRequestHeaders
    ): void
    {

        if ($origin) {
            header('Access-Control-Allow-Origin: ' . $origin);
        }

        header('Access-Control-Allow-Credentials: true');
        header('Access-Control-Max-Age: 86400');
        header('Access-Control-Allow-Methods: GET, PUT, POST, DELETE, OPTIONS');

        if ($accessControlRequestHeaders) {
            header('Access-Control-Allow-Headers: ' . $accessControlRequestHeaders);
        }

        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(200);
            exit;
        }

        header('Content-Type: application/json');

        $config = require __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'config.php';

        $dbConfig = $config['db'];
        $fetcher = new DatabaseFetcher(new DatabaseConnection(
            $dbConfig['host'],
            $dbConfig['database'],
            $dbConfig['username'],
            $dbConfig['password'],
            DatabaseConnection::UTF8_MB4
        ));

        if ($path === '/next' && $this->isGetRequest()) {
            $periods = $fetcher->query(
                $fetcher->createQuery(
                    'crash_period'
                )->select(
                    'period'
                )->limit(
                    1
                )
            );

            if (! $periods) {
                http_response_code(404);
                exit;
            }

            echo json_encode(['next' => $periods[0]['period']]);
            exit;
        } elseif (
            $this->isGetRequest()
            && $periodPeriod = $this->getStringAfterPathPrefix($path, '/period/')
        ) {
            $links = $fetcher->query(
                $fetcher->createQuery(
                    'crash',
                    'c'
                )->join(
                    'crash_period as cp',
                    'c.crash_period_id = cp.id'
                )->select(
                    'c.video_link'
                )->where(
                    'cp.period = :period'
                ),
                [
                    'period' => $periodPeriod
                ]
            );

            if (! $links) {
                http_response_code(404);
                exit;
            }

            echo json_encode([
                'crashes' => array_map(fn ($link) => $link['video_link'], $links)
            ]);
            exit;
        }

        http_response_code(404);
        exit;
    }

    protected function isGetRequest(): bool
    {
        return $_SERVER['REQUEST_METHOD'] === 'GET';
    }

    protected function isPostRequest(): bool
    {
        return $_SERVER['REQUEST_METHOD'] === 'POST';
    }

    protected function getStringAfterPathPrefix(string $path, string $prefix): ?string
    {
        if (strpos($path, $prefix) !== 0) {
            return null;
        }

        $string = substr($path, strlen($prefix));

        return $string ?? null;
    }

    protected function getIntAfterPathPrefix(string $path, string $prefix): ?int
    {
        $id = (int) $this->getStringAfterPathPrefix($path, $prefix);

        return $id ?? null;
    }

    protected function protectUsingToken(?string $authHeader, array $config): void
    {
        if (! isset($config['token'])) {
            throw new RuntimeException('bad config, no token');
        }

        $token = $config['token'];

        if (! $authHeader || $authHeader !== 'Bearer ' . $token) {
            http_response_code(401);
            echo json_encode(['error' => 'Unauthorized']);
            exit;
        }
    }

    protected function getRequestBody(): ?string
    {
        return file_get_contents('php://input') ?? null;
    }

    protected function getCacheFolder(): string
    {
        return __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR;
    }
}
