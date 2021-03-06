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

namespace Sulu\Bundle\SyliusConsumerBundle\Model\Product;

use Sulu\Bundle\SyliusConsumerBundle\Model\Dimension\DimensionInterface;

interface ProductRepositoryInterface
{
    public function create(string $code, DimensionInterface $dimension): ProductInterface;

    public function findByCode(string $code, DimensionInterface $dimension): ?ProductInterface;

    /**
     * @return ProductInterface[]
     */
    public function findAllByCode(string $code): array;

    public function remove(ProductInterface $product): void;
}
