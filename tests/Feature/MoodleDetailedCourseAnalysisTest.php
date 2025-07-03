<?php

namespace Tests\Feature;

use App\Models\User as BackOfficeUser;
use App\Services\MoodleApiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Response as HttpClientResponse;
use Mockery;
use Spatie\Permission\Models\Role as SpatieRole;
use Tests\TestCase;

class MoodleDetailedCourseAnalysisTest extends TestCase
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
    }

    private function mockCourseData(array $courses = [])
    {
        $mockResponse = Mockery::mock(HttpClientResponse::class);
        $mockResponse->shouldReceive('successful')->andReturn(true);
        $mockResponse->shouldReceive('json')->andReturn($courses);
        $this->moodleApiServiceMock->shouldReceive('getCourses')->andReturn($mockResponse);
    }

    public function test_detailed_course_analysis_form_is_accessible()
    {
        $this->actingAs($this->adminUser);
        $this->mockCourseData([['id' => 1, 'fullname' => 'Curso Detallado 1', 'format' => 'topics']]);

        $response = $this->get(route('moodle.reports.detailed-course-analysis.form'));

        $response->assertStatus(200);
        $response->assertViewIs('moodle.reports.detailed-course-analysis-form');
        $response->assertViewHas('courses');
        $response->assertSeeText('Curso Detallado 1');
    }

    public function test_generate_detailed_course_analysis_report_displays_data()
    {
        $this->actingAs($this->adminUser);
        $courseId = 15;
        $courseDetails = ['id' => $courseId, 'fullname' => 'Análisis Profundo de Curso'];

        $courseContents = [ // Sections
            [
                'id' => 1, 'name' => 'Section 1',
                'modules' => [
                    ['id' => 101, 'name' => 'Tarea 1', 'modname' => 'assign', 'instance' => 201],
                    ['id' => 102, 'name' => 'Cuestionario 1', 'modname' => 'quiz', 'instance' => 202],
                ]
            ],
            [
                'id' => 2, 'name' => 'Section 2',
                'modules' => [
                    ['id' => 103, 'name' => 'Foro Discusión', 'modname' => 'forum', 'instance' => 203],
                ]
            ]
        ];
        // Filtered activities based on controller logic
        $expectedCourseActivities = [
            ['id' => 101, 'name' => 'Tarea 1', 'modname' => 'assign', 'instance' => 201],
            ['id' => 102, 'name' => 'Cuestionario 1', 'modname' => 'quiz', 'instance' => 202],
            ['id' => 103, 'name' => 'Foro Discusión', 'modname' => 'forum', 'instance' => 203],
        ];


        $enrolledUsers = [
            ['id' => 201, 'fullname' => 'Alumno Alfa', 'email' => 'alfa@example.com', 'username' => 'alfa'],
            ['id' => 202, 'fullname' => 'Alumna Beta', 'email' => 'beta@example.com', 'username' => 'beta'],
        ];

        // Mock API calls
        $this->mockCourseData([$courseDetails]); // For fetching course details by ID

        $mockContentsResponse = Mockery::mock(HttpClientResponse::class);
        $mockContentsResponse->shouldReceive('successful')->andReturn(true);
        $mockContentsResponse->shouldReceive('json')->andReturn($courseContents);
        $this->moodleApiServiceMock->shouldReceive('getCourseContents')->with($courseId, Mockery::any())->andReturn($mockContentsResponse);

        $mockEnrolledUsersResponse = Mockery::mock(HttpClientResponse::class);
        $mockEnrolledUsersResponse->shouldReceive('successful')->andReturn(true);
        $mockEnrolledUsersResponse->shouldReceive('json')->andReturn($enrolledUsers);
        $this->moodleApiServiceMock->shouldReceive('getEnrolledUsersInCourse')
            ->with($courseId, [['name' => 'userfields', 'value' => 'id,fullname,email,username']])
            ->andReturn($mockEnrolledUsersResponse);

        // Mock Activity Completion for Alfa
        $mockAlfaCompletion = Mockery::mock(HttpClientResponse::class);
        $mockAlfaCompletion->shouldReceive('successful')->andReturn(true);
        $mockAlfaCompletion->shouldReceive('json')->andReturn([
            'statuses' => [
                ['cmid' => 101, 'state' => 1], // Tarea 1: Completo
                ['cmid' => 102, 'state' => 0], // Cuestionario 1: Incompleto
            ]
        ]);
        $this->moodleApiServiceMock->shouldReceive('getActivitiesCompletionStatus')->with($courseId, 201)->andReturn($mockAlfaCompletion);

        // Mock Activity Completion for Beta
        $mockBetaCompletion = Mockery::mock(HttpClientResponse::class);
        $mockBetaCompletion->shouldReceive('successful')->andReturn(true);
        $mockBetaCompletion->shouldReceive('json')->andReturn([
            'statuses' => [
                ['cmid' => 101, 'state' => 2], // Tarea 1: Completo (Aprobado)
                ['cmid' => 102, 'state' => 1], // Cuestionario 1: Completo
                ['cmid' => 103, 'state' => 0], // Foro: Incompleto
            ]
        ]);
        $this->moodleApiServiceMock->shouldReceive('getActivitiesCompletionStatus')->with($courseId, 202)->andReturn($mockBetaCompletion);


        $response = $this->post(route('moodle.reports.detailed-course-analysis.generate'), ['course_id' => $courseId]);

        $response->assertStatus(200);
        $response->assertViewIs('moodle.reports.detailed-course-analysis-show');
        $response->assertViewHas('courseDetails', $courseDetails);
        $response->assertViewHas('courseActivities', $expectedCourseActivities);
        $response->assertViewHas('reportData', function ($reportData) {
            // Check for Alfa
            if (!isset($reportData[201]) || $reportData[201]['user_info']['fullname'] !== 'Alumno Alfa') return false;
            if ($reportData[201]['activities'][101]['completion_state'] !== 'Completo') return false; // Tarea 1
            if ($reportData[201]['activities'][102]['completion_state'] !== 'Incompleto') return false; // Cuestionario 1
            if (isset($reportData[201]['activities'][103])) return false; // Alfa has no data for forum

            // Check for Beta
            if (!isset($reportData[202]) || $reportData[202]['user_info']['fullname'] !== 'Alumna Beta') return false;
            if ($reportData[202]['activities'][101]['completion_state'] !== 'Completo (Aprobado)') return false; // Tarea 1
            if ($reportData[202]['activities'][102]['completion_state'] !== 'Completo') return false; // Cuestionario 1
            if ($reportData[202]['activities'][103]['completion_state'] !== 'Incompleto') return false; // Forum

            return true;
        });

        $response->assertSeeText('Análisis Detallado: Análisis Profundo de Curso');
        $response->assertSeeText('Alumno Alfa');
        $response->assertSeeText('Tarea 1');
        $response->assertSeeText('Cuestionario 1');
        $response->assertSeeText('Foro Discusión');
        $response->assertSeeTextInOrder(['Alumno Alfa', 'Completo', 'Incompleto']); // Alfa's progress for Tarea 1, Cuestionario 1
        $response->assertSeeTextInOrder(['Alumna Beta', 'Completo (Aprobado)', 'Completo', 'Incompleto']); // Beta's progress

        // Check session data for export
        $this->assertEquals(session('detailed_course_analysis_course_name'), $courseDetails['fullname']);
        $this->assertCount(count($expectedCourseActivities), session('detailed_course_analysis_activities'));
        $this->assertCount(2, session('detailed_course_analysis_data')); // 2 users
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
