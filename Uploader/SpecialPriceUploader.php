<?php
/*
 * Copyright © Websolute spa. All rights reserved.
 * See LICENSE and/or COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Websolute\TransporterMagentoProduct\Uploader;

use DateTime;
use Magento\Framework\Stdlib\DateTime as MagentoDateTime;
use Monolog\Logger;
use Silicon\ProductTierPrice\Model\RemoveCatalogTierPriceRuleBySku;
use Websolute\TransporterBase\Api\UploaderInterface;
use Websolute\TransporterBase\Exception\TransporterException;
use Websolute\TransporterEntity\Api\EntityRepositoryInterface;
use Websolute\TransporterImporter\Model\DotConvention;
use Websolute\TransporterMagentoProduct\Api\PriceConfigInterface;
use Websolute\TransporterMagentoProduct\Model\SetSpecialPrice;

class SpecialPriceUploader implements UploaderInterface
{
    /**
     * @var Logger
     */
    private $logger;

    /**
     * @var PriceConfigInterface
     */
    private $config;

    /**
     * @var EntityRepositoryInterface
     */
    private $entityRepository;

    /**
     * @var SetSpecialPrice
     */
    private $setSpecialPrice;

    /**
     * @var DotConvention
     */
    private $dotConvention;

    /**
     * @var string
     */
    private $field;

    /**
     * @var string
     */
    private $fromDate;

    /**
     * @var string
     */
    private $toDate;
    /**
     * @var RemoveCatalogTierPriceRuleBySku
     */
    private $removeCatalogTierPriceRuleBySku;

    /**
     * @param Logger $logger
     * @param PriceConfigInterface $config
     * @param EntityRepositoryInterface $entityRepository
     * @param RemoveCatalogTierPriceRuleBySku $removeCatalogTierPriceRuleBySku
     * @param SetSpecialPrice $setSpecialPrice
     * @param DotConvention $dotConvention
     * @param string $field
     * @param string $fromDate
     * @param string $toDate
     */
    public function __construct(
        Logger $logger,
        PriceConfigInterface $config,
        EntityRepositoryInterface $entityRepository,
        RemoveCatalogTierPriceRuleBySku $removeCatalogTierPriceRuleBySku,
        SetSpecialPrice $setSpecialPrice,
        DotConvention $dotConvention,
        string $field,
        string $fromDate = '',
        string $toDate = ''
    ) {
        $this->logger = $logger;
        $this->entityRepository = $entityRepository;
        $this->config = $config;
        $this->setSpecialPrice = $setSpecialPrice;
        $this->removeCatalogTierPriceRuleBySku = $removeCatalogTierPriceRuleBySku;
        $this->dotConvention = $dotConvention;
        $this->field = $field;
        $this->fromDate = $fromDate;
        $this->toDate = $toDate;
    }

    /**
     * @param int $activityId
     * @param string $uploaderType
     * @throws TransporterException
     */
    public function execute(int $activityId, string $uploaderType): void
    {
        $allActivityEntities = $this->entityRepository->getAllDataManipulatedByActivityIdGroupedByIdentifier($activityId);

        $i = 0;
        $tot = count($allActivityEntities);
        foreach ($allActivityEntities as $entityIdentifier => $entities) {
            $this->logger->info(__(
                'activityId:%1 ~ Uploader ~ uploaderType:%2 ~ entityIdentifier:%3 ~ START',
                $activityId,
                $uploaderType,
                $entityIdentifier,
                ++$i,
                $tot
            ));

            try {
                $sku = $entityIdentifier;
                $price = $this->dotConvention->getValue($entities, $this->field);
                $fromDate = DateTime::createFromFormat(
                    MagentoDateTime::DATETIME_PHP_FORMAT,
                    $this->dotConvention->getValue($entities, $this->fromDate)
                );

                $toDate = DateTime::createFromFormat(
                    MagentoDateTime::DATETIME_PHP_FORMAT,
                    $this->dotConvention->getValue($entities, $this->toDate)
                );
                $reindex = $this->config->isReindexAfterImport();

                if (!isset($price)) {
                    $this->removeCatalogTierPriceRuleBySku->execute($sku);
                    $this->logger->info(__(
                        'activityId:%1 ~ Uploader ~ uploaderType:%2 ~ entityIdentifier:%3 ~ DELETED TIER PRICE: product sku -> %4',
                        $activityId,
                        $uploaderType,
                        $entityIdentifier,
                        $sku
                    ));
                    continue;
                }

                $this->setSpecialPrice->execute($sku, (float)$price, $fromDate, $toDate, $reindex);

                $this->logger->info(__(
                    'activityId:%1 ~ Uploader ~ uploaderType:%2 ~ entityIdentifier:%3 ~ new price value:%4 ~ END',
                    $activityId,
                    $uploaderType,
                    $entityIdentifier,
                    $price
                ));
            } catch (\Exception | TransporterException $e) {
                $this->logger->error(__(
                    'activityId:%1 ~ Uploader ~ uploaderType:%2 ~ entityIdentifier:%3 ~ ERROR ~ error:%4',
                    $activityId,
                    $uploaderType,
                    $entityIdentifier,
                    $e->getMessage()
                ));

                if (!$this->config->continueInCaseOfErrors()) {
                    throw new TransporterException(__(
                        'activityId:%1 ~ Uploader ~ uploaderType:%2 ~ entityIdentifier:%3 ~ END ~ Because of continueInCaseOfErrors = false',
                        $activityId,
                        $uploaderType,
                        $entityIdentifier
                    ));
                }
            }
        }
    }
}
