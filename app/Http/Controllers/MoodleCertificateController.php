<?php

namespace App\Http\Controllers;

use App\Services\MoodleApiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Pagination\LengthAwarePaginator;


class MoodleCertificateController extends Controller
{
    protected MoodleApiService $moodleApiService;

    public function __construct(MoodleApiService $moodleApiService)
    {
        $this->moodleApiService = $moodleApiService;
        // Middleware for permissions can be added here, e.g.,
        // $this->middleware('can:manage moodle certificates');
    }

    /**
     * Display a listing of issued certificates (Reporte de emisión).
     */
    public function indexIssued(Request $request)
    {
        $issuedCertificates = [];
        $page = $request->input('page', 1);
        $perPage = 20; // Configurable

        // Criteria for fetching issued certificates (example: all, or filter by course/user if form is extended)
        // This is highly dependent on the actual Moodle API function for `getIssuedCertificates`
        $criteria = [];
        // Example criteria if filtering is added:
        // if ($request->filled('course_id')) {
        //     $criteria['courseid'] = $request->input('course_id');
        // }
        // if ($request->filled('user_id')) {
        //     $criteria['userid'] = $request->input('user_id');
        // }


        try {
            $response = $this->moodleApiService->getIssuedCertificates($criteria);

            if ($response->successful()) {
                $responseData = $response->json();
                // The structure of 'issuedcertificates' is hypothetical.
                // It needs to match what the (potentially custom) Moodle API function returns.
                // It might be an array of objects, each with user details, course details, certificate details, issue date.
                $issuedCertificates = $responseData['issuedcertificates'] ?? [];

                if (!empty($responseData['warnings'])) {
                    foreach($responseData['warnings'] as $warning) {
                        if (isset($warning['warningcode']) && $warning['warningcode'] === 'notimplemented') {
                             session()->flash('info', 'La función para obtener certificados emitidos es un placeholder y necesita implementación real de API Moodle.');
                        } else {
                            session()->flash('moodle_warnings[]', $warning['message'] ?? 'Advertencia desconocida de Moodle.');
                        }
                    }
                }
            } else {
                $errorData = $response->json();
                $errorMessage = 'Error al obtener certificados emitidos de Moodle.';
                if (isset($errorData['errorcode'])) {
                    $errorMessage .= ' Código: ' . $errorData['errorcode'] . '. Mensaje: ' . $errorData['message'];
                }
                session()->flash('error', $errorMessage);
            }
        } catch (\Exception $e) {
            Log::error('Error fetching issued Moodle certificates: ' . $e->getMessage());
            session()->flash('error', 'Error de conexión o procesamiento al obtener certificados emitidos: ' . $e->getMessage());
        }

        // Manual pagination
        $currentPage = LengthAwarePaginator::resolveCurrentPage('page');
        $currentItems = array_slice($issuedCertificates, ($currentPage - 1) * $perPage, $perPage);
        $paginatedCertificates = new LengthAwarePaginator($currentItems, count($issuedCertificates), $perPage, $currentPage, [
            'path' => LengthAwarePaginator::resolveCurrentPath(),
            'pageName' => 'page',
        ]);


        return view('moodle.certificates.index-issued', compact('paginatedCertificates'));
    }

    /**
     * Show the form for issuing a new certificate.
     * This would typically involve selecting a course, a user, and a certificate template.
     */
    public function showIssueForm(Request $request)
    {
        $courses = [];
        $users = []; // Paginated list of users
        $certificateTemplates = []; // Potentially fetched based on selected course
        $selectedCourseId = $request->input('course_id_form'); // Use a different name to avoid conflict

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

            if ($selectedCourseId) {
                // Fetch certificate templates for the selected course
                $templatesResponse = $this->moodleApiService->getCertificateTemplatesByCourses([$selectedCourseId]);
                if ($templatesResponse->successful() && isset($templatesResponse->json()['customcertificates'])) {
                    $certificateTemplates = $templatesResponse->json()['customcertificates'];
                } else {
                    session()->flash('template_error', 'No se pudieron cargar las plantillas de certificado para el curso seleccionado.');
                }
                // Fetch enrolled users for the selected course
                $enrolledUsersResponse = $this->moodleApiService->getEnrolledUsersInCourse((int)$selectedCourseId, [['name' => 'userfields', 'value' => 'id,fullname,email']]);
                if ($enrolledUsersResponse->successful()) {
                    $users = $enrolledUsersResponse->json() ?? [];
                } else {
                    session()->flash('user_error', 'No se pudieron cargar los usuarios para el curso seleccionado.');
                }
            }

        } catch (\Exception $e) {
            Log::error('Error loading data for issue certificate form: ' . $e->getMessage());
            session()->flash('error', 'Error al cargar datos para el formulario: ' . $e->getMessage());
        }

        // Basic pagination for users if loaded (can be improved)
        $perPage = 50;
        $currentPage = LengthAwarePaginator::resolveCurrentPage('user_page');
        $currentUsers = array_slice($users, ($currentPage - 1) * $perPage, $perPage);
        $paginatedUsers = new LengthAwarePaginator($currentUsers, count($users), $perPage, $currentPage, [
            'path' => LengthAwarePaginator::resolveCurrentPath(),
            'pageName' => 'user_page',
        ]);
        $paginatedUsers->appends($request->except('user_page'));


        return view('moodle.certificates.issue-form', compact('courses', 'paginatedUsers', 'certificateTemplates', 'selectedCourseId'));
    }

    /**
     * Handle the submission for issuing a certificate.
     */
    public function handleIssueCertificate(Request $request)
    {
        $request->validate([
            'course_id_form' => 'required|integer', // Ensure this matches the form field name
            'user_id' => 'required|integer',
            'certificate_id' => 'required|integer', // This is the customcertid (template ID)
        ]);

        $certificateId = $request->input('certificate_id');
        $userId = $request->input('user_id');
        // $courseId = $request->input('course_id_form'); // courseId is not directly used by issueCertificate API call but good for context

        try {
            $response = $this->moodleApiService->issueCertificate($certificateId, $userId);

            if ($response->successful()) {
                // mod_customcert_issue_certificate usually returns an object with 'status' => true and 'issueid'
                $responseData = $response->json();
                if (isset($responseData['status']) && $responseData['status'] === true && isset($responseData['issueid'])) {
                    return redirect()->route('moodle.certificates.issue.form') // Or to issued list
                                     ->with('success', "Certificado emitido exitosamente. ID de Emisión: {$responseData['issueid']}");
                } elseif (isset($responseData['exception'])) {
                     return back()->with('error', "Error de Moodle API: {$responseData['message']} (Code: {$responseData['errorcode']})")->withInput();
                } else {
                    Log::warning('Moodle issueCertificate unexpected successful response structure: ', $responseData ?? []);
                    return back()->with('error', 'Respuesta inesperada de Moodle al emitir certificado. Verifique en Moodle.')->withInput();
                }
            } else {
                $apiError = $response->json();
                return back()->with('error', 'Error en API de Moodle al emitir certificado: ' . ($apiError['message'] ?? $response->body()))->withInput();
            }
        } catch (\Exception $e) {
            Log::error('Error issuing Moodle certificate: ' . $e->getMessage());
            return back()->with('error', 'Error de conexión o procesamiento al emitir certificado: ' . $e->getMessage())->withInput();
        }
    }
}
