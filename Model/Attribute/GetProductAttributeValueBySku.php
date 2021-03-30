<?php
/*
 * Copyright © Websolute spa. All rights reserved.
 * See LICENSE and/or COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Websolute\TransporterMagentoProduct\Model\Attribute;

use Magento\Catalog\Model\ProductFactory;
use Magento\Catalog\Model\ResourceModel\Product;

class GetProductAttributeValueBySku
{
    /**
     * @var Product
     */
    private $productResource;

    /**
     * @var ProductFactory
     */
    private $productFactory;

    /**
     * @param Product $productResource
     * @param ProductFactory $productFactory
     */
    public function __construct(
        Product $productResource,
        ProductFactory $productFactory
    ) {
        $this->productResource = $productResource;
        $this->productFactory = $productFactory;
    }

    /**
     * @param string $sku
     * @param string $attributeCode
     * @return mixed
     */
    public function execute(string $sku, string $attributeCode)
    {
        $product = $this->productFactory->create();
        $productId = $this->productResource->getIdBySku($sku);
        $this->productResource->load($product, $productId, [$attributeCode]);
        return $product->getData($attributeCode);
    }
}
