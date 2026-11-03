<?php
/**
 * Copyright © Blaues Kreuz. Licensed under GPL-2.0-or-later.
 */
declare(strict_types=1);

namespace BlauesKreuz\AgeVerificationMrz\Block\Adminhtml\Report;

use BlauesKreuz\AgeVerificationMrz\Model\Report\EvidenceReport;
use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\ResourceModel\Order\Collection;

/**
 * The report page: filters, one page of orders and the link to the CSV download.
 */
class Evidence extends Template
{
    private const PAGE_SIZE = 50;

    private ?Collection $collection = null;

    /**
     * @param Context $context
     * @param EvidenceReport $report
     * @param array $data
     */
    public function __construct(Context $context, private readonly EvidenceReport $report, array $data = [])
    {
        parent::__construct($context, $data);
    }

    /**
     * The filters of the request, as they are used and as they go into links.
     *
     * @return array{from: string, to: string, only_missing: string}
     */
    public function getFilters(): array
    {
        $request = $this->getRequest();
        $from = (string) $request->getParam('from', '');
        $to = (string) $request->getParam('to', '');

        return [
            'from' => $this->report->isDate($from) ? $from : '',
            'to' => $this->report->isDate($to) ? $to : '',
            'only_missing' => $request->getParam('only_missing') ? '1' : '',
        ];
    }

    /**
     * The orders of the current page.
     *
     * @return Collection
     */
    private function getCollection(): Collection
    {
        if ($this->collection === null) {
            $filters = $this->getFilters();
            $this->collection = $this->report->getCollection(
                $filters['from'],
                $filters['to'],
                $filters['only_missing'] !== ''
            );
            $this->collection->setPageSize(self::PAGE_SIZE)->setCurPage($this->getPage());
        }

        return $this->collection;
    }

    /**
     * The number of the current page.
     *
     * @return int
     */
    public function getPage(): int
    {
        return max(1, (int) $this->getRequest()->getParam('p', 1));
    }

    /**
     * The number of the last page.
     *
     * @return int
     */
    public function getLastPage(): int
    {
        return max(1, (int) $this->getCollection()->getLastPageNumber());
    }

    /**
     * The number of orders in the report.
     *
     * @return int
     */
    public function getTotal(): int
    {
        return (int) $this->getCollection()->getSize();
    }

    /**
     * The column headings.
     *
     * @return string[]
     */
    public function getHeadings(): array
    {
        return $this->report->getHeadings();
    }

    /**
     * The rows of the current page: the cells and the link of the order.
     *
     * @return array<int, array{cells: string[], url: string, missing: bool}>
     */
    public function getRows(): array
    {
        $rows = [];
        /** @var Order $order */
        foreach ($this->getCollection() as $order) {
            $cells = $this->report->getRow($order);
            $rows[] = [
                'cells' => $cells,
                'url' => $this->getUrl('sales/order/view', ['order_id' => $order->getId()]),
                'missing' => $cells[8] === (string) __('No evidence'),
            ];
        }

        return $rows;
    }

    /**
     * The URL of the report with the filters and a page.
     *
     * @param int $page
     * @return string
     */
    public function getPageUrl(int $page): string
    {
        return $this->getUrl('*/*/index', array_filter($this->getFilters() + ['p' => $page > 1 ? $page : '']));
    }

    /**
     * The URL of the CSV download with the filters.
     *
     * @return string
     */
    public function getExportUrl(): string
    {
        return $this->getUrl('*/*/export', array_filter($this->getFilters()));
    }

    /**
     * The URL the filter form is sent to.
     *
     * @return string
     */
    public function getFilterUrl(): string
    {
        return $this->getUrl('*/*/index');
    }
}
