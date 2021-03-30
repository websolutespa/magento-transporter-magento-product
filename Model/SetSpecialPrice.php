<?php
/*
 * Copyright © Websolute spa. All rights reserved.
 * See LICENSE and/or COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Websolute\TransporterMagentoProduct\Model;

use DateTime;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Model\Indexer\Product\Price\Action\Row;
use Magento\Catalog\Model\ProductRepository;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Magento\Framework\Exception\LocalizedException;
use Websolute\TransporterBase\Exception\TransporterException;
use Websolute\TransporterBase\Model\SetAreaCode;

class SetSpecialPrice
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
     * @param Row $indexerRow
     * @param ProductRepository $productRepository
     */
    public function __construct(
        SetAreaCode $setAreaCode,
        Row $indexerRow,
        ProductRepository $productRepository
    ) {
        $this->setAreaCode = $setAreaCode;
        $this->productRepository = $productRepository;
        $this->indexerRow = $indexerRow;
    }

    /**
     * @param string $sku
     * @param float $price
     * @param DateTime|null $fromDate
     * @param DateTime|null $toDate
     * @param bool $reindex
     * @throws TransporterException
     */
    public function execute(string $sku, float $price, DateTime $fromDate = null, DateTime $toDate = null, bool $reindex = false)
    {
        try {
            $this->setAreaCode->execute('adminhtml');
            $product = $this->productRepository->get($sku);

            if ($product->getData('type_id') === Configurable::TYPE_CODE) {
                $this->setSpecialPriceToChildren($product, $price);
            } else {
                $product->setSpecialPrice($price);
                if ($fromDate) {
                    $product->setSpecialPriceFromDate($fromDate);
                }
                if ($toDate) {
                    $product->setSpecialPriceToDate($toDate);
                }
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

    private function setSpecialPriceToChildren(
        ?ProductInterface $product,
        float $price,
        DateTime $fromDate = null,
        DateTime $toDate = null
    ) {
        /** @var ProductInterface[] $children */
        $children = $product->getTypeInstance()->getUsedProducts($product);

        foreach ($children as $child) {

            if (!$child->getId()) {
                continue;
            }

            $child->setSpecialPrice($price);
            if ($fromDate) {
                $child->setSpecialPriceFromDate($fromDate);
            }
            if ($toDate) {
                $child->setSpecialPriceToDate($toDate);
            }
            $this->productRepository->save($child);
        }
    }
}
