<?php
/*
 * Copyright © Websolute spa. All rights reserved.
 * See LICENSE and/or COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Websolute\TransporterMagentoProduct\Manipulator;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Serialize\Serializer\Json;
use Monolog\Logger;
use Websolute\TransporterBase\Api\ManipulatorInterface;
use Websolute\TransporterBase\Exception\TransporterException;
use Websolute\TransporterEntity\Api\Data\EntityInterface;
use Websolute\TransporterImporter\Model\DotConvention;
use Websolute\TransporterMagentoProduct\Model\GenerateUniqueUrlKey;

class GenerateUniqueUrlKeyManipulator implements ManipulatorInterface
{
    /**
     * @var Json
     */
    private $serializer;

    /**
     * @var DotConvention
     */
    private $dotConvention;

    /**
     * @var string
     */
    private $sourceForName;

    /**
     * @var string
     */
    private $destination;

    /**
     * @var GenerateUniqueUrlKey
     */
    private $generateUniqueUrlKey;

    /**
     * @param GenerateUniqueUrlKey $generateUniqueUrlKey
     * @param Json $serializer
     * @param DotConvention $dotConvention
     * @param string $sourceForName
     * @param string $destination
     */
    public function __construct(
        GenerateUniqueUrlKey $generateUniqueUrlKey,
        Json $serializer,
        DotConvention $dotConvention,
        string $sourceForName,
        string $destination
    ) {
        $this->serializer = $serializer;
        $this->dotConvention = $dotConvention;
        $this->sourceForName = $sourceForName;
        $this->destination = $destination;
        $this->generateUniqueUrlKey = $generateUniqueUrlKey;
    }

    /**
     * @param int $activityId
     * @param string $manipulatorType
     * @param string $entityIdentifier
     * @param EntityInterface[] $entities
     * @throws TransporterException|LocalizedException
     */
    public function execute(
        int $activityId,
        string $manipulatorType,
        string $entityIdentifier,
        array $entities
    ): void {
        $sourceIdentifier = $this->dotConvention->getFirst($this->sourceForName);

        if (!array_key_exists($sourceIdentifier, $entities)) {
            throw new TransporterException(__('Invalid sourceIdentifier for class %1', self::class));
        }

        $entity = $entities[$sourceIdentifier];
        $data = $entity->getDataManipulated();
        $data = $this->serializer->unserialize($data);

        $name = $this->dotConvention->getValueFromSecond($data, $this->sourceForName);

        $urlKey = $this->generateUniqueUrlKey->execute($name, $activityId, $this->destination);

        $destination = $this->dotConvention->getFromSecondInDotConvention($this->destination);

        $this->dotConvention->setValue($data, $destination, $urlKey);

        $data = $this->serializer->serialize($data);
        $entity->setDataManipulated($data);
    }
}
