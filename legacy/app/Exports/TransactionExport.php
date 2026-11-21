<?php

namespace App\Exports;

use App\Transaction;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;

class TransactionExport implements FromQuery, WithHeadings, WithMapping, WithColumnFormatting
{
    use Exportable;

    public function __construct(int $month)
    {
        $this->month = $month;
    }

    public function query()
    {
        $month = $this->month;
        $year = substr($month,0,4);
        $month = substr($month,4,2);
        return Transaction::query()->select('transactions.*','users.name')->join('users','user_id','users.id')->whereYear('transactions.created_at',$year)->whereMonth('transactions.created_at',$month);
    }

    public function headings(): array
    {
        return [
            'Date',
            'Time',
            'User',
            'Customer',
            'Total_Item',
            'Total_Price',
            'Discount',
            'Status',
        ];
    }

    public function map($row): array
    {
        return [
            Date::dateTimeToExcel($row->created_at),
            Date::dateTimeToExcel($row->created_at),
            $row->name,
            $row->customer,
            $row->total_item,
            $row->total_price,
            $row->status,
        ];
    }

    public function columnFormats(): array
    {
        return [
            'A' => NumberFormat::FORMAT_DATE_DDMMYYYY,
            'B' => NumberFormat::FORMAT_DATE_TIME3,
        ];
    }
}
