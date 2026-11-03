<?php
/**
 * Copyright © Blaues Kreuz. Licensed under GPL-2.0-or-later.
 */
declare(strict_types=1);

namespace BlauesKreuz\AgeVerificationMrz\Block\Adminhtml\System\Config;

use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;

/**
 * The donation call on the settings page. It is shown to the people who set up the shop, never to its customers.
 */
class Donation extends Field
{
    public const URL = 'https://blaueskreuz.ch/spende';

    /**
     * @inheritdoc
     */
    public function render(AbstractElement $element)
    {
        $text = $this->escapeHtml(__(
            'Perspectives for life – your donation to the Blue Cross makes them possible: support our addiction work with a donation – thank you!'
        ));

        return '<tr id="row_' . $this->escapeHtmlAttr($element->getHtmlId()) . '"><td colspan="4" class="value">'
            . '<a href="' . $this->escapeUrl(self::URL) . '" target="_blank" rel="noopener">' . $text . '</a>'
            . '</td></tr>';
    }
}
