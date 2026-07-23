<?php

/*
 * This file is part of the SymfonyCasts TailwindBundle package.
 * Copyright (c) SymfonyCasts <https://symfonycasts.com/>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfonycasts\TailwindBundle\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfonycasts\TailwindBundle\TailwindVersionFinder;

class TailwindVersionFinderTest extends TestCase
{
    private const TOKEN_ENV_VARS = ['GITHUB_TOKEN', 'GH_TOKEN', 'COMPOSER_AUTH', 'COMPOSER_HOME', 'HOME'];

    /** @var array<string, array{0: mixed, 1: mixed, 2: string|false}> */
    private array $envBackup = [];

    protected function setUp(): void
    {
        foreach (self::TOKEN_ENV_VARS as $name) {
            $this->envBackup[$name] = [$_SERVER[$name] ?? null, $_ENV[$name] ?? null, getenv($name)];
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->envBackup as $name => [$server, $env, $getenv]) {
            if (null !== $server) {
                $_SERVER[$name] = $server;
            } else {
                unset($_SERVER[$name]);
            }

            if (null !== $env) {
                $_ENV[$name] = $env;
            } else {
                unset($_ENV[$name]);
            }

            if (false !== $getenv) {
                putenv("$name=$getenv");
            } else {
                putenv($name);
            }
        }
    }

    /**
     * @dataProvider versionProvider
     */
    public function testGetLatestVersion(string $version, int $expectedMajor): void
    {
        $latestVersion = (new TailwindVersionFinder())->latestVersionFor($version);

        $this->assertStringStartsWith('v'.$expectedMajor.'.', $latestVersion);
    }

    public static function versionProvider(): array
    {
        return [
            'major only' => ['2', 2],
            'v-prefixed major' => ['v3', 3],
            'full version' => ['v4.2.0', 4],
            'partial version' => ['3.3', 3],
        ];
    }

    public function testThrowsOnUnparsableVersion(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot parse major version from "nope".');

        (new TailwindVersionFinder())->latestVersionFor('nope');
    }

    public function testNoAuthorizationHeaderWithoutToken(): void
    {
        $this->clearTokenEnv();

        $this->assertNull($this->captureAuthorizationHeader());
    }

    public function testUsesGitHubTokenEnvVar(): void
    {
        $this->clearTokenEnv();
        $_SERVER['GITHUB_TOKEN'] = 'secret-token';

        $this->assertSame('Bearer secret-token', $this->captureAuthorizationHeader());
    }

    public function testUsesGhTokenEnvVar(): void
    {
        $this->clearTokenEnv();
        $_SERVER['GH_TOKEN'] = 'gh-secret';

        $this->assertSame('Bearer gh-secret', $this->captureAuthorizationHeader());
    }

    public function testUsesComposerAuthEnvVar(): void
    {
        $this->clearTokenEnv();
        $_SERVER['COMPOSER_AUTH'] = json_encode(['github-oauth' => ['github.com' => 'composer-env-token']]);

        $this->assertSame('Bearer composer-env-token', $this->captureAuthorizationHeader());
    }

    public function testUsesComposerAuthJsonFile(): void
    {
        $this->clearTokenEnv();

        $composerHome = sys_get_temp_dir().'/tailwind-token-test-'.uniqid();
        mkdir($composerHome, 0777, true);
        file_put_contents(
            $composerHome.'/auth.json',
            json_encode(['github-oauth' => ['github.com' => 'composer-file-token']])
        );
        $_SERVER['COMPOSER_HOME'] = $composerHome;

        try {
            $this->assertSame('Bearer composer-file-token', $this->captureAuthorizationHeader());
        } finally {
            unlink($composerHome.'/auth.json');
            rmdir($composerHome);
        }
    }

    /**
     * Removes every token source from the environment so a test starts from a
     * known, token-less state (CI sets GITHUB_TOKEN).
     */
    private function clearTokenEnv(): void
    {
        foreach (self::TOKEN_ENV_VARS as $name) {
            unset($_SERVER[$name], $_ENV[$name]);
            putenv($name);
        }
    }

    /**
     * Runs latestVersionFor() against a mocked GitHub API and returns the
     * Authorization header that was sent (or null if none).
     */
    private function captureAuthorizationHeader(): ?string
    {
        $sentAuthorization = null;

        $client = new MockHttpClient(static function (string $method, string $url, array $options) use (&$sentAuthorization): MockResponse {
            foreach ($options['headers'] ?? [] as $header) {
                if (str_starts_with($header, 'Authorization: ')) {
                    $sentAuthorization = substr($header, \strlen('Authorization: '));
                }
            }

            return new MockResponse(json_encode([['tag_name' => 'v4.0.0']]));
        });

        (new TailwindVersionFinder($client))->latestVersionFor('4');

        return $sentAuthorization;
    }
}
