<x-layout bodyClass="g-sidenav-show bg-gray-200">
    <x-navbars.sidebar activePage="moodle-users"></x-navbars.sidebar>
    <main class="main-content position-relative max-height-vh-100 h-100 border-radius-lg">
        <!-- Navbar -->
        <x-navbars.navs.auth titlePage="Actualización Masiva de Usuarios Moodle"></x-navbars.navs.auth>
        <!-- End Navbar -->
        <div class="container-fluid py-4">
            <div class="row">
                <div class="col-12 col-md-8 mx-auto">
                    <div class="card my-4">
                        <div class="card-header p-0 position-relative mt-n4 mx-3 z-index-2">
                            <div class="bg-gradient-info shadow-info border-radius-lg pt-4 pb-3">
                                <h6 class="text-white text-capitalize ps-3">Subir Archivo (CSV, XLSX) para Actualización Masiva</h6>
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

                            <form method="POST" action="{{ route('moodle.users.mass-update.upload') }}" enctype="multipart/form-data">
                                @csrf
                                <div class="input-group input-group-static mb-4">
                                     <label for="user_file" class="form-label">Archivo de Usuarios (CSV, XLSX)</label>
                                     <input type="file" class="form-control" id="user_file" name="user_file" accept=".csv, .txt, application/vnd.openxmlformats-officedocument.spreadsheetml.sheet, application/vnd.ms-excel" required>
                                     @error('user_file')
                                        <p class='text-danger inputerror'>{{ $message }} </p>
                                     @enderror
                                </div>
                                <div class="mb-3">
                                    <p class="text-sm">
                                        <strong>Formato del archivo:</strong>
                                    </p>
                                    <ul class="text-sm">
                                        <li>La primera fila debe ser la cabecera.</li>
                                        <li>Una columna llamada <strong><code>id</code></strong> (con el ID de usuario de Moodle) es <strong>requerida</strong> para identificar a cada usuario.</li>
                                        <li>Incluya solo las columnas de los datos que desea actualizar. Ejemplos de nombres de cabecera: <code>email</code>, <code>firstname</code>, <code>lastname</code>, <code>idnumber</code>, <code>city</code>, <code>country</code>, <code>auth</code>.</li>
                                        <li>Los campos vacíos en el archivo generalmente no se enviarán para actualización (a menos que Moodle los interprete como un "borrado" de valor, lo cual depende del campo).</li>
                                    </ul>
                                     <p class="text-sm">
                                        <a href="#" id="downloadSampleUpdateCsv">Descargar CSV de ejemplo para actualización</a>
                                    </p>
                                </div>

                                <div class="text-center">
                                    <button type="submit" class="btn btn-info mt-3">Subir y Actualizar Usuarios</button>
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
        document.getElementById('downloadSampleUpdateCsv').addEventListener('click', function(e) {
            e.preventDefault();
            // Example with common updatable fields. 'id' is mandatory for identification.
            const csvContent = "id,email,firstname,lastname,idnumber,city,country\n" +
                               "123,nuevo.email@ejemplo.com,NuevoNombre,,NUEVOID001,NuevaCiudad,\n" + // Updates email, firstname, idnumber, city for user 123. Lastname and country unchanged.
                               "124,,OtroNombreActualizado,OtroApellidoActualizado,,,,CL"; // Updates firstname, lastname, country for user 124. Email unchanged.
            const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
            const link = document.createElement("a");
            if (link.download !== undefined) {
                const url = URL.createObjectURL(blob);
                link.setAttribute("href", url);
                link.setAttribute("download", "ejemplo_actualizacion_usuarios_moodle.csv");
                link.style.visibility = 'hidden';
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
            }
        });
    </script>
    @endpush
</x-layout>
