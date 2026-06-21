<?php

/*
 * This file is part of the SymfonyCasts TailwindBundle package.
 * Copyright (c) SymfonyCasts <https://symfonycasts.com/>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfonycasts\TailwindBundle\Tests\fixtures;

use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpKernel\Kernel;
use Symfonycasts\TailwindBundle\SymfonycastsTailwindBundle;
use Symfonycasts\TailwindBundle\TailwindVersionFinder;

class TailwindTestKernel extends Kernel
{
    use MicroKernelTrait;

    /**
     * @param string[]|null $versionFinderReleases tailwind release tags the mocked finder should return (newest first),
     *                                             or null to use the real (network) finder
     */
    public function __construct(
        string $environment,
        bool $debug,
        private readonly array $tailwindConfig = [],
        private readonly ?string $projectDir = null,
        private readonly ?array $versionFinderReleases = null,
    ) {
        parent::__construct($environment, $debug);
    }

    public function getProjectDir(): string
    {
        return $this->projectDir ?? parent::getProjectDir();
    }

    public function boot(): void
    {
        parent::boot();

        if (null !== $this->versionFinderReleases) {
            $this->container->set('.tailwind.version_finder', new TailwindVersionFinder($this->mockHttpClient()));
        }
    }

    public function registerBundles(): array
    {
        return [
            new FrameworkBundle(),
            new SymfonycastsTailwindBundle(),
        ];
    }

    protected function configureContainer(ContainerBuilder $container, LoaderInterface $loader): void
    {
        $container->loadFromExtension('framework', [
            'secret' => 'foo',
            'test' => true,
            'http_method_override' => true,
            'asset_mapper' => [
                'paths' => [
                    __DIR__.'/assets',
                ],
            ],
            'handle_all_throwables' => true,
            'php_errors' => [
                'log' => true,
            ],
        ]);

        $container->loadFromExtension('symfonycasts_tailwind', $this->tailwindConfig ?: [
            'input_css' => [__DIR__.'/assets/styles/app.css'],
            'binary_version' => 'v3.4.17',
        ]);

        if (null !== $this->versionFinderReleases) {
            // declared synthetic so the real instance (with a mock client) can be set in boot()
            $container->register('.tailwind.version_finder', TailwindVersionFinder::class)
                ->setSynthetic(true)
                ->setPublic(true);
        }
    }

    /**
     * A client that mimics the GitHub releases API without hitting the network.
     */
    private function mockHttpClient(): MockHttpClient
    {
        $releases = array_map(static fn (string $tag) => ['tag_name' => $tag], $this->versionFinderReleases);

        return new MockHttpClient(static function (string $method, string $url) use ($releases): MockResponse {
            parse_str(parse_url($url, \PHP_URL_QUERY) ?: '', $query);

            // first page lists the releases, later pages are empty to stop pagination
            return new MockResponse(
                json_encode('1' === ($query['page'] ?? '1') ? $releases : []),
                ['response_headers' => ['Content-Type' => 'application/json']],
            );
        });
    }
}
