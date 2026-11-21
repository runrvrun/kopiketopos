<?php

namespace App\Http\Controllers;

use App\Support\StoreContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class StoreSelectionController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        abort_unless(StoreContext::for()->select((string) $request->input('store')), 403);

        return redirect()->back();
    }
}
