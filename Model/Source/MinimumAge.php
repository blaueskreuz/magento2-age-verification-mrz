<?php
/**
 * Copyright © Blaues Kreuz. Licensed under GPL-2.0-or-later.
 */
declare(strict_types=1);

namespace BlauesKreuz\AgeVerificationMrz\Model\Source;

use BlauesKreuz\AgeVerificationMrz\Model\Config;
use Magento\Eav\Model\Entity\Attribute\Source\AbstractSource;

/**
 * The choices for the "Minimum age" of a product: the default, no age check, and the ages of the settings.
 */
class MinimumAge extends AbstractSource
{
    /**
     * @param Config $config
     */
    public function __construct(private readonly Config $config)
    {
    }

    /**
     * @inheritdoc
     */
    public function getAllOptions()
    {
        $options = [
            ['value' => '', 'label' => __('- Shop default -')],
            ['value' => '0', 'label' => __('No age check')],
        ];
        foreach ($this->config->getAllowedAges() as $age) {
            $options[] = ['value' => (string) $age, 'label' => __('%1 or older', $age)];
        }

        return $options;
    }
}
