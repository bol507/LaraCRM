<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ProcurementCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $data = [
            // item_type
            ['category' => 'item_type', 'code' => 'material', 'label' => 'Material', 'sort_order' => 1],
            ['category' => 'item_type', 'code' => 'tool', 'label' => 'Herramienta', 'sort_order' => 2],
            ['category' => 'item_type', 'code' => 'consumable', 'label' => 'Insumo/Consumible', 'sort_order' => 3],
            ['category' => 'item_type', 'code' => 'service', 'label' => 'Servicio/Mano de obra', 'sort_order' => 4],
            
            // reason_type
            ['category' => 'reason_type', 'code' => 'new_requirement', 'label' => 'Nuevo requerimiento', 'sort_order' => 1],
            ['category' => 'reason_type', 'code' => 'missing', 'label' => 'Falta en inventario', 'sort_order' => 2],
            ['category' => 'reason_type', 'code' => 'damaged', 'label' => 'Material dañado', 'sort_order' => 3],
            ['category' => 'reason_type', 'code' => 'lost', 'label' => 'Extraviado', 'sort_order' => 4],
            ['category' => 'reason_type', 'code' => 'replacement', 'label' => 'Reemplazo/Desgaste', 'sort_order' => 5],
            
            // unit
            ['category' => 'unit', 'code' => 'unit', 'label' => 'Unidad', 'sort_order' => 1],
            ['category' => 'unit', 'code' => 'kg', 'label' => 'Kilogramo (kg)', 'sort_order' => 2],
            ['category' => 'unit', 'code' => 'm', 'label' => 'Metro (m)', 'sort_order' => 3],
            ['category' => 'unit', 'code' => 'liter', 'label' => 'Litro (L)', 'sort_order' => 4],
            ['category' => 'unit', 'code' => 'box', 'label' => 'Caja', 'sort_order' => 5],
            ['category' => 'unit', 'code' => 'set', 'label' => 'Juego/Set', 'sort_order' => 6],
            
            // priority
            ['category' => 'priority', 'code' => 'low', 'label' => 'Baja', 'sort_order' => 1],
            ['category' => 'priority', 'code' => 'medium', 'label' => 'Media', 'sort_order' => 2],
            ['category' => 'priority', 'code' => 'high', 'label' => 'Alta', 'sort_order' => 3],
            ['category' => 'priority', 'code' => 'urgent', 'label' => 'Urgente', 'sort_order' => 4],
        ];

       
        foreach ($data as $row) {
            DB::table('procurement_catalogs')->updateOrInsert(
                ['category' => $row['category'], 'code' => $row['code']],
                $row
            );
        }
    }
}