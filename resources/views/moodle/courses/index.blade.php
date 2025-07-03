<x-layout bodyClass="g-sidenav-show bg-gray-200">
    <x-navbars.sidebar activePage="moodle-courses"></x-navbars.sidebar> {{-- Define activePage --}}
    <main class="main-content position-relative max-height-vh-100 h-100 border-radius-lg">
        <!-- Navbar -->
        <x-navbars.navs.auth titlePage="Gestión de Cursos Moodle"></x-navbars.navs.auth>
        <!-- End Navbar -->
        <div class="container-fluid py-4">
            <div class="row">
                <div class="col-12">
                    <div class="card my-4">
                        <div class="card-header p-0 position-relative mt-n4 mx-3 z-index-2">
                            <div class="bg-gradient-primary shadow-primary border-radius-lg pt-4 pb-3">
                                <h6 class="text-white text-capitalize ps-3">Cursos de Moodle</h6>
                            </div>
                        </div>
                        <div class="card-body px-0 pb-2">
                            <!-- Search form -->
                            <div class="px-4 py-3">
                                <form method="GET" action="{{ route('moodle.courses.index') }}">
                                    <div class="input-group input-group-outline">
                                        <label class="form-label">Buscar por nombre...</label>
                                        <input type="text" class="form-control" name="search" value="{{ $searchTerm ?? '' }}">
                                        <button type="submit" class="btn btn-primary mb-0">Buscar</button>
                                    </div>
                                </form>
                            </div>

                            @if(session('success'))
                                <div class="alert alert-success alert-dismissible text-white fade show mx-4" role="alert">
                                    <span class="alert-text"><strong>Éxito!</strong> {{ session('success') }}</span>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                                </div>
                            @endif
                            @if(session('error'))
                                <div class="alert alert-danger alert-dismissible text-white fade show mx-4" role="alert">
                                    <span class="alert-text"><strong>Error!</strong> {{ session('error') }}</span>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                                </div>
                            @endif
                             @if(session('warning'))
                                <div class="alert alert-warning alert-dismissible text-white fade show mx-4" role="alert">
                                    <span class="alert-text"><strong>Advertencia:</strong> {{ session('warning') }}</span>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                                </div>
                            @endif


                            <div class="table-responsive p-0">
                                <table class="table align-items-center mb-0">
                                    <thead>
                                        <tr>
                                            <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">ID</th>
                                            <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Nombre Completo</th>
                                            <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Nombre Corto</th>
                                            <th class="text-center text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Visible</th>
                                            <th class="text-center text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($paginatedCourses as $course)
                                            <tr>
                                                <td><div class="d-flex px-3 py-1"><h6 class="mb-0 text-sm">{{ $course['id'] }}</h6></div></td>
                                                <td><p class="text-xs font-weight-bold mb-0">{{ $course['fullname'] ?? 'N/A' }}</p></td>
                                                <td><p class="text-xs font-weight-bold mb-0">{{ $course['shortname'] ?? 'N/A' }}</p></td>
                                                <td class="align-middle text-center text-sm">
                                                    @if(isset($course['visible']) && $course['visible'])
                                                        <span class="badge badge-sm bg-gradient-success">Sí</span>
                                                    @else
                                                        <span class="badge badge-sm bg-gradient-danger">No</span>
                                                    @endif
                                                </td>
                                                <td class="align-middle text-center text-sm">
                                                    <form action="{{ route('moodle.courses.toggle-visibility', $course['id']) }}" method="POST" style="display:inline;">
                                                        @csrf
                                                        <input type="hidden" name="visible" value="{{ (isset($course['visible']) && $course['visible']) ? 0 : 1 }}">
                                                        <button type="submit" class="btn btn-sm mb-0 {{ (isset($course['visible']) && $course['visible']) ? 'btn-warning' : 'btn-success' }}">
                                                            {{ (isset($course['visible']) && $course['visible']) ? 'Ocultar' : 'Mostrar' }}
                                                        </button>
                                                    </form>
                                                    {{-- Add other actions like "View Details", "Sync" if needed --}}
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="5" class="text-center py-4">No se encontraron cursos.</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                            <div class="px-4 py-3">
                                {{ $paginatedCourses->links() }}
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
