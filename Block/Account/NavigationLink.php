<?php
/**
 * Copyright © Blaues Kreuz. Licensed under GPL-2.0-or-later.
 */
declare(strict_types=1);

namespace BlauesKreuz\AgeVerificationMrz\Block\Account;

use BlauesKreuz\AgeVerificationMrz\Model\Config;
use Magento\Customer\Block\Account\SortLink;
use Magento\Framework\App\DefaultPathInterface;
use Magento\Framework\View\Element\Template\Context;

/**
 * The link to the age verification page in the navigation of the customer account. It is only shown when the
 * shop lets customers verify in their account.
 */
class NavigationLink extends SortLink
{
    /**
     * @param Context $context
     * @param DefaultPathInterface $defaultPath
     * @param Config $config
     * @param array $data
     */
    public function __construct(
        Context $context,
        DefaultPathInterface $defaultPath,
        private readonly Config $config,
        array $data = []
    ) {
        parent::__construct($context, $defaultPath, $data);
    }

    /**
     * @inheritdoc
     */
    protected function _toHtml()
    {
        return $this->config->isAccountVerificationAllowed() ? parent::_toHtml() : '';
    }
}
