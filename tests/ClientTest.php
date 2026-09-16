<?php

/*
 * This file is part of the kongtent bundle.
 *
 * (c) krausgebaut von Marcel Kraus <mail@krausgebaut.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Krausgebaut\KongtentBundle\Tests;

use Krausgebaut\KongtentBundle\Client;
use Krausgebaut\KongtentBundle\Content;
use Krausgebaut\KongtentBundle\Markdown;
use Krausgebaut\KongtentBundle\Reader;
use Krausgebaut\KongtentBundle\Test\RecordedClient;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\AbstractLogger;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Psr\Log\NullLogger;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\NullAdapter;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\Cache\CacheInterface;

final class ClientTest extends TestCase
{
    public function testTheListCarriesSummariesAndTheContentCarriesItsText(): void
    {
        $client = $this->client();

        $summaries = $client->all();

        self::assertCount(2, $summaries);
        self::assertSame([], $summaries[0]->blocks);
        self::assertNotSame('', $summaries[0]->teaser);

        $whole = $client->one($summaries[0]->slug);

        self::assertNotSame([], $whole->blocks);
    }

    public function testAnUnknownSlugFindsNothing(): void
    {
        self::assertNull($this->client()->one('does-not-exist'));
    }

    #[DataProvider('slugsKongtentCannotHave')]
    public function testASlugKongtentCannotHaveAsksNobody(string $slug): void
    {
        $calls = 0;

        self::assertNull($this->client($this->answering($calls, [], null), new ArrayAdapter())->one($slug));
        self::assertSame(0, $calls);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function slugsKongtentCannotHave(): iterable
    {
        yield 'a colon' => ['a:b'];
        yield 'a query' => ['x?limit=1'];
        yield 'a way up' => ['../foo'];
        yield 'upper case' => ['Upper'];
    }

    public function testTheKeyTravelsInTheHeader(): void
    {
        $seen = null;
        $http = $this->seeing($seen);

        $this->client($http)->all();

        self::assertSame(['X-Api-Key: secret'], $seen['options']['normalized_headers']['x-api-key'] ?? null);
        self::assertStringNotContainsString('secret', $seen['url']);
    }

    public function testACallEndsWhenQuietOrSlow(): void
    {
        $seen = null;
        $http = $this->seeing($seen);

        $this->client($http)->all();

        self::assertSame([5.0, 10.0], [$seen['options']['timeout'] ?? null, $seen['options']['max_duration'] ?? null]);
    }

    public function testARefusalIsNotAnEmptyList(): void
    {
        $http = new MockHttpClient(static fn (): MockResponse => new MockResponse(
            '{"error":"unauthorized"}',
            ['http_code' => 401, 'response_headers' => ['content-type' => 'application/json']],
        ));

        $this->expectException(\RuntimeException::class);

        $this->client($http)->all();
    }

    public function testAnAnswerWithoutTheListIsAFault(): void
    {
        $http = new MockHttpClient(static fn (): MockResponse => new MockResponse(
            '{"articles":[]}',
            ['response_headers' => ['content-type' => 'application/json']],
        ));

        $this->expectException(\RuntimeException::class);

        $this->client($http)->all();
    }

    public function testAnUnreachableSystemIsAFault(): void
    {
        $http = new MockHttpClient(static function (): MockResponse {
            throw new TransportException('broken');
        });

        $this->expectException(\RuntimeException::class);

        $this->client($http)->all();
    }

    public function testAnAnswerIsHeldForAsLongAsKongtentSays(): void
    {
        $calls = 0;
        $http = $this->answering($calls, [$this->list(), $this->list()], 'max-age=60, private');
        $client = $this->client($http, new ArrayAdapter());

        $client->all();
        $client->all();

        self::assertSame(1, $calls);
    }

    public function testAnAnswerKongtentDoesNotLetBeHeldIsAskedForAgain(): void
    {
        foreach ([null, 'max-age=0, private'] as $cacheControl) {
            $calls = 0;
            $http = $this->answering($calls, [$this->list(), $this->list()], $cacheControl);
            $client = $this->client($http, new ArrayAdapter());

            $client->all();
            $client->all();

            self::assertSame(2, $calls, \sprintf('With "%s" the answer was held.', $cacheControl ?? 'nothing'));
        }
    }

    public function testAFreshAnswerComesBeforeTheLastGoodOne(): void
    {
        $calls = 0;
        $client = $this->client($this->answering($calls, [$this->list(), $this->list(1)], null), new ArrayAdapter());

        self::assertCount(2, $client->all());
        self::assertCount(1, $client->all());
    }

    public function testTheLastGoodAnswerStandsInWhenKongtentCannotBeRead(): void
    {
        $calls = 0;
        $logger = $this->recordingLogger();
        $http = $this->answering($calls, [$this->list(), new TransportException('broken')], null);
        $client = $this->client($http, new ArrayAdapter(), $logger);

        $fresh = $client->all();
        $standing = $client->all();

        self::assertSame(2, $calls, 'The second call did not ask kongtent at all.');
        self::assertSame($this->slugs($fresh), $this->slugs($standing));
        self::assertCount(1, $logger->errors);
    }

    public function testAnAnswerThatCannotBeReadNeverReplacesTheLastGoodOne(): void
    {
        $calls = 0;
        $logger = $this->recordingLogger();
        $client = $this->client(
            $this->answering($calls, [$this->list(), '{"data":[]}', new TransportException('broken')], null),
            new ArrayAdapter(),
            $logger,
        );

        $fresh = $client->all();

        self::assertSame($this->slugs($fresh), $this->slugs($client->all()));
        self::assertSame($this->slugs($fresh), $this->slugs($client->all()));
        self::assertCount(2, $logger->errors);
    }

    public function testAMissingContentLeavesNothingBehind(): void
    {
        $cache = new ArrayAdapter();

        self::assertNull($this->client(null, $cache)->one('by-chance'));
        self::assertSame([], $cache->getValues());
    }

    public function testAMissingContentClearsTheCopyItHad(): void
    {
        $calls = 0;
        $client = $this->client($this->answering($calls, [
            (string) file_get_contents(__DIR__.'/fixtures/first-article.json'),
            new MockResponse('{"error":"not_found"}', [
                'http_code' => 404,
                'response_headers' => ['content-type' => 'application/json'],
            ]),
            new TransportException('broken'),
        ], null), new ArrayAdapter());

        self::assertNotNull($client->one('first-article'));
        self::assertNull($client->one('first-article'));

        $this->expectException(\RuntimeException::class);

        $client->one('first-article');
    }

    public function testAFreshAnswerIsReadOnce(): void
    {
        $content = json_decode((string) file_get_contents(__DIR__.'/fixtures/first-article.json'), true);
        $content['blocks'][] = ['type' => 'poll', 'question' => 'Which one?'];
        $calls = 0;
        $logger = $this->recordingLogger();
        $http = $this->answering($calls, [(string) json_encode($content)], null);
        $client = $this->client($http, new ArrayAdapter(), readerLogger: $logger);

        $client->one('first-article');

        self::assertCount(1, $logger->errors);
    }

    public function testTwoKeysNeverShareAnAnswer(): void
    {
        $calls = 0;
        $http = $this->answering($calls, [$this->list(), $this->list()], 'max-age=60, private');
        $cache = new ArrayAdapter();

        $this->client($http, $cache, key: 'secret')->all();
        $this->client($http, $cache, key: 'other')->all();

        self::assertSame(2, $calls);
    }

    /**
     * @param array{url: string, options: array<string, mixed>}|null $seen
     */
    private function seeing(?array &$seen): MockHttpClient
    {
        $answer = static function (string $method, string $url, array $options) use (&$seen): MockResponse {
            $seen = ['url' => $url, 'options' => $options];

            return new MockResponse('{"content":[]}', [
                'response_headers' => ['content-type' => 'application/json'],
            ]);
        };

        return new MockHttpClient($answer);
    }

    /**
     * One answer per call, in order: a body, a response as it is, or an
     * exception to throw.
     *
     * @param list<string|MockResponse|\Throwable> $answers
     */
    private function answering(int &$calls, array $answers, ?string $cacheControl): MockHttpClient
    {
        return new MockHttpClient(static function () use (&$calls, $answers, $cacheControl): MockResponse {
            $answer = $answers[$calls++] ?? throw new \LogicException('The interface was asked once too often.');

            if ($answer instanceof \Throwable) {
                throw $answer;
            }

            if ($answer instanceof MockResponse) {
                return $answer;
            }

            $headers = ['content-type' => 'application/json'];

            if (null !== $cacheControl) {
                $headers['cache-control'] = $cacheControl;
            }

            return new MockResponse($answer, ['response_headers' => $headers]);
        });
    }

    private function list(?int $length = null): string
    {
        $list = json_decode((string) file_get_contents(__DIR__.'/fixtures/list.json'), true);

        if (null !== $length) {
            $list['content'] = \array_slice($list['content'], 0, $length);
        }

        return (string) json_encode($list);
    }

    /**
     * @param list<Content> $contents
     *
     * @return list<string>
     */
    private function slugs(array $contents): array
    {
        return array_map(static fn (Content $one): string => $one->slug, $contents);
    }

    private function recordingLogger(): AbstractLogger
    {
        return new class extends AbstractLogger {
            /** @var list<string> */
            public array $errors = [];

            public function log($level, \Stringable|string $message, array $context = []): void
            {
                if (LogLevel::ERROR === $level) {
                    $this->errors[] = (string) $message;
                }
            }
        };
    }

    private function client(
        ?MockHttpClient $http = null,
        (CacheInterface&CacheItemPoolInterface)|null $cache = null,
        ?LoggerInterface $logger = null,
        string $key = 'secret',
        ?LoggerInterface $readerLogger = null,
    ): Client {
        return new Client(
            $http ?? RecordedClient::fromDirectory(__DIR__.'/fixtures'),
            new Reader(new Markdown(), $readerLogger ?? new NullLogger()),
            $cache ?? new NullAdapter(),
            $logger ?? new NullLogger(),
            'https://kongtent.example',
            $key,
        );
    }
}
