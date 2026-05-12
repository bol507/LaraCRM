<?php
// config/pdf.php

return [
    'paper_size' => env('PDF_PAPER_SIZE', 'letter'),
    'orientation' => env('PDF_ORIENTATION', 'portrait'),
    'default_font' => env('PDF_DEFAULT_FONT', 'dejavu sans'), 
    'allow_remote_images' => env('PDF_ALLOW_REMOTE_IMAGES', true),
    
    'payment_terms' => [
        'purchase_order' => [
            'advance' => 0.60,
            'progress' => 0.30,
            'completion' => 0.10,
        ],
        'quote' => [
            'advance' => 0.50,
            'progress' => 0.50,
        ],
    ],
    
    /*'default_terms' => [
        'purchase_order' => file_get_contents(resource_path('terms/purchase_order_default.txt')),
        'quote' => file_get_contents(resource_path('terms/quote_default.txt')),
    ],*/
];