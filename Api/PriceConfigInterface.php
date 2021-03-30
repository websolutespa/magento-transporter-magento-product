<?php
/*
 * Copyright © Websolute spa. All rights reserved.
 * See LICENSE and/or COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Websolute\TransporterMagentoProduct\Api;

use Websolute\TransporterBase\Api\TransporterConfigInterface;

interface PriceConfigInterface extends TransporterConfigInterface
{
    /**
     * @return bool
     */
    public function isReindexAfterImport(): bool;
}
