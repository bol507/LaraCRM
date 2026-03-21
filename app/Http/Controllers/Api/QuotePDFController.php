<?php

namespace App\Http\Controllers\Api;

use App\Application\UseCases\Quote\GetQuoteUseCase;
use App\Application\UseCases\GetGeneralConditionsUseCase;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Log;

class QuotePDFController extends Controller
{
    protected $getQuoteUseCase;
    protected $getGeneralConditionsUseCase;

    public function __construct(
        GetQuoteUseCase $getQuoteUseCase,
        GetGeneralConditionsUseCase $getGeneralConditionsUseCase
    ) {
        $this->getQuoteUseCase = $getQuoteUseCase;
        $this->getGeneralConditionsUseCase = $getGeneralConditionsUseCase;
    }

    /**
     * Generar PDF de una cotización
     */
    public function generatePDF($quoteId)
    {
        try {
            $quote = $this->getQuoteUseCase->executeById($quoteId);

            if (!$quote) {
                return response()->json(['error' => 'Cotización no encontrada'], 404);
            }

            // Calcular ITBMS (7%)
            $itbms = $quote->subtotal * 0.07;
            $totalWithTax = $quote->subtotal + $itbms;

            // Calcular descuento total - MANEJAR ARRAYS Y OBJETOS
            $totalDiscount = 0;
            foreach ($quote->items as $item) {
                $listprice = is_array($item) ? ($item['listprice'] ?? 0) : ($item->listprice ?? 0);
                $quantity = is_array($item) ? ($item['quantity'] ?? 0) : ($item->quantity ?? 0);
                $discount_percent = is_array($item) ? ($item['discount_percent'] ?? 0) : ($item->discount_percent ?? 0);

                $itemDiscount = ($listprice * $quantity) * ($discount_percent / 100);
                $totalDiscount += $itemDiscount;
            }

            // Calcular abonos (60%, 30%, 10%)
            $abono60 = $totalWithTax * 0.60;
            $abono30 = $totalWithTax * 0.30;
            $abono10 = $totalWithTax * 0.10;

            // Obtener condiciones generales usando caso de uso
            $conditions = $this->getGeneralConditionsUseCase->execute('Quotes');

            $data = [
                'quote' => $quote,
                'itbms' => $itbms,
                'totalDiscount' => $totalDiscount,
                'totalWithTax' => $totalWithTax,
                'abono60' => $abono60,
                'abono30' => $abono30,
                'abono10' => $abono10,
                'terms_conditions' => $conditions,
                'company' => [
                    'name' => 'PACIFIC SAWMILL S.A.',
                    'address' => 'Bodega MOSA-Pallets Galera#1 Plaza Recursos Los Ángeles Calle Sixaola y Ave. 8va. A Norte, Urb. Industrial Los Ángeles Betania',
                    'ruc' => 'RUC: 155662926-2-2018 D.V.29',
                    'phone' => '+507 6676-8704',
                    'email' => 'ventas@canalwoods.com',
                    'social' => '@CanalWoods',
                    'website' => 'www.canalwoods.com'
                ]
            ];

            $pdf = Pdf::loadView('pdf.quote', $data);
            $pdf->setPaper('letter', 'portrait');
            Blade::directive('noescape', function () {
                return "<?php echo ''; ?>";
            });

            return $pdf->download("Cotizacion_{$quote->quoteno}.pdf");
        } catch (\Exception $e) {
            Log::error('Error generando PDF: ' . $e->getMessage());
            return response()->json([
                'error' => 'Error al generar el PDF: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener PDF como vista previa (sin descargar)
     */
    public function previewPDF($quoteId)
    {
        try {
            $quote = $this->getQuoteUseCase->executeById($quoteId);
            if (!$quote) {
                return response()->json(['error' => 'Cotización no encontrada'], 404);
            }

            $itbms = $quote->subtotal * 0.07;
            $totalWithTax = $quote->subtotal + $itbms;

            $totalDiscount = 0;
            foreach ($quote->items as $item) {
                $listprice = is_array($item) ? ($item['listprice'] ?? 0) : ($item->listprice ?? 0);
                $quantity = is_array($item) ? ($item['quantity'] ?? 0) : ($item->quantity ?? 0);
                $discount_percent = is_array($item) ? ($item['discount_percent'] ?? 0) : ($item->discount_percent ?? 0);

                $itemDiscount = ($listprice * $quantity) * ($discount_percent / 100);
                $totalDiscount += $itemDiscount;
            }

            // Calcular abonos (60%, 30%, 10%)
            $abono60 = $totalWithTax * 0.60;
            $abono30 = $totalWithTax * 0.30;
            $abono10 = $totalWithTax * 0.10;

            // Obtener condiciones generales usando caso de uso
            $conditions = $this->getGeneralConditionsUseCase->execute('Quotes');

            $data = [
                'quote' => $quote,
                'itbms' => $itbms,
                'totalDiscount' => $totalDiscount,
                'totalWithTax' => $totalWithTax,
                'abono60' => $abono60,
                'abono30' => $abono30,
                'abono10' => $abono10,
                'terms_conditions' => $conditions,
                'company' => [
                    'name' => 'PACIFIC SAWMILL S.A.',
                    'address' => 'Bodega MOSA-Pallets Galera#1 Plaza Recursos Los Ángeles Calle Sixaola y Ave. 8va. A Norte, Urb. Industrial Los Ángeles Betania',
                    'ruc' => 'RUC: 155662926-2-2018 D.V.29',
                    'phone' => '+507 6676-8704',
                    'email' => 'ventas@canalwoods.com',
                    'social' => '@CanalWoods',
                    'website' => 'www.canalwoods.com'
                ]
            ];

            $pdf = Pdf::loadView('pdf.quote', $data);
            $pdf->setPaper('letter', 'portrait');
            Blade::directive('noescape', function () {
                return "<?php echo ''; ?>";
            });
            return $pdf->stream("Cotizacion_{$quote->quoteno}.pdf");
        } catch (\Exception $e) {
            Log::error('Error previsualizando PDF: ' . $e->getMessage());
            return response()->json([
                'error' => 'Error al generar el PDF: ' . $e->getMessage()
            ], 500);
        }
    }
}
