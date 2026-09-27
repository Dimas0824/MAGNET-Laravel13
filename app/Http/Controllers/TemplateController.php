<?php

namespace App\Http\Controllers;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\View;

class TemplateController extends Controller
{
    /**
     * Templates that may be rendered through the preview endpoint.
     *
     * @var array<int, string>
     */
    private const ALLOWED_TEMPLATES = [
        'curriculum-vitae',
        'laporan-statistik-pdf',
        'surat-izin-magang',
    ];

    public function previewFile(string $file_name)
    {
        abort_unless(in_array($file_name, self::ALLOWED_TEMPLATES, true), 404, 'Template not found');

        $viewPath = 'templates.pdf.'.$file_name;

        if (! View::exists($viewPath)) {
            abort(404, 'Template not found');
        }

        $data = [
            'title' => ucfirst($file_name).' PDF',
        ];

        $pdf = Pdf::loadView($viewPath, $data);

        return $pdf->stream($file_name.'.pdf');
    }
}
