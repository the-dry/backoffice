<?php

namespace Tests\Feature;

use App\Models\User as BackOfficeUser;
use App\Services\MoodleApiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Response as HttpClientResponse;
use Mockery;
use Spatie\Permission\Models\Role as SpatieRole;
use Tests\TestCase;

class MoodleGlobalUserDetailReportTest extends TestCase
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

    public function test_global_user_detail_report_form_is_accessible()
    {
        $this->actingAs($this->adminUser);
        $response = $this->get(route('moodle.reports.global-user-detail.form'));
        $response->assertStatus(200);
        $response->assertViewIs('moodle.reports.global-user-detail-form');
        $response->assertSeeText('Generar Reporte Global por Alumno');
    }

    public function test_generate_global_user_detail_report_basic_flow()
    {
        $this->actingAs($this->adminUser);

        $moodleUsers = [
            ['id' => 101, 'fullname' => 'Global User One', 'email' => 'global1@example.com'],
            ['id' => 102, 'fullname' => 'Global User Two', 'email' => 'global2@example.com'],
        ];
        $coursesForUser101 = [
            ['id' => 201, 'fullname' => 'Curso Alpha'],
            ['id' => 202, 'fullname' => 'Curso Beta'],
        ];
        $coursesForUser102 = [
            ['id' => 201, 'fullname' => 'Curso Alpha'], // Also enrolled in Alpha
        ];

        // Mock getUsers
        $mockUsersResponse = Mockery::mock(HttpClientResponse::class);
        $mockUsersResponse->shouldReceive('successful')->once()->andReturn(true);
        $mockUsersResponse->shouldReceive('json')->once()->andReturn(['users' => $moodleUsers]);
        $this->moodleApiServiceMock->shouldReceive('getUsers')->once()->with([['key' => 'deleted', 'value' => 0]])->andReturn($mockUsersResponse);

        // Mock getUserCourses for User 101
        $mockUser101CoursesResponse = Mockery::mock(HttpClientResponse::class);
        $mockUser101CoursesResponse->shouldReceive('successful')->once()->andReturn(true);
        $mockUser101CoursesResponse->shouldReceive('json')->once()->andReturn($coursesForUser101);
        $this->moodleApiServiceMock->shouldReceive('getUserCourses')->with(101)->andReturn($mockUser101CoursesResponse);

        // Mock getUserCourses for User 102
        $mockUser102CoursesResponse = Mockery::mock(HttpClientResponse::class);
        $mockUser102CoursesResponse->shouldReceive('successful')->once()->andReturn(true);
        $mockUser102CoursesResponse->shouldReceive('json')->once()->andReturn($coursesForUser102);
        $this->moodleApiServiceMock->shouldReceive('getUserCourses')->with(102)->andReturn($mockUser102CoursesResponse);

        // Mock Completion and Grades (simplified: assume all 'Completado' and 'Aprobado')
        $mockCompletionResponse = Mockery::mock(HttpClientResponse::class);
        $mockCompletionResponse->shouldReceive('successful')->andReturn(true);
        $mockCompletionResponse->shouldReceive('json')->andReturn(['completionstatus' => ['completed' => true]]);
        $this->moodleApiServiceMock->shouldReceive('getCourseCompletionStatus')->andReturn($mockCompletionResponse); // Called for each user-course pair

        $mockGradesResponse = Mockery::mock(HttpClientResponse::class);
        $mockGradesResponse->shouldReceive('successful')->andReturn(true);
        $mockGradesResponse->shouldReceive('json')->andReturn(['usergrades' => [['gradeitems' => [['itemtype' => 'course', 'gradeformatted' => 'Aprobado']]]]]);
        $this->moodleApiServiceMock->shouldReceive('getUserGradesInCourse')->andReturn($mockGradesResponse); // Called for each user-course pair


        $response = $this->post(route('moodle.reports.global-user-detail.generate'));

        $response->assertRedirect(route('moodle.reports.global-user-detail.form'));
        $response->assertSessionHas('success', function($value) {
            // 2 users * (2+1 courses) = 3 report lines
            return str_contains($value, 'Reporte Global por Alumno generado. Datos listos para exportar (próximo paso). Cantidad de registros: 3');
        });

        $sessionData = session('global_user_detail_report_data');
        $this->assertCount(3, $sessionData);
        $this->assertEquals('Global User One', $sessionData[0]['user_fullname']);
        $this->assertEquals('Curso Alpha', $sessionData[0]['course_fullname']);
        $this->assertEquals('Completado', $sessionData[0]['completion_status']);
        $this->assertEquals('Aprobado', $sessionData[0]['grade']);

        $this->assertEquals('Global User One', $sessionData[1]['user_fullname']);
        $this->assertEquals('Curso Beta', $sessionData[1]['course_fullname']);

        $this->assertEquals('Global User Two', $sessionData[2]['user_fullname']);
        $this->assertEquals('Curso Alpha', $sessionData[2]['course_fullname']);
    }

    public function test_generate_global_user_detail_report_handles_no_users_found()
    {
        $this->actingAs($this->adminUser);

        $mockUsersResponse = Mockery::mock(HttpClientResponse::class);
        $mockUsersResponse->shouldReceive('successful')->once()->andReturn(true);
        $mockUsersResponse->shouldReceive('json')->once()->andReturn(['users' => []]); // No users
        $this->moodleApiServiceMock->shouldReceive('getUsers')->once()->with([['key' => 'deleted', 'value' => 0]])->andReturn($mockUsersResponse);

        $this->moodleApiServiceMock->shouldNotReceive('getUserCourses');

        $response = $this->post(route('moodle.reports.global-user-detail.generate'));
        $response->assertRedirect(route('moodle.reports.global-user-detail.form'));
        $response->assertSessionHas('info', 'No se encontraron usuarios en Moodle para generar el reporte.');
        $this->assertNull(session('global_user_detail_report_data'));
    }

    public function test_generate_global_user_detail_report_handles_user_with_no_courses()
    {
        $this->actingAs($this->adminUser);
        $moodleUsers = [
            ['id' => 101, 'fullname' => 'User No Courses', 'email' => 'nocourses@example.com'],
        ];

        $mockUsersResponse = Mockery::mock(HttpClientResponse::class);
        $mockUsersResponse->shouldReceive('successful')->once()->andReturn(true);
        $mockUsersResponse->shouldReceive('json')->once()->andReturn(['users' => $moodleUsers]);
        $this->moodleApiServiceMock->shouldReceive('getUsers')->once()->with([['key' => 'deleted', 'value' => 0]])->andReturn($mockUsersResponse);

        $mockUserCoursesResponse = Mockery::mock(HttpClientResponse::class);
        $mockUserCoursesResponse->shouldReceive('successful')->once()->andReturn(true);
        $mockUserCoursesResponse->shouldReceive('json')->once()->andReturn([]); // No courses for this user
        $this->moodleApiServiceMock->shouldReceive('getUserCourses')->with(101)->andReturn($mockUserCoursesResponse);

        $this->moodleApiServiceMock->shouldNotReceive('getCourseCompletionStatus');
        $this->moodleApiServiceMock->shouldNotReceive('getUserGradesInCourse');

        $response = $this->post(route('moodle.reports.global-user-detail.generate'));
        $response->assertRedirect(route('moodle.reports.global-user-detail.form'));
         $response->assertSessionHas('success', function($value) {
            return str_contains($value, 'Cantidad de registros: 1');
        });
        $sessionData = session('global_user_detail_report_data');
        $this->assertCount(1, $sessionData);
        $this->assertEquals('User No Courses', $sessionData[0]['user_fullname']);
        $this->assertEquals('Sin cursos inscritos (o error al obtenerlos)', $sessionData[0]['course_fullname']);
    }


    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
