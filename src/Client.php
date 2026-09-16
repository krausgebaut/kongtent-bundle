<?php

/*
 * This file is part of the kongtent bundle.
 *
 * (c) krausgebaut von Marcel Kraus <mail@krausgebaut.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Krausgebaut\KongtentBundle;

use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * What kongtent answers, held as long as it says and stood in for by the last
 * good answer when it cannot be read.
 */
final readonly class Client
{
    public const string PATH = '/api/v1/content';
    public const string SLUG = '/^[a-z0-9]+(-[a-z0-9]+)*$/';

    private const string KEY_HEADER = 'X-Api-Key';
    private const string LAST_GOOD = '.last-good';
    private const float QUIET_SECONDS = 5.0;
    private const float TOTAL_SECONDS = 10.0;

    public function __construct(
        private HttpClientInterface $http,
        private Reader $reader,
        private CacheInterface&CacheItemPoolInterface $cache,
        private LoggerInterface $logger,
        private string $baseUrl,
        private string $apiKey,
    ) {
    }

    /**
     * Every visible content of the channel, newest first, without blocks.
     *
     * @return list<Content>
     */
    public function all(): array
    {
        return $this->get(self::PATH, function (?array $payload): array {
            // An empty list is a state, a missing one is a fault.
            if (false === \is_array($payload['content'] ?? null)) {
                throw new \RuntimeException('The answer carries no list of contents.');
            }

            return $this->reader->toList($payload['content']);
        });
    }

    public function one(string $slug): ?Content
    {
        // Anything else would carry a query or a way up onto kongtent.
        if (1 !== preg_match(self::SLUG, $slug)) {
            return null;
        }

        return $this->get(
            self::PATH.'/'.$slug,
            fn (?array $payload): ?Content => null === $payload ? null : $this->reader->toContent($payload),
            allowMissing: true,
        );
    }

    /**
     * Only an answer that could be read becomes the last good one.
     *
     * @template T
     *
     * @param \Closure(array<string, mixed>|null): T $read
     *
     * @return T
     */
    private function get(string $path, \Closure $read, bool $allowMissing = false): mixed
    {
        $key = $this->cacheKey($path);
        $fresh = null;

        try {
            $answer = $this->cache->get(
                $key,
                function (CacheItemInterface $item) use ($path, $allowMissing, $key, $read, &$fresh): array {
                    $fresh = $this->fetch($path, $allowMissing, $item, $key, $read);

                    return $fresh['answer'];
                },
            );

            // Asked just now, the answer was read on its way in already.
            return null === $fresh ? $read($answer['payload']) : $fresh['value'];
        } catch (\RuntimeException $problem) {
            return $this->lastGood($key, $path, $problem, $read);
        }
    }

    /**
     * @return array{answer: array{payload: array<string, mixed>|null}, value: mixed}
     */
    private function fetch(
        string $path,
        bool $allowMissing,
        CacheItemInterface $item,
        string $key,
        \Closure $read,
    ): array {
        try {
            $response = $this->http->request('GET', rtrim($this->baseUrl, '/').$path, [
                'headers' => [self::KEY_HEADER => $this->apiKey],
                // `timeout` ends a quiet connection, `max_duration` a slow one.
                'timeout' => self::QUIET_SECONDS,
                'max_duration' => self::TOTAL_SECONDS,
            ]);

            $status = $response->getStatusCode();
            $item->expiresAfter($this->maxAge($response->getHeaders(throw: false)));

            if (404 === $status && $allowMissing) {
                // No copy, so a content taken back never returns through one.
                $this->cache->deleteItem($key.self::LAST_GOOD);

                return ['answer' => ['payload' => null], 'value' => $read(null)];
            }

            if (200 !== $status) {
                throw new \RuntimeException(\sprintf('The interface answered %d to "%s".', $status, $path));
            }

            $payload = $response->toArray();
        } catch (ExceptionInterface $problem) {
            throw new \RuntimeException(\sprintf('The interface could not be read at "%s".', $path), 0, $problem);
        }

        $value = $read($payload);
        $this->cache->save($this->cache->getItem($key.self::LAST_GOOD)->set(['payload' => $payload]));

        return ['answer' => ['payload' => $payload], 'value' => $value];
    }

    private function lastGood(string $key, string $path, \RuntimeException $problem, \Closure $read): mixed
    {
        $kept = $this->cache->getItem($key.self::LAST_GOOD);

        if (false === $kept->isHit()) {
            throw $problem;
        }

        $value = $read($kept->get()['payload'] ?? null);

        $this->logger->error('The interface could not be read, the last good answer stands in.', [
            'path' => $path,
            'exception' => $problem,
        ]);

        return $value;
    }

    /**
     * One entry per address and key, so another kongtent never answers from it.
     */
    private function cacheKey(string $path): string
    {
        return \sprintf(
            'kongtent.%s.%s',
            hash('xxh128', $this->baseUrl."\0".$this->apiKey),
            str_replace('/', '.', trim($path, '/')),
        );
    }

    /**
     * Without a `max-age`, nothing is held – the number is kongtent's.
     *
     * @param array<string, list<string>> $headers
     */
    private function maxAge(array $headers): int
    {
        $control = implode(',', $headers['cache-control'] ?? []);

        return 1 === preg_match('/max-age=(\d+)/', $control, $found) ? (int) $found[1] : 0;
    }
}
