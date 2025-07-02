<?php

namespace Tests\Feature;

use App\Models\User as BackOfficeUser;
use App\Services\MoodleApiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http; // Import Http facade
use Mockery; // Import Mockery
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MoodleUserControllerTest extends TestCase
{
    use RefreshDatabase; // Already globally applied via Pest.php but good for clarity in PHPUnit style tests

    protected BackOfficeUser $adminUser;

    protected function setUp(): void
    {
        parent::setUp();

        // Create roles and admin user for BackOffice
        Role::create(['name' => 'Administrador BackOffice', 'guard_name' => 'web']);
        $this->adminUser = BackOfficeUser::factory()->create();
        $this->adminUser->assignRole('Administrador BackOffice');

        // Mock MoodleApiService to avoid real API calls during tests
        // It's often better to mock at the point of the test if different responses are needed,
        // but a general mock can be set up here if many tests expect similar non-error responses.
    }

    public function test_moodle_users_index_page_is_accessible_by_admin()
    {
        $this->actingAs($this->adminUser);

        // Mock the Moodle API Service response for getUsers
        $mockMoodleResponse = Mockery::mock(Response::class);
        $mockMoodleResponse->shouldReceive('successful')->andReturn(true);
        $mockMoodleResponse->shouldReceive('json')->andReturn(['users' => [], 'warnings' => []]); // Empty users for simplicity

        $moodleApiServiceMock = Mockery::mock(MoodleApiService::class);
        $moodleApiServiceMock->shouldReceive('getUsers')->andReturn($mockMoodleResponse);
        $this->app->instance(MoodleApiService::class, $moodleApiServiceMock);


        $response = $this->get(route('moodle.users.index'));

        $response->assertStatus(200);
        $response->assertViewIs('moodle.users.index');
        $response->assertViewHas('users');
    }

    public function test_moodle_users_index_page_shows_users_from_api()
    {
        $this->actingAs($this->adminUser);

        $fakeMoodleUsers = [
            ['id' => 1, 'username' => 'muser1', 'fullname' => 'Moodle User One', 'email' => 'muser1@example.com'],
            ['id' => 2, 'username' => 'muser2', 'fullname' => 'Moodle User Two', 'email' => 'muser2@example.com'],
        ];

        // Mock the Moodle API Service response
        $mockMoodleResponse = Mockery::mock(Response::class);
        $mockMoodleResponse->shouldReceive('successful')->andReturn(true);
        $mockMoodleResponse->shouldReceive('json')->andReturn(['users' => $fakeMoodleUsers, 'warnings' => []]);

        $moodleApiServiceMock = Mockery::mock(MoodleApiService::class);
        $moodleApiServiceMock->shouldReceive('getUsers')->andReturn($mockMoodleResponse);
        $this->app->instance(MoodleApiService::class, $moodleApiServiceMock);

        $response = $this->get(route('moodle.users.index'));

        $response->assertStatus(200);
        $response->assertSeeText('Moodle User One');
        $response->assertSeeText('muser1@example.com');
        $response->assertSeeText('Moodle User Two');
    }

    public function test_moodle_users_index_search_filters_users()
    {
        $this->actingAs($this->adminUser);
        $searchTerm = 'specificuser';

        $expectedCriteria = [['key' => 'email', 'value' => '%' . $searchTerm . '%']];

        // Mock the Moodle API Service to ensure it's called with correct criteria
        $mockMoodleResponse = Mockery::mock(Response::class);
        $mockMoodleResponse->shouldReceive('successful')->andReturn(true);
        $mockMoodleResponse->shouldReceive('json')->andReturn([
            'users' => [['id' => 3, 'username' => 'specificuser', 'fullname' => 'Specific User', 'email' => 'specificuser@example.com']],
            'warnings' => []
        ]);

        $moodleApiServiceMock = Mockery::mock(MoodleApiService::class);
        $moodleApiServiceMock->shouldReceive('getUsers')
            ->with($expectedCriteria) // Assert that getUsers is called with the search criteria
            ->andReturn($mockMoodleResponse);
        $this->app->instance(MoodleApiService::class, $moodleApiServiceMock);


        $response = $this->get(route('moodle.users.index', ['search' => $searchTerm]));

        $response->assertStatus(200);
        $response->assertSeeText('Specific User');
        $response->assertViewHas('searchTerm', $searchTerm);
    }

    public function test_moodle_user_show_page_is_accessible_and_displays_user()
    {
        $this->actingAs($this->adminUser);
        $moodleUserId = 123;
        $fakeMoodleUser = ['id' => $moodleUserId, 'username' => 'detailuser', 'fullname' => 'Detail User', 'email' => 'detail@example.com'];

        // Mock the Moodle API Service response
        $mockMoodleResponse = Mockery::mock(Response::class);
        $mockMoodleResponse->shouldReceive('successful')->andReturn(true);
        $mockMoodleResponse->shouldReceive('json')->andReturn(['users' => [$fakeMoodleUser]]); // getUsers returns an array

        $moodleApiServiceMock = Mockery::mock(MoodleApiService::class);
        // Expect getUsers to be called with criteria for the specific user ID
        $moodleApiServiceMock->shouldReceive('getUsers')
            ->with([['key' => 'id', 'value' => $moodleUserId]])
            ->andReturn($mockMoodleResponse);
        $this->app->instance(MoodleApiService::class, $moodleApiServiceMock);

        $response = $this->get(route('moodle.users.show', $moodleUserId));

        $response->assertStatus(200);
        $response->assertViewIs('moodle.users.show');
        $response->assertViewHas('user', $fakeMoodleUser);
        $response->assertSeeText('Detail User');
        $response->assertSeeText('detail@example.com');
    }

    public function test_moodle_api_service_handles_connection_error_gracefully_in_controller()
    {
        $this->actingAs($this->adminUser);

        // Mock MoodleApiService to throw a RequestException
        $moodleApiServiceMock = Mockery::mock(MoodleApiService::class);
        $moodleApiServiceMock->shouldReceive('getUsers')
            ->andThrow(new \Illuminate\Http\Client\RequestException(new \Illuminate\Http\Client\Request(new \GuzzleHttp\Psr7\Request('GET', 'test')), new \GuzzleHttp\Psr7\Response())); // Provide valid Request and Response objects
        $this->app->instance(MoodleApiService::class, $moodleApiServiceMock);

        $response = $this->get(route('moodle.users.index'));

        $response->assertStatus(200); // The page itself loads
        $response->assertViewIs('moodle.users.index');
        $response->assertSeeText('Could not connect to Moodle API'); // Check for flash message
        // Ensure users variable is an empty paginator
        $response->assertViewHas('users', function ($users) {
            return $users instanceof \Illuminate\Pagination\LengthAwarePaginator && $users->isEmpty();
        });
    }

    // It would also be beneficial to have unit tests for MoodleApiService itself,
    // mocking the Http client to ensure it formats requests correctly and parses responses.
}
