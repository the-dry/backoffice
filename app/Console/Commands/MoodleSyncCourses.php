<?php

namespace App\Console\Commands;

use App\Models\MoodleCourseLocal;
use App\Services\MoodleApiService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class MoodleSyncCourses extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'moodle:sync-courses';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Synchronizes courses from Moodle to the local database.';

    protected MoodleApiService $moodleApiService;

    /**
     * Create a new command instance.
     *
     * @return void
     */
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
        $this->info('Starting Moodle courses synchronization...');

        try {
            $response = $this->moodleApiService->getCourses();

            if (!$response->successful()) {
                $this->error('Failed to fetch courses from Moodle API.');
                Log::error('MoodleSyncCourses: Failed to fetch courses.', ['response_body' => $response->body()]);
                return Command::FAILURE;
            }

            $moodleCourses = $response->json();
            if (!is_array($moodleCourses)) {
                $this->error('Invalid response format from Moodle API (expected array of courses).');
                Log::error('MoodleSyncCourses: Invalid courses format.', ['response' => $moodleCourses]);
                return Command::FAILURE;
            }

            if (empty($moodleCourses)) {
                $this->info('No courses found in Moodle to synchronize.');
                return Command::SUCCESS;
            }

            $syncedCount = 0;
            $skippedCount = 0;

            foreach ($moodleCourses as $course) {
                if (!isset($course['id']) || !isset($course['fullname'])) {
                    $this->warn("Skipping course due to missing ID or fullname: " . json_encode($course));
                    $skippedCount++;
                    continue;
                }

                // Filter out "Site home" which usually has id = 1 and specific format
                if ($course['id'] == 1 && ($course['format'] ?? '') === 'site') {
                    $this->line("Skipping Site home (ID: 1).");
                    $skippedCount++;
                    continue;
                }

                MoodleCourseLocal::updateOrCreate(
                    ['moodle_id' => $course['id']],
                    [
                        'shortname' => $course['shortname'] ?? null,
                        'fullname' => $course['fullname'],
                        'summary' => $course['summary'] ?? null,
                        'format' => $course['format'] ?? null,
                        'visible' => isset($course['visible']) ? (bool)$course['visible'] : true,
                        'startdate' => isset($course['startdate']) && $course['startdate'] > 0 ? date('Y-m-d H:i:s', $course['startdate']) : null,
                        'enddate' => isset($course['enddate']) && $course['enddate'] > 0 ? date('Y-m-d H:i:s', $course['enddate']) : null,
                        'raw_data' => $course, // Store the whole original payload
                    ]
                );
                $syncedCount++;
                $this->line("Synchronized: {$course['fullname']} (ID: {$course['id']})");
            }

            $this->info("Synchronization complete. {$syncedCount} courses processed, {$skippedCount} courses skipped.");
            return Command::SUCCESS;

        } catch (\Illuminate\Http\Client\RequestException $e) {
            $this->error('Moodle API connection error: ' . $e->getMessage());
            Log::error('MoodleSyncCourses: API connection error.', ['exception' => $e]);
            return Command::FAILURE;
        } catch (\Exception $e) {
            $this->error('An unexpected error occurred: ' . $e->getMessage());
            Log::error('MoodleSyncCourses: Unexpected error.', ['exception' => $e]);
            return Command::FAILURE;
        }
    }
}
