<?php

if (! function_exists('rupiah')) {
    /** Format an amount as Indonesian Rupiah, e.g. Rp12.500. */
    function rupiah(int|float|null $amount): string
    {
        return 'Rp'.number_format((float) $amount, 0, ',', '.');
    }
}

if (! function_exists('rupiah_short')) {
    /** Compact Rupiah for chart labels: Rp850, Rp12,5 rb, Rp2,1 jt, Rp1,3 M. */
    function rupiah_short(int|float|null $amount): string
    {
        $amount = (float) $amount;
        [$div, $unit] = match (true) {
            $amount >= 1e9 => [1e9, ' M'],
            $amount >= 1e6 => [1e6, ' jt'],
            $amount >= 1e3 => [1e3, ' rb'],
            default => [1, ''],
        };
        $value = $amount / $div;

        return 'Rp'.number_format($value, $value < 10 && $div > 1 ? 1 : 0, ',', '.').$unit;
    }
}
