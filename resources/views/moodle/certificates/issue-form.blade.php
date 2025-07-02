<x-layout bodyClass="g-sidenav-show bg-gray-200">
    <x-navbars.sidebar activePage="moodle-certificates-issue"></x-navbars.sidebar> {{-- Define activePage --}}
    <main class="main-content position-relative max-height-vh-100 h-100 border-radius-lg">
        <!-- Navbar -->
        <x-navbars.navs.auth titlePage="Emitir Certificado Moodle"></x-navbars.navs.auth>
        <!-- End Navbar -->
        <div class="container-fluid py-4">
            <div class="row">
                <div class="col-12 col-md-10 mx-auto">
                    <div class="card my-4">
                        <div class="card-header p-0 position-relative mt-n4 mx-3 z-index-2">
                            <div class="bg-gradient-primary shadow-primary border-radius-lg pt-4 pb-3">
                                <h6 class="text-white text-capitalize ps-3">Formulario de Emisión de Certificado</h6>
                            </div>
                        </div>
                        <div class="card-body px-4 pb-3">

                            @if(session('success'))
                                <div class="alert alert-success alert-dismissible text-white fade show" role="alert">
                                    <span class="alert-text"><strong>Éxito!</strong> {{ session('success') }}</span>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                                </div>
                            @endif
                            @if(session('error'))
                                <div class="alert alert-danger alert-dismissible text-white fade show" role="alert">
                                    <span class="alert-text"><strong>Error!</strong> {{ session('error') }}</span>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                                </div>
                            @endif
                             @if(session('template_error'))
                                <div class="alert alert-warning alert-dismissible text-white fade show" role="alert">
                                    <span class="alert-text"><strong>Advertencia:</strong> {{ session('template_error') }}</span>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                                </div>
                            @endif
                             @if(session('user_error'))
                                <div class="alert alert-warning alert-dismissible text-white fade show" role="alert">
                                    <span class="alert-text"><strong>Advertencia:</strong> {{ session('user_error') }}</span>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                                </div>
                            @endif


                            {{-- Form to select course first, then it reloads to show users and templates --}}
                            <form method="GET" action="{{ route('moodle.certificates.issue.form') }}" id="courseSelectionForm" class="mb-4">
                                <div class="input-group input-group-static">
                                    <label for="course_id_form" class="ms-0">1. Seleccione un Curso para Cargar Plantillas y Usuarios</label>
                                    <select name="course_id_form" id="course_id_form" class="form-control" onchange="this.form.submit()">
                                        <option value="">-- Seleccione un Curso --</option>
                                        @foreach ($courses as $course)
                                            <option value="{{ $course['id'] }}" {{ (request()->input('course_id_form') ?? $selectedCourseId) == $course['id'] ? 'selected' : '' }}>
                                                {{ $course['fullname'] }} (ID: {{ $course['id'] }})
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </form>
                            <hr class="horizontal dark my-3">

                            @if($selectedCourseId)
                                <form method="POST" action="{{ route('moodle.certificates.issue.submit') }}">
                                    @csrf
                                    <input type="hidden" name="course_id_form" value="{{ $selectedCourseId }}">

                                    <div class="row">
                                        <div class="col-md-6 mb-3 input-group input-group-static">
                                            <label for="certificate_id" class="ms-0">2. Seleccione Plantilla de Certificado</label>
                                            <select name="certificate_id" id="certificate_id" class="form-control" required>
                                                <option value="">-- Seleccione una Plantilla --</option>
                                                @forelse ($certificateTemplates as $template)
                                                    {{-- The structure of $template depends on mod_customcert_get_customcerts_by_courses response --}}
                                                    {{-- Common fields are 'id' and 'name' --}}
                                                    <option value="{{ $template['id'] }}" {{ old('certificate_id') == $template['id'] ? 'selected' : '' }}>
                                                        {{ $template['name'] }} (ID: {{ $template['id'] }})
                                                    </option>
                                                @empty
                                                    <option value="" disabled>No hay plantillas para este curso o no se pudieron cargar.</option>
                                                @endforelse
                                            </select>
                                            @error('certificate_id') <p class='text-danger inputerror'>{{ $message }} </p> @enderror
                                        </div>

                                        <div class="col-md-6 mb-3 input-group input-group-static">
                                            <label for="user_id" class="ms-0">3. Seleccione Usuario</label>
                                            <select name="user_id" id="user_id" class="form-control" required>
                                                <option value="">-- Seleccione un Usuario --</option>
                                                @forelse ($paginatedUsers as $user) {{-- Assuming $users is passed from controller for selected course --}}
                                                    <option value="{{ $user['id'] }}" {{ old('user_id') == $user['id'] ? 'selected' : '' }}>
                                                        {{ $user['fullname'] }} ({{ $user['email'] }})
                                                    </option>
                                                @empty
                                                    <option value="" disabled>No hay usuarios inscritos en este curso o no se pudieron cargar.</option>
                                                @endforelse
                                            </select>
                                            @error('user_id') <p class='text-danger inputerror'>{{ $message }} </p> @enderror
                                            {{-- Optional: User search/pagination if many users --}}
                                            @if($paginatedUsers->hasPages())
                                                <div class="mt-2">
                                                    {{-- This pagination needs to preserve course_id_form selection for GET request --}}
                                                    {{ $paginatedUsers->appends(['course_id_form' => $selectedCourseId])->links() }}
                                                </div>
                                            @endif
                                        </div>
                                    </div>

                                    <div class="text-center mt-4">
                                        <button type="submit" class="btn btn-primary">Emitir Certificado</button>
                                    </div>
                                </form>
                            @else
                                <p class="text-muted text-center">Por favor, seleccione un curso para ver las plantillas de certificado y los usuarios.</p>
                            @endif

                            <div class="text-start mt-4">
                                 <a href="{{ route('moodle.certificates.issued.index') }}" class="btn btn-secondary btn-sm">Ver Certificados Emitidos</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <x-footers.auth></x-footers.auth>
        </div>
    </main>
    <x-plugins></x-plugins>
</x-layout>
