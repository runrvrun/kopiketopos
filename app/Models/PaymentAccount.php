<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/** Bank account for transfers, shown on receipts that aren't paid yet. */
#[Fillable(['bank', 'account_number', 'account_owner'])]
class PaymentAccount extends Model
{
    //
}
