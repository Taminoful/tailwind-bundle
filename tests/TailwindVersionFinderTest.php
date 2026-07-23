<?php

/*
 * This file is part of the SymfonyCasts TailwindBundle package.
 * Copyright (c) SymfonyCasts <https://symfonycasts.com/>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfonycasts\TailwindBundle\Tests;

use PHPUnit\Framework\TestCase;
use Symfonycasts\TailwindBundle\TailwindVersionFinder;

class TailwindVersionFinderTest extends TestCase
{
    /**
     * @dataProvider versionProvider
     */
    public function testGetLatestVersion(string $version, int $expectedMajor): void
    {
        $versionDetector = new TailwindVersionFinder();
        $latestVersion = $versionDetector->latestVersionFor($version);

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
}
