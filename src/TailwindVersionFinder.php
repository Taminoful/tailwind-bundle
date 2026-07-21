<?php

/*
 * This file is part of the SymfonyCasts TailwindBundle package.
 * Copyright (c) SymfonyCasts <https://symfonycasts.com/>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfonycasts\TailwindBundle;

use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Finds the latest Tailwind CSS version by major version.
 *
 * @author Kevin Bond <kevinbond@gmail.com>
 *
 * @internal
 */
final class TailwindVersionFinder
{
    private HttpClientInterface $httpClient;

    public function __construct(?HttpClientInterface $httpClient = null)
    {
        $this->httpClient = $httpClient ?? HttpClient::create();
    }

    /**
     * Finds the latest release sharing the major version of the given
     * version string (e.g. "4", "v4.2.0", "3.3" all resolve their major).
     */
    public function latestVersionFor(string $version): string
    {
        if (!preg_match('/^v?(\d+)/', $version, $matches)) {
            throw new \InvalidArgumentException(\sprintf('Cannot parse major version from "%s".', $version));
        }

        $majorVersion = (int) $matches[1];

        foreach ($this->tags() as $tag) {
            if (str_starts_with($tag, "v$majorVersion.")) {
                return $tag;
            }
        }

        throw new \RuntimeException(\sprintf('Could not find a Tailwind CSS %d.x release.', $majorVersion));
    }

    /**
     * @return string[]
     */
    private function tags(int $page = 1): iterable
    {
        $options = ['query' => ['page' => $page]];

        // authenticate the call when a GitHub token is available to avoid the
        // low rate limit applied to anonymous requests (60 requests per hour,
        // shared by IP address)
        if (null !== $token = self::githubToken()) {
            $options['auth_bearer'] = $token;
        }

        $releases = $this->httpClient
            ->request('GET', 'https://api.github.com/repos/tailwindlabs/tailwindcss/releases', $options)
            ->toArray()
        ;

        if (!$releases) {
            return;
        }

        foreach ($releases as $release) {
            yield $release['tag_name'];
        }

        yield from $this->tags(++$page);
    }

    /**
     * Looks for a GitHub token, first in the usual environment variables, then
     * in Composer's authentication config (the "github-oauth" token developers
     * commonly already have set up).
     */
    private static function githubToken(): ?string
    {
        foreach (['GITHUB_TOKEN', 'GH_TOKEN'] as $name) {
            if (null !== $token = self::readEnv($name)) {
                return $token;
            }
        }

        return self::composerGithubToken();
    }

    private static function composerGithubToken(): ?string
    {
        $candidates = [];

        if (null !== $composerAuth = self::readEnv('COMPOSER_AUTH')) {
            $candidates[] = $composerAuth;
        }

        foreach (self::composerAuthFiles() as $file) {
            if (is_file($file) && false !== $contents = @file_get_contents($file)) {
                $candidates[] = $contents;
            }
        }

        foreach ($candidates as $json) {
            $data = json_decode($json, true);
            $token = $data['github-oauth']['github.com'] ?? null;

            if (\is_string($token) && '' !== $token) {
                return $token;
            }
        }

        return null;
    }

    /**
     * @return string[]
     */
    private static function composerAuthFiles(): array
    {
        if (null !== $composerHome = self::readEnv('COMPOSER_HOME')) {
            return [$composerHome.'/auth.json'];
        }

        if (null !== $home = self::readEnv('HOME')) {
            return [$home.'/.composer/auth.json', $home.'/.config/composer/auth.json'];
        }

        return [];
    }

    private static function readEnv(string $name): ?string
    {
        $value = $_SERVER[$name] ?? $_ENV[$name] ?? getenv($name);

        return \is_string($value) && '' !== $value ? $value : null;
    }
}
