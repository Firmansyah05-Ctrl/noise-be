<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Barryvdh\DomPDF\Facade\Pdf;

class ExportController extends Controller
{
    // Helper function to format decimal numbers
    private function formatDecimal($value)
    {
        if ($value === null || $value === '') return null;
        return number_format((float)$value, 2);
    }

    // Helper function to format dates
    private function formatDate($dateObj)
    {
        if (!$dateObj) return null;
        return date('d/m/Y H:i:s', strtotime($dateObj));
    }

    // Helper function to get data for a single table
    private function getTableData($tableName, $daysNum)
    {
        // First, find the most recent timestamp in the database
        $latestRow = DB::table($tableName)
            ->orderBy('created_at', 'desc')
            ->first();

        if (!$latestRow) {
            return [];
        }

        $latestTimestamp = $latestRow->created_at;
        $timeRangeStart = date('Y-m-d H:i:s', strtotime($latestTimestamp . " -{$daysNum} days"));

        // Base query with timezone conversion for created_at (+8 hours)
        $query = DB::table($tableName)
            ->select(DB::raw("*, CONVERT_TZ(created_at, '+00:00', '+08:00') as created_at"))
            ->where('created_at', '>=', $timeRangeStart)
            ->where('created_at', '<=', $latestTimestamp)
            ->orderBy('created_at', 'desc');

        return $query->get()->toArray();
    }

    // API endpoint for viewing data
    public function index(Request $request)
    {
        $reportType = $request->query('reportType');
        $days = $request->query('days', 1);

        try {
            // Parse days parameter with fallback to 1
            $daysNum = intval($days) ?: 1;

            $allResults = [];
            $reportTitle = '';

            switch ($reportType) {
                case "laeq":
                    $reportTitle = "LAeq Sound Level Report";
                    $results = $this->getTableData('laeq', $daysNum);
                    $allResults = array_map(function ($row) {
                        return [
                            'id' => $row->id,
                            'laeq' => $this->formatDecimal($row->value),
                            'created_at' => $this->formatDate($row->created_at),
                        ];
                    }, $results);
                    break;

                case "percentiles":
                    $reportTitle = "Sound Level Percentiles Report";
                    $results = $this->getTableData('laeq_metrics', $daysNum);
                    $allResults = array_map(function ($row) {
                        return [
                            'id' => $row->id,
                            'L10' => $this->formatDecimal($row->L10),
                            'L50' => $this->formatDecimal($row->L50),
                            'L90' => $this->formatDecimal($row->L90),
                            'created_at' => $this->formatDate($row->created_at),
                        ];
                    }, $results);
                    break;

                case "extremes":
                    $reportTitle = "Sound Level Extremes Report";
                    $results = $this->getTableData('laeq_lmin_lmax', $daysNum);
                    $allResults = array_map(function ($row) {
                        return [
                            'id' => $row->id,
                            'Lmin' => $this->formatDecimal($row->Lmin),
                            'Lmax' => $this->formatDecimal($row->Lmax),
                            'created_at' => $this->formatDate($row->created_at),
                        ];
                    }, $results);
                    break;

                case "all":
                default:
                    $reportTitle = "Complete Sound Level Report";
                    $laeqData = $this->getTableData('laeq', $daysNum);
                    $metricsData = $this->getTableData('laeq_metrics', $daysNum);
                    $extremesData = $this->getTableData('laeq_lmin_lmax', $daysNum);

                    // Combine all data
                    $combined = [];

                    foreach ($laeqData as $row) {
                        $combined[] = [
                            'id' => $row->id,
                            'laeq' => $this->formatDecimal($row->value),
                            'L10' => null,
                            'L50' => null,
                            'L90' => null,
                            'Lmin' => null,
                            'Lmax' => null,
                            'created_at' => $row->created_at,
                            'db_time' => $row->created_at,
                        ];
                    }

                    foreach ($metricsData as $row) {
                        $combined[] = [
                            'id' => $row->id,
                            'laeq' => null,
                            'L10' => $this->formatDecimal($row->L10),
                            'L50' => $this->formatDecimal($row->L50),
                            'L90' => $this->formatDecimal($row->L90),
                            'Lmin' => null,
                            'Lmax' => null,
                            'created_at' => $row->created_at,
                            'db_time' => $row->created_at,
                        ];
                    }

                    foreach ($extremesData as $row) {
                        $combined[] = [
                            'id' => $row->id,
                            'laeq' => null,
                            'L10' => null,
                            'L50' => null,
                            'L90' => null,
                            'Lmin' => $this->formatDecimal($row->Lmin),
                            'Lmax' => $this->formatDecimal($row->Lmax),
                            'created_at' => $row->created_at,
                            'db_time' => $row->created_at,
                        ];
                    }

                    // Sort by created_at
                    usort($combined, function ($a, $b) {
                        return strtotime($b['db_time']) - strtotime($a['db_time']);
                    });

                    // Fill in missing values with the latest available data
                    $lastValues = [
                        'laeq' => null,
                        'L10' => null,
                        'L50' => null,
                        'L90' => null,
                        'Lmin' => null,
                        'Lmax' => null,
                    ];

                    // First pass to find the most recent values
                    foreach ($combined as $row) {
                        if ($row['laeq'] !== null && $lastValues['laeq'] === null) {
                            $lastValues['laeq'] = $row['laeq'];
                        }
                        if ($row['L10'] !== null && $lastValues['L10'] === null) {
                            $lastValues['L10'] = $row['L10'];
                        }
                        if ($row['L50'] !== null && $lastValues['L50'] === null) {
                            $lastValues['L50'] = $row['L50'];
                        }
                        if ($row['L90'] !== null && $lastValues['L90'] === null) {
                            $lastValues['L90'] = $row['L90'];
                        }
                        if ($row['Lmin'] !== null && $lastValues['Lmin'] === null) {
                            $lastValues['Lmin'] = $row['Lmin'];
                        }
                        if ($row['Lmax'] !== null && $lastValues['Lmax'] === null) {
                            $lastValues['Lmax'] = $row['Lmax'];
                        }
                    }

                    // Second pass to fill in missing values
                    $allResults = array_map(function ($row) use (&$lastValues) {
                        if ($row['laeq'] !== null) $lastValues['laeq'] = $row['laeq'];
                        if ($row['L10'] !== null) $lastValues['L10'] = $row['L10'];
                        if ($row['L50'] !== null) $lastValues['L50'] = $row['L50'];
                        if ($row['L90'] !== null) $lastValues['L90'] = $row['L90'];
                        if ($row['Lmin'] !== null) $lastValues['Lmin'] = $row['Lmin'];
                        if ($row['Lmax'] !== null) $lastValues['Lmax'] = $row['Lmax'];

                        return [
                            'id' => $row['id'],
                            'laeq' => $row['laeq'] !== null ? $row['laeq'] : $lastValues['laeq'],
                            'L10' => $row['L10'] !== null ? $row['L10'] : $lastValues['L10'],
                            'L50' => $row['L50'] !== null ? $row['L50'] : $lastValues['L50'],
                            'L90' => $row['L90'] !== null ? $row['L90'] : $lastValues['L90'],
                            'Lmin' => $row['Lmin'] !== null ? $row['Lmin'] : $lastValues['Lmin'],
                            'Lmax' => $row['Lmax'] !== null ? $row['Lmax'] : $lastValues['Lmax'],
                            'created_at' => $this->formatDate($row['created_at']),
                        ];
                    }, $combined);
                    break;
            }

            if (empty($allResults)) {
                return response()->json(['error' => 'No data found for the given parameters'], 404);
            }

            return response()->json([
                'title' => $reportTitle,
                'data' => $allResults,
            ]);
        } catch (\Exception $error) {
            return response()->json([
                'error' => 'Failed to fetch data',
                'details' => $error->getMessage()
            ], 500);
        }
    }

    // Export endpoint that generates Excel or PDF files
    public function export(Request $request)
    {
        $reportType = $request->query('reportType');
        $format = $request->query('format');
        $days = $request->query('days', 1);

        try {
            // Parse days parameter with fallback to 1
            $daysNum = intval($days) ?: 1;

            $allResults = [];
            $reportTitle = '';
            $columns = [];

            switch ($reportType) {
                case "laeq":
                    $reportTitle = "LAeq Sound Level Report";
                    $columns = [
                        ['header' => "ID", 'key' => "id", 'width' => 10],
                        ['header' => "LAeq (dB)", 'key' => "laeq", 'width' => 15],
                        ['header' => "Created At", 'key' => "created_at", 'width' => 20],
                    ];
                    $results = $this->getTableData('laeq', $daysNum);
                    $allResults = array_map(function ($row) {
                        return [
                            'id' => $row->id,
                            'laeq' => $this->formatDecimal($row->value),
                            'created_at' => $this->formatDate($row->created_at),
                        ];
                    }, $results);
                    break;

                case "percentiles":
                    $reportTitle = "Sound Level Percentiles Report";
                    $columns = [
                        ['header' => "ID", 'key' => "id", 'width' => 10],
                        ['header' => "L10 (dB)", 'key' => "L10", 'width' => 15],
                        ['header' => "L50 (dB)", 'key' => "L50", 'width' => 15],
                        ['header' => "L90 (dB)", 'key' => "L90", 'width' => 15],
                        ['header' => "Created At", 'key' => "created_at", 'width' => 20],
                    ];
                    $results = $this->getTableData('laeq_metrics', $daysNum);
                    $allResults = array_map(function ($row) {
                        return [
                            'id' => $row->id,
                            'L10' => $this->formatDecimal($row->L10),
                            'L50' => $this->formatDecimal($row->L50),
                            'L90' => $this->formatDecimal($row->L90),
                            'created_at' => $this->formatDate($row->created_at),
                        ];
                    }, $results);
                    break;

                case "extremes":
                    $reportTitle = "Sound Level Extremes Report";
                    $columns = [
                        ['header' => "ID", 'key' => "id", 'width' => 10],
                        ['header' => "Lmin (dB)", 'key' => "Lmin", 'width' => 15],
                        ['header' => "Lmax (dB)", 'key' => "Lmax", 'width' => 15],
                        ['header' => "Created At", 'key' => "created_at", 'width' => 20],
                    ];
                    $results = $this->getTableData('laeq_lmin_lmax', $daysNum);
                    $allResults = array_map(function ($row) {
                        return [
                            'id' => $row->id,
                            'Lmin' => $this->formatDecimal($row->Lmin),
                            'Lmax' => $this->formatDecimal($row->Lmax),
                            'created_at' => $this->formatDate($row->created_at),
                        ];
                    }, $results);
                    break;

                case "all":
                default:
                    $reportTitle = "Complete Sound Level Report";
                    $columns = [
                        ['header' => "ID", 'key' => "id", 'width' => 10],
                        ['header' => "LAeq (dB)", 'key' => "laeq", 'width' => 15],
                        ['header' => "L10 (dB)", 'key' => "L10", 'width' => 15],
                        ['header' => "L50 (dB)", 'key' => "L50", 'width' => 15],
                        ['header' => "L90 (dB)", 'key' => "L90", 'width' => 15],
                        ['header' => "Lmin (dB)", 'key' => "Lmin", 'width' => 15],
                        ['header' => "Lmax (dB)", 'key' => "Lmax", 'width' => 15],
                        ['header' => "Created At", 'key' => "created_at", 'width' => 20],
                    ];

                    $laeqData = $this->getTableData('laeq', $daysNum);
                    $metricsData = $this->getTableData('laeq_metrics', $daysNum);
                    $extremesData = $this->getTableData('laeq_lmin_lmax', $daysNum);

                    // Combine all data
                    $combined = [];

                    foreach ($laeqData as $row) {
                        $combined[] = [
                            'id' => $row->id,
                            'laeq' => $this->formatDecimal($row->value),
                            'L10' => null,
                            'L50' => null,
                            'L90' => null,
                            'Lmin' => null,
                            'Lmax' => null,
                            'created_at' => $row->created_at,
                            'db_time' => $row->created_at,
                        ];
                    }

                    foreach ($metricsData as $row) {
                        $combined[] = [
                            'id' => $row->id,
                            'laeq' => null,
                            'L10' => $this->formatDecimal($row->L10),
                            'L50' => $this->formatDecimal($row->L50),
                            'L90' => $this->formatDecimal($row->L90),
                            'Lmin' => null,
                            'Lmax' => null,
                            'created_at' => $row->created_at,
                            'db_time' => $row->created_at,
                        ];
                    }

                    foreach ($extremesData as $row) {
                        $combined[] = [
                            'id' => $row->id,
                            'laeq' => null,
                            'L10' => null,
                            'L50' => null,
                            'L90' => null,
                            'Lmin' => $this->formatDecimal($row->Lmin),
                            'Lmax' => $this->formatDecimal($row->Lmax),
                            'created_at' => $row->created_at,
                            'db_time' => $row->created_at,
                        ];
                    }

                    // Sort by created_at
                    usort($combined, function ($a, $b) {
                        return strtotime($b['db_time']) - strtotime($a['db_time']);
                    });

                    // Fill in missing values with the latest available data
                    $lastValues = [
                        'laeq' => null,
                        'L10' => null,
                        'L50' => null,
                        'L90' => null,
                        'Lmin' => null,
                        'Lmax' => null,
                    ];

                    // First pass to find the most recent values
                    foreach ($combined as $row) {
                        if ($row['laeq'] !== null && $lastValues['laeq'] === null) {
                            $lastValues['laeq'] = $row['laeq'];
                        }
                        if ($row['L10'] !== null && $lastValues['L10'] === null) {
                            $lastValues['L10'] = $row['L10'];
                        }
                        if ($row['L50'] !== null && $lastValues['L50'] === null) {
                            $lastValues['L50'] = $row['L50'];
                        }
                        if ($row['L90'] !== null && $lastValues['L90'] === null) {
                            $lastValues['L90'] = $row['L90'];
                        }
                        if ($row['Lmin'] !== null && $lastValues['Lmin'] === null) {
                            $lastValues['Lmin'] = $row['Lmin'];
                        }
                        if ($row['Lmax'] !== null && $lastValues['Lmax'] === null) {
                            $lastValues['Lmax'] = $row['Lmax'];
                        }
                    }

                    // Second pass to fill in missing values
                    $allResults = array_map(function ($row) use (&$lastValues) {
                        if ($row['laeq'] !== null) $lastValues['laeq'] = $row['laeq'];
                        if ($row['L10'] !== null) $lastValues['L10'] = $row['L10'];
                        if ($row['L50'] !== null) $lastValues['L50'] = $row['L50'];
                        if ($row['L90'] !== null) $lastValues['L90'] = $row['L90'];
                        if ($row['Lmin'] !== null) $lastValues['Lmin'] = $row['Lmin'];
                        if ($row['Lmax'] !== null) $lastValues['Lmax'] = $row['Lmax'];

                        return [
                            'id' => $row['id'],
                            'laeq' => $row['laeq'] !== null ? $row['laeq'] : $lastValues['laeq'],
                            'L10' => $row['L10'] !== null ? $row['L10'] : $lastValues['L10'],
                            'L50' => $row['L50'] !== null ? $row['L50'] : $lastValues['L50'],
                            'L90' => $row['L90'] !== null ? $row['L90'] : $lastValues['L90'],
                            'Lmin' => $row['Lmin'] !== null ? $row['Lmin'] : $lastValues['Lmin'],
                            'Lmax' => $row['Lmax'] !== null ? $row['Lmax'] : $lastValues['Lmax'],
                            'created_at' => $this->formatDate($row['created_at']),
                        ];
                    }, $combined);
                    break;
            }

            if (empty($allResults)) {
                return response()->json(['error' => 'No data found for the given parameters'], 404);
            }

            // Generate filename with date
            $currentDate = date('Y-m-d');
            $filename = "noise_report_{$reportType}_{$currentDate}";

            if ($format === "excel") {
                return $this->generateExcel($allResults, $reportTitle, $filename, $columns);
            } elseif ($format === "pdf") {
                return $this->generatePDF($allResults, $reportTitle, $filename, $reportType);
            } else {
                return response()->json(['error' => 'Invalid format. Please use "excel" or "pdf"'], 400);
            }
        } catch (\Exception $error) {
            return response()->json([
                'error' => 'Failed to export data',
                'details' => $error->getMessage()
            ], 500);
        }
    }

    // Helper function to generate Excel report
    private function generateExcel($data, $title, $filename, $columns)
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Add title row
        $sheet->mergeCells('A1:' . chr(65 + count($columns) - 1) . '1');
        $sheet->setCellValue('A1', $title);
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal('center');

        // Add date information
        $sheet->mergeCells('A2:' . chr(65 + count($columns) - 1) . '2');
        date_default_timezone_set('Asia/Kuala_Lumpur');
        $currentDateTime = date('d/m/Y H:i:s');
        $sheet->setCellValue('A2', 'Report generated on: ' . $currentDateTime);
        $sheet->getStyle('A2')->getFont()->setItalic(true)->setSize(12);
        $sheet->getStyle('A2')->getAlignment()->setHorizontal('center');

        // Add data range information
        $earliestDate = new \DateTime();
        $latestDate = new \DateTime('1970-01-01');

        foreach ($data as $row) {
            $rowDate = new \DateTime($row['created_at']);
            if ($rowDate < $earliestDate) $earliestDate = $rowDate;
            if ($rowDate > $latestDate) $latestDate = $rowDate;
        }

        $sheet->mergeCells('A3:' . chr(65 + count($columns) - 1) . '3');
        // $sheet->setCellValue('A3', 'Data range: ' . $earliestDate->format('d/m/Y H:i:s') . ' to ' . $latestDate->format('d/m/Y H:i:s'));
        $sheet->getStyle('A3')->getFont()->setSize(10);
        $sheet->getStyle('A3')->getAlignment()->setHorizontal('center');

        // Empty row
        $sheet->setCellValue('A4', '');

        // Add headers
        $headerRow = 5;
        $headerValues = array_column($columns, 'header');
        $sheet->fromArray($headerValues, null, 'A' . $headerRow);

        // Style headers
        $headerStyle = [
            'font' => ['bold' => true],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['argb' => 'FFD3D3D3']
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN
                ]
            ]
        ];
        $sheet->getStyle('A' . $headerRow . ':' . chr(65 + count($columns) - 1) . $headerRow)->applyFromArray($headerStyle);

        // Add data rows
        $dataRow = $headerRow + 1;
        $dataValues = [];
        foreach ($data as $row) {
            $dataRowValues = [];
            foreach ($columns as $col) {
                $dataRowValues[] = $row[$col['key']] ?? '';
            }
            $dataValues[] = $dataRowValues;
        }
        $sheet->fromArray($dataValues, null, 'A' . $dataRow);

        // Style data rows
        $dataStyle = [
            'borders' => [
                'allBorders' => [
                    'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN
                ]
            ]
        ];
        $sheet->getStyle('A' . $dataRow . ':' . chr(65 + count($columns) - 1) . ($dataRow + count($data) - 1))->applyFromArray($dataStyle);

        // Set column widths
        foreach ($columns as $index => $col) {
            $columnLetter = chr(65 + $index);
            $sheet->getColumnDimension($columnLetter)->setWidth($col['width']);
        }

        // Create writer and save to temporary file
        $writer = new Xlsx($spreadsheet);
        $tempFile = tempnam(sys_get_temp_dir(), 'excel');
        $writer->save($tempFile);

        // Return the file as a download
        return response()->download($tempFile, $filename . '.xlsx')->deleteFileAfterSend(true);
    }

    // Helper function to generate PDF report
    private function generatePDF($data, $title, $filename, $reportType)
    {
        // Tingkatkan memory limit untuk menangani data besar
        ini_set('memory_limit', '-1'); // Tidak ada batas memory
        ini_set('max_execution_time', 300); // Batas waktu eksekusi 5 menit

        // Define headers based on report type
        $headers = ['ID', 'Created At'];
        if ($reportType === "laeq" || $reportType === "all") {
            array_splice($headers, 1, 0, "LAeq (dB)");
        }
        if ($reportType === "percentiles" || $reportType === "all") {
            $insertPosition = $reportType === "all" ? 2 : 1;
            array_splice($headers, $insertPosition, 0, ["L10 (dB)", "L50 (dB)", "L90 (dB)"]);
        }
        if ($reportType === "extremes" || $reportType === "all") {
            $insertPosition = $reportType === "all" ? 5 : 1;
            array_splice($headers, $insertPosition, 0, ["Lmin (dB)", "Lmax (dB)"]);
        }

        date_default_timezone_set('Asia/Kuala_Lumpur');
        $currentDateTime = date('d/m/Y H:i:s');

        // Optimasi: Batasi data yang diproses jika terlalu besar
        $chunkedData = array_slice($data, 0, 1000); // Ambil maksimal 1000 record

        // Prepare data for PDF
        $pdfData = [
            'title' => $title,
            'headers' => $headers,
            'data' => $chunkedData,
            'reportType' => $reportType,
            'generatedDate' => $currentDateTime,
        ];

        // Find earliest and latest dates
        $earliestDate = new \DateTime();
        $latestDate = new \DateTime('1970-01-01');

        foreach ($chunkedData as $row) {
            $rowDate = new \DateTime($row['created_at']);
            if ($rowDate < $earliestDate) $earliestDate = $rowDate;
            if ($rowDate > $latestDate) $latestDate = $rowDate;
        }

        $pdfData['dataRange'] = $earliestDate->format('d/m/Y H:i:s') . ' to ' . $latestDate->format('d/m/Y H:i:s');
        $pdfData['totalRecords'] = count($data); // Simpan total record asli

        // Generate PDF dengan pengaturan optimal
        $pdf = PDF::loadView('exports.noise_report', $pdfData)
            ->setPaper('a4', 'landscape')
            ->setOption('isPhpEnabled', true)
            ->setOption('isRemoteEnabled', true)
            ->setOption('isHtml5ParserEnabled', true);

        return $pdf->download($filename . '.pdf');
    }
}
