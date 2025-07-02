<?php

namespace Tests\Feature;

use App\Models\User as BackOfficeUser;
use App\Services\MoodleApiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Response as HttpClientResponse;
use Mockery;
use Spatie\Permission\Models\Role as SpatieRole; // Alias for Spatie's Role
use Tests\TestCase;

class MoodleCourseEnrolmentTest extends TestCase
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

    public function test_mass_enrolment_form_is_accessible_and_loads_data()
    {
        $this->actingAs($this->adminUser);

        $fakeCourses = [
            ['id' => 10, 'fullname' => 'Curso de Prueba 1', 'shortname' => 'CP1'],
            ['id' => 11, 'fullname' => 'Curso de Prueba 2', 'shortname' => 'CP2'],
        ];
        $fakeUsers = [
            // Moodle's getUsers returns a structure like ['users' => [...users...], 'warnings' => []]
            'users' => [
                ['id' => 101, 'username' => 'muser1', 'fullname' => 'Moodle User One', 'email' => 'muser1@example.com'],
                ['id' => 102, 'username' => 'muser2', 'fullname' => 'Moodle User Two', 'email' => 'muser2@example.com'],
            ],
            'warnings' => [],
        ];

        $mockCoursesResponse = Mockery::mock(HttpClientResponse::class);
        $mockCoursesResponse->shouldReceive('successful')->andReturn(true);
        $mockCoursesResponse->shouldReceive('json')->andReturn($fakeCourses);
        $this->moodleApiServiceMock->shouldReceive('getCourses')->once()->andReturn($mockCoursesResponse);

        $mockUsersResponse = Mockery::mock(HttpClientResponse::class);
        $mockUsersResponse->shouldReceive('successful')->andReturn(true);
        $mockUsersResponse->shouldReceive('json')->andReturn($fakeUsers);
        $this->moodleApiServiceMock->shouldReceive('getUsers')->once()->with([])->andReturn($mockUsersResponse);


        $response = $this->get(route('moodle.enrolments.mass-create.form'));

        $response->assertStatus(200);
        $response->assertViewIs('moodle.enrolments.mass-create');
        $response->assertViewHas('courses');
        $response->assertViewHas('users');
        $response->assertViewHas('roles');
        $response->assertSeeText('Curso de Prueba 1');
        $response->assertSeeText('Moodle User One');
    }

    public function test_mass_enrolment_submits_correct_data_to_service()
    {
        $this->actingAs($this->adminUser);

        $courseId = 10;
        $roleId = 5; // Student role
        $userIds = [101, 102];

        $expectedEnrolmentsPayload = [];
        foreach ($userIds as $userId) {
            $expectedEnrolmentsPayload[] = [
                'roleid' => $roleId,
                'userid' => $userId,
                'courseid' => $courseId,
            ];
        }

        $mockEnrolResponse = Mockery::mock(HttpClientResponse::class);
        $mockEnrolResponse->shouldReceive('successful')->andReturn(true);
        $mockEnrolResponse->shouldReceive('json')->andReturn(null); // enrol_manual_enrol_users returns null on success

        $this->moodleApiServiceMock
            ->shouldReceive('enrolUsers')
            ->once()
            ->with(Mockery::on(function ($argument) use ($expectedEnrolmentsPayload) {
                // Check if the argument matches the expected structure and content
                if (count($argument) !== count($expectedEnrolmentsPayload)) return false;
                foreach($expectedEnrolmentsPayload as $key => $expected) {
                    if ($argument[$key]['roleid'] !== $expected['roleid'] ||
                        $argument[$key]['userid'] !== $expected['userid'] ||
                        $argument[$key]['courseid'] !== $expected['courseid']) {
                        return false;
                    }
                }
                return true;
            }))
            ->andReturn($mockEnrolResponse);

        $response = $this->post(route('moodle.enrolments.mass-create.submit'), [
            'course_id' => $courseId,
            'role_id' => $roleId,
            'user_ids' => $userIds,
        ]);

        $response->assertRedirect(route('moodle.enrolments.mass-create.form'));
        $response->assertSessionHas('success', function($value){
            return str_contains($value, 'Proceso de inscripción masiva completado. Intentos: 2. Exitosos: 2.');
        });
        $response->assertSessionHasNoErrors(); // No 'error' or 'upload_errors'
    }

    public function test_mass_enrolment_handles_moodle_api_error()
    {
        $this->actingAs($this->adminUser);

        $mockEnrolResponse = Mockery::mock(HttpClientResponse::class);
        $mockEnrolResponse->shouldReceive('successful')->andReturn(false);
        $mockEnrolResponse->shouldReceive('json')->andReturn(['errorcode' => 'api_enrol_error', 'message' => 'Moodle API enrolment failed']);
        $mockEnrolResponse->shouldReceive('body')->andReturn('Moodle API enrolment failed');


        $this->moodleApiServiceMock
            ->shouldReceive('enrolUsers')
            ->once()
            ->andReturn($mockEnrolResponse);

        $response = $this->post(route('moodle.enrolments.mass-create.submit'), [
            'course_id' => 10,
            'role_id' => 5,
            'user_ids' => [101],
        ]);

        $response->assertRedirect(); // Redirects back to the form
        $response->assertSessionHas('error');
        $response->assertSessionHas('upload_errors', function ($errors) {
            return is_array($errors) && count($errors) > 0 && str_contains($errors[0], 'Error en API de Moodle al inscribir usuarios');
        });
    }

    public function test_mass_enrolment_validation_fails_for_missing_data()
    {
        $this->actingAs($this->adminUser);

        // Test missing course_id
        $response = $this->post(route('moodle.enrolments.mass-create.submit'), [
            // 'course_id' => 10, // Missing
            'role_id' => 5,
            'user_ids' => [101],
        ]);
        $response->assertSessionHasErrors('course_id');

        // Test missing role_id
        $response = $this->post(route('moodle.enrolments.mass-create.submit'), [
            'course_id' => 10,
            // 'role_id' => 5, // Missing
            'user_ids' => [101],
        ]);
        $response->assertSessionHasErrors('role_id');

        // Test missing user_ids
        $response = $this->post(route('moodle.enrolments.mass-create.submit'), [
            'course_id' => 10,
            'role_id' => 5,
            // 'user_ids' => [101], // Missing
        ]);
        $response->assertSessionHasErrors('user_ids');

        // Test user_ids not an array
        $response = $this->post(route('moodle.enrolments.mass-create.submit'), [
            'course_id' => 10,
            'role_id' => 5,
            'user_ids' => 'not-an-array',
        ]);
        $response->assertSessionHasErrors('user_ids');

        // Test user_ids contains non-integer
        $response = $this->post(route('moodle.enrolments.mass-create.submit'), [
            'course_id' => 10,
            'role_id' => 5,
            'user_ids' => [101, 'abc', 102],
        ]);
        $response->assertSessionHasErrors('user_ids.*');
    }


    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
