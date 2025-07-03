<?php

namespace App\Console\Commands;

use App\Services\MoodleApiService;
use App\Notifications\CourseEndingSoonNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Carbon\Carbon;

class SendCourseEndingSoonReminders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'moodle:send-course-ending-reminders {days=7 : Number of days before end date to send reminder}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Scans Moodle courses and sends reminders to enrolled users if a course is ending soon.';

    protected MoodleApiService $moodleApiService;

    public function __construct(MoodleApiService $moodleApiService)
    {
        parent::__construct();
        $this->moodleApiService = $moodleApiService;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $daysBeforeEnd = (int) $this->argument('days');
        $this->info("Buscando cursos que finalizan en aproximadamente {$daysBeforeEnd} días...");

        try {
            $coursesResponse = $this->moodleApiService->getCourses();
            if (!$coursesResponse->successful()) {
                $this->error('No se pudieron obtener los cursos de Moodle.');
                Log::error('SendCourseEndingSoonReminders: Failed to fetch courses.', ['response' => $coursesResponse->body()]);
                return Command::FAILURE;
            }

            $allCourses = $coursesResponse->json();
            if (!is_array($allCourses)) {
                $this->error('Respuesta inesperada de la API de Moodle al obtener cursos.');
                return Command::FAILURE;
            }

            $targetEndDate = Carbon::now()->addDays($daysBeforeEnd);
            $remindersSentCount = 0;

            foreach ($allCourses as $course) {
                if (!isset($course['id']) || !isset($course['enddate']) || $course['enddate'] == 0) {
                    continue; // Skip courses without ID or end date
                }

                // Filter out site home or other non-relevant courses
                if ($course['id'] == 1 && ($course['format'] ?? '') === 'site') {
                    continue;
                }
                if (isset($course['visible']) && ($course['visible'] === 0 || $course['visible'] === false)) {
                    continue; // Skip hidden courses
                }


                $courseEndDate = Carbon::createFromTimestamp($course['enddate']);

                // Check if the course ends on the target date (ignoring time part for daily check)
                if ($courseEndDate->isSameDay($targetEndDate)) {
                    $this->line("Curso '{$course['fullname']}' (ID: {$course['id']}) finaliza el " . $courseEndDate->format('Y-m-d'));

                    // Get enrolled users for this course
                    $enrolledUsersResponse = $this->moodleApiService->getEnrolledUsersInCourse($course['id'], [['name' => 'userfields', 'value' => 'id,email,fullname']]);
                    if ($enrolledUsersResponse->successful()) {
                        $enrolledUsers = $enrolledUsersResponse->json();
                        if (is_array($enrolledUsers)) {
                            foreach ($enrolledUsers as $moodleUser) {
                                if (isset($moodleUser['email']) && !empty($moodleUser['email'])) {
                                    $this->line("  -> Enviando recordatorio a {$moodleUser['fullname']} ({$moodleUser['email']})");

                                    // Pass course ID to notification for action URL context
                                    // This is a bit of a hack as Notifiable is not the Moodle user.
                                    // The notification's toMail() will need to be aware of this.
                                    $notifiableUser = new \stdClass(); // Create a generic object
                                    $notifiableUser->email = $moodleUser['email'];
                                    $notifiableUser->course_id_for_notification = $course['id']; // For action URL in MailMessage

                                    // Using Notification facade to route to an arbitrary email address
                                    Notification::route('mail', $moodleUser['email'])
                                        ->notify(new CourseEndingSoonNotification(
                                            $course['fullname'],
                                            $courseEndDate->format('d/m/Y'),
                                            $moodleUser['email'] // This email is for reference inside notification if needed
                                        ));
                                    $remindersSentCount++;
                                } else {
                                     $this->warn("  -> Usuario {$moodleUser['fullname']} (ID: {$moodleUser['id']}) no tiene email, no se puede notificar.");
                                }
                            }
                        }
                    } else {
                        $this->warn("  -> No se pudieron obtener usuarios inscritos para el curso ID {$course['id']}.");
                        Log::warning('SendCourseEndingSoonReminders: Failed to fetch enrolled users for course.', ['course_id' => $course['id'], 'response' => $enrolledUsersResponse->body()]);
                    }
                }
            }

            $this->info("Proceso completado. {$remindersSentCount} recordatorios enviados.");
            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error('Ocurrió un error: ' . $e->getMessage());
            Log::error('SendCourseEndingSoonReminders: Unexpected error.', ['exception' => $e]);
            return Command::FAILURE;
        }
    }
}
