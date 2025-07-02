<?php

namespace Tests\Feature;

use App\Models\User as BackOfficeUser;
use App\Services\MoodleApiService;
use App\Exports\UsersByCountryExport; // Import the export class
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Response as HttpClientResponse;
use Maatwebsite\Excel\Facades\Excel; // Import Excel facade
use Mockery;
use Spatie\Permission\Models\Role as SpatieRole;
use Tests\TestCase;

class DashboardControllerTest extends TestCase
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

        Excel::fake(); // Fake the Excel facade
    }

    private function mockCommonMoodleApiResponses(array $users = [], array $courses = [])
    {
        // Mock for getUsers
        $mockUsersResponse = Mockery::mock(HttpClientResponse::class);
        $mockUsersResponse->shouldReceive('successful')->andReturn(true);
        $mockUsersResponse->shouldReceive('json')->andReturn(['users' => $users]); // Ensure it returns the 'users' key
        $this->moodleApiServiceMock->shouldReceive('getUsers')->andReturn($mockUsersResponse);

        // Mock for getCourses
        $mockCoursesResponse = Mockery::mock(HttpClientResponse::class);
        $mockCoursesResponse->shouldReceive('successful')->andReturn(true);
        $mockCoursesResponse->shouldReceive('json')->andReturn($courses);
        $this->moodleApiServiceMock->shouldReceive('getCourses')->andReturn($mockCoursesResponse);
    }

    private function getSampleMoodleUsers(): array
    {
        return [
            ['id' => 1, 'username' => 'user1', 'fullname' => 'User One', 'email' => 'user1@example.com', 'country' => 'CL', 'firstaccess' => time() - (5 * 24 * 60 * 60)], // 5 days ago
            ['id' => 2, 'username' => 'user2', 'fullname' => 'User Two', 'email' => 'user2@example.com', 'country' => 'AR', 'firstaccess' => time() - (10 * 24 * 60 * 60)], // 10 days ago
            ['id' => 3, 'username' => 'user3', 'fullname' => 'User Three', 'email' => 'user3@example.com', 'country' => 'CL', 'firstaccess' => time() - (40 * 24 * 60 * 60)], // 40 days ago
            ['id' => 4, 'username' => 'user4', 'fullname' => 'User Four', 'email' => 'user4@example.com', 'country' => 'PE', 'firstaccess' => time()], // today
            ['id' => 5, 'username' => 'user5', 'fullname' => 'User Five', 'email' => 'user5@example.com', 'country' => 'US', 'firstaccess' => time() - (2 * 24 * 60 * 60)],
            ['id' => 6, 'username' => 'user6', 'fullname' => 'User Six', 'email' => 'user6@example.com', 'country' => 'US', 'firstaccess' => time() - (60 * 24 * 60 * 60)],
            ['id' => 7, 'username' => 'user7', 'fullname' => 'User Seven', 'email' => 'user7@example.com', 'country' => 'BR', 'firstaccess' => time() - (20 * 24 * 60 * 60)],
            ['id' => 8, 'username' => 'user8', 'fullname' => 'User Eight', 'email' => 'user8@example.com', 'country' => 'UY', 'firstaccess' => time() - (15 * 24 * 60 * 60)],
            ['id' => 9, 'username' => 'user9', 'fullname' => 'User Nine', 'email' => 'user9@example.com', 'country' => 'CO', 'firstaccess' => time() - (25 * 24 * 60 * 60)],
            ['id' => 10, 'username' => 'user10', 'fullname' => 'User Ten', 'email' => 'user10@example.com', 'country' => 'MX', 'firstaccess' => time() - (5 * 24 * 60 * 60)],
        ];
    }

    private function getSampleMoodleCourses(): array
    {
        return [
            ['id' => 101, 'fullname' => 'Active Course 1', 'visible' => 1],
            ['id' => 102, 'fullname' => 'Inactive Course', 'visible' => 0],
            ['id' => 103, 'fullname' => 'Active Course 2', 'visible' => 1],
             ['id' => 1, 'fullname' => 'Site Home', 'format' => 'site', 'visible' => 1], // Site home to be filtered out
        ];
    }


    public function test_dashboard_index_is_accessible_and_loads_data_correctly()
    {
        $this->actingAs($this->adminUser);
        $sampleUsers = $this->getSampleMoodleUsers();
        $sampleCourses = $this->getSampleMoodleCourses();
        $this->mockCommonMoodleApiResponses($sampleUsers, $sampleCourses);

        $response = $this->get(route('dashboard'));

        $response->assertStatus(200);
        $response->assertViewIs('dashboard.index');

        // Check for active courses count (2 active courses + site home filtered out)
        $response->assertViewHas('activeCoursesCount', 2);

        // Check for recent users count (users 1, 4, 5, 7, 8, 9, 10 = 7 users within 30 days)
        $response->assertViewHas('recentUsersCount', 7);

        // Check structure of usersByCountryData (Top 5 + Others)
        // CL:2, US:2, AR:1, PE:1, BR:1, UY:1, CO:1, MX:1
        // Expected top: CL (2), US (2), AR (1), PE (1), BR (1). Others: UY(1)+CO(1)+MX(1) = 3
        $response->assertViewHas('usersByCountryData', function ($data) {
            if (count($data) !== 6) return false; // CL, US, AR, PE, BR, Otros
            $categories = array_column($data, 'category');
            $values = array_column($data, 'value');

            return in_array('CL', $categories) && $data[array_search('CL', $categories)]['value'] == 2 &&
                   in_array('US', $categories) && $data[array_search('US', $categories)]['value'] == 2 &&
                   in_array('AR', $categories) && $data[array_search('AR', $categories)]['value'] == 1 &&
                   in_array('PE', $categories) && $data[array_search('PE', $categories)]['value'] == 1 &&
                   in_array('BR', $categories) && $data[array_search('BR', $categories)]['value'] == 1 &&
                   in_array('Otros', $categories) && $data[array_search('Otros', $categories)]['value'] == 3;
        });

        $response->assertSeeText('Usuarios de Moodle por País'); // Chart title
        $response->assertSee('<div id="usersByCountryChart"', false); // Chart container
    }

    public function test_export_users_by_country_downloads_excel_file()
    {
        $this->actingAs($this->adminUser);
        $sampleUsers = $this->getSampleMoodleUsers();
        // getCourses is not called by exportUsersByCountry, so no need to mock it here explicitly unless controller changes
        $this->mockCommonMoodleApiResponses($sampleUsers, []);


        $response = $this->get(route('dashboard.export.users-by-country'));

        $response->assertStatus(200); // Should be a download response
        $response->assertHeader('content-disposition', 'attachment; filename=usuarios_por_pais.xlsx');

        // Assert that Excel::download was called with the correct export class and filename
        Excel::assertDownloaded('usuarios_por_pais.xlsx', function (UsersByCountryExport $export) use ($sampleUsers) {
            // You can further inspect the $export object if needed, e.g., the data it contains.
            // This requires the data processing logic in exportUsersByCountry to be correct.
            // For example, check if the collection passed to the export matches expected processed data.
            $collection = $export->collection();

            // Expected data after processing in controller and export class:
            // CL:2, US:2, AR:1, PE:1, BR:1, Otros:3
            $expectedCount = 6; // CL, US, AR, PE, BR, Otros
            if ($collection->count() !== $expectedCount) return false;

            $clData = $collection->firstWhere('País', 'CL');
            $usData = $collection->firstWhere('País', 'US');
            $otrosData = $collection->firstWhere('País', 'Otros');

            return $clData !== null && $clData['Cantidad de Usuarios'] == 2 &&
                   $usData !== null && $usData['Cantidad de Usuarios'] == 2 &&
                   $otrosData !== null && $otrosData['Cantidad de Usuarios'] == 3;
        });
    }

    public function test_export_users_by_country_handles_moodle_api_failure()
    {
        $this->actingAs($this->adminUser);

        // Mock MoodleApiService to simulate failure for getUsers
        $mockUsersResponse = Mockery::mock(HttpClientResponse::class);
        $mockUsersResponse->shouldReceive('successful')->andReturn(false);
        $mockUsersResponse->shouldReceive('status')->andReturn(500);
        $mockUsersResponse->shouldReceive('body')->andReturn('Moodle API Error');
        $this->moodleApiServiceMock->shouldReceive('getUsers')->andReturn($mockUsersResponse);
        // No need to mock getCourses if it's not called by the export method directly

        $response = $this->get(route('dashboard.export.users-by-country'));

        $response->assertRedirect(route('dashboard'));
        $response->assertSessionHas('error', 'No se pudieron obtener los datos para exportar.');
        Excel::assertNotDownloaded('usuarios_por_pais.xlsx');
    }


    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
