<?php
/*
 * Copyright © Websolute spa. All rights reserved.
 * See LICENSE and/or COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Websolute\TransporterMagentoProduct\Model;

use Magento\Catalog\Model\Product\Url;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\UrlRewrite\Model\ResourceModel\UrlRewrite as UrlRewriteResource;
use Magento\UrlRewrite\Model\ResourceModel\UrlRewriteCollectionFactory;
use Websolute\TransporterImporter\Model\DotConvention;

class GenerateUniqueUrlKey
{
    /**
     * @var array
     */
    private $urlKeyCandidateCache = [];

    /**
     * @var SlugifyText
     */
    private $slugifyText;

    /**
     * @var Url
     */
    private $url;

    /**
     * @var UrlRewriteCollectionFactory
     */
    private $urlRewriteCollectionFactory;

    /**
     * @var Json
     */
    private $serializer;

    /**
     * @var DotConvention
     */
    private $dotConvention;

    /**
     * @var UrlRewriteResource
     */
    private $urlRewriteResourceModel;

    /**
     * @param UrlRewriteCollectionFactory $urlRewriteCollectionFactory
     * @param UrlRewriteResource $urlRewriteResourceModel
     * @param Url $url
     * @param Json $serializer
     * @param DotConvention $dotConvention
     * @param SlugifyText $slugifyText
     */
    public function __construct(
        UrlRewriteCollectionFactory $urlRewriteCollectionFactory,
        UrlRewriteResource $urlRewriteResourceModel,
        Url $url,
        Json $serializer,
        DotConvention $dotConvention,
        SlugifyText $slugifyText
    ) {
        $this->urlRewriteCollectionFactory = $urlRewriteCollectionFactory;
        $this->url = $url;
        $this->slugifyText = $slugifyText;
        $this->serializer = $serializer;
        $this->dotConvention = $dotConvention;
        $this->urlRewriteResourceModel = $urlRewriteResourceModel;
    }

    /**
     * @param string $name
     * @param int $activityId
     * @param string $destination
     * @return string
     * @throws \Exception
     */
    public function execute(string $name, int $activityId, string $destination): string
    {
        $productName = $this->slugifyText->execute($name);

        $urlKeyBase = $this->url->formatUrlKey($productName);

        $generated = false;
        $increment = -1;
        $urlKeyCandidate = '';

        while (!$generated) {
            $increment++;
            $urlKeyCandidate = $urlKeyBase;
            if ($increment > 0) {
                $urlKeyCandidate .= '-' . $increment;
            }
            $urlKeyCandidate .= '.html';

            if (array_search($urlKeyCandidate, $this->urlKeyCandidateCache) !== false) {
                continue;
            }

            $this->urlKeyCandidateCache[] = $urlKeyCandidate;

            $this->deleteIfExistsInUrlRewriteTable($urlKeyCandidate);

            $generated = true;
        }

        return str_replace('.html', '', $urlKeyCandidate);
    }

    /**
     * @param string $urlKey
     * @throws \Exception
     */
    private function deleteIfExistsInUrlRewriteTable(string $urlKey)
    {
        $urlRewriteCollection = $this->urlRewriteCollectionFactory->create();
        $urlRewriteCollection->addFieldToFilter('request_path', ['eq' => $urlKey]);
        $urls = $urlRewriteCollection->getItems();

        foreach ($urls as $url) {
            $this->urlRewriteResourceModel->delete($url);
        }
    }
}
