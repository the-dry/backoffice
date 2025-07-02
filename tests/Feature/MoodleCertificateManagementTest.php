<?php

namespace Tests\Feature;

use App\Models\User as BackOfficeUser;
use App\Services\MoodleApiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Response as HttpClientResponse;
use Mockery;
use Spatie\Permission\Models\Role as SpatieRole;
use Tests\TestCase;

class MoodleCertificateManagementTest extends TestCase
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

    private function mockCommonCertificateApis(array $courses = [], array $templates = [], array $enrolledUsers = [], array $issuedCertificates = [])
    {
        $mockCoursesResponse = Mockery::mock(HttpClientResponse::class);
        $mockCoursesResponse->shouldReceive('successful')->andReturn(true);
        $mockCoursesResponse->shouldReceive('json')->andReturn($courses);
        $this->moodleApiServiceMock->shouldReceive('getCourses')->andReturn($mockCoursesResponse);

        $mockTemplatesResponse = Mockery::mock(HttpClientResponse::class);
        $mockTemplatesResponse->shouldReceive('successful')->andReturn(true);
        $mockTemplatesResponse->shouldReceive('json')->andReturn(['customcertificates' => $templates]); // Based on controller logic
        $this->moodleApiServiceMock->shouldReceive('getCertificateTemplatesByCourses')->andReturn($mockTemplatesResponse);

        $mockEnrolledUsersResponse = Mockery::mock(HttpClientResponse::class);
        $mockEnrolledUsersResponse->shouldReceive('successful')->andReturn(true);
        $mockEnrolledUsersResponse->shouldReceive('json')->andReturn($enrolledUsers);
        $this->moodleApiServiceMock->shouldReceive('getEnrolledUsersInCourse')->andReturn($mockEnrolledUsersResponse);

        $mockIssuedCertsResponse = Mockery::mock(HttpClientResponse::class);
        $mockIssuedCertsResponse->shouldReceive('successful')->andReturn(true);
        $mockIssuedCertsResponse->shouldReceive('json')->andReturn(['issuedcertificates' => $issuedCertificates, 'warnings' => []]);
        $this->moodleApiServiceMock->shouldReceive('getIssuedCertificates')->andReturn($mockIssuedCertsResponse);
    }

    public function test_issue_certificate_form_is_accessible_and_loads_initial_courses()
    {
        $this->actingAs($this->adminUser);
        $fakeCourses = [
            ['id' => 10, 'fullname' => 'Curso de Certificados 1', 'format' => 'topics'],
        ];
        $this->mockCommonCertificateApis(courses: $fakeCourses);

        $response = $this->get(route('moodle.certificates.issue.form'));

        $response->assertStatus(200);
        $response->assertViewIs('moodle.certificates.issue-form');
        $response->assertViewHas('courses');
        $response->assertSeeText('Curso de Certificados 1');
    }

    public function test_issue_certificate_form_loads_templates_and_users_when_course_is_selected()
    {
        $this->actingAs($this->adminUser);
        $courseId = 10;
        $fakeCourses = [['id' => $courseId, 'fullname' => 'Curso Seleccionado', 'format' => 'topics']];
        $fakeTemplates = [['id' => 1, 'name' => 'Plantilla Cert A']];
        $fakeUsers = [['id' => 101, 'fullname' => 'Usuario Certificable', 'email' => 'cert@example.com']];

        $this->mockCommonCertificateApis(courses: $fakeCourses, templates: $fakeTemplates, enrolledUsers: $fakeUsers);

        $response = $this->get(route('moodle.certificates.issue.form', ['course_id_form' => $courseId]));

        $response->assertStatus(200);
        $response->assertViewHas('certificateTemplates', $fakeTemplates);
        $response->assertViewHas('paginatedUsers'); // Check that users are passed (paginated)
        $response->assertSeeText('Plantilla Cert A');
        $response->assertSeeText('Usuario Certificable');
    }

    public function test_handle_issue_certificate_successfully()
    {
        $this->actingAs($this->adminUser);
        $courseId = 10;
        $userId = 101;
        $certificateTemplateId = 1;

        $mockIssueResponse = Mockery::mock(HttpClientResponse::class);
        $mockIssueResponse->shouldReceive('successful')->andReturn(true);
        $mockIssueResponse->shouldReceive('json')->andReturn(['status' => true, 'issueid' => 555]);

        $this->moodleApiServiceMock
            ->shouldReceive('issueCertificate')
            ->once()
            ->with($certificateTemplateId, $userId)
            ->andReturn($mockIssueResponse);

        $response = $this->post(route('moodle.certificates.issue.submit'), [
            'course_id_form' => $courseId, // For context, though not directly used by issueCertificate API
            'user_id' => $userId,
            'certificate_id' => $certificateTemplateId,
        ]);

        $response->assertRedirect(route('moodle.certificates.issue.form'));
        $response->assertSessionHas('success', 'Certificado emitido exitosamente. ID de Emisión: 555');
    }

    public function test_handle_issue_certificate_fails_with_api_error()
    {
        $this->actingAs($this->adminUser);

        $mockIssueResponse = Mockery::mock(HttpClientResponse::class);
        $mockIssueResponse->shouldReceive('successful')->andReturn(false);
        $mockIssueResponse->shouldReceive('json')->andReturn(['errorcode' => 'api_issue_failed', 'message' => 'Error al emitir']);
        $mockIssueResponse->shouldReceive('body')->andReturn('Error al emitir');


        $this->moodleApiServiceMock
            ->shouldReceive('issueCertificate')
            ->once()
            ->andReturn($mockIssueResponse);

        $response = $this->post(route('moodle.certificates.issue.submit'), [
            'course_id_form' => 10,
            'user_id' => 101,
            'certificate_id' => 1,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error', function($value){
            return str_contains($value, 'Error en API de Moodle al emitir certificado: Error al emitir');
        });
    }

    public function test_index_issued_certificates_displays_report()
    {
        $this->actingAs($this->adminUser);
        $fakeIssuedCerts = [
            ['id' => 1, 'customcertid' => 10, 'certificatename' => 'Cert A', 'userid' => 101, 'userfullname' => 'Usuario Uno', 'courseid' => 100, 'coursename' => 'Curso X', 'timeissued' => time()],
            ['id' => 2, 'customcertid' => 11, 'certificatename' => 'Cert B', 'userid' => 102, 'userfullname' => 'Usuario Dos', 'courseid' => 101, 'coursename' => 'Curso Y', 'timeissued' => time() - 3600],
        ];
        $this->mockCommonCertificateApis(issuedCertificates: $fakeIssuedCerts); // Mocks getIssuedCertificates

        $response = $this->get(route('moodle.certificates.issued.index'));
        $response->assertStatus(200);
        $response->assertViewIs('moodle.certificates.index-issued');
        $response->assertViewHas('paginatedCertificates');
        $response->assertSeeText('Usuario Uno');
        $response->assertSeeText('Curso Y');
    }

    public function test_index_issued_certificates_handles_placeholder_api_function()
    {
        $this->actingAs($this->adminUser);

        // Mock getIssuedCertificates to return the placeholder warning
        $mockPlaceholderResponse = Mockery::mock(HttpClientResponse::class);
        $mockPlaceholderResponse->shouldReceive('successful')->andReturn(true);
        $mockPlaceholderResponse->shouldReceive('json')->andReturn([
            'issuedcertificates' => [],
            'warnings' => [['item' => 'getIssuedCertificates', 'warningcode' => 'notimplemented', 'message' => 'This Moodle API function is a placeholder.']]
        ]);
        $this->moodleApiServiceMock->shouldReceive('getIssuedCertificates')->once()->andReturn($mockPlaceholderResponse);

        $response = $this->get(route('moodle.certificates.issued.index'));
        $response->assertStatus(200);
        $response->assertSessionHas('info', 'La función para obtener certificados emitidos es un placeholder y necesita implementación real de API Moodle.');
        $response->assertSeeText('No se encontraron certificados emitidos o la funcionalidad API no está completamente implementada.');
    }


    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
