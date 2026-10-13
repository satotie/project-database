<?php

namespace App\Http\Controllers;

use App\Services\SalesReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SalesReportExportController extends Controller
{
    public function __invoke(Request $request): StreamedResponse
    {
        $data = $request->validate([
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
        ]);

        $report = new SalesReportService(Carbon::parse($data['from']), Carbon::parse($data['to']));
        $filename = "sales-report-{$data['from']}-to-{$data['to']}.csv";

        return response()->streamDownload(function () use ($report, $data) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel shows Thai correctly

            $summary = $report->summary();
            fputcsv($out, ['รายงานยอดขาย', $data['from'], $data['to']]);
            fputcsv($out, ['รายได้รวม', $summary['revenue']]);
            fputcsv($out, ['จำนวนคำสั่งซื้อ', $summary['orders']]);
            fputcsv($out, ['ยอดเฉลี่ยต่อคำสั่งซื้อ', $summary['average']]);
            fputcsv($out, ['ส่วนลดรวม', $summary['discount']]);
            fputcsv($out, []);

            fputcsv($out, ['วันที่', 'รายได้']);
            foreach ($report->revenuePerDay() as $day => $revenue) {
                fputcsv($out, [$day, $revenue]);
            }
            fputcsv($out, []);

            fputcsv($out, ['อันดับ', 'สินค้า', 'แบรนด์', 'จำนวน', 'ยอดขาย']);
            foreach ($report->topProducts() as $i => $row) {
                fputcsv($out, [$i + 1, $row->name, $row->brand, $row->quantity, $row->revenue]);
            }
            fputcsv($out, []);

            fputcsv($out, ['แบรนด์', 'จำนวน', 'ยอดขาย']);
            foreach ($report->revenueByBrand() as $row) {
                fputcsv($out, [$row->name, $row->quantity, $row->revenue]);
            }
            fputcsv($out, []);

            fputcsv($out, ['หมวดหมู่', 'จำนวน', 'ยอดขาย']);
            foreach ($report->revenueByCategory() as $row) {
                fputcsv($out, [$row->name, $row->quantity, $row->revenue]);
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
