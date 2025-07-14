<?php

namespace LaravelDaily\Invoices;

use Barryvdh\DomPDF\Facade\Pdf as PDF;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\View;
use LaravelDaily\Invoices\Classes\InvoiceItem;
use LaravelDaily\Invoices\Classes\Party;
use LaravelDaily\Invoices\Contracts\PartyContract;
use LaravelDaily\Invoices\Traits\CurrencyFormatter;
use LaravelDaily\Invoices\Traits\DateFormatter;
use LaravelDaily\Invoices\Traits\InvoiceHelpers;
use LaravelDaily\Invoices\Traits\SavesFiles;
use LaravelDaily\Invoices\Traits\SerialNumberFormatter;

/**
 * Class Invoices.
 */
class Invoice
{
    use CurrencyFormatter;
    use DateFormatter;
    use InvoiceHelpers;
    use SavesFiles;
    use SerialNumberFormatter;

    public const TABLE_COLUMNS = 4;

    /**
     * @var string
     */
    public $name;

    /**
     * @var PartyContract
     */
    public $seller;

    /**
     * @var PartyContract
     */
    public $buyer;

    /**
     * @var Collection
     */
    public $items;

    /**
     * @var string
     */
    public $template;

    /**
     * @var string
     */
    public $filename;

    /**
     * @var string
     */
    public $notes;

    /**
     * @var string
     */
    public $status;

    /**
     * @var string
     */
    public $logo;

    /**
     * @var float
     */
    public $discount_percentage;

    /**
     * @var float
     */
    public $total_discount;

    /**
     * @var float
     */
    public $tax_rate;

    /**
     * @var float
     */
    public $taxable_amount;

    /**
     * @var float
     */
    public $shipping_amount;

    /**
     * @var float
     */
    public $total_taxes;

    /**
     * @var float
     */
    public $total_amount;

    /**
     * @var bool
     */
    public $hasItemUnits;

    /**
     * @var bool
     */
    public $hasItemDiscount;

    /**
     * @var bool
     */
    public $hasItemTax;

    /**
     * @var int
     */
    public $table_columns;

    /**
     * @var PDF
     */
    public $pdf;

    /**
     * @var string
     */
    public $output;

    /**
     * @var mixed
     */
    protected $userDefinedData;

    /**
     * @var array
     */
    protected array $paperOptions;

    /**
     * @var array
     */
    protected $options;

    /**
     * Invoice constructor.
     *
     * @param string $name
     *
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     */
    public function __construct($name = '')
    {
        // Invoice
        $this->name     = $name ?: $this->getDefaultInvoiceName();
        $this->seller   = $this->createSeller();
        $this->items    = Collection::make([]);
        $this->template = 'default';

        // Date
        $this->date           = Carbon::now();
        $this->date_format    = $this->getConfig('invoices.date.format', 'Y-m-d');
        $this->pay_until_days = $this->getConfig('invoices.date.pay_until_days', 7);

        // Serial Number
        $this->series               = $this->getConfig('invoices.serial_number.series', 'AA');
        $this->sequence_padding     = $this->getConfig('invoices.serial_number.sequence_padding', 5);
        $this->delimiter            = $this->getConfig('invoices.serial_number.delimiter', '.');
        $this->serial_number_format = $this->getConfig('invoices.serial_number.format', '{SERIES}{DELIMITER}{SEQUENCE}');
        $this->sequence($this->getConfig('invoices.serial_number.sequence', 1));

        // Filename
        $this->filename($this->getDefaultFilename($this->name));

        // Currency
        $this->currency_code                = $this->getConfig('invoices.currency.code', 'eur');
        $this->currency_fraction            = $this->getConfig('invoices.currency.fraction', 'ct.');
        $this->currency_symbol              = $this->getConfig('invoices.currency.symbol', '€');
        $this->currency_decimals            = $this->getConfig('invoices.currency.decimals', 2);
        $this->currency_decimal_point       = $this->getConfig('invoices.currency.decimal_point', '.');
        $this->currency_thousands_separator = $this->getConfig('invoices.currency.thousands_separator', '');
        $this->currency_format              = $this->getConfig('invoices.currency.format', '{VALUE} {SYMBOL}');

        // Paper
        $this->paperOptions = $this->getConfig('invoices.paper', ['size' => 'a4', 'orientation' => 'portrait']);

        // DomPDF options - Updated for Laravel 12 compatibility
        $dompdfOptions = [];
        if ($this->isLaravelAvailable() && app()->bound('dompdf.options')) {
            $dompdfOptions = app('dompdf.options');
        }
        $this->options = array_merge($dompdfOptions, $this->getConfig('invoices.dompdf_options', ['enable_php' => true]));

        $this->disk          = $this->getConfig('invoices.disk', 'local');
        $this->table_columns = static::TABLE_COLUMNS;
    }

    /**
     * Check if Laravel is available
     *
     * @return bool
     */
    protected function isLaravelAvailable(): bool
    {
        return function_exists('app') && function_exists('config');
    }

    /**
     * Get config value with fallback
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    protected function getConfig(string $key, $default = null)
    {
        if ($this->isLaravelAvailable()) {
            return config($key, $default);
        }
        return $default;
    }

    /**
     * Get default invoice name
     *
     * @return string
     */
    protected function getDefaultInvoiceName(): string
    {
        if ($this->isLaravelAvailable()) {
            return __('invoices::invoice.invoice');
        }
        return 'Invoice';
    }

    /**
     * Create seller instance
     *
     * @return PartyContract
     */
    protected function createSeller(): PartyContract
    {
        if ($this->isLaravelAvailable()) {
            return app()->make(config('invoices.seller.class'));
        }
        return new \LaravelDaily\Invoices\Classes\Seller();
    }

    /**
     * @param string $name
     *
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     *
     * @return Invoice
     */
    public static function make($name = '')
    {
        return new static($name);
    }

    /**
     * @return Party
     */
    public static function makeParty(array $attributes = [])
    {
        return new Party($attributes);
    }

    /**
     * @return InvoiceItem
     */
    public static function makeItem(string $title = '')
    {
        return (new InvoiceItem())->title($title);
    }

    /**
     * @return $this
     */
    public function addItem(InvoiceItem $item)
    {
        $this->items->push($item);

        return $this;
    }

    /**
     * @param $items
     *
     * @return $this
     */
    public function addItems($items)
    {
        foreach ($items as $item) {
            $this->addItem($item);
        }

        return $this;
    }

    /**
     * @throws Exception
     *
     * @return $this
     */
    public function render()
    {
        if ($this->pdf) {
            return $this;
        }

        $this->beforeRender();

        $template = sprintf('invoices::templates.%s', $this->template);
        $view     = View::make($template, ['invoice' => $this]);
        $html     = mb_convert_encoding($view, 'HTML-ENTITIES', 'UTF-8');

        $this->pdf = PDF::setOptions($this->options)
            ->setPaper($this->paperOptions['size'], $this->paperOptions['orientation'])
            ->loadHtml($html);
        $this->output = $this->pdf->output();

        return $this;
    }

    public function toHtml()
    {
        $template = sprintf('invoices::templates.%s', $this->template);

        return View::make($template, ['invoice' => $this]);
    }

    /**
     * @throws Exception
     *
     * @return Response
     */
    public function stream()
    {
        $this->render();

        return new Response($this->output, Response::HTTP_OK, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $this->filename . '"',
        ]);
    }

    /**
     * @throws Exception
     *
     * @return Response
     */
    public function download()
    {
        $this->render();

        return new Response($this->output, Response::HTTP_OK, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $this->filename . '"',
            'Content-Length'      => strlen($this->output),
        ]);
    }
}
