<?php

namespace App\Http\Controllers;

use App\Services\MoodleApiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Pagination\LengthAwarePaginator;


class MoodleCourseController extends Controller
{
    protected MoodleApiService $moodleApiService;

    public function __construct(MoodleApiService $moodleApiService)
    {
        $this->moodleApiService = $moodleApiService;
        // Middleware for permissions, e.g. $this->middleware('can:manage moodle courses');
    }

    /**
     * Display a listing of Moodle courses with an option to toggle visibility.
     */
    public function index(Request $request)
    {
        $allCoursesRaw = [];
        $searchTerm = $request->input('search', '');
        $page = $request->input('page', 1);
        $perPage = 20; // Configurable

        try {
            // In a real scenario with many courses, we would filter via API if possible,
            // or use the locally synced moodle_courses_local table.
            // For now, fetching all and then filtering/paginating.
            $response = $this->moodleApiService->getCourses();

            if ($response->successful()) {
                $coursesData = $response->json();
                if (is_array($coursesData)) {
                    // Filter out "Site home"
                    $allCoursesRaw = array_filter($coursesData, fn($course) => isset($course['id']) && $course['id'] != 1);

                    if (!empty($searchTerm)) {
                        $allCoursesRaw = array_filter($allCoursesRaw, function ($course) use ($searchTerm) {
                            return stripos($course['fullname'], $searchTerm) !== false ||
                                   stripos($course['shortname'], $searchTerm) !== false;
                        });
                    }
                } else {
                    session()->flash('error', 'Respuesta inesperada al obtener cursos de Moodle.');
                }
            } else {
                session()->flash('error', 'No se pudieron cargar los cursos de Moodle.');
            }
        } catch (\Exception $e) {
            Log::error('Error fetching Moodle courses for management: ' . $e->getMessage());
            session()->flash('error', 'Error de conexión al cargar cursos: ' . $e->getMessage());
        }

        $currentPage = LengthAwarePaginator::resolveCurrentPage('page');
        $currentItems = array_slice($allCoursesRaw, ($currentPage - 1) * $perPage, $perPage);
        $paginatedCourses = new LengthAwarePaginator($currentItems, count($allCoursesRaw), $perPage, $currentPage, [
            'path' => LengthAwarePaginator::resolveCurrentPath(),
            'pageName' => 'page',
        ]);
        $paginatedCourses->appends($request->except('page'));

        return view('moodle.courses.index', compact('paginatedCourses', 'searchTerm'));
    }

    /**
     * Update the visibility of a Moodle course.
     */
    public function toggleVisibility(Request $request, int $courseId)
    {
        $request->validate([
            'visible' => 'required|boolean',
        ]);

        $newVisibility = (int)$request->input('visible');

        try {
            $courseData = [
                [
                    'id' => $courseId,
                    'visible' => $newVisibility,
                ]
            ];
            $response = $this->moodleApiService->updateCourses($courseData);

            if ($response->successful()) {
                // core_course_update_courses usually returns warnings if any, or empty on full success
                // Check for specific error structures if Moodle sends them even on 200 OK
                $responseData = $response->json();
                if (isset($responseData['warnings']) && !empty($responseData['warnings'])) {
                     session()->flash('warning', 'Curso actualizado, pero Moodle reportó advertencias: ' . json_encode($responseData['warnings']));
                } elseif (isset($responseData['exception'])) {
                     session()->flash('error', 'Error de Moodle API al actualizar visibilidad: ' . $responseData['message']);
                }
                 else {
                    session()->flash('success', 'Visibilidad del curso actualizada exitosamente.');
                }
            } else {
                $apiError = $response->json();
                session()->flash('error', 'Error en API de Moodle al actualizar visibilidad: ' . ($apiError['message'] ?? $response->body()));
            }
        } catch (\Exception $e) {
            Log::error("Error toggling Moodle course (ID: {$courseId}) visibility: " . $e->getMessage());
            session()->flash('error', 'Error de conexión o procesamiento al actualizar visibilidad: ' . $e->getMessage());
        }

        return redirect()->route('moodle.courses.index', session('moodle_courses_index_query_params', []));
    }
}
