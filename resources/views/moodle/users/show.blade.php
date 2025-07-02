<x-layout bodyClass="g-sidenav-show bg-gray-200">
    <x-navbars.sidebar activePage="moodle-users"></x-navbars.sidebar>
    <main class="main-content position-relative max-height-vh-100 h-100 border-radius-lg">
        <!-- Navbar -->
        <x-navbars.navs.auth titlePage="Detalle Usuario Moodle"></x-navbars.navs.auth>
        <!-- End Navbar -->
        <div class="container-fluid py-4">
            <div class="row">
                <div class="col-12">
                    <div class="card my-4">
                        <div class="card-header p-0 position-relative mt-n4 mx-3 z-index-2">
                            <div class="bg-gradient-primary shadow-primary border-radius-lg pt-4 pb-3">
                                <h6 class="text-white text-capitalize ps-3">Detalles del Usuario: {{ $user['fullname'] ?? 'N/A' }}</h6>
                            </div>
                        </div>
                        <div class="card-body px-4 pb-2">
                            @if(session('error'))
                                <div class="alert alert-danger alert-dismissible text-white fade show" role="alert">
                                    <span class="alert-icon align-middle"><span class="material-icons text-md">warning</span></span>
                                    <span class="alert-text"><strong>Error!</strong> {{ session('error') }}</span>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close">
                                        <span aria-hidden="true">&times;</span>
                                    </button>
                                </div>
                            @endif

                            @if (isset($user) && is_array($user))
                                <ul class="list-group">
                                    <li class="list-group-item border-0 ps-0 pt-0 text-sm"><strong class="text-dark">ID Moodle:</strong> {{ $user['id'] }}</li>
                                    <li class="list-group-item border-0 ps-0 text-sm"><strong class="text-dark">Nombre de Usuario:</strong> {{ $user['username'] ?? 'N/A' }}</li>
                                    <li class="list-group-item border-0 ps-0 text-sm"><strong class="text-dark">Nombre Completo:</strong> {{ $user['fullname'] ?? 'N/A' }}</li>
                                    <li class="list-group-item border-0 ps-0 text-sm"><strong class="text-dark">Email:</strong> {{ $user['email'] ?? 'N/A' }}</li>
                                    <li class="list-group-item border-0 ps-0 text-sm"><strong class="text-dark">Ciudad:</strong> {{ $user['city'] ?? 'N/A' }}</li>
                                    <li class="list-group-item border-0 ps-0 text-sm"><strong class="text-dark">País:</strong> {{ $user['country'] ?? 'N/A' }}</li>
                                    <li class="list-group-item border-0 ps-0 text-sm"><strong class="text-dark">Primer Acceso:</strong> {{ isset($user['firstaccess']) && $user['firstaccess'] > 0 ? date('Y-m-d H:i:s', $user['firstaccess']) : 'N/A' }}</li>
                                    <li class="list-group-item border-0 ps-0 text-sm"><strong class="text-dark">Último Acceso:</strong> {{ isset($user['lastaccess']) && $user['lastaccess'] > 0 ? date('Y-m-d H:i:s', $user['lastaccess']) : 'N/A' }}</li>
                                    <li class="list-group-item border-0 ps-0 text-sm"><strong class="text-dark">Idioma:</strong> {{ $user['lang'] ?? 'N/A' }}</li>
                                    <li class="list-group-item border-0 ps-0 text-sm"><strong class="text-dark">Zona Horaria:</strong> {{ $user['timezone'] ?? 'N/A' }}</li>
                                     <li class="list-group-item border-0 ps-0 text-sm"><strong class="text-dark">Suspendido:</strong> {{ isset($user['suspended']) && $user['suspended'] ? 'Sí' : 'No' }}</li>
                                    {{-- Puedes añadir más campos según la estructura de datos de Moodle y lo que devuelva la API --}}
                                    {{-- Por ejemplo, campos de perfil personalizados si están disponibles:
                                    @if (!empty($user['profilefields']))
                                        @foreach ($user['profilefields'] as $field_name => $field_value)
                                            <li class="list-group-item border-0 ps-0 text-sm"><strong class="text-dark">{{ $field_name }}:</strong> {{ $field_value }}</li>
                                        @endforeach
                                    @endif
                                    --}}
                                </ul>
                                <h6 class="mt-4">Datos Crudos (JSON):</h6>
                                <pre style="white-space: pre-wrap; word-wrap: break-word; background-color: #f8f9fa; padding: 10px; border-radius: 4px;">{{ json_encode($user, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                            @else
                                <p>No se pudieron cargar los detalles del usuario.</p>
                            @endif

                            <div class="mt-4">
                                <a href="{{ route('moodle.users.index') }}" class="btn btn-secondary">Volver a la lista</a>
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
