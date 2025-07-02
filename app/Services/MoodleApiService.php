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
}
