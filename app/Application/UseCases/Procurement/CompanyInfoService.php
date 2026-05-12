<?php
// app/Application/UseCases/Procurement/CompanyInfoService.php

namespace App\Application\UseCases\Procurement;

use Illuminate\Support\Facades\DB;

class CompanyInfoService
{
    public function getForPDF(): array
    {
        return [
            'name' => config('company.legal_name', 'Tu Empresa S.A.'),
            'address' => config('company.address'),
            'ruc' => config('company.ruc'),
            'phone' => config('company.phone'),
            'email' => config('company.email'),
            'website' => config('company.website'),
            'logo_path' => public_path(config('company.logo_pdf')), // Para incluir logo en PDF
        ];
    }
}
