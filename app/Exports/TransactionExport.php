<?php

namespace App\Exports;

use App\Models\Transaction;
use Carbon\CarbonImmutable;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Monthly transaction export, same layout as v1:
 * Date | Time | User | Customer | Total_Item | Total_Price | Discount | Status
 *
 * v1 skipped the discount value, so its Discount column held the status and
 * Status was empty. Here each value sits under its own heading.
 */
class TransactionExport
{
    public const HEADINGS = ['Date', 'Time', 'User', 'Customer', 'Total_Item', 'Total_Price', 'Discount', 'Status'];

    /** @param  array<int>  $storeIds */
    public function __construct(private CarbonImmutable $month, private array $storeIds) {}

    public function filename(): string
    {
        return 'kopiketo-transaction-'.$this->month->format('Y-m').'.xlsx';
    }

    public function spreadsheet(): Spreadsheet
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Worksheet');
        $sheet->fromArray(self::HEADINGS, null, 'A1');

        $row = 2;
        Transaction::query()
            ->leftJoin('users', 'users.id', '=', 'transactions.user_id')
            ->select('transactions.*', 'users.name as user_name')
            ->forStores($this->storeIds)
            ->whereBetween('transactions.created_at', [$this->month->startOfMonth(), $this->month->endOfMonth()])
            ->orderBy('transactions.created_at')
            ->orderBy('transactions.id')
            ->chunk(1000, function ($chunk) use ($sheet, &$row) {
                foreach ($chunk as $t) {
                    $excelDate = Date::dateTimeToExcel($t->created_at);
                    $sheet->fromArray([
                        $excelDate,
                        $excelDate,
                        $t->user_name,
                        $t->customer,
                        $t->total_item,
                        $t->total_price,
                        $t->discount,
                        $t->status,
                    ], null, 'A'.$row, true);
                    $row++;
                }
            });

        $last = max(2, $row - 1);
        $sheet->getStyle("A2:A{$last}")->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_DATE_DDMMYYYY);
        $sheet->getStyle("B2:B{$last}")->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_DATE_TIME3);

        return $spreadsheet;
    }

    public function writer(): Xlsx
    {
        return new Xlsx($this->spreadsheet());
    }
}
