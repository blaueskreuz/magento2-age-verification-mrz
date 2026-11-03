<?php
/**
 * Copyright © Blaues Kreuz. Licensed under GPL-2.0-or-later.
 */
declare(strict_types=1);

namespace BlauesKreuz\AgeVerificationMrz\Model;

use BlauesKreuz\AgeVerificationMrz\Setup\Patch\Data\AddProductAttribute;
use Magento\Catalog\Model\ResourceModel\Product as ProductResource;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Model\Quote;

/**
 * Works out the age a cart needs: the highest minimum age of its products.
 *
 * A product's own value wins over the shop default, whether higher or lower. For a product with options
 * (for example a configurable product) the chosen variation overrides its parent.
 */
class RequiredAgeResolver
{
    /**
     * @param Config $config
     * @param ProductResource $productResource
     */
    public function __construct(
        private readonly Config $config,
        private readonly ProductResource $productResource
    ) {
    }

    /**
     * The age the cart needs, 0 if no check is needed.
     *
     * @param CartInterface $quote
     * @return int
     */
    public function resolve(CartInterface $quote): int
    {
        /** @var Quote $quote */
        $storeId = (int) $quote->getStoreId();
        if (!$this->config->isEnabled($storeId)) {
            return 0;
        }
        $default = $this->config->getDefaultMinimumAge($storeId);
        $required = 0;
        foreach ($quote->getAllItems() as $item) {
            if ($item->getHasChildren() || $item->isDeleted()) {
                continue;
            }
            $value = $this->rawValue((int) $item->getProductId(), $storeId);
            if ($value === null && $item->getParentItem()) {
                $value = $this->rawValue((int) $item->getParentItem()->getProductId(), $storeId);
            }
            $required = max($required, $value ?? $default);
        }

        return $required;
    }

    /**
     * The value set on a product, null if it uses the default.
     *
     * @param int $productId
     * @param int $storeId
     * @return int|null
     */
    private function rawValue(int $productId, int $storeId): ?int
    {
        $value = $this->productResource->getAttributeRawValue(
            $productId,
            AddProductAttribute::ATTRIBUTE_CODE,
            $storeId
        );
        if (is_array($value)) {
            $value = $value[AddProductAttribute::ATTRIBUTE_CODE] ?? null;
        }

        return ($value === null || $value === false || $value === '') ? null : max(0, (int) $value);
    }
}
