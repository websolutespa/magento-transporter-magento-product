<?php
/*
 * Copyright © Websolute spa. All rights reserved.
 * See LICENSE and/or COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Websolute\TransporterMagentoProduct\Uploader;

use DateTime;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Stdlib\DateTime as MagentoDateTime;
use Monolog\Logger;
use Silicon\CustomerGroup\Model\GetOrCreateCustomerGroup;
use Silicon\CustomerGroup\Model\RemoveCatalogPriceRuleByRuleName;
use Silicon\CustomerGroup\Model\SetCatalogPriceRuleByCustomerGroup;
use Silicon\CustomerGroup\Model\SetCustomCustomerGroupForCustomer;
use Websolute\TransporterBase\Api\UploaderInterface;
use Websolute\TransporterBase\Exception\TransporterException;
use Websolute\TransporterEntity\Api\EntityRepositoryInterface;
use Websolute\TransporterImporter\Model\DotConvention;
use Websolute\TransporterMagentoCustomer\Model\SetCustomerGroup;
use Websolute\TransporterMagentoProduct\Api\PriceConfigInterface;
use Websolute\TransporterTeamSystemAdaptor\Model\DateTimeFormat;

class CustomerPriceArtUploader implements UploaderInterface
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
     * @var string
     */
    private $sku;

    /**
     * @var SetCatalogPriceRuleByCustomerGroup
     */
    private $setCatalogPriceRuleByCustomerGroup;

    /**
     * @var RemoveCatalogPriceRuleByRuleName
     */
    private $removeCatalogPriceRuleByRuleName;

    /**
     * @var SetCustomCustomerGroupForCustomer
     */
    private $customCustomerGroupForCustomer;


    /**
     * @param SetCatalogPriceRuleByCustomerGroup $setCatalogPriceRuleByCustomerGroup
     * @param RemoveCatalogPriceRuleByRuleName $removeCatalogPriceRuleByRuleName
     * @param SetCustomCustomerGroupForCustomer $customCustomerGroupForCustomer
     * @param GetOrCreateCustomerGroup $getOrCreateCustomerGroup
     * @param EntityRepositoryInterface $entityRepository
     * @param SetCustomerGroup $setCustomerGroup
     * @param PriceConfigInterface $config
     * @param DotConvention $dotConvention
     * @param Logger $logger
     * @param string $ruleName
     * @param string $codCli
     * @param string $field
     * @param string $sku
     * @param string $fromDate
     * @param string $toDate
     */
    public function __construct(
        SetCatalogPriceRuleByCustomerGroup $setCatalogPriceRuleByCustomerGroup,
        RemoveCatalogPriceRuleByRuleName $removeCatalogPriceRuleByRuleName,
        SetCustomCustomerGroupForCustomer $customCustomerGroupForCustomer,
        GetOrCreateCustomerGroup $getOrCreateCustomerGroup,
        EntityRepositoryInterface $entityRepository,
        SetCustomerGroup $setCustomerGroup,
        PriceConfigInterface $config,
        DotConvention $dotConvention,
        Logger $logger,
        string $ruleName,
        string $codCli,
        string $field,
        string $sku,
        string $fromDate = '',
        string $toDate = ''
    ) {
        $this->setCatalogPriceRuleByCustomerGroup = $setCatalogPriceRuleByCustomerGroup;
        $this->removeCatalogPriceRuleByRuleName = $removeCatalogPriceRuleByRuleName;
        $this->customCustomerGroupForCustomer = $customCustomerGroupForCustomer;
        $this->getOrCreateCustomerGroup = $getOrCreateCustomerGroup;
        $this->entityRepository = $entityRepository;
        $this->setCustomerGroup = $setCustomerGroup;
        $this->dotConvention = $dotConvention;
        $this->ruleName = $ruleName;
        $this->fromDate = $fromDate;
        $this->logger = $logger;
        $this->config = $config;
        $this->codCli = $codCli;
        $this->toDate = $toDate;
        $this->field = $field;
        $this->sku = $sku;
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
                $price = $this->dotConvention->getValue($entities, $this->field);
                $sku = $this->dotConvention->getValue($entities, $this->sku);
                $ruleName = $this->dotConvention->getValue($entities, $this->ruleName);

                if (!isset($price)) {
                    $this->removeCatalogPriceRuleByRuleName->execute($ruleName);
                    $this->logger->info(__(
                        'activityId:%1 ~ Uploader ~ uploaderType:%2 ~ entityIdentifier:%3 ~ DELETED RULE: product sku -> %4, rule name -> %5, codCli -> %6',
                        $activityId,
                        $uploaderType,
                        $entityIdentifier,
                        $sku,
                        $ruleName,
                        $codCli
                    ));
                    continue;
                }

                $customCustomerGroup = $this->customCustomerGroupForCustomer->execute((string)$codCli);

                //create new rule for specific product, called group name - codart - varart, within customer
                // group new created above

                $rule = $this->prepareRuleArray($entities, $ruleName, (float)$price, $sku);
                $this->setCatalogPriceRuleByCustomerGroup->execute(
                    (int)$customCustomerGroup->getId(),
                    $customCustomerGroup->getCode(),
                    $rule
                );

                $this->logger->info(__(
                    'activityId:%1 ~ Uploader ~ uploaderType:%2 ~ entityIdentifier:%3 ~ new customer price rule:%4 ~ from date:%5 ~ to date:%6 ~ amount:%7 ~ END',
                    $activityId,
                    $uploaderType,
                    $entityIdentifier,
                    $rule['name'],
                    $rule['from'],
                    $rule['to'],
                    $rule['amount']
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
     * @param float $price
     * @param string $sku
     * @return array
     * @throws TransporterException
     */
    protected function prepareRuleArray(array $entities, string $ruleName, float $price, string $sku): array
    {
        $fromDate = DateTime::createFromFormat(
            DateTimeFormat::TEAMSYSTEM_FORMAT,
            $this->dotConvention->getValue($entities, $this->fromDate)
        );
        $toDate = DateTime::createFromFormat(
            DateTimeFormat::TEAMSYSTEM_FORMAT,
            $this->dotConvention->getValue($entities, $this->toDate)
        );

        if ($fromDate === false) {
            throw new TransporterException(__(
                'UPLOADER RULE NAME: %1 ~ FROM DATE INVALID: %2',
                $ruleName,
                $this->fromDate
            ));
        }

        if ($toDate === false) {
            throw new TransporterException(__(
                'UPLOADER RULE NAME: %1 ~ TO DATE INVALID: %2',
                $ruleName,
                $this->fromDate
            ));
        }

        $conditions =
            '{"type":"Magento\\\\CatalogRule\\\\Model\\\\Rule\\\\Condition\\\\Combine","attribute":null,"operator":null,"value":"1","is_value_processed":null,"aggregator":"all","conditions":[{"type":"Magento\\\\CatalogRule\\\\Model\\\\Rule\\\\Condition\\\\Product","attribute":"codArt","operator":"==","value":"' . $sku . '","is_value_processed":false}]}';

        return [
            'name' => $ruleName,
            'from' => $fromDate->format(MagentoDateTime::DATETIME_PHP_FORMAT),
            'to' => $toDate->format(MagentoDateTime::DATETIME_PHP_FORMAT),
            'amount' => $price,
            'conditions' => $conditions,
        ];
    }
}
