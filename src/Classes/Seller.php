<?php

namespace LaravelDaily\Invoices\Classes;

use LaravelDaily\Invoices\Contracts\PartyContract;

/**
 * Class Seller
 */
class Seller implements PartyContract
{
    /**
     * @var string
     */
    public $name;

    /**
     * @var string
     */
    public $address;

    /**
     * @var string
     */
    public $code;

    /**
     * @var string
     */
    public $vat;

    /**
     * @var string
     */
    public $phone;

    /**
     * @var array
     */
    public $custom_fields;

    /**
     * Seller constructor.
     */
    public function __construct(array $attributes = [])
    {
        if (empty($attributes)) {
            $this->loadDefaultAttributes();
        } else {
            $this->loadAttributes($attributes);
        }
    }

    /**
     * Load default attributes from config or use fallbacks
     */
    protected function loadDefaultAttributes(): void
    {
        $this->name = $this->getConfig('invoices.seller.attributes.name', 'Default Company');
        $this->address = $this->getConfig('invoices.seller.attributes.address', 'Default Address');
        $this->code = $this->getConfig('invoices.seller.attributes.code', 'DEFAULT');
        $this->vat = $this->getConfig('invoices.seller.attributes.vat', 'VAT123456');
        $this->phone = $this->getConfig('invoices.seller.attributes.phone', '+1234567890');
        $this->custom_fields = $this->getConfig('invoices.seller.attributes.custom_fields', []);
    }

    /**
     * Load attributes from provided array
     */
    protected function loadAttributes(array $attributes): void
    {
        $this->name = $attributes['name'] ?? $this->getConfig('invoices.seller.attributes.name', 'Default Company');
        $this->address = $attributes['address'] ?? $this->getConfig('invoices.seller.attributes.address', 'Default Address');
        $this->code = $attributes['code'] ?? $this->getConfig('invoices.seller.attributes.code', 'DEFAULT');
        $this->vat = $attributes['vat'] ?? $this->getConfig('invoices.seller.attributes.vat', 'VAT123456');
        $this->phone = $attributes['phone'] ?? $this->getConfig('invoices.seller.attributes.phone', '+1234567890');
        $this->custom_fields = $attributes['custom_fields'] ?? $this->getConfig('invoices.seller.attributes.custom_fields', []);
    }

    /**
     * Get config value with fallback
     */
    protected function getConfig(string $key, $default = null)
    {
        if (function_exists('config')) {
            return config($key, $default);
        }
        return $default;
    }
}
