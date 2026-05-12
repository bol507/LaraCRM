<?php
// app/Application/UseCases/Procurement/GetPurchaseOrderForPdfUseCase.php

namespace App\Application\UseCases\Procurement;

use Illuminate\Support\Facades\DB;

class GetPurchaseOrderForPdfUseCase
{
    // app/Application/UseCases/Procurement/GetPurchaseOrderForPdfUseCase.php

    public function execute(int $poId, int $projectId): ?object
    {
        $po = DB::connection('vtiger')
            ->table('nova_purchase_orders as po')
            ->leftJoin('vtiger_vendor as v', 'po.vendor_id', '=', 'v.vendorid')
            ->where('po.id', $poId)
            ->where('po.project_id', $projectId) 
            ->select('po.*', 'v.vendorname as vendor_name', 'v.email as vendor_email', 'v.phone as vendor_phone')
            ->first();

        if (!$po) return null;

        // Cargar ítems
        $po->items = DB::connection('vtiger')
            ->table('nova_purchase_order_items')
            ->where('purchase_order_id', $poId)
            ->orderBy('id')
            ->get();

        // Calcular subtotal si no viene
        $po->subtotal ??= $po->items->sum(fn($i) => (float)($i->line_total ?? 0));

        return $po;
    }
}
