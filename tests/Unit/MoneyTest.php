<?php

namespace Tests\Unit;

use App\Support\Money;
use PHPUnit\Framework\TestCase;

class MoneyTest extends TestCase
{
    public function test_normalize(): void
    {
        $this->assertSame('0.00', Money::normalize(null));
        $this->assertSame('0.00', Money::normalize(''));
        $this->assertSame('12.00', Money::normalize('12'));
        $this->assertSame('12.35', Money::normalize('12.345'));
        $this->assertSame('-12.35', Money::normalize('-12.345'));
        $this->assertSame('0.30', Money::normalize(0.1 + 0.2));
        $this->assertSame('0.00', Money::normalize('-0.001'));
        $this->assertSame('999999999999.99', Money::normalize('999999999999.99'));
    }

    public function test_arithmetic_is_exact(): void
    {
        $this->assertSame('0.30', Money::add('0.10', '0.20'));
        $total = '0';
        for ($i = 0; $i < 1000; $i++) {
            $total = Money::add($total, '0.01');
        }
        $this->assertSame('10.00', $total);
        $this->assertSame('-0.01', Money::sub('100.00', '100.01'));
        $this->assertSame(1, Money::compare('100.01', '100'));
    }

    public function test_divide_up_and_percent(): void
    {
        $this->assertSame('33.34', Money::divideUp('100', 3));
        $this->assertSame('25.00', Money::divideUp('100', 4));
        $this->assertSame('100.00', Money::divideUp('100', 0));
        $this->assertSame(33.3, Money::percent('1', '3'));
        $this->assertSame(100.0, Money::percent('150', '100'));
        $this->assertSame(0.0, Money::percent('5', '0'));
    }
}
