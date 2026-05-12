<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Orden de Compra #{{ $po->po_number }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #333; }
        .header { display: flex; justify-content: space-between; margin-bottom: 20px; border-bottom: 2px solid #333; padding-bottom: 10px; }
        .company-info h2 { margin: 0 0 5px 0; font-size: 16px; }
        .po-info { text-align: right; }
        .po-info p { margin: 2px 0; }
        .vendor-info { margin: 15px 0; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th { background: #f5f5f5; padding: 8px; text-align: left; border: 1px solid #ddd; font-weight: bold; }
        td { padding: 8px; border: 1px solid #ddd; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .totals { float: right; width: 300px; margin-top: 10px; }
        .totals-row { display: flex; justify-content: space-between; padding: 5px 0; border-bottom: 1px solid #eee; }
        .totals-row.total { font-weight: bold; font-size: 14px; border-top: 2px solid #333; border-bottom: none; padding-top: 10px; }
        .footer { margin-top: 40px; font-size: 10px; color: #666; text-align: center; }
        .signature { margin-top: 50px; display: flex; justify-content: space-between; }
        .signature-box { width: 45%; border-top: 1px solid #333; padding-top: 5px; text-align: center; font-size: 10px; }
    </style>
</head>
<body>
    <div class="header">
        <div class="company-info">
            <h2>{{ config('app.name', 'Tu Empresa') }}</h2>
            <p>{{ config('app.address', 'Dirección de la empresa') }}<br>
            {{ config('app.phone', 'Teléfono') }} | {{ config('app.email', 'email@empresa.com') }}</p>
        </div>
        <div class="po-info">
            <h3 style="margin: 0;">ORDEN DE COMPRA</h3>
            <p><strong>#{{ $po->po_number }}</strong></p>
            <p>Fecha: {{ \Carbon\Carbon::parse($po->created_at)->format('d/m/Y') }}</p>
            @if($po->expected_delivery_date)
            <p>Entrega esperada: {{ \Carbon\Carbon::parse($po->expected_delivery_date)->format('d/m/Y') }}</p>
            @endif
        </div>
    </div>

    <div class="vendor-info">
        <p><strong>Proveedor:</strong> {{ $po->vendor_name ?? 'N/A' }}<br>
        @if($po->vendor_address){{ $po->vendor_address }}<br>@endif
        @if($po->vendor_email){{ $po->vendor_email }}<br>@endif
        @if($po->vendor_phone){{ $po->vendor_phone }}@endif</p>
    </div>

    @if($po->material_request_id)
    <p style="font-size: 10px; color: #666;">
        <strong>Referencia:</strong> Solicitud de Material #{{ $po->material_request_id }}
    </p>
    @endif

    <table>
        <thead>
            <tr>
                <th width="5%">#</th>
                <th width="40%">Descripción</th>
                <th class="text-center" width="10%">Cant.</th>
                <th class="text-center" width="10%">Unidad</th>
                <th class="text-right" width="12%">Precio Unit.</th>
                <th class="text-right" width="10%">Desc.</th>
                <th class="text-right" width="13%">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($po->items as $index => $item)
            <tr>
                <td class="text-center">{{ $index + 1 }}</td>
                <td>
                    <strong>{{ $item->item_name }}</strong>
                    @if($item->notes)<br><small style="color:#666">{{ $item->notes }}</small>@endif
                </td>
                <td class="text-center">{{ number_format($item->quantity, 2) }}</td>
                <td class="text-center">{{ $item->unit }}</td>
                <td class="text-right">${{ number_format($item->unit_price, 2) }}</td>
                <td class="text-right">{{ number_format($item->discount_percent ?? 0, 1) }}%</td>
                <td class="text-right"><strong>${{ number_format($item->line_total, 2) }}</strong></td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="totals">
        <div class="totals-row">
            <span>Subtotal:</span>
            <span>${{ number_format($po->subtotal ?? $po->total_amount, 2) }}</span>
        </div>
        @if(($po->tax_amount ?? 0) > 0)
        <div class="totals-row">
            <span>Impuestos:</span>
            <span>${{ number_format($po->tax_amount, 2) }}</span>
        </div>
        @endif
        <div class="totals-row total">
            <span>TOTAL:</span>
            <span>${{ number_format($po->total_amount, 2) }}</span>
        </div>
    </div>

    @if($po->terms)
    <div style="clear: both; margin-top: 30px; page-break-inside: avoid;">
        <p><strong>Términos y Condiciones:</strong></p>
        <p style="font-size: 10px; white-space: pre-wrap;">{{ $po->terms }}</p>
    </div>
    @endif

    @if($po->internal_notes)
    <div style="margin-top: 20px; padding: 10px; background: #f9f9f9; border-left: 3px solid #ccc; font-size: 10px;">
        <strong>Notas Internas:</strong><br>
        {{ $po->internal_notes }}
    </div>
    @endif

    <div class="signature">
        <div class="signature-box">
            <p>Firma Autorizada</p>
            <p style="font-size: 9px; color: #999;">Nombre y cargo</p>
        </div>
        <div class="signature-box">
            <p>Recibido por Proveedor</p>
            <p style="font-size: 9px; color: #999;">Fecha y firma</p>
        </div>
    </div>

    <div class="footer">
        <p>Documento generado electrónicamente el {{ now()->format('d/m/Y H:i') }} | PO #{{ $po->po_number }}</p>
    </div>
</body>
</html>