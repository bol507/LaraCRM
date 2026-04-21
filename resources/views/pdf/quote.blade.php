<!doctype html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <title>Cotización {{ $quote->quoteno }}</title>
    <style>
        body {
            font-family: Helvetica, Arial, sans-serif;
            margin: 0;
            padding: 0;
            color: #000;
            font-size: 9pt;
        }

        @page {
            size: letter portrait;
            margin: 10mm 10mm 15mm 10mm;
        }

        .content {
            padding: 10mm 8mm 10mm 12mm;
            page-break-inside: avoid;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }

        th {
            background: #eee;
            border: 1px solid #999;
            padding: 4px;
            font-size: 8pt;
            text-align: center;
            font-weight: bold;
        }

        td {
            border: 1px solid #999;
            padding: 4px;
            font-size: 8pt;
        }

        .header-table {
            width: 100%;
            border-bottom: 2px solid #000;
            margin-bottom: 15px;
        }

        .header-table td {
            border: none;
            padding: 0;
            vertical-align: top;
        }

        .company-info {
            width: 65%;
        }

        .company-info h1 {
            font-size: 14pt;
            font-weight: bold;
            margin: 0 0 5px 0;
        }

        .company-info p {
            font-size: 8pt;
            margin: 2px 0;
            line-height: 1.3;
        }

        .quote-meta {
            width: 35%;
            text-align: right;
        }

        .quote-meta h2 {
            font-size: 16pt;
            font-weight: bold;
            margin: 0 0 5px 0;
        }

        .quote-meta p {
            font-size: 8pt;
            margin: 2px 0;
            line-height: 1.3;
        }

        .client-section {
            font-size: 11pt;
            font-weight: bold;
            margin-bottom: 15px;
        }

        .qty {
            width: 12%;
            text-align: center;
        }

        .name {
            width: 58%;
            text-align: left;
        }

        .price {
            width: 12%;
            text-align: right;
        }

        .total {
            width: 18%;
            text-align: right;
        }

        .totales {
            margin-top: 15px;
            width: 60%;
            margin-left: auto;
        }

        .totales td {
            border: none;
            font-size: 8pt;
            padding: 2px 4px;
        }

        .totales .label {
            text-align: right;
            font-weight: bold;
        }

        .totales .valor {
            text-align: right;
            font-weight: bold;
        }

        .label-total {
            font-size: 11pt;
            font-weight: bold;
        }

        .valor-total {
            font-size: 11pt;
            font-weight: bold;
        }

        .tc-box {
            padding: 12mm;
            border: 1px solid #ddd;
        }

        .tc-titulo {
            font-size: 10pt;
            font-weight: bold;
            color: #004b8d;
            margin-bottom: 10px;
        }

        .tc-texto {
            font-size: 7pt;
            line-height: 1.5;
            text-align: justify;
        }

        .small {
            font-size: 7pt;
        }

        .bold {
            font-weight: bold;
        }

        .first-page-content {
            min-height: 210mm;
            /* Ajusta según necesites */
        }

        /* ✅ Condiciones de pago al final de la primera página */
        .footer-conditions {
            position: fixed;
            bottom: 0cm;
            left: 0cm;
            right: 0cm;
            height: 2cm;

            font-size: 7pt;
            line-height: 1.3;
            text-align: left;

        }

        /* ✅ Para que los términos y condiciones empiecen en nueva página */
        .terms-section {
            page-break-before: always;
            margin-top: 25mm;
        }

        /* ✅ Evitar que el contenido se corte */
        .no-page-break {
            page-break-inside: avoid;
            break-inside: avoid;
        }
    </style>
</head>

<body>
    <div class="content">
        <!-- HEADER - USANDO TABLAS -->
        <table class="header-table">
            <tr>
                <td class="company-info" valign="top">
                    <div style="margin-bottom: 8px;">
                        <img src="{{ public_path('images/logo/canal_logo.png') }}"
                            alt="Pacific Sawmill S.A."
                            style="height: 60px; width: auto; display: block;">
                    </div>
                    <div class="client-section">PACIFIC SAWMILL S.A.</div>
                    <p>Bodega MOSA-Pallets Galera #1 Plaza Recursos Los Ángeles</p>
                    <p>Calle Sixaola y Ave. 8va. A Norte, Urb. Industrial Los Ángeles Betania</p>
                    <p>RUC: 155662926-2-2018 D.V.29</p>
                    <p>+507 6676-8704 ventas@canalwoods.com</p>
                </td>
                <td class="quote-meta" valign="top">
                    <h2>{{ $quote->quoteno }}</h2>
                    <p>@CanalWoods</p>
                    <p>{{ strtoupper(\Carbon\Carbon::parse($quote->createdtime)->locale('es')->translatedFormat('d/M/Y')) }}</p>
                    <p>www.canalwoods.com</p>
                </td>
            </tr>
        </table>

        <!-- CLIENTE -->
        <div class="client-section">
            {{ $quote->account_name }}
        </div>

        <!-- TABLA DE PRODUCTOS -->
        <table>
            <thead>
                <tr>
                    <th class="qty">Cant.</th>
                    <th class="name">Producto / Servicio</th>
                    <th class="price">Precio</th>
                    <th class="total">Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach($quote->items as $item)
                @php
                $is_array = is_array($item);
                $quantity = $is_array ? ($item['quantity'] ?? 1) : ($item->quantity ?? 1);
                $description = $is_array ? ($item['description'] ?? '') : ($item->description ?? '');
                $comment = $is_array ? ($item['comment'] ?? '') : ($item->comment ?? '');
                $listprice = $is_array ? ($item['listprice'] ?? 0) : ($item->listprice ?? 0);
                $total = $is_array ? ($item['total'] ?? 0) : ($item->total ?? 0);
                @endphp
                <tr>
                    <td class="qty">{{ $quantity }}</td>
                    <td class="name">
                        <strong>{{ $description }}</strong>
                        @if($comment)
                        <br><span class="small">{{ $comment }}</span>
                        @endif
                    </td>
                    <td class="price">${{ number_format($listprice, 2, '.', ',') }}</td>
                    <td class="total">${{ number_format($total, 2, '.', ',') }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>

        <!-- TOTALES -->
        <table class="totales">
            <tr>
                <td class="label">Subtotal:</td>
                <td class="valor">${{ number_format($quote->subtotal, 2, '.', ',') }}</td>
            </tr>

            @if($totalDiscount > 0)
            <tr>
                <td class="label">Descuento:</td>
                <td class="valor">-${{ number_format($totalDiscount, 2, '.', ',') }}</td>
            </tr>
            @endif

            <tr>
                <td class="label">ITBMS:</td>
                <td class="valor">${{ number_format($itbms, 2, '.', ',') }}</td>
            </tr>

            <tr>
                <td class="label label-total">TOTAL:</td>
                <td class="valor valor-total">${{ number_format($totalWithTax, 2, '.', ',') }}</td>
            </tr>

            <!-- Abonos -->
            <tr style="background-color: #e6f2ff;">
                <td class="label">Abono requerido (60%):</td>
                <td class="valor">${{ number_format($abono60, 2, '.', ',') }}</td>
            </tr>
            <tr style="background-color: #fff9e6;">
                <td class="label">Abono 30%:</td>
                <td class="valor">${{ number_format($abono30, 2, '.', ',') }}</td>
            </tr>
            <tr style="background-color: #ffe6e6;">
                <td class="label">Abono 10%:</td>
                <td class="valor">${{ number_format($abono10, 2, '.', ',') }}</td>
            </tr>
        </table>
        <!-- ✅ CONDICIONES DE PAGO - AL FINAL DE LA PRIMERA PÁGINA -->
        <div class="footer-conditions">
            <strong>Condiciones de pago:</strong><br>
            ACH: Banco General, Cuenta Corriente #: 03-49-01-123670-9, Pacific Sawmill S.A. Disponibilidad dependiente de inventario al momento de compra.
            La madera posee rajaduras, nudos, distintos colores e imperfecciones propias de su naturaleza.
            Tolerancia: +- 1/16 en espesor, +- 1/8 en ancho, +- 1 en largo.
            Esta cotización tiene una validez de 15 días calendario.
        </div>

        <!-- DESCRIPCIÓN -->
        @if($quote->description)
        <div style="page-break-before: always; margin-top: 20mm;">
            <div class="bold" style="margin-bottom: 10px;">Descripción:</div>
            <div style="font-size: 8pt; line-height: 1.5;">
                {!! nl2br(e($quote->description)) !!}
            </div>
        </div>
        @endif


    </div>

    <!-- TÉRMINOS Y CONDICIONES - SIEMPRE EN NUEVA PÁGINA -->
    <div class="terms-section">
        <div class="tc-box">
            <div class="tc-titulo">TÉRMINOS Y CONDICIONES</div>
            <div class="tc-texto">
                @if($terms_conditions)
                {!! nl2br(e($terms_conditions)) !!}
                @else
                Para una cotizacion precisa es necesario:
                1. Foto del espacio donde se ubicará el mueble.
                2. Medidas del espacio aproximadas.
                3. Foto de referencia del mueble deseado.

                No se realizaran presupuestos de instalación o estimaciones de presupuesto de instalacion en obras de construccion que no esten terminadas y acondicionadas para la instalcion de mobiliarios

                Para dar inicio a los trabajos requerimos un anticipo del 60% del monto total.
                El 40% restante se cancela cuando el o los productos estan terminados en taller.
                En caso de estar incluida la instalacion se cancelara 30% en la entrega y el 10% restante al finalizar la instalacion.

                El cliente debe verificar el presupuesto para asegurarse que incluye todo lo que desea y con las especificaciones que espera recibir Antes de iniciar el proceso de transformacion de materiales el cliente debe aprobar diseño.
                los tiempos de decision de cliente en cuanto aprobacion y eleccion de color son limitados a 3 dias hábiles.
                Cliente debe proveer muestra fisica en madera del color deseado para dar inicio a la fabricación

                La inspeccion para medir abombamiento niveles plomadas y planos del area donde van los muebles tiene un costo de 100.00$ El presupuesto de instalación es elaborado después de la visita tecnica.
                Cliente de compromete a aceptar los criterios tecnicos de Canalwood, de lo contrario no hay reclamo.
                Canalwoods no se hace responsable por decisiones del cliente No se aceptan reclamos despues de recibir conforme la mercancia

                En algunos casos se requiere desarrollos que deben ser especificados en la cotización.
                @endif
            </div>
        </div>
    </div>
</body>

</html>