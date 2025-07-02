<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithTitle;

class CourseProgressExport implements FromCollection, WithHeadings, ShouldAutoSize, WithTitle
{
    protected $data;
    protected $courseName;

    public function __construct(array $data, string $courseName)
    {
        // Data should be an array of arrays, where each inner array is a user's progress row
        // Example: [['id' => ..., 'fullname' => ..., 'email' => ..., 'completion_status' => ..., 'grade' => ...], ...]
        $this->data = collect($data)->map(function ($item) {
            return [
                'ID Usuario' => $item['id'] ?? 'N/A',
                'Nombre Completo' => $item['fullname'] ?? 'N/A',
                'Email' => $item['email'] ?? 'N/A',
                'Username' => $item['username'] ?? 'N/A',
                'Estado Completitud' => $item['completion_status'] ?? 'N/A',
                'Calificación Final' => $item['grade'] ?? 'N/A',
                'Primer Acceso Curso' => $item['firstaccess'] ?? 'N/A',
                'Último Acceso Curso' => $item['lastaccess'] ?? 'N/A',
            ];
        });
        $this->courseName = $courseName;
    }

    /**
    * @return \Illuminate\Support\Collection
    */
    public function collection()
    {
        return $this->data;
    }

    public function headings(): array
    {
        return [
            'ID Usuario',
            'Nombre Completo',
            'Email',
            'Username',
            'Estado Completitud',
            'Calificación Final',
            'Primer Acceso al Curso',
            'Último Acceso al Curso',
        ];
    }

    /**
     * @return string
     */
    public function title(): string
    {
        // Generate a sheet title, remove invalid characters for Excel sheet names
        $safeCourseName = preg_replace('/[\\\\:\\/\\?\\*\\[\\]]+/', '', $this->courseName);
        return substr('Progreso - ' . $safeCourseName, 0, 31); // Max 31 chars for sheet title
    }
}
