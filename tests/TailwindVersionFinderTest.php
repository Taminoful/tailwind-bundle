<?php

/*
 * This file is part of the SymfonyCasts TailwindBundle package.
 * Copyright (c) SymfonyCasts <https://symfonycasts.com/>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfonycasts\TailwindBundle\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\HttpClient;
use Symfonycasts\TailwindBundle\TailwindVersionFinder;

class TailwindVersionFinderTest extends TestCase
{
    /**
     * @dataProvider majorVersionProvider
     */
    public function testGetLatestVersion(int $majorVersion): void
    {
        $options = [];

        if ($_SERVER['GITHUB_TOKEN'] ?? null) {
            $options['auth_bearer'] = $_SERVER['GITHUB_TOKEN'];
        }

        $versionDetector = new TailwindVersionFinder(HttpClient::create($options));
        $latestVersion = $versionDetector->latestVersionFor($majorVersion);

        $this->assertStringStartsWith('v'.$majorVersion.'.', $latestVersion);
    }

    public static function majorVersionProvider(): array
    {
        return [
            [2],
            [3],
            [4],
        ];
    }
}
