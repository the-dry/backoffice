<x-layout bodyClass="g-sidenav-show bg-gray-200">
    <x-navbars.sidebar activePage="moodle-users"></x-navbars.sidebar>
    <main class="main-content position-relative max-height-vh-100 h-100 border-radius-lg">
        <!-- Navbar -->
        <x-navbars.navs.auth titlePage="Gestión de Usuarios Moodle"></x-navbars.navs.auth>
        <!-- End Navbar -->
        <div class="container-fluid py-4">
            <div class="row">
                <div class="col-12">
                    <div class="card my-4">
                        <div class="card-header p-0 position-relative mt-n4 mx-3 z-index-2">
                            <div class="bg-gradient-primary shadow-primary border-radius-lg pt-4 pb-3">
                                <h6 class="text-white text-capitalize ps-3">Usuarios de Moodle</h6>
                            </div>
                        </div>
                        <div class="card-body px-0 pb-2">
                            <!-- Search form -->
                            <div class="px-4 py-3">
                                <form method="GET" action="{{ route('moodle.users.index') }}">
                                    <div class="input-group input-group-outline">
                                        <label class="form-label">Buscar por email...</label>
                                        <input type="text" class="form-control" name="search" value="{{ $searchTerm ?? '' }}">
                                        <button type="submit" class="btn btn-primary mb-0">Buscar</button>
                                    </div>
                                </form>
                            </div>
                             <div class="px-4 pb-0 pt-0 text-end">
                                <a href="{{ route('moodle.users.mass-create.form') }}" class="btn btn-success btn-sm mb-0">
                                    <i class="material-icons text-sm">group_add</i>&nbsp;&nbsp;Creación Masiva de Usuarios
                                </a>
                            </div>

                            @if(session('success'))
                                <div class="alert alert-success alert-dismissible text-white fade show mx-4" role="alert">
                                    <span class="alert-icon align-middle">
                                        <span class="material-icons text-md">check_circle</span>
                                    </span>
                                    <span class="alert-text"><strong>Éxito!</strong> {{ session('success') }}</span>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close">
                                        <span aria-hidden="true">&times;</span>
                                    </button>
                                </div>
                            @endif
                            @if(session('error'))
                                <div class="alert alert-danger alert-dismissible text-white fade show mx-4" role="alert">
                                    <span class="alert-icon align-middle">
                                        <span class="material-icons text-md">warning</span>
                                    </span>
                                    <span class="alert-text"><strong>Error!</strong> {{ session('error') }}</span>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close">
                                        <span aria-hidden="true">&times;</span>
                                    </button>
                                </div>
                            @endif
                            @if(session('moodle_warnings'))
                                <div class="alert alert-warning alert-dismissible text-white fade show mx-4" role="alert">
                                    <span class="alert-icon align-middle">
                                        <span class="material-icons text-md">info</span>
                                    </span>
                                    <span class="alert-text"><strong>Advertencias de Moodle:</strong>
                                        <ul>
                                            @foreach(session('moodle_warnings') as $warning)
                                                <li>{{ $warning['message'] ?? 'Advertencia desconocida' }}</li>
                                            @endforeach
                                        </ul>
                                    </span>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close">
                                        <span aria-hidden="true">&times;</span>
                                    </button>
                                </div>
                            @endif

                            <div class="table-responsive p-0">
                                <table class="table align-items-center mb-0">
                                    <thead>
                                        <tr>
                                            <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">ID</th>
                                            <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Nombre Completo</th>
                                            <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Email</th>
                                            <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Username</th>
                                            <th class="text-center text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($users as $user)
                                            <tr>
                                                <td>
                                                    <div class="d-flex px-3 py-1">
                                                        <div class="d-flex flex-column justify-content-center">
                                                            <h6 class="mb-0 text-sm">{{ $user['id'] }}</h6>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <p class="text-xs font-weight-bold mb-0">{{ $user['fullname'] ?? 'N/A' }}</p>
                                                </td>
                                                <td>
                                                    <p class="text-xs font-weight-bold mb-0">{{ $user['email'] ?? 'N/A' }}</p>
                                                </td>
                                                <td>
                                                    <p class="text-xs font-weight-bold mb-0">{{ $user['username'] ?? 'N/A' }}</p>
                                                </td>
                                                <td class="align-middle text-center text-sm">
                                                    <a href="{{ route('moodle.users.show', $user['id']) }}" class="btn btn-sm btn-info mb-0">Ver</a>
                                                    {{-- Add other actions like Edit, Delete if needed later --}}
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="5" class="text-center py-4">
                                                    <p class="text-xs font-weight-bold mb-0">No se encontraron usuarios de Moodle.</p>
                                                    @if(empty($searchTerm))
                                                        <p class="text-xs mb-0">Intente una búsqueda para encontrar usuarios.</p>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                            <div class="px-4 py-3">
                                {{ $users->links() }}
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
