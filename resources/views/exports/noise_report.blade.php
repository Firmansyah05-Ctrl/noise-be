<!DOCTYPE html>
<html>

<head>
    <title>{{ $title }}</title>
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 10px;
            /* Ukuran font lebih kecil */
        }

        .title {
            font-size: 16px;
            /* Sedikit lebih kecil */
            font-weight: bold;
            text-align: center;
            margin-bottom: 15px;
        }

        .date,
        .range {
            font-size: 9px;
            text-align: center;
            margin-bottom: 8px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 8px;
            /* Ukuran font tabel lebih kecil */
        }

        th,
        td {
            padding: 3px;
            /* Padding lebih kecil */
            border: 1px solid #000;
        }

        th {
            background-color: #e0e0e0;
            font-weight: bold;
        }

        .note {
            font-size: 8px;
            font-style: italic;
            text-align: center;
            margin-top: 8px;
        }

        tr:nth-child(even) {
            background-color: #f2f2f2;
            /* Zebra stripe untuk keterbacaan */
        }
    </style>
</head>

<body>
    <div class="title">{{ $title }}</div>
    <div class="date">Report generated on: {{ $generatedDate }}</div>
    {{-- <div class="range">Showing {{ count($data) }} of {{ $totalRecords }} records | Data range: {{ $dataRange }} --}}
    </div>

    <table>
        <thead>
            <tr>
                @foreach ($headers as $header)
                    <th>{{ $header }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach ($data as $row)
                <tr>
                    <td>{{ $row['id'] }}</td>
                    @if ($reportType === 'laeq' || $reportType === 'all')
                        <td>{{ $row['laeq'] ?? '' }}</td>
                    @endif
                    @if ($reportType === 'percentiles' || $reportType === 'all')
                        <td>{{ $row['L10'] ?? '' }}</td>
                        <td>{{ $row['L50'] ?? '' }}</td>
                        <td>{{ $row['L90'] ?? '' }}</td>
                    @endif
                    @if ($reportType === 'extremes' || $reportType === 'all')
                        <td>{{ $row['Lmin'] ?? '' }}</td>
                        <td>{{ $row['Lmax'] ?? '' }}</td>
                    @endif
                    <td>{{ $row['created_at'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    @if ($totalRecords > count($data))
        <div class="note">
            Note: Showing first {{ count($data) }} records only. Total records available: {{ $totalRecords }}.<br>
            Please filter your data or export in smaller chunks for complete results.
        </div>
    @endif
</body>

</html>
