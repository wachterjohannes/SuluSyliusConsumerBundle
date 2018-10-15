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

namespace Sulu\Bundle\SyliusConsumerBundle\Model\Product\Handler\Message;

use Sulu\Bundle\SyliusConsumerBundle\Model\Content\Message\PublishContentMessage;
use Sulu\Bundle\SyliusConsumerBundle\Model\Dimension\DimensionInterface;
use Sulu\Bundle\SyliusConsumerBundle\Model\Dimension\DimensionRepositoryInterface;
use Sulu\Bundle\SyliusConsumerBundle\Model\Product\Exception\ProductInformationNotFoundException;
use Sulu\Bundle\SyliusConsumerBundle\Model\Product\Exception\ProductNotFoundException;
use Sulu\Bundle\SyliusConsumerBundle\Model\Product\Message\PublishProductMessage;
use Sulu\Bundle\SyliusConsumerBundle\Model\Product\ProductInformationInterface;
use Sulu\Bundle\SyliusConsumerBundle\Model\Product\ProductInformationRepositoryInterface;
use Sulu\Bundle\SyliusConsumerBundle\Model\Product\ProductInformationVariantRepositoryInterface;
use Sulu\Bundle\SyliusConsumerBundle\Model\Product\ProductInterface;
use Sulu\Bundle\SyliusConsumerBundle\Model\RoutableResource\Message\PublishRoutableResourceMessage;
use Symfony\Cmf\Api\Slugifier\SlugifierInterface;
use Symfony\Component\Messenger\MessageBusInterface;

class PublishProductMessageHandler
{
    /**
     * @var ProductInformationRepositoryInterface
     */
    private $productInformationRepository;

    /**
     * @var ProductInformationVariantRepositoryInterface
     */
    private $variantRepository;

    /**
     * @var DimensionRepositoryInterface
     */
    private $dimensionRepository;

    /**
     * @var MessageBusInterface
     */
    private $messageBus;

    /**
     * @var SlugifierInterface
     */
    private $slugifier;

    public function __construct(
        ProductInformationRepositoryInterface $productInformationRepository,
        ProductInformationVariantRepositoryInterface $variantRepository,
        DimensionRepositoryInterface $dimensionRepository,
        MessageBusInterface $messageBus,
        SlugifierInterface $slugifier
    ) {
        $this->productInformationRepository = $productInformationRepository;
        $this->dimensionRepository = $dimensionRepository;
        $this->messageBus = $messageBus;
        $this->slugifier = $slugifier;
        $this->variantRepository = $variantRepository;
    }

    public function __invoke(PublishProductMessage $message): ProductInformationInterface
    {
        try {
            $liveProduct = $this->publishProductInformation($message->getCode(), $message->getLocale());
        } catch (ProductInformationNotFoundException $exception) {
            throw new ProductNotFoundException($message->getCode(), 0, $exception);
        }

        $this->messageBus->dispatch(
            new PublishContentMessage(ProductInterface::RESOURCE_KEY, $message->getCode(), $message->getLocale())
        );

        // FIXME generate route by-schema
        $routePath = '/products/' . $this->slugifier->slugify($message->getCode());
        $this->messageBus->dispatch(
            new PublishRoutableResourceMessage(
                ProductInterface::RESOURCE_KEY,
                $message->getCode(),
                $message->getLocale(),
                $routePath
            )
        );

        return $liveProduct;
    }

    private function publishProductInformation(string $code, string $locale): ProductInformationInterface
    {
        $draftDimension = $this->dimensionRepository->findOrCreateByAttributes(
            [
                DimensionInterface::ATTRIBUTE_KEY_STAGE => DimensionInterface::ATTRIBUTE_VALUE_DRAFT,
                DimensionInterface::ATTRIBUTE_KEY_LOCALE => $locale,
            ]
        );
        $liveDimension = $this->dimensionRepository->findOrCreateByAttributes(
            [
                DimensionInterface::ATTRIBUTE_KEY_STAGE => DimensionInterface::ATTRIBUTE_VALUE_LIVE,
                DimensionInterface::ATTRIBUTE_KEY_LOCALE => $locale,
            ]
        );

        $draftProduct = $this->productInformationRepository->findByCode($code, $draftDimension);
        if (!$draftProduct) {
            throw new ProductInformationNotFoundException($code);
        }

        $liveProduct = $this->productInformationRepository->findByCode($code, $liveDimension);
        if (!$liveProduct) {
            $liveProduct = $this->productInformationRepository->create($code, $liveDimension);
        }

        $liveProduct->setName($draftProduct->getName());

        $this->synchronizeVariants($draftProduct, $liveProduct);

        return $liveProduct;
    }

    private function synchronizeVariants(ProductInformationInterface $draftProduct, ProductInformationInterface $liveProduct): void
    {
        $codes = [];
        foreach ($draftProduct->getVariants() as $draftVariant) {
            $variant = $liveProduct->findVariantByCode($draftVariant->getCode());
            if (!$variant) {
                $variant = $this->variantRepository->create($liveProduct, $draftVariant->getCode());
            }

            $variant->setName($draftVariant->getName());

            $codes[] = $variant->getCode();
        }

        foreach ($liveProduct->getVariants() as $variant) {
            if (!in_array($variant->getCode(), $codes)) {
                $liveProduct->removeVariant($variant);
            }
        }
    }
}
