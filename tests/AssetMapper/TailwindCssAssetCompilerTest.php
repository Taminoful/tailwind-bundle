<?php

/*
 * This file is part of the SymfonyCasts TailwindBundle package.
 * Copyright (c) SymfonyCasts <https://symfonycasts.com/>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfonycasts\TailwindBundle\Tests\AssetMapper;

use PHPUnit\Framework\TestCase;
use Symfony\Component\AssetMapper\AssetMapperInterface;
use Symfony\Component\AssetMapper\MappedAsset;
use Symfony\Component\Filesystem\Filesystem;
use Symfonycasts\TailwindBundle\AssetMapper\TailwindCssAssetCompiler;
use Symfonycasts\TailwindBundle\TailwindBuilder;

class TailwindCssAssetCompilerTest extends TestCase
{
    private string $varDir;

    protected function setUp(): void
    {
        $this->varDir = __DIR__.'/../fixtures/var/tailwind';
        $fs = new Filesystem();
        $fs->mkdir($this->varDir);
        $fs->dumpFile($this->varDir.'/app.built.css', 'output content from Tailwind');
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->varDir);
    }

    public function testCompile(): void
    {
        $projectDir = realpath(__DIR__.'/../fixtures');
        $builder = new TailwindBuilder(
            $projectDir,
            [$projectDir.'/assets/styles/app.css'],
            $this->varDir,
            binaryVersion: 'v3.4.17',
        );

        $compiler = new TailwindCssAssetCompiler($builder);
        $asset1 = new MappedAsset('styles/other.css', __DIR__.'/../fixtures/assets/styles/other.css');
        // extra ../ added so the path doesn't exactly match the string used above
        $asset2 = new MappedAsset('styles/app.css', __DIR__.'/../../tests/fixtures/assets/styles/app.css');
        $this->assertFalse($compiler->supports($asset1));
        $this->assertTrue($compiler->supports($asset2));

        $this->assertSame('output content from Tailwind', $compiler->compile('input content', $asset2, $this->createMock(AssetMapperInterface::class)));
    }
}
