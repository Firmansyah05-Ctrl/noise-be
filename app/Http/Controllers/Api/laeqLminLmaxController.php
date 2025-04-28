<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class laeqLminLmaxController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        try {
            $limit = $request->query('limit');
            $startDate = $request->query('startDate');
            $endDate = $request->query('endDate');

            // First, find the most recent timestamp in the database
            $latestRow = DB::table('laeq_lmin_lmax')
                ->orderBy('created_at', 'desc')
                ->first(['created_at']);

            if (!$latestRow) {
                return response()->json([], 200);
            }

            $latestTimestamp = Carbon::parse($latestRow->created_at);
            $twentyFourHoursAgo = $latestTimestamp->copy()->subHours(24);

            // Base query with timezone conversion for created_at (+8 hours)
            $query = DB::table('laeq_lmin_lmax')
                ->select('*', DB::raw("CONVERT_TZ(created_at, '+00:00', '+08:00') as created_at"));

            // Use custom date range if provided, otherwise use last 24 hours from latest data
            if ($startDate) {
                $query->where('created_at', '>=', Carbon::parse($startDate));
            } else {
                $query->where('created_at', '>=', $twentyFourHoursAgo);
            }

            if ($endDate) {
                $query->where('created_at', '<=', Carbon::parse($endDate));
            } else {
                $query->where('created_at', '<=', $latestTimestamp);
            }

            $query->orderBy('created_at', 'desc');

            if ($limit) {
                $query->limit((int)$limit);
            }

            $rows = $query->get();

            // Format the data
            $formattedRows = $rows->map(function ($row) {
                $date = Carbon::parse($row->created_at);

                return [
                    'id' => $row->id ?? null,
                    'Lmin' => $row->Lmin ?? 0,
                    'Lmax' => $row->Lmax ?? 0,
                    'created_at' => $date->format('Y-m-d H:i:s'),
                    // Tambahkan field lain sesuai kebutuhan
                ];
            });

            return response()->json($formattedRows, 200);
        } catch (\Exception $error) {
            Log::error("Error fetching LAeq lmin lmax data: " . $error->getMessage());
            return response()->json([
                'error' => 'Failed to fetch LAeq lmin lmax data',
                'details' => $error->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
