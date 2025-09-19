<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class GroupPaymentValidationTest extends TestCase
{
    public function testAmountsValidationSucceedsWithinTolerance(): void
    {
        $result = btr_group_payment_amounts_are_valid(610.80, [305.40, 305.40]);

        $this->assertTrue($result['is_valid']);
        $this->assertSame(610.80, $result['expected']);
        $this->assertSame(610.80, $result['sum']);
        $this->assertSame(0.0, $result['difference']);
    }

    public function testAmountsValidationFailsWhenDifferenceTooHigh(): void
    {
        $result = btr_group_payment_amounts_are_valid(610.80, [320.00, 270.00], 0.01);

        $this->assertFalse($result['is_valid']);
        $this->assertSame(-20.8, round($result['difference'], 1));
    }

    public function testSharesValidationPasses(): void
    {
        $result = btr_group_payment_shares_are_valid(4, [2, 2]);

        $this->assertTrue($result['is_valid']);
        $this->assertSame(4, $result['sum']);
    }

    public function testSharesValidationFailsWhenSumDoesNotMatch(): void
    {
        $result = btr_group_payment_shares_are_valid(4, [1, 1]);

        $this->assertFalse($result['is_valid']);
        $this->assertSame(2, $result['sum']);
        $this->assertSame(4, $result['expected']);
    }
}
