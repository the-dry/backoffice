<x-layout bodyClass="g-sidenav-show bg-gray-200">
    <x-navbars.sidebar activePage="moodle-users"></x-navbars.sidebar>
    <main class="main-content position-relative max-height-vh-100 h-100 border-radius-lg">
        <!-- Navbar -->
        <x-navbars.navs.auth titlePage="Creación Masiva de Usuarios Moodle"></x-navbars.navs.auth>
        <!-- End Navbar -->
        <div class="container-fluid py-4">
            <div class="row">
                <div class="col-12 col-md-8 mx-auto">
                    <div class="card my-4">
                        <div class="card-header p-0 position-relative mt-n4 mx-3 z-index-2">
                            <div class="bg-gradient-primary shadow-primary border-radius-lg pt-4 pb-3">
                                <h6 class="text-white text-capitalize ps-3">Subir Archivo CSV para Creación Masiva</h6>
                            </div>
                        </div>
                        <div class="card-body px-4 pb-3">

                            @if(session('success'))
                                <div class="alert alert-success alert-dismissible text-white fade show" role="alert">
                                    <span class="alert-icon align-middle"><span class="material-icons text-md">check_circle</span></span>
                                    <span class="alert-text"><strong>Éxito!</strong> {{ session('success') }}</span>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close">
                                        <span aria-hidden="true">&times;</span>
                                    </button>
                                </div>
                            @endif

                            @if(session('error'))
                                <div class="alert alert-danger alert-dismissible text-white fade show" role="alert">
                                    <span class="alert-icon align-middle"><span class="material-icons text-md">warning</span></span>
                                    <span class="alert-text"><strong>Error!</strong> {{ session('error') }}</span>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close">
                                        <span aria-hidden="true">&times;</span>
                                    </button>
                                </div>
                            @endif

                            @if(session('upload_errors') && is_array(session('upload_errors')))
                                <div class="alert alert-warning alert-dismissible text-dark fade show" role="alert">
                                    <strong>Se encontraron los siguientes problemas con el archivo o los datos:</strong>
                                    <ul>
                                        @foreach(session('upload_errors') as $errMsg)
                                            <li>{{ $errMsg }}</li>
                                        @endforeach
                                    </ul>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close">
                                        <span aria-hidden="true">&times;</span>
                                    </button>
                                </div>
                            @endif

                            <form method="POST" action="{{ route('moodle.users.mass-create.upload') }}" enctype="multipart/form-data">
                                @csrf
                                <div class="input-group input-group-static mb-4">
                                     <label for="user_file" class="form-label">Archivo CSV de Usuarios</label>
                                     <input type="file" class="form-control" id="user_file" name="user_file" required>
                                     @error('user_file')
                                        <p class='text-danger inputerror'>{{ $message }} </p>
                                     @enderror
                                </div>
                                <div class="mb-3">
                                    <p class="text-sm">
                                        <strong>Formato del archivo CSV:</strong>
                                    </p>
                                    <ul class="text-sm">
                                        <li>La primera fila debe ser la cabecera.</li>
                                        <li>Columnas requeridas (los nombres de cabecera deben coincidir exactamente): <code>username</code>, <code>password</code>, <code>firstname</code>, <code>lastname</code>, <code>email</code>.</li>
                                        <li>Columnas opcionales (ejemplos): <code>idnumber</code>, <code>city</code>, <code>country</code> (código de 2 letras), <code>auth</code> (e.g., 'manual').</li>
                                        <li>Ejemplo de preferencia para forzar cambio de contraseña: una columna llamada <code>forcepasswordchange</code> con valor <code>1</code> (esto requeriría mapeo en el controlador para convertirlo al formato de `preferences` de Moodle). Por simplicidad, el controlador actual no implementa mapeo complejo de `preferences` o `customfields` desde CSV directamente.</li>
                                    </ul>
                                    <p class="text-sm">
                                        <a href="#" id="downloadSampleCsv">Descargar CSV de ejemplo</a>
                                    </p>
                                </div>

                                <div class="text-center">
                                    <button type="submit" class="btn btn-primary mt-3">Subir y Crear Usuarios</button>
                                </div>
                                <div class="text-start mt-3">
                                     <a href="{{ route('moodle.users.index') }}" class="btn btn-secondary btn-sm">Volver a la lista</a>
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
        document.getElementById('downloadSampleCsv').addEventListener('click', function(e) {
            e.preventDefault();
            const csvContent = "username,password,firstname,lastname,email,idnumber,city,country,auth\n" +
                               "usuarioejemplo1,P@$$wOrd1,NombreUno,ApellidoUno,uno@ejemplo.com,ID001,CiudadUno,CL,manual\n" +
                               "usuarioejemplo2,P@$$wOrd2,NombreDos,ApellidoDos,dos@ejemplo.com,ID002,CiudadDos,CL,manual";
            const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
            const link = document.createElement("a");
            if (link.download !== undefined) {
                const url = URL.createObjectURL(blob);
                link.setAttribute("href", url);
                link.setAttribute("download", "ejemplo_usuarios_moodle.csv");
                link.style.visibility = 'hidden';
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
            }
        });
    </script>
    @endpush
</x-layout>
