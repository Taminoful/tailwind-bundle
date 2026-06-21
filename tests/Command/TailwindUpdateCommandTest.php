<?php

/*
 * This file is part of the SymfonyCasts TailwindBundle package.
 * Copyright (c) SymfonyCasts <https://symfonycasts.com/>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfonycasts\TailwindBundle\Tests\Command;

use Symfony\Component\Yaml\Yaml;

class TailwindUpdateCommandTest extends BaseCommandTestCase
{
    public function testUpdate(): void
    {
        $configFile = $this->tempDir.'/config/packages/symfonycasts_tailwind.yaml';
        $this->filesystem->dumpFile($configFile, Yaml::dump([
            'symfonycasts_tailwind' => ['binary_version' => 'v3.4.17'],
        ]));

        self::bootKernel([
            'project_dir' => $this->tempDir,
            'version_finder_releases' => ['v4.1.0', 'v3.4.20', 'v3.4.17'],
            'tailwind_config' => [
                'binary_version' => 'v3.4.17',
                'input_css' => [self::FIXTURES_DIR.'/assets/styles/app.css'],
            ],
        ]);

        $this->assertSame('v3.4.17', Yaml::parseFile($configFile)['symfonycasts_tailwind']['binary_version']);

        $tester = $this->commandTester('tailwind:update');
        $tester->execute([]);

        $tester->assertCommandIsSuccessful();
        $this->assertStringContainsString('updated from v3.4.17 to v3.4.20', $tester->getDisplay());
        $this->assertSame('v3.4.20', Yaml::parseFile($configFile)['symfonycasts_tailwind']['binary_version']);
    }

    public function testCannotUpdateWhenManagingOwnBinary(): void
    {
        self::bootKernel([
            'project_dir' => $this->tempDir,
            'tailwind_config' => [
                'binary' => 'node_modules/.bin/tailwindcss',
                'input_css' => [self::FIXTURES_DIR.'/assets/styles/app.css'],
            ],
        ]);

        $tester = $this->commandTester('tailwind:update');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('you are managing your own Tailwind CSS binary');

        $tester->execute([]);
    }

    public function testCannotUpdateWithoutCurrentVersion(): void
    {
        self::bootKernel([
            'project_dir' => $this->tempDir,
            'tailwind_config' => [
                // no binary_version configured, so the current version is unknown
                'input_css' => [self::FIXTURES_DIR.'/assets/styles/app.css'],
            ],
        ]);

        $tester = $this->commandTester('tailwind:update');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cannot determine the current version');

        $tester->execute([]);
    }

    public function testCannotUpdateWithoutABundleConfigFile(): void
    {
        // a newer release is available, but there is no config file to update
        self::bootKernel([
            'project_dir' => $this->tempDir,
            'version_finder_releases' => ['v3.4.20', 'v3.4.17'],
            'tailwind_config' => [
                'binary_version' => 'v3.4.17',
                'input_css' => [self::FIXTURES_DIR.'/assets/styles/app.css'],
            ],
        ]);

        $tester = $this->commandTester('tailwind:update');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('non-standard Symfony setup');

        $tester->execute([]);
    }
}
