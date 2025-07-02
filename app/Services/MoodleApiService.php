<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\Response;
use Illuminate\Http\Client\RequestException;

class MoodleApiService
{
    protected string $baseUrl;
    protected string $token;
    protected string $format;

    public function __construct()
    {
        $this->baseUrl = config('moodle.base_url');
        $this->token = config('moodle.token');
        $this->format = config('moodle.format', 'json');

        if (empty($this->baseUrl) || $this->baseUrl === 'YOUR_MOODLE_SITE_URL' || empty($this->token) || $this->token === 'YOUR_MOODLE_WS_TOKEN') {
            // Consider throwing an exception or logging a warning if configuration is missing
            // For now, we'll allow it to proceed, but real calls would fail.
        }
    }

    /**
     * Make a request to the Moodle API.
     *
     * @param string $wsFunction The Moodle webservice function name.
     * @param array $params Additional parameters for the webservice function.
     * @return Response
     * @throws RequestException
     */
    protected function makeRequest(string $wsFunction, array $params = []): Response
    {
        $defaultParams = [
            'wstoken' => $this->token,
            'wsfunction' => $wsFunction,
            'moodlewsrestformat' => $this->format,
        ];

        $response = Http::asForm()->post($this->baseUrl, array_merge($defaultParams, $params));

        // Check for Moodle API errors if possible (often returns 200 OK with error payload)
        // Moodle errors are usually in the response body, e.g., {"exception":"...", "errorcode":"..."}
        if ($response->successful()) {
            $data = $response->json();
            if (is_array($data) && isset($data['exception'])) {
                // This is a Moodle specific error, not an HTTP error
                // We might want to throw a custom exception here
                // For now, we'll let it pass and the caller can inspect the JSON
            }
        }

        // $response->throw(); // This would throw for HTTP 4xx/5xx errors

        return $response;
    }

    /**
     * Get Moodle site information.
     * Corresponds to Moodle's `core_webservice_get_site_info`.
     *
     * @return Response
     * @throws RequestException
     */
    public function getSiteInfo(): Response
    {
        return $this->makeRequest('core_webservice_get_site_info');
    }

    /**
     * Get users from Moodle.
     * Corresponds to Moodle's `core_user_get_users`.
     *
     * @param array $criteria Criteria to search users. Example: [['key' => 'email', 'value' => '%@example.com%']]
     *                         Refer to Moodle documentation for `core_user_get_users` for criteria options.
     *                         Common keys: 'id', 'lastname', 'firstname', 'idnumber', 'username', 'email', 'city', 'country'.
     * @return Response
     * @throws RequestException
     */
    public function getUsers(array $criteria = []): Response
    {
        // The core_user_get_users function expects criteria in a specific nested structure.
        // $params = [];
        // foreach ($criteria as $index => $criterion) {
        //     $params["criteria[{$index}][key]"] = $criterion['key'];
        //     $params["criteria[{$index}][value]"] = $criterion['value'];
        // }
        // The Http client might handle nested arrays directly, let's try that first.
        // If not, the above commented structure or Http::asForm()->post($url, $flattenedParams) would be needed.

        return $this->makeRequest('core_user_get_users', ['criteria' => $criteria]);
    }

    // Example of a function that might take parameters
    // public function getUsersByField(string $field, array $values): Response
    // {
    //     $params = [
    //         'field' => $field, // e.g., 'id', 'email'
    //         'values' => $values, // array of values
    //     ];
    //     // core_user_get_users_by_field is deprecated, use core_user_get_users with criteria instead.
    //     // Example criteria for getUsersByField('email', ['user1@example.com', 'user2@example.com']):
    //     // $criteria = [];
    //     // foreach ($values as $value) {
    //     //    $criteria[] = ['key' => $field, 'value' => $value];
    //     // }
    //     // return $this->getUsers($criteria);
    //
    //     // For single field matching multiple OR values, Moodle might require separate calls or specific criteria formatting.
    //     // The typical use of core_user_get_users is more like SQL AND conditions for multiple criteria items.
    //     // If you need OR for a single field (e.g. email = 'a' OR email = 'b'), it's often simpler to make multiple calls or fetch more data and filter locally.
    //     // However, for searching (LIKE), one criterion is fine: [['key' => 'email', 'value' => '%search%']]
    // }

    /**
     * Create users in Moodle.
     * Corresponds to Moodle's `core_user_create_users`.
     *
     * @param array $usersData An array of user data arrays. Each user data array should conform to
     *                         the structure expected by `core_user_create_users`.
     *                         Required fields typically include: 'username', 'password', 'firstname', 'lastname', 'email'.
     *                         Optional fields: 'idnumber', 'city', 'country', 'preferences', 'customfields', etc.
     *                         Example:
     *                         [
     *                             [
     *                                 'username' => 'newuser1',
     *                                 'password' => 'Password123!',
     *                                 'firstname' => 'New',
     *                                 'lastname' => 'UserOne',
     *                                 'email' => 'newuser1@example.com',
     *                                 // 'auth' => 'manual', // or 'ldap', 'shibboleth' etc. if needed
     *                                 // 'preferences' => [['type' => 'auth_forcepasswordchange', 'value' => 1]]
     *                             ],
     *                             // ... more users
     *                         ]
     * @return Response
     * @throws RequestException
     */
    public function createUsers(array $usersData): Response
    {
        // The Moodle API expects parameters for arrays (like users) to be indexed.
        // e.g., users[0][username], users[0][password], users[1][username] etc.
        // Laravel's HTTP client should handle this correctly if we pass the array directly.
        return $this->makeRequest('core_user_create_users', ['users' => $usersData]);
    }

    /**
     * Update users in Moodle.
     * Corresponds to Moodle's `core_user_update_users`.
     *
     * @param array $usersData An array of user data arrays. Each user data array must include 'id' (Moodle user ID)
     *                         and other fields to be updated.
     *                         Example:
     *                         [
     *                             [
     *                                 'id' => 123,
     *                                 'email' => 'updateduser1@example.com',
     *                                 'firstname' => 'UpdatedFirst',
     *                                 // 'preferences' => [['type' => 'email_bounce', 'value' => 1]],
     *                                 // 'customfields' => [['type' => 'profilefield_gender', 'value' => 'Female']]
     *                             ],
     *                             // ... more users to update
     *                         ]
     * @return Response
     * @throws RequestException
     */
    public function updateUsers(array $usersData): Response
    {
        // Similar to createUsers, Moodle API expects indexed array parameters.
        return $this->makeRequest('core_user_update_users', ['users' => $usersData]);
    }

    /**
     * Get courses from Moodle.
     * Corresponds to Moodle's `core_course_get_courses`.
     * Optionally filter by course IDs.
     *
     * @param array $courseIds Optional array of Moodle course IDs to filter by.
     * @return Response
     * @throws RequestException
     */
    public function getCourses(array $courseIds = []): Response
    {
        $params = [];
        if (!empty($courseIds)) {
            // The API expects options[ids][0], options[ids][1]...
            // $params = ['options' => ['ids' => $courseIds]]; // This might work directly with Laravel HTTP client
            // Or, build it manually if needed:
            foreach ($courseIds as $index => $id) {
                $params["options[ids][{$index}]"] = $id;
            }
        }
        return $this->makeRequest('core_course_get_courses', $params);
    }

    /**
     * Enrol users in a Moodle course.
     * Corresponds to Moodle's `enrol_manual_enrol_users`.
     *
     * @param array $enrolments Array of enrolment data. Each item should be an array with:
     *                          'roleid' => int (e.g., 5 for student),
     *                          'userid' => int (Moodle user ID),
     *                          'courseid' => int (Moodle course ID),
     *                          Optional: 'timestart', 'timeend', 'suspend'
     *                         Example:
     *                         [
     *                             ['roleid' => 5, 'userid' => 123, 'courseid' => 1],
     *                             ['roleid' => 5, 'userid' => 124, 'courseid' => 1, 'timestart' => time() + 3600],
     *                         ]
     * @return Response
     * @throws RequestException
     */
    public function enrolUsers(array $enrolments): Response
    {
        // API expects enrolments[0][roleid], enrolments[0][userid], etc.
        return $this->makeRequest('enrol_manual_enrol_users', ['enrolments' => $enrolments]);
    }

    /**
     * Get the completion status of a user in a specific course.
     * Corresponds to Moodle's `core_completion_get_course_completion_status`.
     *
     * @param int $courseId The Moodle course ID.
     * @param int $userId The Moodle user ID.
     * @return Response
     * @throws RequestException
     */
    public function getCourseCompletionStatus(int $courseId, int $userId): Response
    {
        return $this->makeRequest('core_completion_get_course_completion_status', [
            'courseid' => $courseId,
            'userid' => $userId,
        ]);
    }

    /**
     * Get grade items (grades) for a user in a specific course.
     * Corresponds to Moodle's `gradereport_user_get_grade_items`.
     *
     * @param int $courseId The Moodle course ID.
     * @param int $userId The Moodle user ID.
     * @param int|null $groupId Optional group ID.
     * @return Response
     * @throws RequestException
     */
    public function getUserGradesInCourse(int $courseId, int $userId, ?int $groupId = null): Response
    {
        $params = [
            'courseid' => $courseId,
            'userid' => $userId,
        ];
        if ($groupId !== null) {
            $params['groupid'] = $groupId;
        }
        return $this->makeRequest('gradereport_user_get_grade_items', $params);
    }

    /**
     * Get users enrolled in a specific course.
     * Corresponds to Moodle's `core_enrol_get_enrolled_users`.
     *
     * @param int $courseId The Moodle course ID.
     * @param array $options Optional array of options (e.g., 'withcapability', 'groupid', 'onlyactive', 'userfields').
     *                       Example: [['name' => 'userfields', 'value' => 'id,fullname,email']]
     * @return Response
     * @throws RequestException
     */
    public function getEnrolledUsersInCourse(int $courseId, array $options = []): Response
    {
        $params = ['courseid' => $courseId];
        if (!empty($options)) {
            // Moodle expects options as options[0][name], options[0][value]
            // For simplicity in this helper, we might directly map known options
            // or require the caller to format the options array correctly.
            // Example: if (isset($options['userfields'])) $params['options[0][name]'] = 'userfields'; $params['options[0][value]'] = implode(',', $options['userfields']);
            // For now, let's assume options are passed pre-formatted if complex.
            // A simpler way if options are direct key-values for the API:
            // $params['options'] = $options; // This might work if Http client handles array nesting for 'options'

            // Let's use the indexed format for options as it's more reliable for Moodle
            $moodleOptions = [];
            foreach ($options as $key => $option) {
                 if (is_array($option) && isset($option['name']) && isset($option['value'])) {
                    $moodleOptions[] = $option; // Assumes options are already in Moodle's expected [{name:x, value:y}] format
                }
            }
            if (!empty($moodleOptions)) {
                $params['options'] = $moodleOptions;
            }
        }
        return $this->makeRequest('core_enrol_get_enrolled_users', $params);
    }

    /**
     * Get certificate templates available in courses.
     * Assumes use of `mod_customcert` plugin.
     * Corresponds to Moodle's `mod_customcert_get_customcerts_by_courses`.
     *
     * @param array $courseIds Array of Moodle course IDs.
     * @return Response
     * @throws RequestException
     */
    public function getCertificateTemplatesByCourses(array $courseIds): Response
    {
        // API expects courseids[0]=id1, courseids[1]=id2 ...
        $params = [];
        foreach ($courseIds as $index => $id) {
            $params["courseids[{$index}]"] = $id;
        }
        return $this->makeRequest('mod_customcert_get_customcerts_by_courses', $params);
    }

    /**
     * Issue a certificate to a user.
     * Assumes use of `mod_customcert` plugin.
     * Corresponds to Moodle's `mod_customcert_issue_certificate`.
     *
     * @param int $certificateId The ID of the custom certificate template.
     * @param int $userId The Moodle user ID.
     * @return Response
     * @throws RequestException
     */
    public function issueCertificate(int $certificateId, int $userId): Response
    {
        return $this->makeRequest('mod_customcert_issue_certificate', [
            'customcertid' => $certificateId, // Parameter name might be 'customcertid' or 'certificateid'
            'userid' => $userId,
        ]);
    }

    /**
     * Get issued certificates for a user in a course or for a specific certificate template.
     * This function is HYPOTHETICAL as `mod_customcert` might not have a direct WS for this.
     * Often, issued certificates are linked to the user's profile or course participation.
     * We might need to list issued certificates by other means, or this function might not be available.
     * A common way is to check `mod_customcert_get_user_certificates` if available,
     * or infer from `mod_customcert_get_customcert_issues`.
     *
     * For now, let's assume a function like `mod_customcert_get_issued_certificates` exists or can be created.
     * Parameters would likely be courseid and/or userid.
     *
     * @param array $criteria Example: ['courseid' => 1, 'userid' => 2] or ['customcertid' => 3]
     * @return Response
     * @throws RequestException
     */
    public function getIssuedCertificates(array $criteria): Response
    {
        // This is a placeholder. The actual function and parameters will depend on Moodle's capabilities.
        // Example call if a function `mod_customcert_get_user_certificates` existed:
        // if (isset($criteria['userid'])) {
        //     return $this->makeRequest('mod_customcert_get_user_certificates', [
        //         'userid' => $criteria['userid'],
        //         'courseid' => $criteria['courseid'] ?? 0, // Optional course ID
        //     ]);
        // }
        // Or for all issues of a certificate template:
        // if (isset($criteria['customcertid'])) {
        //    return $this->makeRequest('mod_customcert_get_customcert_issues', ['customcertid' => $criteria['customcertid']]);
        // }

        // For now, returning a dummy response structure indicating function needs real implementation.
        Log::warning('MoodleApiService::getIssuedCertificates is a placeholder and needs actual Moodle API function.');
        $dummyResponse = new \Illuminate\Http\Client\Response(new \GuzzleHttp\Psr7\Response(200, [], json_encode([
            'issuedcertificates' => [],
            'warnings' => [['item' => 'getIssuedCertificates', 'warningcode' => 'notimplemented', 'message' => 'This Moodle API function is a placeholder.']]
        ])));
        return $dummyResponse; // This will allow controller logic to be written.
    }
}
