<?php

namespace App\Http\Controllers;

use App\Services\MoodleApiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MoodleReportController extends Controller
{
    protected MoodleApiService $moodleApiService;

    public function __construct(MoodleApiService $moodleApiService)
    {
        $this->moodleApiService = $moodleApiService;
        // Consider adding middleware for permissions here later, e.g., $this->middleware('can:view moodle reports');
    }

    /**
     * Show a form to select a course for the progress report.
     */
    public function showCourseProgressReportForm(Request $request)
    {
        $courses = [];
        try {
            $coursesResponse = $this->moodleApiService->getCourses();
            if ($coursesResponse->successful()) {
                $coursesData = $coursesResponse->json();
                if (is_array($coursesData)) {
                    $courses = array_filter($coursesData, fn($course) => isset($course['id']) && $course['id'] != 1 && ($course['format'] ?? '') !== 'site');
                }
            } else {
                session()->flash('error', 'No se pudieron cargar los cursos de Moodle.');
            }
        } catch (\Exception $e) {
            Log::error('Error fetching courses for report form: ' . $e->getMessage());
            session()->flash('error', 'Error de conexión al cargar cursos: ' . $e->getMessage());
        }

        return view('moodle.reports.course-progress-form', compact('courses'));
    }

    /**
     * Display the progress report for a selected course.
     */
    public function generateCourseProgressReport(Request $request)
    {
        $request->validate([
            'course_id' => 'required|integer',
        ]);

        $courseId = $request->input('course_id');
        $courseDetails = null;
        $enrolledUsersWithProgress = [];

        try {
            // Get course details
            $courseResponse = $this->moodleApiService->getCourses([$courseId]);
            if ($courseResponse->successful() && !empty($courseResponse->json())) {
                $courseDetails = $courseResponse->json()[0] ?? null;
            } else {
                 session()->flash('error', 'No se pudieron obtener los detalles del curso seleccionado.');
                return redirect()->route('moodle.reports.course-progress.form');
            }

            if (!$courseDetails) {
                session()->flash('error', 'Curso no encontrado.');
                return redirect()->route('moodle.reports.course-progress.form');
            }

            // Get enrolled users
            // Request specific user fields like id, fullname, email
            $enrolledUsersOptions = [
                ['name' => 'userfields', 'value' => 'id,fullname,email,username,firstaccess,lastaccess']
            ];
            $enrolledUsersResponse = $this->moodleApiService->getEnrolledUsersInCourse($courseId, $enrolledUsersOptions);

            if ($enrolledUsersResponse->successful()) {
                $moodleUsers = $enrolledUsersResponse->json();
                if (is_array($moodleUsers)) {
                    foreach ($moodleUsers as $user) {
                        if (!isset($user['id'])) continue;

                        $userData = [
                            'id' => $user['id'],
                            'fullname' => $user['fullname'] ?? 'N/A',
                            'email' => $user['email'] ?? 'N/A',
                            'username' => $user['username'] ?? 'N/A',
                            'firstaccess' => ($user['firstaccess'] ?? 0) > 0 ? date('Y-m-d H:i:s', $user['firstaccess']) : 'N/A',
                            'lastaccess' => ($user['lastaccess'] ?? 0) > 0 ? date('Y-m-d H:i:s', $user['lastaccess']) : 'N/A',
                            'completion_status' => 'No disponible',
                            'grade' => 'N/A',
                            'completion_details' => [],
                        ];

                        // Get completion status for each user
                        $completionResponse = $this->moodleApiService->getCourseCompletionStatus($courseId, $user['id']);
                        if ($completionResponse->successful() && isset($completionResponse->json()['completionstatus'])) {
                            $status = $completionResponse->json()['completionstatus'];
                            $userData['completion_status'] = $status['completed'] ? 'Completado' : 'En Progreso';
                            // $userData['completion_details'] = $status['completions'] ?? []; // Array of activity completions
                        } else {
                             Log::warning("Failed to get completion status for user {$user['id']} in course {$courseId}");
                        }

                        // Get grades for each user
                        $gradesResponse = $this->moodleApiService->getUserGradesInCourse($courseId, $user['id']);
                        if ($gradesResponse->successful() && isset($gradesResponse->json()['usergrades'][0]['gradeitems'])) {
                             $gradeItems = $gradesResponse->json()['usergrades'][0]['gradeitems'];
                             // Find overall course grade if available (often the last item or has specific type)
                             // This logic can be complex depending on Moodle's gradebook structure.
                             // For simplicity, let's look for a final grade or average.
                             $finalGrade = 'N/A';
                             foreach ($gradeItems as $item) {
                                 if (($item['itemtype'] ?? '') === 'course') {
                                     $finalGrade = $item['gradeformatted'] ?? ($item['graderaw'] ?? 'N/A');
                                     break;
                                 }
                             }
                             $userData['grade'] = $finalGrade;
                             // $userData['grade_details'] = $gradeItems; // Full grade breakdown
                        } else {
                            Log::warning("Failed to get grades for user {$user['id']} in course {$courseId}");
                        }

                        $enrolledUsersWithProgress[] = $userData;
                    }
                }
            } else {
                session()->flash('error', 'No se pudieron obtener los usuarios inscritos en el curso.');
            }

        } catch (\Exception $e) {
            Log::error('Error generating course progress report: ' . $e->getMessage());
            session()->flash('error', 'Error al generar el reporte: ' . $e->getMessage());
            return redirect()->route('moodle.reports.course-progress.form');
        }

        // Store data in session for export if needed, or re-fetch on export.
        // For simplicity, we might re-fetch, but session is an option for smaller datasets.
        session(['course_progress_report_data' => $enrolledUsersWithProgress, 'course_progress_report_course_name' => $courseDetails['fullname'] ?? 'Reporte']);


        return view('moodle.reports.course-progress-show', compact('courseDetails', 'enrolledUsersWithProgress'));
    }

    // Method for Excel export will be added later
    public function exportCourseProgressReport(Request $request)
    {
        // Retrieve data from session (set by generateCourseProgressReport)
        // This is a simple way; for production, consider re-fetching or more robust temporary storage if data is large.
        $reportData = session('course_progress_report_data');
        $courseName = session('course_progress_report_course_name', 'Progreso_Curso');

        if (empty($reportData)) {
            // Try to generate it again if course_id is available, or redirect with error
            $courseId = $request->query('course_id'); // Assuming course_id might be passed if session is lost
            if ($courseId) {
                // Temporarily redirect to generate report, then user can click export again.
                // This isn't ideal UX, but simple for now.
                // A better approach would be to pass all necessary parameters to the export function
                // or ensure the generate function is robust enough to be called directly.
                // For now, we'll just rely on the session or fail.
                 return redirect()->route('moodle.reports.course-progress.form')->with('error', 'No hay datos de reporte para exportar. Por favor, genere el reporte primero.');
            }
            return redirect()->route('moodle.reports.course-progress.form')->with('error', 'No hay datos de reporte para exportar o la sesión expiró.');
        }

        $safeCourseName = preg_replace('/[^a-zA-Z0-9_ \.-]/', '', $courseName); // Sanitize filename
        $fileName = 'reporte_progreso_' . $safeCourseName . '_' . date('Ymd_His') . '.xlsx';

        return \Maatwebsite\Excel\Facades\Excel::download(new \App\Exports\CourseProgressExport($reportData, $courseName), $fileName);
    }
}
