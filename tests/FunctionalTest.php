<?php

/*
 * This file is part of the SymfonyCasts TailwindBundle package.
 * Copyright (c) SymfonyCasts <https://symfonycasts.com/>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfonycasts\TailwindBundle\Tests;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\AssetMapper\AssetMapperInterface;
use Symfony\Component\AssetMapper\MappedAsset;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfonycasts\TailwindBundle\Tests\fixtures\TailwindTestKernel;

class FunctionalTest extends KernelTestCase
{
    private const BUILT_CSS_DIR = __DIR__.'/../var/tailwind';

    protected function setUp(): void
    {
        (new Filesystem())->remove(__DIR__.'/../var');
    }

    protected static function createKernel(array $options = []): KernelInterface
    {
        return new TailwindTestKernel(
            $options['environment'] ?? 'test',
            $options['debug'] ?? false,
            $options['tailwind_config'] ?? [],
        );
    }

    public function testExceptionThrownIfFileNotBuiltInNonTestEnv(): void
    {
        self::bootKernel(['environment' => 'dev']);
        $assetMapper = self::getContainer()->get('asset_mapper');
        \assert($assetMapper instanceof AssetMapperInterface);

        $this->expectException(\RuntimeException::class);
        $assetMapper->getAsset('styles/app.css');
    }

    public function testExceptionNotThrownIfFileNotBuiltInTestEnv(): void
    {
        $this->expectNotToPerformAssertions();

        self::bootKernel(['environment' => 'test']);
        $assetMapper = self::getContainer()->get('asset_mapper');
        \assert($assetMapper instanceof AssetMapperInterface);

        $assetMapper->getAsset('styles/app.css');
    }

    public function testBuiltCSSFileIsUsedWithV3(): void
    {
        (new Filesystem())->dumpFile(self::BUILT_CSS_DIR.'/app.built.css', <<<EOF
        body {
            padding: 17px;
            background-image: url('../images/penguin.png');
        }
        EOF);

        self::bootKernel();
        $assetMapper = self::getContainer()->get('asset_mapper');
        \assert($assetMapper instanceof AssetMapperInterface);

        $asset = $assetMapper->getAsset('styles/app.css');
        $this->assertInstanceOf(MappedAsset::class, $asset);
        $this->assertStringContainsString('padding: 17px', $asset->content);
        // verify the core CSS compiler that handles url() was executed
        $this->assertMatchesRegularExpression('/penguin-[a-f0-9]{32}|[\w\d-]{7}\.png/', $asset->content);
    }

    public function testBuiltCSSFileIsUsedWithV4(): void
    {
        (new Filesystem())->dumpFile(self::BUILT_CSS_DIR.'/v4.built.css', <<<EOF
        body {
            background-color: black;
        }
        EOF);

        self::bootKernel(['tailwind_config' => [
            'input_css' => [__DIR__.'/fixtures/assets/styles/v4.css'],
            'binary_version' => 'v4.0.7',
        ]]);
        $assetMapper = self::getContainer()->get('asset_mapper');
        \assert($assetMapper instanceof AssetMapperInterface);

        $asset = $assetMapper->getAsset('styles/v4.css');
        $this->assertInstanceOf(MappedAsset::class, $asset);
        $this->assertStringContainsString('background-color: black', $asset->content);
    }
}
