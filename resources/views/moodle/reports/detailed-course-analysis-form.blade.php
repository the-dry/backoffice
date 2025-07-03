<x-layout bodyClass="g-sidenav-show bg-gray-200">
    <x-navbars.sidebar activePage="moodle-reports-detailed-course-analysis"></x-navbars.sidebar> {{-- Define activePage --}}
    <main class="main-content position-relative max-height-vh-100 h-100 border-radius-lg">
        <!-- Navbar -->
        <x-navbars.navs.auth titlePage="Análisis Detallado de Curso"></x-navbars.navs.auth>
        <!-- End Navbar -->
        <div class="container-fluid py-4">
            <div class="row">
                <div class="col-12 col-md-8 mx-auto">
                    <div class="card my-4">
                        <div class="card-header p-0 position-relative mt-n4 mx-3 z-index-2">
                            <div class="bg-gradient-primary shadow-primary border-radius-lg pt-4 pb-3">
                                <h6 class="text-white text-capitalize ps-3">Seleccionar Curso para Análisis Detallado</h6>
                            </div>
                        </div>
                        <div class="card-body px-4 pb-3">

                            @if(session('error'))
                                <div class="alert alert-danger alert-dismissible text-white fade show" role="alert">
                                    <span class="alert-icon align-middle"><span class="material-icons text-md">warning</span></span>
                                    <span class="alert-text"><strong>Error!</strong> {{ session('error') }}</span>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                                </div>
                            @endif

                            <form method="POST" action="{{ route('moodle.reports.detailed-course-analysis.generate') }}">
                                @csrf
                                <div class="input-group input-group-static mb-4">
                                    <label for="course_id" class="ms-0">Curso de Moodle</label>
                                    <select name="course_id" id="course_id" class="form-control" required>
                                        <option value="">-- Seleccione un Curso --</option>
                                        @forelse ($courses as $course)
                                            <option value="{{ $course['id'] }}">
                                                {{ $course['fullname'] }} (ID: {{ $course['id'] }})
                                            </option>
                                        @empty
                                            <option value="" disabled>No hay cursos disponibles o no se pudieron cargar.</option>
                                        @endforelse
                                    </select>
                                    @error('course_id') <p class='text-danger inputerror'>{{ $message }} </p> @enderror
                                </div>

                                <div class="text-center">
                                    <button type="submit" class="btn btn-primary mt-3">Generar Análisis Detallado</button>
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
</x-layout>
