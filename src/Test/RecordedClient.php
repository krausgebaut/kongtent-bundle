<?php

/*
 * This file is part of the kongtent bundle.
 *
 * (c) krausgebaut von Marcel Kraus <mail@krausgebaut.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Krausgebaut\KongtentBundle\Test;

use Krausgebaut\KongtentBundle\Client;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * kongtent out of files: `list.json` for the list, `<slug>.json` for one
 * content, `404` for everything else. Under `src/`, because a site binds it.
 */
final class RecordedClient
{
    public static function fromDirectory(string $directory): MockHttpClient
    {
        return new MockHttpClient(static function (string $method, string $url) use ($directory): MockResponse {
            $path = parse_url($url, \PHP_URL_PATH) ?: '';
            $slug = substr($path, \strlen(Client::PATH.'/'));
            $file = match (true) {
                Client::PATH === $path => 'list',
                str_starts_with($path, Client::PATH.'/') && 1 === preg_match(Client::SLUG, $slug) => $slug,
                default => null,
            };

            $recorded = null === $file ? null : rtrim($directory, '/').'/'.$file.'.json';

            if (null === $recorded || false === is_file($recorded)) {
                return new MockResponse('{"error":"not_found"}', [
                    'http_code' => 404,
                    'response_headers' => ['content-type' => 'application/json'],
                ]);
            }

            return new MockResponse((string) file_get_contents($recorded), [
                'response_headers' => ['content-type' => 'application/json'],
            ]);
        });
    }
}
