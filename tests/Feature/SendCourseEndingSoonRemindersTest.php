<?php

namespace Tests\Feature;

use App\Services\MoodleApiService;
use App\Notifications\CourseEndingSoonNotification;
use Illuminate\Foundation\Testing\RefreshDatabase; // Not strictly needed if not saving to local DB
use Illuminate\Http\Client\Response as HttpClientResponse;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Log;
use Mockery;
use Carbon\Carbon;
use Tests\TestCase;

class SendCourseEndingSoonRemindersTest extends TestCase
{
    // use RefreshDatabase; // Only if the command itself writes to the DB, which this one doesn't directly

    protected Mockery\MockInterface $moodleApiServiceMock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->moodleApiServiceMock = Mockery::mock(MoodleApiService::class);
        $this->app->instance(MoodleApiService::class, $this->moodleApiServiceMock);

        Notification::fake(); // Fake notifications to assert they are sent
    }

    public function test_reminder_sent_for_course_ending_in_7_days()
    {
        $daysToTest = 7;
        $targetEndDate = Carbon::now()->addDays($daysToTest)->startOfDay(); // Command logic might compare day part

        $coursesFromApi = [
            [ // Course ending on target date
                'id' => 101,
                'fullname' => 'Curso Finalizando Pronto',
                'enddate' => $targetEndDate->timestamp,
                'visible' => 1,
                'format' => 'topics',
            ],
            [ // Course ending much later
                'id' => 102,
                'fullname' => 'Curso Lejano',
                'enddate' => Carbon::now()->addDays(30)->timestamp,
                'visible' => 1,
                'format' => 'topics',
            ],
            [ // Course already ended
                'id' => 103,
                'fullname' => 'Curso Terminado',
                'enddate' => Carbon::now()->subDays(5)->timestamp,
                'visible' => 1,
                'format' => 'topics',
            ],
             [ // Course ending on target date but hidden
                'id' => 104,
                'fullname' => 'Curso Oculto Finalizando Pronto',
                'enddate' => $targetEndDate->timestamp,
                'visible' => 0,
                'format' => 'topics',
            ],
        ];

        $enrolledUsersForCourse101 = [
            ['id' => 201, 'fullname' => 'Estudiante A', 'email' => 'student.a@example.com'],
            ['id' => 202, 'fullname' => 'Estudiante B', 'email' => 'student.b@example.com'],
            ['id' => 203, 'fullname' => 'Estudiante Sin Email', 'email' => ''], // No email
        ];

        $mockCoursesResponse = Mockery::mock(HttpClientResponse::class);
        $mockCoursesResponse->shouldReceive('successful')->once()->andReturn(true);
        $mockCoursesResponse->shouldReceive('json')->once()->andReturn($coursesFromApi);
        $this->moodleApiServiceMock->shouldReceive('getCourses')->once()->andReturn($mockCoursesResponse);

        $mockEnrolledUsersResponse = Mockery::mock(HttpClientResponse::class);
        $mockEnrolledUsersResponse->shouldReceive('successful')->once()->andReturn(true);
        $mockEnrolledUsersResponse->shouldReceive('json')->once()->andReturn($enrolledUsersForCourse101);
        $this->moodleApiServiceMock
            ->shouldReceive('getEnrolledUsersInCourse')
            ->once()
            ->with(101, [['name' => 'userfields', 'value' => 'id,email,fullname']])
            ->andReturn($mockEnrolledUsersResponse);

        // Execute the command with the 'days' argument
        $this->artisan("moodle:send-course-ending-reminders {$daysToTest}")
            ->expectsOutput("Buscando cursos que finalizan en aproximadamente {$daysToTest} días...")
            ->expectsOutput("Curso 'Curso Finalizando Pronto' (ID: 101) finaliza el " . $targetEndDate->format('Y-m-d'))
            ->expectsOutput("  -> Enviando recordatorio a Estudiante A (student.a@example.com)")
            ->expectsOutput("  -> Enviando recordatorio a Estudiante B (student.b@example.com)")
            ->expectsOutput("  -> Usuario Estudiante Sin Email (ID: 203) no tiene email, no se puede notificar.")
            ->expectsOutput("Proceso completado. 2 recordatorios enviados.")
            ->assertExitCode(0); // Illuminate\Console\Command::SUCCESS

        Notification::assertSentTo(
            new \Illuminate\Notifications\AnonymousNotifiable, // For Notification::route()
            CourseEndingSoonNotification::class,
            function ($notification, $channels, $notifiable) {
                return $notifiable->routes['mail'] === 'student.a@example.com' &&
                       $notification->courseName === 'Curso Finalizando Pronto';
            }
        );
        Notification::assertSentTo(
            new \Illuminate\Notifications\AnonymousNotifiable,
            CourseEndingSoonNotification::class,
            function ($notification, $channels, $notifiable) {
                return $notifiable->routes['mail'] === 'student.b@example.com' &&
                       $notification->courseName === 'Curso Finalizando Pronto';
            }
        );

        // Ensure notification was not sent for the user without email
        Notification::assertNotSentTo(
             new \Illuminate\Notifications\AnonymousNotifiable,
             CourseEndingSoonNotification::class,
             function ($notification, $channels, $notifiable) {
                // This check is a bit tricky with AnonymousNotifiable if we don't know who it was.
                // It's easier to check that only 2 notifications were sent in total.
                return true; // Placeholder, main check is count below
             }
        );
        Notification::assertCount(2); // Total notifications sent
    }

    public function test_no_reminders_sent_if_no_courses_match_criteria()
    {
        $daysToTest = 3; // Different day to not match
        $targetEndDate = Carbon::now()->addDays($daysToTest)->startOfDay();

        $coursesFromApi = [
            [
                'id' => 101,
                'fullname' => 'Curso No Coincidente',
                'enddate' => Carbon::now()->addDays(10)->timestamp, // Ends in 10 days
                'visible' => 1,
                'format' => 'topics',
            ]
        ];

        $mockCoursesResponse = Mockery::mock(HttpClientResponse::class);
        $mockCoursesResponse->shouldReceive('successful')->once()->andReturn(true);
        $mockCoursesResponse->shouldReceive('json')->once()->andReturn($coursesFromApi);
        $this->moodleApiServiceMock->shouldReceive('getCourses')->once()->andReturn($mockCoursesResponse);

        $this->moodleApiServiceMock->shouldNotReceive('getEnrolledUsersInCourse'); // Should not be called

        $this->artisan("moodle:send-course-ending-reminders {$daysToTest}")
            ->expectsOutput("Proceso completado. 0 recordatorios enviados.")
            ->assertExitCode(0);

        Notification::assertNothingSent();
    }

    public function test_command_handles_moodle_api_failure_for_courses()
    {
        $mockCoursesResponse = Mockery::mock(HttpClientResponse::class);
        $mockCoursesResponse->shouldReceive('successful')->once()->andReturn(false);
        $mockCoursesResponse->shouldReceive('body')->once()->andReturn('API Error Body');
        $this->moodleApiServiceMock->shouldReceive('getCourses')->once()->andReturn($mockCoursesResponse);

        Log::shouldReceive('error')->once()->with('SendCourseEndingSoonReminders: Failed to fetch courses.', Mockery::any());

        $this->artisan('moodle:send-course-ending-reminders 7')
            ->expectsOutput('No se pudieron obtener los cursos de Moodle.')
            ->assertExitCode(1); // Illuminate\Console\Command::FAILURE

        Notification::assertNothingSent();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
