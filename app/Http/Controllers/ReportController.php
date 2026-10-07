<?php

namespace App\Http\Controllers;

use App\FinancialReport;
use Carbon\CarbonImmutable;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

class ReportController extends Controller
{
    public function download(Request $request, FinancialReport $report): Response
    {
        $data = $request->validate([
            'period' => ['required', Rule::in(['month', 'year'])],
            'year' => ['required', 'integer', 'between:1900,9998'],
            'month' => ['exclude_unless:period,month', 'required', 'integer', 'between:1,12'],
        ]);
        $start = CarbonImmutable::create((int) $data['year'], (int) ($data['month'] ?? 1), 1)->startOfDay();
        $end = $data['period'] === 'year' ? $start->addYear() : $start->addMonth();
        $period = $data['period'] === 'year' ? $start->format('Y') : $start->format('F Y');
        $filename = 'finance-report-'.$start->format($data['period'] === 'year' ? 'Y' : 'Y-m').'.pdf';
        $html = view('report-pdf', [
            ...$report->data($request->user(), $start, $end),
            'period' => $period,
            'owner' => $request->user()->name,
            'generated' => now()->format(($request->user()->date_format ?? 'd.m.Y').' H:i'),
            'dateFormat' => $request->user()->date_format ?? 'd.m.Y',
        ])->render();
        $options = new Options;
        $options->setDefaultFont('DejaVu Sans');
        $options->setIsRemoteEnabled(false);
        $options->setIsPhpEnabled(false);
        $options->setIsJavascriptEnabled(false);
        $options->setFontCache(storage_path('framework/cache'));
        $pdf = new Dompdf($options);
        $pdf->setPaper('A4');
        $pdf->loadHtml($html, 'UTF-8');
        $pdf->render();
        $pdf->getCanvas()->page_text(42, 808, 'Page {PAGE_NUM} of {PAGE_COUNT}', $pdf->getFontMetrics()->getFont('DejaVu Sans'), 8, [0.4, 0.45, 0.5]);

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
