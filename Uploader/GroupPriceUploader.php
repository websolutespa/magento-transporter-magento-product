<?php
/*
 * Copyright © Websolute spa. All rights reserved.
 * See LICENSE and/or COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Websolute\TransporterMagentoProduct\Uploader;

use DateTime;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Stdlib\DateTime as MagentoDateTime;
use Monolog\Logger;
use Silicon\CustomerGroup\Model\GetOrCreateCustomerGroup;
use Silicon\CustomerGroup\Model\SetCatalogPriceRuleByCustomerGroup;
use Websolute\TransporterBase\Api\UploaderInterface;
use Websolute\TransporterBase\Exception\TransporterException;
use Websolute\TransporterEntity\Api\EntityRepositoryInterface;
use Websolute\TransporterImporter\Model\DotConvention;
use Websolute\TransporterMagentoCustomer\Model\SetCustomerGroup;
use Websolute\TransporterMagentoProduct\Api\PriceConfigInterface;

class GroupPriceUploader implements UploaderInterface
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
     * @var GetOrCreateCustomerGroup
     */
    private $getOrCreateCustomerGroup;

    /**
     * @var SetCustomerGroup
     */
    private $setCustomerGroup;

    /**
     * @var string
     */
    private $codCli;

    /**
     * @var string
     */
    private $ruleName;

    /**
     * @var SetCatalogPriceRuleByCustomerGroup
     */
    private $setCatalogPriceRuleByCustomerGroup;

    /**
     * @param Logger $logger
     * @param PriceConfigInterface $config
     * @param EntityRepositoryInterface $entityRepository
     * @param SetCustomerGroup $setCustomerGroup
     * @param DotConvention $dotConvention
     * @param GetOrCreateCustomerGroup $getOrCreateCustomerGroup
     * @param SetCatalogPriceRuleByCustomerGroup $setCatalogPriceRuleByCustomerGroup
     * @param string $codCli
     * @param string $ruleName
     * @param string $field
     * @param string $fromDate
     * @param string $toDate
     */
    public function __construct(
        Logger $logger,
        PriceConfigInterface $config,
        EntityRepositoryInterface $entityRepository,
        SetCustomerGroup $setCustomerGroup,
        DotConvention $dotConvention,
        GetOrCreateCustomerGroup $getOrCreateCustomerGroup,
        SetCatalogPriceRuleByCustomerGroup $setCatalogPriceRuleByCustomerGroup,
        string $codCli,
        string $ruleName,
        string $field,
        string $fromDate = '',
        string $toDate = ''
    ) {
        $this->logger = $logger;
        $this->entityRepository = $entityRepository;
        $this->config = $config;
        $this->dotConvention = $dotConvention;
        $this->getOrCreateCustomerGroup = $getOrCreateCustomerGroup;
        $this->setCustomerGroup = $setCustomerGroup;
        $this->setCatalogPriceRuleByCustomerGroup = $setCatalogPriceRuleByCustomerGroup;
        $this->codCli = $codCli;
        $this->ruleName = $ruleName;
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
                $codCli = $this->dotConvention->getValue($entities, $this->codCli);
                $groupCode = $this->dotConvention->getValue($entities, $this->field);
                $ruleName = $this->dotConvention->getValue($entities, $this->ruleName);

                try {
                    $customerGroupId = $this->getOrCreateCustomerGroup->execute($groupCode);
                } catch (AlreadyExistsException | NoSuchEntityException | LocalizedException $e) {
                    if (!$this->config->continueInCaseOfErrors()) {
                        throw new TransporterException(__($e->getMessage()));
                    }
                    continue;
                }

                //associate customer group id to a related customer
                $this->setCustomerGroup->execute((int)$customerGroupId, (string)$codCli);

                //create/update catalog price rule
                $rule = $this->prepareRuleArray($entities, $ruleName);
                $this->setCatalogPriceRuleByCustomerGroup->execute($customerGroupId, $groupCode, $rule);

                $this->logger->info(__(
                    'activityId:%1 ~ Uploader ~ uploaderType:%2 ~ entityIdentifier:%3 ~ new customer group value:%4 ~ from date: %5 ~ to date:%6 ~ END',
                    $activityId,
                    $uploaderType,
                    $entityIdentifier,
                    $rule['name'],
                    $rule['from'],
                    $rule['to']
                ));
            } catch (TransporterException $e) {
            } catch (LocalizedException $e) {
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

    /**
     * @param array $entities
     * @param string $ruleName
     * @return array
     * @throws TransporterException
     */
    protected function prepareRuleArray(array $entities, string $ruleName): array
    {
        $fromDate = DateTime::createFromFormat(
            MagentoDateTime::DATETIME_PHP_FORMAT,
            $this->dotConvention->getValue($entities, $this->fromDate)
        );
        $toDate = DateTime::createFromFormat(
            MagentoDateTime::DATETIME_PHP_FORMAT,
            $this->dotConvention->getValue($entities, $this->toDate)
        );

        return [
            'name' => $ruleName,
            'from' => $fromDate->format(MagentoDateTime::DATETIME_PHP_FORMAT),
            'to' => $toDate->format(MagentoDateTime::DATETIME_PHP_FORMAT)
        ];
    }
}
