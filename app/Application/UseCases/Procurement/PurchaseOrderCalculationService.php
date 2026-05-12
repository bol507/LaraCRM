<?php
// app/Application/UseCases/Procurement/PurchaseOrderCalculationService.php

namespace App\Application\UseCases\Procurement;

class PurchaseOrderCalculationService
{
    public function calculateForPO(object $po): array
    {
        $subtotal = (float) ($po->subtotal ?? $po->items->sum(fn($i) => $i->line_total ?? 0));

        // ✅ ITBMS configurable por proyecto/país (no hardcodeado)
        $itbmsRate = config('taxes.itbms_rate', 0.07); // 7% default
        $itbms = $subtotal * $itbmsRate;
        $totalWithTax = $subtotal + $itbms;

        // ✅ Calcular descuentos totales desde ítems
        $totalDiscount = $po->items->sum(
            fn($i) => ((float)($i->unit_price ?? 0) * (float)($i->quantity ?? 0)) *
                ((float)($i->discount_percent ?? 0) / 100)
        );

        // ✅ Términos de pago configurables (60/30/10 o personalizado)
        $paymentTerms = config('pdf.payment_terms.purchase_order', [
            'advance' => 0.60,
            'progress' => 0.30,
            'completion' => 0.10,
        ]);

        return [
            'subtotal' => $subtotal,
            'itbms_rate' => $itbmsRate,
            'itbms' => $itbms,
            'total_discount' => $totalDiscount,
            'total_with_tax' => $totalWithTax,
            'payment_schedule' => [
                'advance' => round($totalWithTax * $paymentTerms['advance'], 2),
                'progress' => round($totalWithTax * $paymentTerms['progress'], 2),
                'completion' => round($totalWithTax * $paymentTerms['completion'], 2),
            ],
        ];
    }
}
