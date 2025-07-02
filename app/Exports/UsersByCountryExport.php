<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class UsersByCountryExport implements FromCollection, WithHeadings, ShouldAutoSize
{
    protected $data;

    public function __construct(array $data)
    {
        // Data should be in the format: [['category' => 'CountryName', 'value' => count], ...]
        $this->data = collect($data)->map(function ($item) {
            return [
                'Pais' => $item['category'], // Heading 'Pais'
                'CantidadUsuarios' => $item['value'], // Heading 'CantidadUsuarios'
            ];
        });
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
            'País',
            'Cantidad de Usuarios',
        ];
    }
}
