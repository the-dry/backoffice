<?php

namespace Tests\Feature;

use App\Models\User as BackOfficeUser;
use App\Services\MoodleApiService;
use App\Exports\CourseProgressExport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Response as HttpClientResponse;
use Maatwebsite\Excel\Facades\Excel;
use Mockery;
use Spatie\Permission\Models\Role as SpatieRole;
use Tests\TestCase;

class MoodleCourseProgressReportTest extends TestCase
{
    use RefreshDatabase;

    protected BackOfficeUser $adminUser;
    protected Mockery\MockInterface $moodleApiServiceMock;

    protected function setUp(): void
    {
        parent::setUp();

        if (!SpatieRole::where('name', 'Administrador BackOffice')->exists()) {
            SpatieRole::create(['name' => 'Administrador BackOffice', 'guard_name' => 'web']);
        }

        $this->adminUser = BackOfficeUser::factory()->create();
        $this->adminUser->assignRole('Administrador BackOffice');

        $this->moodleApiServiceMock = Mockery::mock(MoodleApiService::class);
        $this->app->instance(MoodleApiService::class, $this->moodleApiServiceMock);

        Excel::fake();
    }

    private function mockCourseData(array $courses = [])
    {
        $mockResponse = Mockery::mock(HttpClientResponse::class);
        $mockResponse->shouldReceive('successful')->andReturn(true);
        $mockResponse->shouldReceive('json')->andReturn($courses);
        $this->moodleApiServiceMock->shouldReceive('getCourses')->andReturn($mockResponse);
    }

    private function mockEnrolledUsersData(array $users = [])
    {
        $mockResponse = Mockery::mock(HttpClientResponse::class);
        $mockResponse->shouldReceive('successful')->andReturn(true);
        $mockResponse->shouldReceive('json')->andReturn($users); // Assumes the direct array of users
        $this->moodleApiServiceMock->shouldReceive('getEnrolledUsersInCourse')->andReturn($mockResponse);
    }

    private function mockCompletionStatusData(bool $completed = true, array $completions = [])
    {
        $mockResponse = Mockery::mock(HttpClientResponse::class);
        $mockResponse->shouldReceive('successful')->andReturn(true);
        $mockResponse->shouldReceive('json')->andReturn([
            'completionstatus' => [
                'completed' => $completed,
                'completions' => $completions,
            ]
        ]);
        // This mock will apply to all calls for completion status in a test
        // For more granular control, specify ->with(courseId, userId)
        $this->moodleApiServiceMock->shouldReceive('getCourseCompletionStatus')->andReturn($mockResponse);
    }

    private function mockUserGradesData($gradeFormatted = 'N/A', array $gradeItems = [])
    {
        if (empty($gradeItems) && $gradeFormatted !== 'N/A') {
             // If a simple grade is given, create a basic course grade item
            $gradeItems[] = ['itemtype' => 'course', 'gradeformatted' => $gradeFormatted];
        } elseif (empty($gradeItems)) {
            $gradeItems[] = ['itemtype' => 'course', 'gradeformatted' => 'N/A']; // Default if nothing specified
        }

        $mockResponse = Mockery::mock(HttpClientResponse::class);
        $mockResponse->shouldReceive('successful')->andReturn(true);
        $mockResponse->shouldReceive('json')->andReturn([
            'usergrades' => [ // gradereport_user_get_grade_items returns an array of usergrade objects
                [
                    'courseid' => 1, // Example course ID
                    'userid' => 1,   // Example user ID, doesn't matter much for this general mock
                    'gradeitems' => $gradeItems
                ]
            ]
        ]);
        $this->moodleApiServiceMock->shouldReceive('getUserGradesInCourse')->andReturn($mockResponse);
    }


    public function test_course_progress_report_form_is_accessible_and_loads_courses()
    {
        $this->actingAs($this->adminUser);
        $fakeCourses = [
            ['id' => 10, 'fullname' => 'Curso Test A', 'format' => 'topics'],
            ['id' => 1, 'fullname' => 'Site Home', 'format' => 'site'], // Should be filtered out
        ];
        $this->mockCourseData($fakeCourses);

        $response = $this->get(route('moodle.reports.course-progress.form'));

        $response->assertStatus(200);
        $response->assertViewIs('moodle.reports.course-progress-form');
        $response->assertViewHas('courses', function($coursesInView) {
            return count($coursesInView) === 1 && $coursesInView[0]['id'] === 10;
        });
        $response->assertSeeText('Curso Test A');
        $response->assertDontSeeText('Site Home');
    }

    public function test_generate_course_progress_report_displays_data()
    {
        $this->actingAs($this->adminUser);
        $courseId = 10;
        $courseDetails = ['id' => $courseId, 'fullname' => 'Curso de Detalle'];

        $enrolledUsers = [
            ['id' => 101, 'fullname' => 'Estudiante Uno', 'email' => 'est1@example.com', 'username' => 'est1'],
            ['id' => 102, 'fullname' => 'Estudiante Dos', 'email' => 'est2@example.com', 'username' => 'est2'],
        ];

        $this->mockCourseData([$courseDetails]); // For fetching course details
        $this->mockEnrolledUsersData($enrolledUsers);

        // Mock completion and grades for each user (simplified)
        $this->moodleApiServiceMock->shouldReceive('getCourseCompletionStatus')
            ->with($courseId, 101)
            ->andReturn(Mockery::mock(HttpClientResponse::class, ['successful' => true, 'json' => fn() => ['completionstatus' => ['completed' => true]]]));
        $this->moodleApiServiceMock->shouldReceive('getCourseCompletionStatus')
            ->with($courseId, 102)
            ->andReturn(Mockery::mock(HttpClientResponse::class, ['successful' => true, 'json' => fn() => ['completionstatus' => ['completed' => false]]]));

        $this->moodleApiServiceMock->shouldReceive('getUserGradesInCourse')
            ->with($courseId, 101, null)
            ->andReturn(Mockery::mock(HttpClientResponse::class, ['successful' => true, 'json' => fn() => ['usergrades' => [['gradeitems' => [['itemtype' => 'course', 'gradeformatted' => '95%']]]]]]));
        $this->moodleApiServiceMock->shouldReceive('getUserGradesInCourse')
            ->with($courseId, 102, null)
            ->andReturn(Mockery::mock(HttpClientResponse::class, ['successful' => true, 'json' => fn() => ['usergrades' => [['gradeitems' => [['itemtype' => 'course', 'gradeformatted' => '60%']]]]]]));


        $response = $this->post(route('moodle.reports.course-progress.generate'), ['course_id' => $courseId]);

        $response->assertStatus(200);
        $response->assertViewIs('moodle.reports.course-progress-show');
        $response->assertViewHas('courseDetails', $courseDetails);
        $response->assertViewHas('enrolledUsersWithProgress', function($progressData) {
            return count($progressData) === 2 &&
                   $progressData[0]['fullname'] === 'Estudiante Uno' && $progressData[0]['completion_status'] === 'Completado' && $progressData[0]['grade'] === '95%' &&
                   $progressData[1]['fullname'] === 'Estudiante Dos' && $progressData[1]['completion_status'] === 'En Progreso' && $progressData[1]['grade'] === '60%';
        });
        $response->assertSeeText('Estudiante Uno');
        $response->assertSeeText('Completado');
        $response->assertSeeText('95%');
        $response->assertSeeText('Estudiante Dos');
        $response->assertSeeText('En Progreso');
        $response->assertSeeText('60%');

        // Check if data is stored in session for export
        $this->assertEquals(session('course_progress_report_course_name'), $courseDetails['fullname']);
        $this->assertCount(2, session('course_progress_report_data'));
    }

    public function test_export_course_progress_report_downloads_excel()
    {
        $this->actingAs($this->adminUser);
        $courseId = 10;
        $courseName = 'Curso de Exportación';
        $reportData = [
            ['id' => 101, 'fullname' => 'Export User 1', 'email' => 'export1@example.com', 'username' => 'export1', 'completion_status' => 'Completado', 'grade' => 'A', 'firstaccess' => '2023-01-01', 'lastaccess' => '2023-01-10'],
        ];

        // Put data into session as the controller expects for export
        session([
            'course_progress_report_data' => $reportData,
            'course_progress_report_course_name' => $courseName
        ]);

        $response = $this->get(route('moodle.reports.course-progress.export', ['course_id' => $courseId])); // course_id in query for potential future use by controller

        $response->assertStatus(200);
        $expectedFilename = 'reporte_progreso_' . preg_replace('/[^a-zA-Z0-9_ \.-]/', '', $courseName) . '_' . date('Ymd_His') . '.xlsx';
        // We can't check exact filename due to date, but we can check the start
        $this->assertTrue(
            str_starts_with(
                $response->headers->get('content-disposition'),
                'attachment; filename=reporte_progreso_' . preg_replace('/[^a-zA-Z0-9_ \.-]/', '', $courseName)
            )
        );

        Excel::assertDownloaded($expectedFilename, function (CourseProgressExport $export) use ($reportData) {
            $collection = $export->collection();
            return $collection->count() === 1 && $collection->first()['Nombre Completo'] === 'Export User 1';
        });
    }

    public function test_export_course_progress_report_redirects_if_no_data_in_session()
    {
        $this->actingAs($this->adminUser);
        // Ensure session is empty for this data
        session()->forget(['course_progress_report_data', 'course_progress_report_course_name']);

        $response = $this->get(route('moodle.reports.course-progress.export', ['course_id' => 10]));

        $response->assertRedirect(route('moodle.reports.course-progress.form'));
        $response->assertSessionHas('error', 'No hay datos de reporte para exportar o la sesión expiró.');
        Excel::assertNotDownloaded();
    }


    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
