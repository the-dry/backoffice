<x-layout bodyClass="g-sidenav-show bg-gray-200">
    <x-navbars.sidebar activePage="moodle-reports-global-user-detail"></x-navbars.sidebar> {{-- Define activePage --}}
    <main class="main-content position-relative max-height-vh-100 h-100 border-radius-lg">
        <!-- Navbar -->
        <x-navbars.navs.auth titlePage="Reporte Global: Detalle por Alumno"></x-navbars.navs.auth>
        <!-- End Navbar -->
        <div class="container-fluid py-4">
            <div class="row">
                <div class="col-12 col-md-10 mx-auto">
                    <div class="card my-4">
                        <div class="card-header p-0 position-relative mt-n4 mx-3 z-index-2">
                            <div class="bg-gradient-primary shadow-primary border-radius-lg pt-4 pb-3">
                                <h6 class="text-white text-capitalize ps-3">Generar Reporte Global por Alumno</h6>
                            </div>
                        </div>
                        <div class="card-body px-4 pb-3">

                            @if(session('success'))
                                <div class="alert alert-success alert-dismissible text-white fade show" role="alert">
                                    <span class="alert-text"><strong>Éxito!</strong> {{ session('success') }}</span>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                                    {{-- Add button to download if data is ready in session --}}
                                    @if(session('global_user_detail_report_data'))
                                        <div class="mt-2">
                                            {{-- <a href="{{ route('moodle.reports.global-user-detail.export') }}" class="btn btn-sm btn-light mb-0">
                                                <i class="material-icons text-sm">file_download</i>&nbsp;Descargar Reporte Generado (Excel)
                                            </a> --}}
                                            <p class="text-sm text-white">Funcionalidad de descarga se implementará en el siguiente paso.</p>
                                        </div>
                                    @endif
                                </div>
                            @endif
                            @if(session('error'))
                                <div class="alert alert-danger alert-dismissible text-white fade show" role="alert">
                                    <span class="alert-text"><strong>Error!</strong> {{ session('error') }}</span>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                                </div>
                            @endif
                             @if(session('info'))
                                <div class="alert alert-info alert-dismissible text-white fade show" role="alert">
                                    <span class="alert-text"><strong>Info:</strong> {{ session('info') }}</span>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                                </div>
                            @endif

                            <p class="text-sm">
                                Este reporte generará un listado detallado de todos los usuarios y los cursos en los que están inscritos,
                                junto con su estado de completitud y calificación final para cada curso.
                            </p>
                            <p class="text-sm text-warning">
                                <span class="material-icons text-sm">warning</span>
                                <strong>Advertencia:</strong> La generación de este reporte puede tomar un tiempo considerable y consumir
                                recursos significativos si hay una gran cantidad de usuarios y cursos en Moodle.
                            </p>

                            {{-- Add User Filters Here Later if needed --}}
                            {{-- Example:
                            <form method="POST" action="{{ route('moodle.reports.global-user-detail.generate') }}">
                                @csrf
                                <div class="input-group input-group-static mb-3">
                                    <label for="user_filter_email" class="ms-0">Filtrar por Email de Usuario (opcional, contiene):</label>
                                    <input type="text" name="user_email_contains" id="user_filter_email" class="form-control">
                                </div>
                                <button type="submit" class="btn btn-primary mt-3">Generar Reporte</button>
                            </form>
                            --}}

                            <form method="POST" action="{{ route('moodle.reports.global-user-detail.generate') }}">
                                @csrf
                                <div class="text-center">
                                    <button type="submit" class="btn btn-primary mt-3">Generar Reporte Global por Alumno</button>
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
