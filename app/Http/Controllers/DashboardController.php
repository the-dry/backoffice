<?php

namespace App\Http\Controllers;

use App\Services\MoodleApiService;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;

class DashboardController extends Controller
{
    protected MoodleApiService $moodleApiService;

    public function __construct(MoodleApiService $moodleApiService)
    {
        $this->moodleApiService = $moodleApiService;
    }

    public function index()
    {
        $usersByCountryData = [];
        $recentUsersCount = 0;
        $activeCoursesCount = 0;
        // Add more stats as needed

        try {
            // Example: Get users to count by country
            // Note: core_user_get_users can be slow if there are many users.
            // For a real dashboard, pre-aggregated data or specific Moodle reports/API calls would be better.
            // This is a simplified example.
            $usersResponse = $this->moodleApiService->getUsers([['key' => 'deleted', 'value' => 0]]); // Get non-deleted users

            if ($usersResponse->successful()) {
                $moodleUsers = $usersResponse->json()['users'] ?? ($usersResponse->json() ?? []);
                 if (is_array($moodleUsers) && isset($moodleUsers['users']) && is_array($moodleUsers['users'])) {
                    $moodleUsers = $moodleUsers['users'];
                }


                if (is_array($moodleUsers)) {
                    // Process for Users by Country chart
                    $countryCounts = [];
                    foreach ($moodleUsers as $user) {
                        $country = $user['country'] ?? 'Desconocido';
                        if (!empty($country) && strlen($country) === 2) { // Assuming 2-letter country codes
                           $country = strtoupper($country); // Normalize
                        } else if (empty($country)) {
                            $country = 'Desconocido';
                        }
                        // Else, if country name is longer, use as is or try to map.
                        // For simplicity, we'll use it as is if not a 2-letter code.

                        $countryCounts[$country] = ($countryCounts[$country] ?? 0) + 1;
                    }
                    arsort($countryCounts); // Sort by count descending

                    // Take top N countries and group others into "Otros"
                    $topN = 5;
                    $usersByCountryData = array_slice($countryCounts, 0, $topN, true);
                    if (count($countryCounts) > $topN) {
                        $othersCount = array_sum(array_slice($countryCounts, $topN));
                        if ($othersCount > 0) {
                            $usersByCountryData['Otros'] = $othersCount;
                        }
                    }
                    // Format for amCharts [{country: "US", value: 100}, {country: "CA", value: 50}]
                    $usersByCountryData = collect($usersByCountryData)->map(function ($value, $key) {
                        return ['category' => $key, 'value' => $value];
                    })->values()->all();


                    // Example: Count recently registered users (e.g., last 30 days)
                    // This requires 'timecreated' field from Moodle users.
                    // core_user_get_users usually returns 'firstaccess' and 'lastaccess', not 'timecreated' by default.
                    // 'timecreated' might need to be fetched via custom profile fields or another API if not standard.
                    // For now, this is a placeholder.
                    $recentUsersCount = count(array_filter($moodleUsers, function ($user) {
                        $thirtyDaysAgo = time() - (30 * 24 * 60 * 60);
                        return ($user['firstaccess'] ?? 0) > $thirtyDaysAgo; // Using firstaccess as a proxy
                    }));

                } else {
                    Log::warning('Moodle API getUsers did not return an array for users.', ['response' => $moodleUsers]);
                }

            } else {
                Log::error('Failed to fetch users from Moodle for dashboard stats.', [
                    'status' => $usersResponse->status(),
                    'body' => $usersResponse->body()
                ]);
            }

            // Example: Get active courses count
            $coursesResponse = $this->moodleApiService->getCourses();
            if ($coursesResponse->successful()) {
                $courses = $coursesResponse->json();
                 if (is_array($courses)) {
                    // Filter out site home or other non-relevant courses
                    $activeCoursesCount = count(array_filter($courses, fn($course) => isset($course['id']) && $course['id'] != 1 && !($course['visible'] === 0 || (isset($course['visible']) && $course['visible'] === false)) ));
                }
            } else {
                 Log::error('Failed to fetch courses from Moodle for dashboard stats.', [
                    'status' => $coursesResponse->status(),
                    'body' => $coursesResponse->body()
                ]);
            }

        } catch (\Illuminate\Http\Client\RequestException $e) {
            Log::error('Moodle API connection error for dashboard: ' . $e->getMessage());
            session()->flash('error', 'No se pudo conectar con Moodle para cargar estadísticas.');
        } catch (\Exception $e) {
            Log::error('Unexpected error fetching dashboard data: ' . $e->getMessage());
             session()->flash('error', 'Ocurrió un error inesperado al cargar estadísticas.');
        }

        return view('dashboard.index', compact('usersByCountryData', 'recentUsersCount', 'activeCoursesCount'));
    }

    public function exportUsersByCountry()
    {
        // This logic is duplicated from the index method.
        // In a real application, this should be refactored into a private method or a dedicated service/action class
        // to avoid duplication and ensure consistency.
        $usersByCountryData = [];
        try {
            $usersResponse = $this->moodleApiService->getUsers([['key' => 'deleted', 'value' => 0]]);
            if ($usersResponse->successful()) {
                $moodleUsers = $usersResponse->json()['users'] ?? ($usersResponse->json() ?? []);
                if (is_array($moodleUsers) && isset($moodleUsers['users']) && is_array($moodleUsers['users'])) {
                    $moodleUsers = $moodleUsers['users'];
                }

                if (is_array($moodleUsers)) {
                    $countryCounts = [];
                    foreach ($moodleUsers as $user) {
                        $country = $user['country'] ?? 'Desconocido';
                        if (!empty($country) && strlen($country) === 2) {
                           $country = strtoupper($country);
                        } else if (empty($country)) {
                            $country = 'Desconocido';
                        }
                        $countryCounts[$country] = ($countryCounts[$country] ?? 0) + 1;
                    }
                    arsort($countryCounts);

                    $topN = 5; // Consistent with dashboard display logic
                    $dataForChart = array_slice($countryCounts, 0, $topN, true);
                    if (count($countryCounts) > $topN) {
                        $othersCount = array_sum(array_slice($countryCounts, $topN));
                        if ($othersCount > 0) {
                            $dataForChart['Otros'] = $othersCount;
                        }
                    }
                    $usersByCountryData = collect($dataForChart)->map(function ($value, $key) {
                        return ['category' => $key, 'value' => $value];
                    })->values()->all();
                }
            } else {
                Log::error('Failed to fetch users for Excel export.', ['status' => $usersResponse->status(), 'body' => $usersResponse->body()]);
                return redirect()->route('dashboard')->with('error', 'No se pudieron obtener los datos para exportar.');
            }
        } catch (\Exception $e) {
            Log::error('Error during Excel export data fetching: ' . $e->getMessage());
            return redirect()->route('dashboard')->with('error', 'Error al preparar datos para exportación.');
        }

        if (empty($usersByCountryData)) {
             return redirect()->route('dashboard')->with('error', 'No hay datos de usuarios por país para exportar.');
        }

        return \Maatwebsite\Excel\Facades\Excel::download(new \App\Exports\UsersByCountryExport($usersByCountryData), 'usuarios_por_pais.xlsx');
    }
}
