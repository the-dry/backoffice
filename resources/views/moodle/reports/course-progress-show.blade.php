<x-layout bodyClass="g-sidenav-show bg-gray-200">
    <x-navbars.sidebar activePage="moodle-reports-course-progress"></x-navbars.sidebar> {{-- Define activePage --}}
    <main class="main-content position-relative max-height-vh-100 h-100 border-radius-lg">
        <!-- Navbar -->
        <x-navbars.navs.auth titlePage="Reporte de Progreso: {{ $courseDetails['fullname'] ?? 'Curso Desconocido' }}"></x-navbars.navs.auth>
        <!-- End Navbar -->
        <div class="container-fluid py-4">
            <div class="row">
                <div class="col-12">
                    <div class="card my-4">
                        <div class="card-header p-0 position-relative mt-n4 mx-3 z-index-2">
                            <div class="bg-gradient-primary shadow-primary border-radius-lg pt-4 pb-3 d-flex justify-content-between align-items-center">
                                <h6 class="text-white text-capitalize ps-3">Progreso de Usuarios en: {{ $courseDetails['fullname'] ?? 'Curso Desconocido' }} (ID: {{ $courseDetails['id'] ?? 'N/A' }})</h6>
                                @if(!empty($enrolledUsersWithProgress) && isset($courseDetails['id']))
                                <a href="{{ route('moodle.reports.course-progress.export', ['course_id' => $courseDetails['id']]) }}" class="btn btn-sm btn-light mb-0 me-3">
                                    <i class="material-icons text-sm">file_download</i>&nbsp;Exportar a Excel
                                </a>
                                @endif
                            </div>
                        </div>
                        <div class="card-body px-0 pb-2">
                             @if(session('error'))
                                <div class="alert alert-danger alert-dismissible text-white fade show mx-4" role="alert">
                                    <span class="alert-text"><strong>Error!</strong> {{ session('error') }}</span>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                                </div>
                            @endif

                            @if (empty($enrolledUsersWithProgress))
                                <p class="text-center p-4">No hay usuarios inscritos o no se pudo obtener información de progreso para este curso.</p>
                            @else
                                <div class="table-responsive p-0">
                                    <table class="table align-items-center mb-0">
                                        <thead>
                                            <tr>
                                                <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">ID Usuario</th>
                                                <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Nombre Completo</th>
                                                <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Email</th>
                                                <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Username</th>
                                                <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Estado Completitud</th>
                                                <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Calificación Final</th>
                                                <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Primer Acceso al Curso</th>
                                                <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Último Acceso al Curso</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($enrolledUsersWithProgress as $progress)
                                                <tr>
                                                    <td><div class="d-flex px-3 py-1"><h6 class="mb-0 text-sm">{{ $progress['id'] }}</h6></div></td>
                                                    <td><p class="text-xs font-weight-bold mb-0">{{ $progress['fullname'] }}</p></td>
                                                    <td><p class="text-xs font-weight-bold mb-0">{{ $progress['email'] }}</p></td>
                                                    <td><p class="text-xs font-weight-bold mb-0">{{ $progress['username'] }}</p></td>
                                                    <td>
                                                        <span class="badge badge-sm bg-gradient-{{ $progress['completion_status'] === 'Completado' ? 'success' : 'warning' }}">
                                                            {{ $progress['completion_status'] }}
                                                        </span>
                                                    </td>
                                                    <td><p class="text-xs font-weight-bold mb-0">{{ $progress['grade'] }}</p></td>
                                                    <td><p class="text-xs font-weight-bold mb-0">{{ $progress['firstaccess'] }}</p></td>
                                                    <td><p class="text-xs font-weight-bold mb-0">{{ $progress['lastaccess'] }}</p></td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @endif
                             <div class="px-4 py-3">
                                <a href="{{ route('moodle.reports.course-progress.form') }}" class="btn btn-secondary btn-sm">Seleccionar Otro Curso</a>
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
