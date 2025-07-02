<?php

namespace App\Http\Controllers;

use App\Services\MoodleApiService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class MoodleUserController extends Controller
{
    protected MoodleApiService $moodleApiService;

    public function __construct(MoodleApiService $moodleApiService)
    {
        $this->moodleApiService = $moodleApiService;
        // Consider adding middleware for permissions here later, e.g., $this->middleware('can:view moodle users');
    }

    public function index(Request $request)
    {
        $criteria = [];
        $searchTerm = $request->input('search', '');

        if (!empty($searchTerm)) {
            // Simple search: Moodle's core_user_get_users typically uses 'anyfield' for a general search
            // or you can specify fields like 'email', 'username', 'firstname', 'lastname'
            // For a general search across common fields, you might need multiple criteria or a custom Moodle plugin/API.
            // Let's try searching by email, firstname, lastname as an example with OR like behavior (multiple criteria with same key type won't work as OR)
            // A simple approach for "any field" search using standard API might involve multiple calls or a broader single call then filtering.
            // For now, we'll search in 'email'. A more complex search can be built later.
            // Moodle's default for multiple criteria is AND. If you want OR, you'd typically search for one, then another, then merge.
            // A common pattern is to search for a value that could be in email OR username OR firstname OR lastname.
            // This example will search for the term in the email, username, first name, or last name.
            // This usually requires separate calls or a more complex query if the API doesn't support 'anyfield' matching this way.
            // Let's simplify and search only by email for now, or use a general search if available.
            // The 'value' for core_user_get_users can often use SQL LIKE syntax e.g. '%searchterm%'

            // Option 1: Search a specific field (e.g., email)
            // $criteria[] = ['key' => 'email', 'value' => '%' . $searchTerm . '%'];

            // Option 2: Search multiple fields (Moodle API will treat these as AND, so this is not ideal for "any field contains X")
            // $criteria[] = ['key' => 'email', 'value' => '%' . $searchTerm . '%'];
            // $criteria[] = ['key' => 'username', 'value' => '%' . $searchTerm . '%'];
            // $criteria[] = ['key' => 'firstname', 'value' => '%' . $searchTerm . '%'];
            // $criteria[] = ['key' => 'lastname', 'value' => '%' . $searchTerm . '%'];

            // For a true "any field" search, Moodle's API is a bit limited.
            // Some Moodle instances have `searchfields` parameter in `core_user_get_users`
            // Let's assume a simpler search for now, just by email as an example.
            // A real implementation would need to clarify how broad the search should be.
            $criteria[] = ['key' => 'email', 'value' => '%' . $searchTerm . '%'];
            // Or, to search multiple fields, you might try: (this syntax might not be supported by all Moodle versions or configurations for `core_user_get_users`)
            // $criteria[] = ['key' => 'fullname', 'value' => '%' . $searchTerm . '%']; // 'fullname' is not standard, but some setups might have it.
                                                                                   // More robust: search by individual name fields.
        }

        try {
            $response = $this->moodleApiService->getUsers($criteria);
            $moodleUsersRaw = [];

            if ($response->successful()) {
                // core_user_get_users returns an object with a 'users' array and a 'warnings' array
                $responseData = $response->json();
                if (isset($responseData['users']) && is_array($responseData['users'])) {
                    $moodleUsersRaw = $responseData['users'];
                } elseif (is_array($responseData) && !isset($responseData['exception'])) {
                    // If the top-level response is an array of users (less common for core_user_get_users but possible with other user functions)
                    $moodleUsersRaw = $responseData;
                }

                // Handle Moodle API warnings if necessary
                if (isset($responseData['warnings']) && !empty($responseData['warnings'])) {
                    // Log warnings or pass them to the view
                    session()->flash('moodle_warnings', $responseData['warnings']);
                }

            } else {
                // Handle HTTP error or Moodle specific error in JSON payload
                $errorData = $response->json();
                $errorMessage = 'Error fetching users from Moodle.';
                if (isset($errorData['errorcode'])) {
                    $errorMessage .= ' Code: ' . $errorData['errorcode'] . '. Message: ' . $errorData['message'];
                } elseif ($response->serverError()) {
                    $errorMessage .= ' Server error occurred.';
                } elseif ($response->clientError()) {
                    $errorMessage .= ' Client error occurred.';
                }
                session()->flash('error', $errorMessage);
            }

            // Manual pagination because Moodle API might not support it directly for this call
            $perPage = 15;
            $currentPage = Paginator::resolveCurrentPage('page');
            $currentItems = array_slice($moodleUsersRaw, ($currentPage - 1) * $perPage, $perPage);
            $paginatedUsers = new LengthAwarePaginator($currentItems, count($moodleUsersRaw), $perPage, $currentPage, [
                'path' => Paginator::resolveCurrentPath(),
                'pageName' => 'page',
            ]);

            // Append search query to pagination links
            $paginatedUsers->appends($request->except('page'));


            return view('moodle.users.index', [
                'users' => $paginatedUsers,
                'searchTerm' => $searchTerm
            ]);

        } catch (\Illuminate\Http\Client\RequestException $e) {
            // Handle connection exceptions, timeouts, etc.
            session()->flash('error', 'Could not connect to Moodle API: ' . $e->getMessage());
            return view('moodle.users.index', ['users' => new LengthAwarePaginator([], 0, $perPage, 1), 'searchTerm' => $searchTerm]);
        } catch (\Exception $e) {
            // Catch any other generic error
             session()->flash('error', 'An unexpected error occurred: ' . $e->getMessage());
            return view('moodle.users.index', ['users' => new LengthAwarePaginator([], 0, $perPage, 1), 'searchTerm' => $searchTerm]);
        }
    }

    public function show(Request $request, $userId)
    {
        // Moodle User ID
        try {
            $criteria = [['key' => 'id', 'value' => $userId]];
            $response = $this->moodleApiService->getUsers($criteria); // getUsers returns an array of users

            if ($response->successful()) {
                $responseData = $response->json();
                if (isset($responseData['users']) && count($responseData['users']) > 0) {
                    $user = $responseData['users'][0]; // Get the first user
                    return view('moodle.users.show', ['user' => $user]);
                } elseif (is_array($responseData) && !empty($responseData) && !isset($responseData['users']) && !isset($responseData['exception'])) {
                    // If the response is a single user object directly (less common for core_user_get_users)
                     $user = $responseData[0] ?? $responseData; // Adjust if it's not an array of one
                    return view('moodle.users.show', ['user' => $user]);
                } else {
                    session()->flash('error', 'User not found in Moodle.');
                    return redirect()->route('moodle.users.index');
                }
            } else {
                session()->flash('error', 'Error fetching user details from Moodle.');
                return redirect()->route('moodle.users.index');
            }
        } catch (\Illuminate\Http\Client\RequestException $e) {
            session()->flash('error', 'Could not connect to Moodle API: ' . $e->getMessage());
            return redirect()->route('moodle.users.index');
        } catch (\Exception $e) {
            session()->flash('error', 'An unexpected error occurred: ' . $e->getMessage());
            return redirect()->route('moodle.users.index');
        }
    }

    public function showMassCreateForm()
    {
        return view('moodle.users.mass-create');
    }

    public function handleMassCreateUpload(Request $request)
    {
        $request->validate([
            'user_file' => 'required|file|mimes:csv,txt|max:5120', // Max 5MB CSV file
        ]);

        $file = $request->file('user_file');
        $path = $file->getRealPath();

        $usersToCreate = [];
        $errors = [];
        $createdCount = 0;
        $failedCount = 0;

        // Basic CSV parsing (a more robust solution would use a library like league/csv)
        if (($handle = fopen($path, 'r')) !== false) {
            $header = fgetcsv($handle); // Assuming first row is header
            if (!$header || count($header) < 4) { // Basic check for username, password, firstname, lastname, email
                 return back()->with('error', 'Archivo CSV inválido o faltan columnas esenciales (username, password, firstname, lastname, email).');
            }

            // Map header names to Moodle API expected keys if necessary. For now, assume direct mapping.
            // Example: $headerMap = ['usuario' => 'username', 'clave' => 'password', ...];

            $rowNum = 1;
            while (($data = fgetcsv($handle)) !== false) {
                $rowNum++;
                if (count($data) === count($header)) {
                    $userData = array_combine($header, $data);

                    // Basic validation for each user - can be expanded significantly
                    if (empty($userData['username']) || empty($userData['password']) || empty($userData['firstname']) || empty($userData['lastname']) || empty($userData['email'])) {
                        $errors[] = "Fila {$rowNum}: Faltan datos requeridos (username, password, firstname, lastname, email).";
                        continue;
                    }
                    if (!filter_var($userData['email'], FILTER_VALIDATE_EMAIL)) {
                        $errors[] = "Fila {$rowNum}: Email inválido '{$userData['email']}'.";
                        continue;
                    }
                    // Add more validation as needed (e.g. password complexity if not handled by Moodle)

                    // Ensure all required Moodle fields are present with correct keys
                    $moodleUserPayload = [
                        'username' => trim($userData['username']),
                        'password' => trim($userData['password']),
                        'firstname' => trim($userData['firstname']),
                        'lastname' => trim($userData['lastname']),
                        'email' => trim($userData['email']),
                        // Add other optional fields from CSV if they exist and are mapped
                        // 'auth' => $userData['auth'] ?? 'manual', // Default auth method
                        // 'idnumber' => $userData['idnumber'] ?? '',
                        // 'city' => $userData['city'] ?? '',
                        // 'country' => $userData['country'] ?? '', // Moodle expects 2-letter country code
                        // 'preferences' => [['type' => 'auth_forcepasswordchange', 'value' => 1]], // Example preference
                    ];
                    $usersToCreate[] = $moodleUserPayload;
                } else {
                    $errors[] = "Fila {$rowNum}: Número incorrecto de columnas.";
                }
            }
            fclose($handle);
        } else {
            return back()->with('error', 'No se pudo abrir el archivo CSV.');
        }

        if (!empty($usersToCreate)) {
            try {
                // Moodle API might have limits on how many users can be created in one call.
                // Consider chunking $usersToCreate if necessary. For now, send all.
                $response = $this->moodleApiService->createUsers($usersToCreate);

                if ($response->successful()) {
                    $responseData = $response->json();
                    // Response for core_user_create_users is typically an array of created user objects or an empty array if all failed.
                    // If some users were created and some failed, Moodle usually returns info about the ones created
                    // and warnings for those that failed.
                    if (is_array($responseData)) {
                         // Assuming responseData is an array of successfully created user objects
                        $createdCount = count($responseData);
                        // We need to be more precise: check if responseData contains errors or is a list of successes
                        // A common pattern for Moodle create functions is to return an array of created IDs or objects.
                        // If an error occurs for a specific user, it might be in a 'warnings' part of the response or simply that user is omitted from success list.
                        // For now, a simple count. Better: iterate $responseData and confirm IDs.
                    }

                    if (isset($responseData['warnings']) && !empty($responseData['warnings'])) {
                        foreach($responseData['warnings'] as $warning) {
                            $errors[] = "Advertencia de Moodle: " . ($warning['message'] ?? json_encode($warning));
                        }
                    }
                    // If $responseData is empty but no exception, it might mean all failed at Moodle level.
                    // The Moodle API might not always throw an exception for partial failures.
                    if ($createdCount < count($usersToCreate) && empty($errors) && $createdCount == 0) {
                        $errors[] = "Moodle no reportó usuarios creados exitosamente, pero tampoco errores específicos. Revise los logs de Moodle.";
                    }


                } else {
                    $apiError = $response->json();
                    $errors[] = 'Error en API de Moodle al crear usuarios: ' . ($apiError['message'] ?? $response->body());
                }
            } catch (\Illuminate\Http\Client\RequestException $e) {
                $errors[] = 'Error de conexión al crear usuarios en Moodle: ' . $e->getMessage();
            } catch (\Exception $e) {
                $errors[] = 'Error inesperado durante la creación masiva: ' . $e->getMessage();
            }
        }

        $failedCount = count($usersToCreate) - $createdCount; // This is an estimate if Moodle doesn't clearly state failures

        $feedbackMessage = "Proceso de creación masiva completado. Usuarios intentados: " . count($usersToCreate) . ". Creados exitosamente: {$createdCount}. Fallidos/Omitidos: {$failedCount}.";

        if (!empty($errors)) {
            return back()->with('error', $feedbackMessage)->with('upload_errors', $errors)->withInput();
        }

        return redirect()->route('moodle.users.index')->with('success', $feedbackMessage);
    }
}
