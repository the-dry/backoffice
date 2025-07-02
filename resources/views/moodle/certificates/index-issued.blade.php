<x-layout bodyClass="g-sidenav-show bg-gray-200">
    <x-navbars.sidebar activePage="moodle-certificates-issued"></x-navbars.sidebar> {{-- Define activePage --}}
    <main class="main-content position-relative max-height-vh-100 h-100 border-radius-lg">
        <!-- Navbar -->
        <x-navbars.navs.auth titlePage="Reporte de Certificados Emitidos (Moodle)"></x-navbars.navs.auth>
        <!-- End Navbar -->
        <div class="container-fluid py-4">
            <div class="row">
                <div class="col-12">
                    <div class="card my-4">
                        <div class="card-header p-0 position-relative mt-n4 mx-3 z-index-2">
                            <div class="bg-gradient-primary shadow-primary border-radius-lg pt-4 pb-3 d-flex justify-content-between align-items-center">
                                <h6 class="text-white text-capitalize ps-3">Certificados Emitidos en Moodle</h6>
                                {{-- Add Export Button Later --}}
                                {{-- @if(!$paginatedCertificates->isEmpty())
                                <a href="#" class="btn btn-sm btn-light mb-0 me-3">
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
                            @if(session('info'))
                                <div class="alert alert-info alert-dismissible text-white fade show mx-4" role="alert">
                                    <span class="alert-text"><strong>Info:</strong> {{ session('info') }}</span>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                                </div>
                            @endif
                             @if(session('moodle_warnings'))
                                <div class="alert alert-warning alert-dismissible text-dark fade show mx-4" role="alert">
                                    <strong>Advertencias de Moodle:</strong>
                                    <ul>
                                        @foreach(session('moodle_warnings') as $warning)
                                            <li>{{ $warning }}</li>
                                        @endforeach
                                    </ul>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                                </div>
                            @endif

                            {{-- Add Filters Here if needed (e.g., by course, user, date range) --}}

                            @if ($paginatedCertificates->isEmpty())
                                <p class="text-center p-4">No se encontraron certificados emitidos o la funcionalidad API no está completamente implementada.</p>
                            @else
                                <div class="table-responsive p-0">
                                    <table class="table align-items-center mb-0">
                                        <thead>
                                            <tr>
                                                {{-- These columns are HYPOTHETICAL and depend on what `getIssuedCertificates` can return --}}
                                                <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">ID Emisión</th>
                                                <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Certificado (Plantilla)</th>
                                                <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Usuario</th>
                                                <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Curso</th>
                                                <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Fecha Emisión</th>
                                                <th class="text-center text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Acciones</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($paginatedCertificates as $cert)
                                                <tr>
                                                    {{-- Adjust these based on actual data structure from Moodle API --}}
                                                    <td><div class="d-flex px-3 py-1"><h6 class="mb-0 text-sm">{{ $cert['id'] ?? 'N/A' }}</h6></div></td>
                                                    <td><p class="text-xs font-weight-bold mb-0">{{ $cert['certificatename'] ?? ($cert['customcertid'] ?? 'N/A') }}</p></td>
                                                    <td><p class="text-xs font-weight-bold mb-0">{{ $cert['userfullname'] ?? ($cert['userid'] ?? 'N/A') }}</p></td>
                                                    <td><p class="text-xs font-weight-bold mb-0">{{ $cert['coursename'] ?? ($cert['courseid'] ?? 'N/A') }}</p></td>
                                                    <td><p class="text-xs font-weight-bold mb-0">{{ isset($cert['timeissued']) ? date('Y-m-d H:i:s', $cert['timeissued']) : 'N/A' }}</p></td>
                                                    <td class="align-middle text-center text-sm">
                                                        {{-- Action to view/download certificate if link is available --}}
                                                        @if(isset($cert['downloadurl']))
                                                            <a href="{{ $cert['downloadurl'] }}" target="_blank" class="btn btn-sm btn-info mb-0">Ver/Descargar</a>
                                                        @else
                                                            N/A
                                                        @endif
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                                <div class="px-4 py-3">
                                    {{ $paginatedCertificates->links() }}
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
            <x-footers.auth></x-footers.auth>
        </div>
    </main>
    <x-plugins></x-plugins>
</x-layout>
