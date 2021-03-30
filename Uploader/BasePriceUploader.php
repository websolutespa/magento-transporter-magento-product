<?php
/*
 * Copyright © Websolute spa. All rights reserved.
 * See LICENSE and/or COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Websolute\TransporterMagentoProduct\Uploader;

use Monolog\Logger;
use Websolute\TransporterBase\Api\UploaderInterface;
use Websolute\TransporterBase\Exception\TransporterException;
use Websolute\TransporterEntity\Api\EntityRepositoryInterface;
use Websolute\TransporterImporter\Model\DotConvention;
use Websolute\TransporterMagentoProduct\Api\PriceConfigInterface;
use Websolute\TransporterMagentoProduct\Model\SetBasePrice;

class BasePriceUploader implements UploaderInterface
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
     * @var SetBasePrice
     */
    private $setBasePrice;

    /**
     * @var DotConvention
     */
    private $dotConvention;

    /**
     * @var string
     */
    private $field;

    /**
     * @param Logger $logger
     * @param PriceConfigInterface $config
     * @param EntityRepositoryInterface $entityRepository
     * @param SetBasePrice $setBasePrice
     * @param DotConvention $dotConvention
     * @param string $field
     */
    public function __construct(
        Logger $logger,
        PriceConfigInterface $config,
        EntityRepositoryInterface $entityRepository,
        SetBasePrice $setBasePrice,
        DotConvention $dotConvention,
        string $field
    ) {
        $this->logger = $logger;
        $this->entityRepository = $entityRepository;
        $this->config = $config;
        $this->setBasePrice = $setBasePrice;
        $this->dotConvention = $dotConvention;
        $this->field = $field;
    }

    /**
     * @param int $activityId
     * @param string $uploaderType
     * @throws TransporterException
     */
    public function execute(int $activityId, string $uploaderType): void
    {
        $allActivityEntities = $this->entityRepository->getAllDataManipulatedByActivityIdGroupedByIdentifier($activityId);

        foreach ($allActivityEntities as $entityIdentifier => $entities) {
            $this->logger->info(__(
                'activityId:%1 ~ Uploader ~ uploaderType:%2 ~ entityIdentifier:%3 ~ START',
                $activityId,
                $uploaderType,
                $entityIdentifier
            ));

            try {
                $sku = $entityIdentifier;
                $price = (float)$this->dotConvention->getValue($entities, $this->field);
                $reindex = $this->config->isReindexAfterImport();

                $this->setBasePrice->execute($sku, $price, $reindex);

                $this->logger->info(__(
                    'activityId:%1 ~ Uploader ~ uploaderType:%2 ~ entityIdentifier:%3 ~ new price value:%4 ~ END',
                    $activityId,
                    $uploaderType,
                    $entityIdentifier,
                    $price
                ));
            } catch (TransporterException $e) {
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
