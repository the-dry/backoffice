<?php

namespace App\Imports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;

class MoodleUsersUpdateImport implements ToCollection, WithHeadingRow, SkipsEmptyRows
{
    /**
    * @param Collection $rows
    */
    public function collection(Collection $rows)
    {
        // Esta colección contendrá cada fila del Excel como un array asociativo
        // (si WithHeadingRow está habilitado y el Excel tiene cabeceras).
        // El controlador se encargará de procesar esta colección.
        // No hacemos procesamiento aquí para mantener la clase de importación simple.
        // Alternativamente, podríamos hacer validación o transformación aquí.
        return $rows;
    }

    // Opcional: definir reglas de validación aquí con WithValidation
    // public function rules(): array
    // {
    //     return [
    //         '*.id' => 'required|integer', // Moodle user ID
    //         '*.email' => 'sometimes|email',
    //         // ... otras reglas para columnas que podrías tener
    //     ];
    // }

    // Opcional: custom validation messages
    // public function customValidationMessages()
    // {
    //     return [
    //         '*.id.required' => 'La columna "id" (ID de Moodle) es requerida para cada usuario.',
    //         '*.email.email' => 'El email proporcionado no es válido.',
    //     ];
    // }
}
