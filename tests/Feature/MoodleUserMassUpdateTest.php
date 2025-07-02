<?php

namespace Tests\Feature;

use App\Models\User as BackOfficeUser;
use App\Services\MoodleApiService;
use App\Imports\MoodleUsersUpdateImport; // Import the class
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Response as HttpClientResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel; // Import facade
use Mockery;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MoodleUserMassUpdateTest extends TestCase
{
    use RefreshDatabase;

    protected BackOfficeUser $adminUser;
    protected Mockery\MockInterface $moodleApiServiceMock;

    protected function setUp(): void
    {
        parent::setUp();

        if (!Role::where('name', 'Administrador BackOffice')->exists()) {
            Role::create(['name' => 'Administrador BackOffice', 'guard_name' => 'web']);
        }

        $this->adminUser = BackOfficeUser::factory()->create();
        $this->adminUser->assignRole('Administrador BackOffice');

        $this->moodleApiServiceMock = Mockery::mock(MoodleApiService::class);
        $this->app->instance(MoodleApiService::class, $this->moodleApiServiceMock);

        // Mock Maatwebsite\Excel specific to this test if needed, or rely on its actual implementation with fake files.
        // For simplicity, we'll let Excel facade attempt to parse the fake file.
    }

    public function test_mass_update_form_is_accessible()
    {
        $this->actingAs($this->adminUser);
        $response = $this->get(route('moodle.users.mass-update.form'));
        $response->assertStatus(200);
        $response->assertViewIs('moodle.users.mass-update');
    }

    public function test_mass_update_handles_valid_file_upload_successfully()
    {
        $this->actingAs($this->adminUser);

        Storage::fake('local');
        // Simulate an XLSX or CSV file content for update
        // Header: id,email,firstname (id is mandatory)
        $header = 'id,email,firstname';
        $row1 = '123,updated.user1@example.com,UpdatedNameOne';
        $row2 = '124,updated.user2@example.com,'; // Update email, firstname will be empty (should not be sent if logic filters empty)
        $content = implode("\n", [$header, $row1, $row2]);
        $file = UploadedFile::fake()->createWithContent('users_update.csv', $content); // Can be .xlsx if Excel::fake() is used more deeply

        $expectedUsersPayload = [
            ['id' => 123, 'email' => 'updated.user1@example.com', 'firstname' => 'UpdatedNameOne'],
            ['id' => 124, 'email' => 'updated.user2@example.com'], // firstname is empty, so it's not included based on controller logic
        ];

        // Filter out users with only 'id' for the expectation, based on controller logic
        $filteredExpectedUsersPayload = array_filter($expectedUsersPayload, function($user) {
            return count(array_filter($user, fn($value) => $value !== null && $value !== '')) > 1;
        });
        // Re-index if necessary, though Mockery::on might not care about numeric indices if it's a simple array comparison
        $filteredExpectedUsersPayload = array_values($filteredExpectedUsersPayload);


        $mockMoodleResponse = Mockery::mock(HttpClientResponse::class);
        $mockMoodleResponse->shouldReceive('successful')->andReturn(true);
        $mockMoodleResponse->shouldReceive('json')->andReturn(null); // core_user_update_users often returns null or empty on success

        $this->moodleApiServiceMock
            ->shouldReceive('updateUsers')
            ->once()
            ->with(Mockery::on(function ($argument) use ($filteredExpectedUsersPayload) {
                 // Basic check for content
                if (count($argument) !== count($filteredExpectedUsersPayload)) return false;
                foreach ($filteredExpectedUsersPayload as $idx => $expectedUser) {
                    if (!isset($argument[$idx]) || $argument[$idx]['id'] !== $expectedUser['id']) return false;
                    if (isset($expectedUser['email']) && (!isset($argument[$idx]['email']) || $argument[$idx]['email'] !== $expectedUser['email'])) return false;
                    if (isset($expectedUser['firstname']) && (!isset($argument[$idx]['firstname']) || $argument[$idx]['firstname'] !== $expectedUser['firstname'])) return false;
                }
                return true;
            }))
            ->andReturn($mockMoodleResponse);

        // Mock Excel facade if you want to control the collection returned from import
        // Excel::fake();
        // Excel::shouldReceive('toCollection')->andReturn(collect([ collect($expectedUsersPayload) ]));
        // However, for this test, letting it parse the fake CSV is fine.

        $response = $this->post(route('moodle.users.mass-update.upload'), [
            'user_file' => $file,
        ]);

        $response->assertRedirect(route('moodle.users.index'));
        $response->assertSessionHas('success', function ($value) {
            // Check if the success message contains expected counts
            return str_contains($value, 'Filas procesadas del archivo: 2') &&
                   str_contains($value, 'Usuarios enviados para actualizar: 2') && // Both had an ID
                   str_contains($value, 'Actualizados exitosamente (según Moodle): 2'); // Assumes all sent were updated
        });
        $response->assertSessionHasNoErrors();
    }

    public function test_mass_update_handles_file_with_missing_id()
    {
        $this->actingAs($this->adminUser);
        Storage::fake('local');
        $header = 'id,email,firstname';
        $row1 = ',missing.id@example.com,MissingIdName'; // Missing ID
        $content = implode("\n", [$header, $row1]);
        $file = UploadedFile::fake()->createWithContent('users_missing_id.csv', $content);

        // MoodleApiService should not be called if all rows have errors before API call
        $this->moodleApiServiceMock->shouldNotReceive('updateUsers');

        $response = $this->post(route('moodle.users.mass-update.upload'), [
            'user_file' => $file,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $response->assertSessionHas('upload_errors', function ($errors) {
            return is_array($errors) && count($errors) === 1 && str_contains($errors[0], "Falta el 'id' del usuario de Moodle");
        });
    }

    public function test_mass_update_handles_file_with_only_id_and_no_updatable_data()
    {
        $this->actingAs($this->adminUser);
        Storage::fake('local');
        $header = 'id,email,firstname'; // Assume these are the only possible columns in this simple test file
        $row1 = '123,,'; // ID present, but no other data to update
        $content = implode("\n", [$header, $row1]);
        $file = UploadedFile::fake()->createWithContent('users_only_id.csv', $content);

        $this->moodleApiServiceMock->shouldNotReceive('updateUsers');

        $response = $this->post(route('moodle.users.mass-update.upload'), [
            'user_file' => $file,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $response->assertSessionHas('upload_errors', function ($errors) {
            return is_array($errors) && count($errors) === 1 && str_contains($errors[0], "No hay datos para actualizar para el usuario ID 123");
        });
    }


    public function test_mass_update_handles_moodle_api_error_response()
    {
        $this->actingAs($this->adminUser);
        Storage::fake('local');
        $header = 'id,email';
        $row1 = '123,update.fail@example.com';
        $content = implode("\n", [$header, $row1]);
        $file = UploadedFile::fake()->createWithContent('users_update_fail.csv', $content);

        $mockMoodleResponse = Mockery::mock(HttpClientResponse::class);
        $mockMoodleResponse->shouldReceive('successful')->andReturn(false);
        $mockMoodleResponse->shouldReceive('json')->andReturn(['errorcode' => 'api_update_error', 'message' => 'Moodle API update failed']);
        $mockMoodleResponse->shouldReceive('body')->andReturn('Moodle API update failed');


        $this->moodleApiServiceMock
            ->shouldReceive('updateUsers')
            ->once()
            ->andReturn($mockMoodleResponse);

        $response = $this->post(route('moodle.users.mass-update.upload'), [
            'user_file' => $file,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $response->assertSessionHas('upload_errors', function ($errors) {
            return str_contains($errors[0], 'Error en API de Moodle al actualizar usuarios');
        });
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
