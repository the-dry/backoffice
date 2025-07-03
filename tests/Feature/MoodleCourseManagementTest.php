<?php

namespace Tests\Feature;

use App\Models\User as BackOfficeUser;
use App\Services\MoodleApiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Response as HttpClientResponse;
use Mockery;
use Spatie\Permission\Models\Role as SpatieRole;
use Tests\TestCase;

class MoodleCourseManagementTest extends TestCase
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

    private function mockGetCoursesApi(array $courses = [])
    {
        $mockResponse = Mockery::mock(HttpClientResponse::class);
        $mockResponse->shouldReceive('successful')->andReturn(true);
        $mockResponse->shouldReceive('json')->andReturn($courses);
        $this->moodleApiServiceMock->shouldReceive('getCourses')->andReturn($mockResponse);
    }

    private function mockUpdateCoursesApi(bool $success = true, ?array $warnings = [], ?array $exception = null)
    {
        $mockResponse = Mockery::mock(HttpClientResponse::class);
        $mockResponse->shouldReceive('successful')->andReturn($success);

        $jsonResponse = [];
        if (!empty($warnings)) {
            $jsonResponse['warnings'] = $warnings;
        }
        if (!empty($exception)) {
            $jsonResponse['exception'] = $exception['type'] ?? 'unknown_exception';
            $jsonResponse['errorcode'] = $exception['errorcode'] ?? 'unknown_error';
            $jsonResponse['message'] = $exception['message'] ?? 'An error occurred.';
        }
        // If successful and no warnings/exception, Moodle might return empty or null
        if ($success && empty($jsonResponse)) {
            $mockResponse->shouldReceive('json')->andReturn(null);
        } else {
            $mockResponse->shouldReceive('json')->andReturn($jsonResponse);
        }

        if (!$success) {
            $mockResponse->shouldReceive('body')->andReturn(json_encode($jsonResponse));
        }

        $this->moodleApiServiceMock->shouldReceive('updateCourses')->andReturn($mockResponse);
    }

    public function test_course_management_index_is_accessible_and_lists_courses()
    {
        $this->actingAs($this->adminUser);
        $fakeCourses = [
            ['id' => 10, 'fullname' => 'Curso Gestionable 1', 'shortname' => 'CG1', 'visible' => 1],
            ['id' => 1, 'fullname' => 'Site Home', 'shortname' => 'SITEHOME', 'visible' => 1], // Should be filtered out
            ['id' => 11, 'fullname' => 'Curso Oculto 2', 'shortname' => 'CO2', 'visible' => 0],
        ];
        $this->mockGetCoursesApi($fakeCourses);

        $response = $this->get(route('moodle.courses.index'));

        $response->assertStatus(200);
        $response->assertViewIs('moodle.courses.index');
        $response->assertViewHas('paginatedCourses', function($courses) {
            return $courses->count() === 2; // Site home filtered
        });
        $response->assertSeeText('Curso Gestionable 1');
        $response->assertSeeText('Curso Oculto 2');
        $response->assertDontSeeText('Site Home');
        $response->assertSeeInOrder(['Curso Gestionable 1', 'Sí', 'Ocultar']); // Visible course
        $response->assertSeeInOrder(['Curso Oculto 2', 'No', 'Mostrar']);   // Hidden course
    }

    public function test_toggle_course_visibility_to_hidden_successfully()
    {
        $this->actingAs($this->adminUser);
        $courseId = 10;

        $this->mockUpdateCoursesApi(true); // Simulate successful update

        // Store current query params to assert redirect back with them (even if empty)
        session(['moodle_courses_index_query_params' => ['page' => 1]]);


        $response = $this->post(route('moodle.courses.toggle-visibility', $courseId), [
            'visible' => 0, // Attempt to hide
        ]);

        $this->moodleApiServiceMock->shouldHaveReceived('updateCourses')->once()->with([
            ['id' => $courseId, 'visible' => 0]
        ]);
        $response->assertRedirect(route('moodle.courses.index', ['page' => 1]));
        $response->assertSessionHas('success', 'Visibilidad del curso actualizada exitosamente.');
    }

    public function test_toggle_course_visibility_to_visible_successfully()
    {
        $this->actingAs($this->adminUser);
        $courseId = 11;
        $this->mockUpdateCoursesApi(true);
        session(['moodle_courses_index_query_params' => []]);


        $response = $this->post(route('moodle.courses.toggle-visibility', $courseId), [
            'visible' => 1, // Attempt to show
        ]);

        $this->moodleApiServiceMock->shouldHaveReceived('updateCourses')->once()->with([
            ['id' => $courseId, 'visible' => 1]
        ]);
        $response->assertRedirect(route('moodle.courses.index', []));
        $response->assertSessionHas('success', 'Visibilidad del curso actualizada exitosamente.');
    }

    public function test_toggle_course_visibility_handles_moodle_api_error()
    {
        $this->actingAs($this->adminUser);
        $courseId = 12;
        $this->mockUpdateCoursesApi(false, [], ['message' => 'Update failed in Moodle']);
        session(['moodle_courses_index_query_params' => []]);

        $response = $this->post(route('moodle.courses.toggle-visibility', $courseId), [
            'visible' => 0,
        ]);

        $response->assertRedirect(route('moodle.courses.index', []));
        $response->assertSessionHas('error', function($value){
            return str_contains($value, 'Error en API de Moodle al actualizar visibilidad: Update failed in Moodle');
        });
    }

    public function test_toggle_course_visibility_handles_moodle_api_warnings()
    {
        $this->actingAs($this->adminUser);
        $courseId = 13;
        $this->mockUpdateCoursesApi(true, [['itemid' => $courseId, 'warningcode' => 'some_warning', 'message' => 'A Moodle warning occurred']]);
        session(['moodle_courses_index_query_params' => []]);

        $response = $this->post(route('moodle.courses.toggle-visibility', $courseId), [
            'visible' => 1,
        ]);

        $response->assertRedirect(route('moodle.courses.index', []));
        $response->assertSessionHas('warning', function($value){
            return str_contains($value, 'Curso actualizado, pero Moodle reportó advertencias:');
        });
    }


    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
