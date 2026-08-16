<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Centralized File Upload Limits & Allowed MIME Types
    |--------------------------------------------------------------------------
    |
    | Centralized configuration referenced by all upload validation rules,
    | proposal/invoice PDF generation, and image gallery uploads across the app.
    |
    */

    'max_file_size_kb' => env('UPLOAD_MAX_FILE_SIZE_KB', 5120), // 5MB limit

    'allowed_pdf_mimes' => ['pdf', 'application/pdf'],

    'allowed_image_mimes' => ['jpg', 'jpeg', 'png', 'webp', 'image/jpeg', 'image/png', 'image/webp'],

    'allowed_document_mimes' => ['pdf', 'doc', 'docx', 'xls', 'xlsx'],

];
