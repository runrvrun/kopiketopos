<?php

namespace Tests\Feature;

use App\Exports\TransactionExport;
use App\Models\Transaction;
use Carbon\CarbonImmutable;
use PhpOffice\PhpSpreadsheet\Shared\Date;

class ExportTest extends PosTestCase
{
    public function test_monthly_export_matches_the_v1_layout(): void
    {
        $admin = $this->admin();
        $this->travelTo(CarbonImmutable::parse('2026-11-05 14:30:00'));
        Transaction::create(['store_id' => $this->pamulang->id, 'user_id' => $admin->id, 'customer' => 'Budi', 'total_item' => 2, 'total_price' => 50000, 'discount' => 5000, 'status' => 'Paid']);
        Transaction::create(['store_id' => $this->senopati->id, 'total_item' => 1, 'total_price' => 20000]);
        $this->travelTo(CarbonImmutable::parse('2026-10-31 23:00:00'));
        Transaction::create(['store_id' => $this->pamulang->id, 'total_item' => 1, 'total_price' => 10000, 'status' => 'Paid']);
        $this->travelBack();

        $this->actingAs($admin)
            ->get(route('admin.history.download', ['bulan' => '202611']))
            ->assertOk()
            ->assertDownload('kopiketo-transaction-2026-11.xlsx');

        $sheet = (new TransactionExport(CarbonImmutable::parse('2026-11-01'), [$this->pamulang->id, $this->senopati->id]))
            ->spreadsheet()->getActiveSheet()->toArray(null, false, false);

        $this->assertSame(TransactionExport::HEADINGS, $sheet[0]);
        $this->assertCount(3, $sheet); // header + 2 November rows
        $this->assertSame('2026-11-05 14:30', Date::excelToDateTimeObject($sheet[1][0])->format('Y-m-d H:i'));
        $this->assertSame([$admin->name, 'Budi', 2, 50000, 5000, 'Paid'], array_slice($sheet[1], 2));
        $this->assertSame([null, null, 1, 20000, null, null], array_slice($sheet[2], 2));
    }

    public function test_invalid_month_is_rejected(): void
    {
        $this->actingAs($this->admin())->get(route('admin.history.download', ['bulan' => '202613']))->assertSessionHasErrors('bulan');
    }
}
