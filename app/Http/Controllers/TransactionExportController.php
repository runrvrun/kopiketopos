<?php

namespace App\Http\Controllers;

use App\Exports\TransactionExport;
use App\Support\StoreContext;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TransactionExportController extends Controller
{
    public function __invoke(Request $request): StreamedResponse
    {
        $validated = $request->validate(['bulan' => ['required', 'regex:/^\d{4}(0[1-9]|1[0-2])$/']]);

        $month = CarbonImmutable::createFromFormat('!Ym', $validated['bulan']);
        $export = new TransactionExport($month, StoreContext::for()->storeIds());

        return response()->streamDownload(
            fn () => $export->writer()->save('php://output'),
            $export->filename(),
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        );
    }
}
