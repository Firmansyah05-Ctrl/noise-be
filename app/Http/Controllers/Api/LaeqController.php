<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class LaeqController extends Controller
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
            $latestRow = DB::table('laeq')
                ->orderBy('created_at', 'desc')
                ->first(['created_at']);

            if (!$latestRow) {
                return response()->json([]);
            }

            $latestTimestamp = Carbon::parse($latestRow->created_at);
            $twentyFourHoursAgo = $latestTimestamp->copy()->subHours(24);

            // Base query with timezone conversion for created_at (+8 hours)
            $query = DB::table('laeq')
                ->select(DB::raw("*, CONVERT_TZ(created_at, '+00:00', '+08:00') as created_at"));

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

            // Format date and add validation
            $formattedRows = $rows->map(function ($row) {
                $formattedRow = (array)$row;

                // Format created_at in the desired format: YYYY-MM-DD HH:MM:SS
                if (isset($formattedRow['created_at'])) {
                    $date = Carbon::parse($formattedRow['created_at']);
                    $formattedRow['created_at'] = $date->format('Y-m-d H:i:s');
                }

                // Validate value field
                $formattedRow['value'] = isset($formattedRow['value']) ? $formattedRow['value'] : 0;

                return $formattedRow;
            });

            return response()->json($formattedRows);
        } catch (\Exception $error) {
            Log::error("Error fetching LAeq table data:", ['error' => $error]);
            return response()->json(['error' => 'Failed to fetch LAeq table data'], 500);
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
