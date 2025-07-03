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

    /**
     * Show a form to select a course for the detailed analysis report.
     */
    public function showDetailedCourseAnalysisForm(Request $request)
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
            Log::error('Error fetching courses for detailed analysis form: ' . $e->getMessage());
            session()->flash('error', 'Error de conexión al cargar cursos: ' . $e->getMessage());
        }

        return view('moodle.reports.detailed-course-analysis-form', compact('courses'));
    }

    /**
     * Generate and display the detailed course analysis report.
     */
    public function generateDetailedCourseAnalysisReport(Request $request)
    {
        $request->validate(['course_id' => 'required|integer']);
        $courseId = $request->input('course_id');
        $courseDetails = null;
        $reportData = []; // Structure: ['user_id' => ['user_info' => ..., 'activities' => [...]]]
        $courseActivities = []; // Structure: ['id' => ..., 'name' => ..., 'modname' => ...]

        try {
            // 1. Get Course Details
            $courseResponse = $this->moodleApiService->getCourses([$courseId]);
            if ($courseResponse->successful() && !empty($courseResponse->json())) {
                $courseDetails = $courseResponse->json()[0] ?? null;
            }
            if (!$courseDetails) {
                return redirect()->route('moodle.reports.detailed-course-analysis.form')->with('error', 'Curso no encontrado.');
            }

            // 2. Get Course Contents (Activities/Modules)
            $contentsResponse = $this->moodleApiService->getCourseContents($courseId);
            if ($contentsResponse->successful()) {
                $sections = $contentsResponse->json();
                if (is_array($sections)) {
                    foreach ($sections as $section) {
                        if (isset($section['modules']) && is_array($section['modules'])) {
                            foreach ($section['modules'] as $module) {
                                if (isset($module['id']) && isset($module['name']) && isset($module['modname'])) {
                                     // We only care about activities that can be graded or have completion
                                    if (in_array($module['modname'], ['assign', 'quiz', 'lesson', 'forum', 'scorm', 'h5pactivity', 'url', 'page', 'resource', 'folder'])) { // Example list
                                        $courseActivities[] = [
                                            'id' => $module['id'], // This is cmid (course module id)
                                            'name' => $module['name'],
                                            'modname' => $module['modname'],
                                            'instance' => $module['instance'] ?? null, // Instance ID of the module (e.g. assignid)
                                        ];
                                    }
                                }
                            }
                        }
                    }
                }
            } else {
                 Log::warning("Could not fetch contents for course {$courseId}");
            }

            if (empty($courseActivities)) {
                // No relevant activities or failed to fetch, but can still show enrolled users.
                // Or return with a message. For now, proceed to show users.
            }


            // 3. Get Enrolled Users
            $enrolledUsersResponse = $this->moodleApiService->getEnrolledUsersInCourse($courseId, [['name' => 'userfields', 'value' => 'id,fullname,email,username']]);
            if ($enrolledUsersResponse->successful()) {
                $moodleUsers = $enrolledUsersResponse->json();
                if (is_array($moodleUsers)) {
                    foreach ($moodleUsers as $user) {
                        if (!isset($user['id'])) continue;

                        $userId = $user['id'];
                        $currentUserData = [
                            'user_info' => $user,
                            'activities' => []
                        ];

                        // 4. For each user, get activity completion and grades
                        if (!empty($courseActivities)) {
                            $activityCompletionResponse = $this->moodleApiService->getActivitiesCompletionStatus($courseId, $userId);
                            $activityCompletions = [];
                            if ($activityCompletionResponse->successful() && isset($activityCompletionResponse->json()['statuses'])) {
                                foreach($activityCompletionResponse->json()['statuses'] as $status) {
                                    $activityCompletions[$status['cmid']] = $status;
                                }
                            }

                            foreach ($courseActivities as $activity) {
                                $cmid = $activity['id'];
                                $activityData = [
                                    'cmid' => $cmid,
                                    'name' => $activity['name'],
                                    'modname' => $activity['modname'],
                                    'completion_state' => 'N/A', // 0=incomplete, 1=complete, 2=complete pass, 3=complete fail
                                    'grade' => 'N/A',
                                ];

                                if (isset($activityCompletions[$cmid])) {
                                    $comp = $activityCompletions[$cmid];
                                    switch ($comp['state']) {
                                        case 0: $activityData['completion_state'] = 'Incompleto'; break;
                                        case 1: $activityData['completion_state'] = 'Completo'; break;
                                        case 2: $activityData['completion_state'] = 'Completo (Aprobado)'; break; // May not apply to all
                                        case 3: $activityData['completion_state'] = 'Completo (Reprobado)'; break; // May not apply to all
                                        default: $activityData['completion_state'] = 'Desconocido';
                                    }
                                }

                                // Fetching individual activity grades can be very API intensive.
                                // gradereport_user_get_grade_items gives all grades for a user in a course.
                                // We already fetch this in the simple progress report.
                                // For this detailed one, if we need specific activity grades not in the main gradebook summary,
                                // we might need calls like `mod_assign_get_submission_status` or `mod_quiz_get_user_attempts`.
                                // This requires knowing the 'instance' ID of the module.
                                // For simplicity, we'll rely on the overall grade report for now or leave specific activity grades as N/A.
                                // If we had `mod_assign_get_grades` for specific assignment instances:
                                // if ($activity['modname'] === 'assign' && $activity['instance']) {
                                //    $assignGradesResp = $this->moodleApiService->getAssignmentGrades([$activity['instance']]); // This API gets grades for ALL users for the assignment.
                                //    // Then filter for current $userId. This is not efficient if done per user.
                                // }
                                $currentUserData['activities'][$cmid] = $activityData;
                            }
                        }
                        $reportData[$userId] = $currentUserData;
                    }
                }
            } else {
                return redirect()->route('moodle.reports.detailed-course-analysis.form')->with('error', 'No se pudieron obtener los usuarios inscritos.');
            }

        } catch (\Exception $e) {
            Log::error('Error generating detailed course analysis report: ' . $e->getMessage(), ['exception' => $e]);
            return redirect()->route('moodle.reports.detailed-course-analysis.form')->with('error', 'Error al generar el reporte detallado: ' . $e->getMessage());
        }

        session([
            'detailed_course_analysis_data' => $reportData,
            'detailed_course_analysis_activities' => $courseActivities,
            'detailed_course_analysis_course_name' => $courseDetails['fullname'] ?? 'Análisis_Detallado'
        ]);

        return view('moodle.reports.detailed-course-analysis-show', compact('courseDetails', 'reportData', 'courseActivities'));
    }

    /**
     * Show a form or options for the Global User Detail Report.
     * This might include filters for users (e.g., by cohort, first letter of lastname, etc.)
     * to avoid fetching all users if the Moodle instance is very large.
     */
    public function showGlobalUserDetailReportForm(Request $request)
    {
        // For now, no specific filters on the form, will attempt to fetch all users.
        // Add filters here if needed (e.g., input for user ID, email, namecontains)
        return view('moodle.reports.global-user-detail-form');
    }

    /**
     * Generate and display the Global User Detail Report.
     * This report lists each user and all courses they are enrolled in, with their status/grade.
     */
    public function generateGlobalUserDetailReport(Request $request)
    {
        // TODO: Implement pagination for users if fetching all is too slow.
        // For now, fetching all users (can be very resource-intensive).
        // Consider adding filters in showGlobalUserDetailReportForm and passing them here.
        $moodleUsers = [];
        $reportLines = []; // Each line: user_info, course_info, progress_info

        try {
            $usersResponse = $this->moodleApiService->getUsers([['key' => 'deleted', 'value' => 0]]); // Get non-deleted users
            if ($usersResponse->successful()) {
                $usersData = $usersResponse->json()['users'] ?? ($usersResponse->json() ?? []);
                 if (is_array($usersData) && isset($usersData['users']) && is_array($usersData['users'])) { // Handle Moodle's inconsistent response
                    $usersData = $usersData['users'];
                }
                $moodleUsers = is_array($usersData) ? $usersData : [];

            } else {
                return redirect()->route('moodle.reports.global-user-detail.form')->with('error', 'No se pudieron obtener los usuarios de Moodle.');
            }

            if (empty($moodleUsers)) {
                 return redirect()->route('moodle.reports.global-user-detail.form')->with('info', 'No se encontraron usuarios en Moodle para generar el reporte.');
            }

            foreach ($moodleUsers as $user) {
                if (!isset($user['id'])) continue;
                $userId = $user['id'];

                $userCoursesResponse = $this->moodleApiService->getUserCourses($userId);
                if ($userCoursesResponse->successful()) {
                    $coursesEnrolled = $userCoursesResponse->json();
                    if (is_array($coursesEnrolled) && !empty($coursesEnrolled)) {
                        foreach ($coursesEnrolled as $course) {
                            if (!isset($course['id'])) continue;
                            $courseId = $course['id'];

                            $completionStatus = 'N/A';
                            $grade = 'N/A';

                            // Get completion
                            $completionResponse = $this->moodleApiService->getCourseCompletionStatus($courseId, $userId);
                            if ($completionResponse->successful() && isset($completionResponse->json()['completionstatus'])) {
                                $status = $completionResponse->json()['completionstatus'];
                                $completionStatus = $status['completed'] ? 'Completado' : 'En Progreso';
                            }

                            // Get grade
                            $gradesResponse = $this->moodleApiService->getUserGradesInCourse($courseId, $userId);
                            if ($gradesResponse->successful() && isset($gradesResponse->json()['usergrades'][0]['gradeitems'])) {
                                 $gradeItems = $gradesResponse->json()['usergrades'][0]['gradeitems'];
                                 foreach ($gradeItems as $item) {
                                     if (($item['itemtype'] ?? '') === 'course') {
                                         $grade = $item['gradeformatted'] ?? ($item['graderaw'] ?? 'N/A');
                                         break;
                                     }
                                 }
                            }

                            $reportLines[] = [
                                'user_id' => $userId,
                                'user_fullname' => $user['fullname'] ?? 'N/A',
                                'user_email' => $user['email'] ?? 'N/A',
                                'course_id' => $courseId,
                                'course_fullname' => $course['fullname'] ?? 'N/A',
                                'completion_status' => $completionStatus,
                                'grade' => $grade,
                                // TODO: Add 'establecimiento', 'ley contractual', etc. if/when available
                                // These would likely come from $user['customfields'] or a local DB mapping
                            ];
                        }
                    } else {
                        // User might not be enrolled in any courses, or only in "site home" which might be filtered by API
                         $reportLines[] = [
                            'user_id' => $userId,
                            'user_fullname' => $user['fullname'] ?? 'N/A',
                            'user_email' => $user['email'] ?? 'N/A',
                            'course_id' => null,
                            'course_fullname' => 'Sin cursos inscritos (o error al obtenerlos)',
                            'completion_status' => 'N/A',
                            'grade' => 'N/A',
                        ];
                    }
                } else {
                    Log::warning("Could not fetch courses for user ID {$userId}. Response: " . $userCoursesResponse->body());
                }
            }

        } catch (\Exception $e) {
            Log::error('Error generating global user detail report: ' . $e->getMessage(), ['exception' => $e]);
            return redirect()->route('moodle.reports.global-user-detail.form')->with('error', 'Error al generar el reporte global por alumno: ' . $e->getMessage());
        }

        // For now, no specific view for this, just pass to a generic table view or prepare for export.
        // Storing in session for potential export.
        session([
            'global_user_detail_report_data' => $reportLines,
            'global_user_detail_report_name' => 'Reporte_Global_Detalle_Alumnos'
        ]);

        // This report can be very large, so direct view might not be ideal.
        // For now, let's redirect to a page that confirms generation and offers download.
        // Or, directly offer download if that's the primary goal.
        // For this step, let's just show a simplified success message and prepare for export.
        // A proper view would paginate $reportLines.

        // return view('moodle.reports.global-user-detail-show', compact('reportLines'));
        return redirect()->route('moodle.reports.global-user-detail.form')
                         ->with('success', 'Reporte Global por Alumno generado. Datos listos para exportar (próximo paso). Cantidad de registros: ' . count($reportLines));

    }
}
