<?php

/*
 * This file is part of the SymfonyCasts TailwindBundle package.
 * Copyright (c) SymfonyCasts <https://symfonycasts.com/>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfonycasts\TailwindBundle\Tests\Command;

use Symfony\Component\Yaml\Yaml;

class TailwindInitCommandTest extends BaseCommandTestCase
{
    public function testInit(): void
    {
        $cssFile = $this->tempDir.'/assets/styles/app.css';
        $this->filesystem->dumpFile($cssFile, '/* existing styles */');
        // config/packages always exists in a real Symfony app
        $this->filesystem->mkdir($this->tempDir.'/config/packages');

        self::bootKernel([
            'project_dir' => $this->tempDir,
            'version_finder_releases' => ['v4.1.0', 'v3.4.20', 'v3.4.17'],
            'tailwind_config' => [
                'input_css' => [$cssFile],
                'binary_version' => 'v3.4.17',
            ],
        ]);

        $configFile = $this->tempDir.'/config/packages/symfonycasts_tailwind.yaml';
        $this->assertFileDoesNotExist($configFile);
        $this->assertStringNotContainsString('@import "tailwindcss";', file_get_contents($cssFile));

        $tester = $this->commandTester('tailwind:init');
        // 1) not managing own binary, 2) major version 4 (v4 needs no binary to init)
        $tester->setInputs(['no', '4']);
        $tester->execute([], ['interactive' => true]);

        $tester->assertCommandIsSuccessful();

        $config = Yaml::parseFile($configFile);
        $this->assertSame('v4.1.0', $config['symfonycasts_tailwind']['binary_version']);

        $css = file_get_contents($cssFile);
        $this->assertStringContainsString('@import "tailwindcss";', $css);
        $this->assertStringContainsString('/* existing styles */', $css);
    }

    public function testMustBeRunInteractively(): void
    {
        self::bootKernel([
            'project_dir' => $this->tempDir,
            'tailwind_config' => [
                'input_css' => [self::FIXTURES_DIR.'/assets/styles/app.css'],
                'binary_version' => 'v3.4.17',
            ],
        ]);

        $tester = $this->commandTester('tailwind:init');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('tailwind:init command must be run interactively');

        $tester->execute([], ['interactive' => false]);
    }
}
