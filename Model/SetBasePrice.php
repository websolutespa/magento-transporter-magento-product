<?php
/*
 * Copyright © Websolute spa. All rights reserved.
 * See LICENSE and/or COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Websolute\TransporterMagentoProduct\Model;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Model\Indexer\Product\Price\Action\Row;
use Magento\Catalog\Model\ProductRepository;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\StateException;
use Websolute\TransporterBase\Exception\TransporterException;
use Websolute\TransporterBase\Model\SetAreaCode;

class SetBasePrice
{
    /**
     * @var SetAreaCode
     */
    private $setAreaCode;

    /**
     * @var ProductRepository
     */
    private $productRepository;

    /**
     * @var Row
     */
    private $indexerRow;

    /**
     * @param SetAreaCode $setAreaCode
     * @param ProductRepository $productRepository
     * @param Row $indexerRow
     */
    public function __construct(
        SetAreaCode $setAreaCode,
        ProductRepository $productRepository,
        Row $indexerRow
    ) {
        $this->setAreaCode = $setAreaCode;
        $this->productRepository = $productRepository;
        $this->indexerRow = $indexerRow;
    }

    /**
     * @param string $sku
     * @param float $price
     * @param bool $reindex
     * @throws TransporterException
     */
    public function execute(string $sku, float $price, bool $reindex = false)
    {
        try {
            $this->setAreaCode->execute('adminhtml');
            $product = $this->productRepository->get($sku);

            if ($product->getData('type_id') === Configurable::TYPE_CODE) {
                $this->setPriceToChildren($product, $price);
            } else {
                $product->setPrice($price);
                $this->productRepository->save($product);
            }
        } catch (LocalizedException $e) {
            throw new TransporterException(__($e->getMessage()));
        }

        if ($reindex) {
            try {
                $this->indexerRow->execute((int)$product->getId());
            } catch (LocalizedException $e) {
                throw new TransporterException(__($e->getMessage()));
            }
        }
    }

    /**
     * @param ProductInterface|null $product
     * @param float $price
     * @throws CouldNotSaveException
     * @throws InputException
     * @throws StateException
     */
    private function setPriceToChildren(?ProductInterface $product, float $price)
    {
        /** @var ProductInterface[] $children */
        $children = $product->getTypeInstance()->getUsedProducts($product);

        foreach ($children as $child) {

            if (!$child->getId()) {
                continue;
            }

            $child->setPrice($price);
            $this->productRepository->save($child);
        }
    }
}
