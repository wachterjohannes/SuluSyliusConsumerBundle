<?php

declare(strict_types=1);

/*
 * This file is part of Sulu.
 *
 * (c) MASSIVE ART WebServices GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Sulu\Bundle\SyliusConsumerBundle\Tests\Unit\Routing;

use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Sulu\Bundle\HttpCacheBundle\CacheLifetime\CacheLifetimeResolverInterface;
use Sulu\Bundle\SyliusConsumerBundle\Controller\Product\WebsiteProductController;
use Sulu\Bundle\SyliusConsumerBundle\Model\Content\ContentInterface;
use Sulu\Bundle\SyliusConsumerBundle\Model\Product\Exception\ProductNotFoundException;
use Sulu\Bundle\SyliusConsumerBundle\Model\Product\ProductInterface;
use Sulu\Bundle\SyliusConsumerBundle\Model\Product\Query\FindPublishedProductQuery;
use Sulu\Bundle\SyliusConsumerBundle\Model\RoutableResource\RoutableResource;
use Sulu\Bundle\SyliusConsumerBundle\Model\RoutableResource\RoutableResourceInterface;
use Sulu\Bundle\SyliusConsumerBundle\Routing\ProductRouteDefaultsProvider;
use Sulu\Component\Content\Metadata\Factory\StructureMetadataFactoryInterface;
use Sulu\Component\Content\Metadata\StructureMetadata;
use Symfony\Component\Messenger\MessageBusInterface;

class ProductRouteDefaultsProviderTest extends TestCase
{
    public function testGetByEntity(): void
    {
        $messageBus = $this->prophesize(MessageBusInterface::class);
        $factory = $this->prophesize(StructureMetadataFactoryInterface::class);
        $cacheLifetimeResolver = $this->prophesize(CacheLifetimeResolverInterface::class);

        $provider = new ProductRouteDefaultsProvider(
            $messageBus->reveal(),
            $factory->reveal(),
            $cacheLifetimeResolver->reveal()
        );

        $product = $this->prophesize(ProductInterface::class);
        $content = $this->prophesize(ContentInterface::class);
        $content->getType()->willReturn('default');
        $product->getContent()->willReturn($content);

        $messageBus->dispatch(
            Argument::that(
                function (FindPublishedProductQuery $query) {
                    return 'product-1' === $query->getCode() && 'en' === $query->getLocale();
                }
            )
        )->willReturn($product->reveal())->shouldBeCalled();

        $metadata = $this->prophesize(StructureMetadata::class);
        $metadata->getController()->willReturn(WebsiteProductController::class);
        $metadata->getView()->willReturn('templates/product');
        $metadata->getCacheLifetime()->willReturn(['type' => 'seconds', 'value' => 3600]);

        $factory->getStructureMetadata(ProductInterface::RESOURCE_KEY, 'default')->willReturn($metadata->reveal());

        $cacheLifetimeResolver->supports('seconds', 3600)->willReturn(true);
        $cacheLifetimeResolver->resolve('seconds', 3600)->willReturn(3600);

        $defaults = $provider->getByEntity(RoutableResourceInterface::class, 'product-1', 'en');
        $this->assertEquals(
            [
                'object' => $product->reveal(),
                'view' => 'templates/product',
                '_cacheLifetime' => 3600,
                '_controller' => WebsiteProductController::class,
            ],
            $defaults
        );
    }

    public function testIsPublished(): void
    {
        $messageBus = $this->prophesize(MessageBusInterface::class);
        $factory = $this->prophesize(StructureMetadataFactoryInterface::class);
        $cacheLifetimeResolver = $this->prophesize(CacheLifetimeResolverInterface::class);

        $provider = new ProductRouteDefaultsProvider(
            $messageBus->reveal(),
            $factory->reveal(),
            $cacheLifetimeResolver->reveal()
        );

        $product = $this->prophesize(ProductInterface::class);

        $messageBus->dispatch(
            Argument::that(
                function (FindPublishedProductQuery $query) {
                    return 'product-1' === $query->getCode() && 'en' === $query->getLocale();
                }
            )
        )->willReturn($product->reveal())->shouldBeCalled();

        $this->assertTrue($provider->isPublished(RoutableResourceInterface::class, 'product-1', 'en'));
    }

    public function testIsNotPublished(): void
    {
        $messageBus = $this->prophesize(MessageBusInterface::class);
        $factory = $this->prophesize(StructureMetadataFactoryInterface::class);
        $cacheLifetimeResolver = $this->prophesize(CacheLifetimeResolverInterface::class);

        $provider = new ProductRouteDefaultsProvider(
            $messageBus->reveal(),
            $factory->reveal(),
            $cacheLifetimeResolver->reveal()
        );

        $messageBus->dispatch(
            Argument::that(
                function (FindPublishedProductQuery $query) {
                    return 'product-1' === $query->getCode() && 'en' === $query->getLocale();
                }
            )
        )->willThrow(new ProductNotFoundException('product-1'))->shouldBeCalled();

        $this->assertFalse($provider->isPublished(RoutableResourceInterface::class, 'product-1', 'en'));
    }

    public function testSupports(): void
    {
        $messageBus = $this->prophesize(MessageBusInterface::class);
        $factory = $this->prophesize(StructureMetadataFactoryInterface::class);
        $cacheLifetimeResolver = $this->prophesize(CacheLifetimeResolverInterface::class);

        $provider = new ProductRouteDefaultsProvider(
            $messageBus->reveal(),
            $factory->reveal(),
            $cacheLifetimeResolver->reveal()
        );

        $this->assertTrue($provider->supports(RoutableResource::class));
    }

    public function testNoSupports(): void
    {
        $messageBus = $this->prophesize(MessageBusInterface::class);
        $factory = $this->prophesize(StructureMetadataFactoryInterface::class);
        $cacheLifetimeResolver = $this->prophesize(CacheLifetimeResolverInterface::class);

        $provider = new ProductRouteDefaultsProvider(
            $messageBus->reveal(),
            $factory->reveal(),
            $cacheLifetimeResolver->reveal()
        );

        $this->assertFalse($provider->supports(\stdClass::class));
    }
}
