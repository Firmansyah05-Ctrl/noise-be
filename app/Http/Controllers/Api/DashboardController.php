<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DashboardController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        try {
            // Run all queries in parallel using Laravel's DB facade
            $results = DB::transaction(function () {
                return [
                    'latestLaeqResult' => DB::select("
                        SELECT *, CONVERT_TZ(created_at, '+00:00', '+08:00') as created_at 
                        FROM laeq 
                        ORDER BY created_at DESC 
                        LIMIT 1
                    "),
                    'mqttStatusResult' => DB::select("
                        SELECT *, CONVERT_TZ(updated_at, '+00:00', '+08:00') as updated_at 
                        FROM mqtt_status 
                        ORDER BY updated_at DESC 
                        LIMIT 1
                    "),
                    'latestHourlyResult' => DB::select("
                        SELECT *, CONVERT_TZ(created_at, '+00:00', '+08:00') as created_at 
                        FROM laeq_lmin_lmax 
                        ORDER BY created_at DESC 
                        LIMIT 1
                    "),
                    'latestRealtimeResult' => DB::select("
                        SELECT *, CONVERT_TZ(created_at, '+00:00', '+08:00') as created_at 
                        FROM laeq_metrics 
                        ORDER BY created_at DESC 
                        LIMIT 1
                    "),
                    'todayStatsResult' => DB::select("
                        SELECT MAX(value) as maxLaeq, MIN(value) as minLaeq, AVG(value) as avgLaeq 
                        FROM laeq 
                        WHERE created_at >= ?
                    ", [Carbon::today()]),
                ];
            });

            // Get L10, L50, L90 values from laeq_metrics
            $L10 = $results['latestRealtimeResult'][0]->L10 ?? 0;
            $L50 = $results['latestRealtimeResult'][0]->L50 ?? 0;
            $L90 = $results['latestRealtimeResult'][0]->L90 ?? 0;

            // Get Lmax and Lmin from laeq_lmin_lmax
            $Lmax = $results['latestHourlyResult'][0]->Lmax ?? 0;
            $Lmin = $results['latestHourlyResult'][0]->Lmin ?? 0;

            // Format dates for each result
            $formatDate = function ($dateObj) {
                if (!$dateObj) return null;
                return Carbon::parse($dateObj)->format('Y-m-d H:i:s');
            };

            // Format the created_at and updated_at fields in the results
            if (!empty($results['latestLaeqResult']) && isset($results['latestLaeqResult'][0]->created_at)) {
                $results['latestLaeqResult'][0]->created_at = $formatDate($results['latestLaeqResult'][0]->created_at);
            }

            if (!empty($results['mqttStatusResult']) && isset($results['mqttStatusResult'][0]->updated_at)) {
                $results['mqttStatusResult'][0]->updated_at = $formatDate($results['mqttStatusResult'][0]->updated_at);
            }

            if (!empty($results['latestHourlyResult']) && isset($results['latestHourlyResult'][0]->created_at)) {
                $results['latestHourlyResult'][0]->created_at = $formatDate($results['latestHourlyResult'][0]->created_at);
            }

            if (!empty($results['latestRealtimeResult']) && isset($results['latestRealtimeResult'][0]->created_at)) {
                $results['latestRealtimeResult'][0]->created_at = $formatDate($results['latestRealtimeResult'][0]->created_at);
            }

            // Construct response with fallbacks for null values
            $responseData = [
                'latestLaeq' => !empty($results['latestLaeqResult'])
                    ? (object) array_merge(
                        (array) $results['latestLaeqResult'][0],
                        ['L10' => $L10, 'L50' => $L50, 'L90' => $L90, 'Lmax' => $Lmax, 'Lmin' => $Lmin]
                    )
                    : null,
                'mqttStatus' => !empty($results['mqttStatusResult'])
                    ? $results['mqttStatusResult'][0]
                    : (object) ['status' => 'Offline'],
                'latestHourly' => !empty($results['latestHourlyResult'])
                    ? (object) [
                        'Lmax' => $results['latestHourlyResult'][0]->Lmax ?? 0,
                        'Lmin' => $results['latestHourlyResult'][0]->Lmin ?? 0,
                        'created_at' => $results['latestHourlyResult'][0]->created_at ?? null,
                    ]
                    : null,
                'latestRealtime' => !empty($results['latestRealtimeResult'])
                    ? (object) [
                        'L10' => $results['latestRealtimeResult'][0]->L10 ?? 0,
                        'L50' => $results['latestRealtimeResult'][0]->L50 ?? 0,
                        'L90' => $results['latestRealtimeResult'][0]->L90 ?? 0,
                        'created_at' => $results['latestRealtimeResult'][0]->created_at ?? null,
                    ]
                    : null,
                'todayStats' => !empty($results['todayStatsResult'])
                    ? $results['todayStatsResult'][0]
                    : (object) ['maxLaeq' => 0, 'minLaeq' => 0, 'avgLaeq' => 0],
            ];

            return response()->json($responseData);
        } catch (\Exception $error) {
            Log::error("Error fetching dashboard summary:", ['error' => $error->getMessage()]);
            return response()->json([
                'error' => 'Failed to fetch dashboard summary',
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
