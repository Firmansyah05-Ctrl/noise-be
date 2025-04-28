<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class mqttStatusController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        try {
            $limit = $request->query('limit');
            $status = $request->query('status');
            $sort = $request->query('sort');

            // Base query with timezone conversion for updated_at
            $query = DB::table('mqtt_status')
                ->select('*', DB::raw("CONVERT_TZ(updated_at, '+00:00', '+08:00') as updated_at"));

            // Add status filter if provided
            if ($status) {
                $query->where('status', 'like', $status . '%');
            }

            // Get the most recent timestamp from the database
            $latestTimestamp = DB::table('mqtt_status')
                ->select(DB::raw("MAX(CONVERT_TZ(updated_at, '+00:00', '+08:00')) as latest_timestamp"))
                ->first()
                ->latest_timestamp;

            if ($latestTimestamp) {
                // Calculate 24 hours before the latest timestamp
                $twentyFourHoursBeforeLatest = Carbon::parse($latestTimestamp)->subHours(24);

                $query->where(DB::raw("CONVERT_TZ(updated_at, '+00:00', '+08:00')"), '>=', $twentyFourHoursBeforeLatest);
            }

            // Add sorting if provided
            if ($sort) {
                $sortParts = explode(',', $sort);
                $sortField = $sortParts[0];
                $sortDirection = $sortParts[1] ?? 'DESC';
                $query->orderBy($sortField, $sortDirection);
            } else {
                $query->orderBy('updated_at', 'DESC');
            }

            // Add limit if provided
            if ($limit) {
                $query->limit((int)$limit);
            }

            $rows = $query->get();

            // Format the rows to match exactly what's in the database
            $formattedRows = $rows->map(function ($row) {
                // Format dates to match the format in the database (YYYY-MM-DD HH:MM:SS)
                if (isset($row->created_at)) {
                    $row->created_at = Carbon::parse($row->created_at)->format('Y-m-d H:i:s');
                }
                if (isset($row->updated_at)) {
                    $row->updated_at = Carbon::parse($row->updated_at)->format('Y-m-d H:i:s');
                }
                return $row;
            });

            return response()->json($formattedRows, 200);
        } catch (\Exception $error) {
            Log::error("Error fetching MQTT status: " . $error->getMessage());
            return response()->json([
                'error' => 'Failed to fetch MQTT status',
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
