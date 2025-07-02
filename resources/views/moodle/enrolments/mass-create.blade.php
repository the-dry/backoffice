<x-layout bodyClass="g-sidenav-show bg-gray-200">
    <x-navbars.sidebar activePage="moodle-enrolments"></x-navbars.sidebar> {{-- activePage needs to be defined or a new one created --}}
    <main class="main-content position-relative max-height-vh-100 h-100 border-radius-lg">
        <!-- Navbar -->
        <x-navbars.navs.auth titlePage="Inscripción Masiva en Cursos Moodle"></x-navbars.navs.auth>
        <!-- End Navbar -->
        <div class="container-fluid py-4">
            <div class="row">
                <div class="col-12">
                    <div class="card my-4">
                        <div class="card-header p-0 position-relative mt-n4 mx-3 z-index-2">
                            <div class="bg-gradient-primary shadow-primary border-radius-lg pt-4 pb-3">
                                <h6 class="text-white text-capitalize ps-3">Inscribir Usuarios en Curso</h6>
                            </div>
                        </div>
                        <div class="card-body px-4 pb-3">

                            @if(session('success'))
                                <div class="alert alert-success alert-dismissible text-white fade show" role="alert">
                                    <span class="alert-icon align-middle"><span class="material-icons text-md">check_circle</span></span>
                                    <span class="alert-text"><strong>Éxito!</strong> {{ session('success') }}</span>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                                </div>
                            @endif
                            @if(session('error'))
                                <div class="alert alert-danger alert-dismissible text-white fade show" role="alert">
                                    <span class="alert-icon align-middle"><span class="material-icons text-md">warning</span></span>
                                    <span class="alert-text"><strong>Error!</strong> {{ session('error') }}</span>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                                </div>
                            @endif
                            @if(session('upload_errors') && is_array(session('upload_errors')))
                                <div class="alert alert-warning alert-dismissible text-dark fade show" role="alert">
                                    <strong>Se encontraron los siguientes problemas:</strong>
                                    <ul>
                                        @foreach(session('upload_errors') as $errMsg)
                                            <li>{{ $errMsg }}</li>
                                        @endforeach
                                    </ul>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                                </div>
                            @endif

                            <form method="POST" action="{{ route('moodle.enrolments.mass-create.submit') }}">
                                @csrf

                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="course_id">Seleccionar Curso:</label>
                                        <select name="course_id" id="course_id" class="form-control form-select p-2" required>
                                            <option value="">-- Seleccione un Curso --</option>
                                            @forelse ($courses as $course)
                                                <option value="{{ $course['id'] }}" {{ old('course_id') == $course['id'] ? 'selected' : '' }}>
                                                    {{ $course['fullname'] }} (ID: {{ $course['id'] }})
                                                </option>
                                            @empty
                                                <option value="" disabled>No hay cursos disponibles o no se pudieron cargar.</option>
                                            @endforelse
                                        </select>
                                        @error('course_id') <p class='text-danger inputerror'>{{ $message }} </p> @enderror
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="role_id">Asignar Rol:</label>
                                        <select name="role_id" id="role_id" class="form-control form-select p-2" required>
                                            <option value="">-- Seleccione un Rol --</option>
                                            @foreach ($roles as $id => $name)
                                                <option value="{{ $id }}" {{ old('role_id', 5) == $id ? 'selected' : '' }}> {{-- Default to student (ID 5) --}}
                                                    {{ $name }} (ID: {{ $id }})
                                                </option>
                                            @endforeach
                                        </select>
                                        @error('role_id') <p class='text-danger inputerror'>{{ $message }} </p> @enderror
                                    </div>
                                </div>

                                <hr class="horizontal dark my-3">

                                <h6 class="mb-3">Seleccionar Usuarios a Inscribir:</h6>

                                <!-- User Search Form -->
                                <div class="row mb-3">
                                    <div class="col-md-8">
                                        <div class="input-group input-group-outline">
                                            <label class="form-label">Buscar usuarios por email...</label>
                                            <input type="text" class="form-control" name="user_search_term_display" value="{{ $usersSearchTerm ?? '' }}"
                                                   form="userSearchForm"> {{-- Associate with a dummy form or handle via JS --}}
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                         {{-- The search form will submit the whole page, so we need to preserve course/role selection if possible or use AJAX --}}
                                        <button type="submit" class="btn btn-outline-primary mb-0" formaction="{{ route('moodle.enrolments.mass-create.form') }}" formmethod="GET" id="userSearchButton">Buscar Usuarios</button>
                                    </div>
                                </div>
                                 @if(session('user_fetch_error'))
                                    <div class="alert alert-warning text-white fade show" role="alert">
                                        {{ session('user_fetch_error') }}
                                    </div>
                                @endif


                                <div class="table-responsive p-0" style="max-height: 400px; overflow-y: auto;">
                                    <table class="table align-items-center mb-0">
                                        <thead>
                                            <tr>
                                                <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7"><input type="checkbox" id="selectAllUsers"></th>
                                                <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Nombre</th>
                                                <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Email</th>
                                                <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Username</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse ($users as $user)
                                                <tr>
                                                    <td><input type="checkbox" name="user_ids[]" value="{{ $user['id'] }}" class="user-checkbox"></td>
                                                    <td>{{ $user['fullname'] ?? 'N/A' }}</td>
                                                    <td>{{ $user['email'] ?? 'N/A' }}</td>
                                                    <td>{{ $user['username'] ?? 'N/A' }}</td>
                                                </tr>
                                            @empty
                                                <tr>
                                                    <td colspan="4" class="text-center py-3">
                                                        @if(!empty($usersSearchTerm))
                                                            No se encontraron usuarios para "{{$usersSearchTerm}}".
                                                        @else
                                                            Realice una búsqueda para encontrar usuarios o no hay usuarios para mostrar.
                                                        @endif
                                                    </td>
                                                </tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                                <div class="mt-2 px-0">
                                    {{ $users->appends(['user_search' => $usersSearchTerm])->links() }}
                                </div>
                                 @error('user_ids') <p class='text-danger inputerror mt-2'>{{ $message }} </p> @enderror


                                <div class="text-center mt-4">
                                    <button type="submit" class="btn btn-primary">Inscribir Seleccionados</button>
                                </div>
                                <div class="text-start mt-3">
                                     <a href="{{ route('moodle.users.index') }}" class="btn btn-secondary btn-sm">Cancelar</a>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
            <x-footers.auth></x-footers.auth>
        </div>
    </main>
    <x-plugins></x-plugins>
    @push('js')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const selectAllCheckbox = document.getElementById('selectAllUsers');
            const userCheckboxes = document.querySelectorAll('.user-checkbox');

            if(selectAllCheckbox) {
                selectAllCheckbox.addEventListener('change', function () {
                    userCheckboxes.forEach(checkbox => {
                        checkbox.checked = this.checked;
                    });
                });
            }

            // Persist search term in search input (the form GET reloads the page)
            const urlParams = new URLSearchParams(window.location.search);
            const userSearchTerm = urlParams.get('user_search');
            const searchInput = document.querySelector('input[name="user_search_term_display"]');
            if (searchInput && userSearchTerm) {
                searchInput.value = userSearchTerm;
                 // If the input-group-outline has is-focused and is-filled, remove them if input is not empty
                if (searchInput.value !== "") {
                    const parentDiv = searchInput.closest('.input-group.input-group-outline');
                    if (parentDiv) {
                        parentDiv.classList.add('is-filled');
                    }
                }
            }

            // When submitting main form, ensure user_search_term_display is not submitted with it
            // as the main form is POST for enrolment.
            const mainForm = document.querySelector('form[action="{{ route('moodle.enrolments.mass-create.submit') }}"]');
            if(mainForm && searchInput) {
                mainForm.addEventListener('submit', function() {
                    searchInput.name = ""; // Temporarily remove name to prevent submission with main form
                });
            }
            // Restore name for search button if needed, or handle search submission via JS to only include relevant params
            const searchButton = document.getElementById('userSearchButton');
            if (searchButton && searchInput) {
                 searchButton.addEventListener('click', function(e) {
                    // Ensure the search input has its name set for the GET request
                    const currentSearchValue = document.querySelector('input[name="user_search_term_display"]').value;
                    // Create a hidden input for the actual search parameter for the GET form
                    let hiddenSearchInput = document.querySelector('input[name="user_search"]');
                    if (!hiddenSearchInput) {
                        hiddenSearchInput = document.createElement('input');
                        hiddenSearchInput.type = 'hidden';
                        hiddenSearchInput.name = 'user_search';
                        e.target.form.appendChild(hiddenSearchInput); // Assuming search button is part of a form or we find the correct one
                    }
                    hiddenSearchInput.value = currentSearchValue;
                });
            }


        });
    </script>
    @endpush
</x-layout>
