<?php

namespace LaravelDaily\Invoices\Tests;

use LaravelDaily\Invoices\Invoice;
use LaravelDaily\Invoices\Classes\Buyer;
use LaravelDaily\Invoices\Classes\InvoiceItem;
use LaravelDaily\Invoices\Classes\Seller;
use PHPUnit\Framework\TestCase;

class LaravelIntegrationTest extends TestCase
{
    /**
     * @test
     * @return void
     */
    public function test_basic_invoice_creation()
    {
        // Test basic invoice creation without Laravel context
        $invoice = Invoice::make('Test Invoice');
        
        $this->assertInstanceOf(Invoice::class, $invoice);
        $this->assertEquals('Test Invoice', $invoice->name);
    }

    /**
     * @test
     * @return void
     */
    public function test_invoice_with_buyer_and_items()
    {
        $customer = new Buyer([
            'name' => 'John Doe',
            'custom_fields' => [
                'email' => 'test@example.com',
            ],
        ]);

        $item = InvoiceItem::make('Service 1')->pricePerUnit(2);

        $invoice = Invoice::make()
            ->buyer($customer)
            ->addItem($item);

        $this->assertInstanceOf(Invoice::class, $invoice);
        $this->assertEquals($customer, $invoice->buyer);
        $this->assertCount(1, $invoice->items);
    }

    /**
     * @test
     * @return void
     */
    public function test_invoice_calculations()
    {
        $item = InvoiceItem::make('Service 1')
            ->pricePerUnit(10)
            ->quantity(2);

        $invoice = Invoice::make()
            ->addItem($item)
            ->discountByPercent(10)
            ->taxRate(15);

        // Test that calculations can be performed
        $this->assertInstanceOf(Invoice::class, $invoice);
        $this->assertCount(1, $invoice->items);
    }

    /**
     * @test
     * @return void
     */
    public function test_currency_formatting()
    {
        $invoice = Invoice::make()
            ->currencySymbol('$')
            ->currencyCode('USD')
            ->currencyFormat('{SYMBOL}{VALUE}');

        $formatted = $invoice->formatCurrency(123.45);
        
        $this->assertStringContainsString('$', $formatted);
        $this->assertStringContainsString('123.45', $formatted);
    }

    /**
     * @test
     * @return void
     */
    public function test_invoice_item_calculations()
    {
        $item = InvoiceItem::make('Test Service')
            ->pricePerUnit(100)
            ->quantity(2)
            ->discountByPercent(10);

        $item->calculate(2); // Calculate with 2 decimal places

        $this->assertEquals(100, $item->price_per_unit);
        $this->assertEquals(2, $item->quantity);
        $this->assertEquals(10, $item->discount);
        $this->assertTrue($item->discount_percentage);
        
        // Test that the discount was applied correctly
        $this->assertEquals(20, $item->discount); // 10% of 200 (100 * 2)
    }

    /**
     * @test
     * @return void
     */
    public function test_seller_creation()
    {
        $seller = new Seller([
            'name' => 'Test Company',
            'address' => '123 Test Street',
            'code' => 'TEST123',
            'vat' => 'VAT123456',
            'phone' => '+1234567890',
        ]);

        $this->assertEquals('Test Company', $seller->name);
        $this->assertEquals('123 Test Street', $seller->address);
        $this->assertEquals('TEST123', $seller->code);
        $this->assertEquals('VAT123456', $seller->vat);
        $this->assertEquals('+1234567890', $seller->phone);
    }

    /**
     * @test
     * @return void
     */
    public function test_buyer_creation()
    {
        $buyer = new Buyer([
            'name' => 'John Doe',
            'address' => '456 Customer Ave',
            'code' => 'CUST789',
            'custom_fields' => [
                'email' => 'john@example.com',
                'phone' => '+0987654321',
            ],
        ]);

        $this->assertEquals('John Doe', $buyer->name);
        $this->assertEquals('456 Customer Ave', $buyer->address);
        $this->assertEquals('CUST789', $buyer->code);
        $this->assertEquals('john@example.com', $buyer->custom_fields['email']);
        $this->assertEquals('+0987654321', $buyer->custom_fields['phone']);
    }

    /**
     * @test
     * @return void
     */
    public function test_invoice_serial_number_generation()
    {
        $invoice = Invoice::make()
            ->series('TEST')
            ->sequence(123);

        $serialNumber = $invoice->getSerialNumber();
        
        // Should contain the series and sequence
        $this->assertStringContainsString('TEST', $serialNumber);
        $this->assertStringContainsString('123', $serialNumber);
    }

    /**
     * @test
     * @return void
     */
    public function test_invoice_date_formatting()
    {
        $invoice = Invoice::make()
            ->dateFormat('Y-m-d');

        $date = $invoice->getDate();
        
        // Should be a valid date format
        $this->assertIsString($date);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $date);
    }
} 