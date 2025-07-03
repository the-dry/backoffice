<x-layout bodyClass="g-sidenav-show bg-gray-200">
    <x-navbars.sidebar activePage="moodle-reports-detailed-course-analysis"></x-navbars.sidebar>
    <main class="main-content position-relative max-height-vh-100 h-100 border-radius-lg">
        <!-- Navbar -->
        <x-navbars.navs.auth titlePage="Análisis Detallado: {{ $courseDetails['fullname'] ?? 'Curso Desconocido' }}"></x-navbars.navs.auth>
        <!-- End Navbar -->
        <div class="container-fluid py-4">
            <div class="row">
                <div class="col-12">
                    <div class="card my-4">
                        <div class="card-header p-0 position-relative mt-n4 mx-3 z-index-2">
                            <div class="bg-gradient-primary shadow-primary border-radius-lg pt-4 pb-3 d-flex justify-content-between align-items-center">
                                <h6 class="text-white text-capitalize ps-3">Análisis Detallado: {{ $courseDetails['fullname'] ?? 'Curso Desconocido' }} (ID: {{ $courseDetails['id'] ?? 'N/A' }})</h6>
                                {{-- Add Export Button Later --}}
                                {{-- @if(!empty($reportData))
                                <a href="{{-- route('moodle.reports.detailed-course-analysis.export', ['course_id' => $courseDetails['id']]) --}}" class="btn btn-sm btn-light mb-0 me-3">
                                    <i class="material-icons text-sm">file_download</i>&nbsp;Exportar a Excel
                                </a>
                                @endif --}}
                            </div>
                        </div>
                        <div class="card-body px-0 pb-2">
                             @if(session('error'))
                                <div class="alert alert-danger alert-dismissible text-white fade show mx-4" role="alert">
                                    <span class="alert-text"><strong>Error!</strong> {{ session('error') }}</span>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                                </div>
                            @endif

                            @if (empty($reportData))
                                <p class="text-center p-4">No hay datos de usuarios o actividades para este curso, o no se pudo generar el análisis.</p>
                            @else
                                <div class="table-responsive p-0">
                                    <table class="table align-items-center mb-0 table-bordered">
                                        <thead>
                                            <tr>
                                                <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Usuario</th>
                                                @foreach ($courseActivities as $activity)
                                                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 text-center" title="{{ $activity['modname'] }} - ID: {{ $activity['id'] }}">
                                                        {{ Str::limit($activity['name'], 25) }} <br/>
                                                        <span class="text-muted text-xs">({{ $activity['modname'] }})</span>
                                                    </th>
                                                @endforeach
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($reportData as $userId => $userData)
                                                <tr>
                                                    <td class="align-middle">
                                                        <div class="d-flex flex-column justify-content-center px-2 py-1">
                                                            <h6 class="mb-0 text-sm">{{ $userData['user_info']['fullname'] ?? 'N/A' }}</h6>
                                                            <p class="text-xs text-secondary mb-0">{{ $userData['user_info']['email'] ?? '' }}</p>
                                                        </div>
                                                    </td>
                                                    @foreach ($courseActivities as $activity)
                                                        @php
                                                            $activityProgress = $userData['activities'][$activity['id']] ?? null;
                                                        @endphp
                                                        <td class="align-middle text-center text-sm">
                                                            @if($activityProgress)
                                                                <span class="badge badge-sm bg-gradient-{{
                                                                    $activityProgress['completion_state'] === 'Completo' || $activityProgress['completion_state'] === 'Completo (Aprobado)' ? 'success' :
                                                                    ($activityProgress['completion_state'] === 'Incompleto' ? 'warning' : 'secondary')
                                                                }}">
                                                                    {{ $activityProgress['completion_state'] }}
                                                                </span>
                                                                <br>
                                                                <small class="text-muted">Nota: {{ $activityProgress['grade'] ?? 'N/A' }}</small>
                                                            @else
                                                                N/A
                                                            @endif
                                                        </td>
                                                    @endforeach
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @endif
                             <div class="px-4 py-3">
                                <a href="{{ route('moodle.reports.detailed-course-analysis.form') }}" class="btn btn-secondary btn-sm">Seleccionar Otro Curso</a>
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
