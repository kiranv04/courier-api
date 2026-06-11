<?php

namespace App\Helpers;

class AmountInWords
{
    private static array $ones = [
        '', 'ONE', 'TWO', 'THREE', 'FOUR', 'FIVE', 'SIX', 'SEVEN', 'EIGHT', 'NINE',
        'TEN', 'ELEVEN', 'TWELVE', 'THIRTEEN', 'FOURTEEN', 'FIFTEEN', 'SIXTEEN',
        'SEVENTEEN', 'EIGHTEEN', 'NINETEEN',
    ];

    private static array $tens = [
        '', '', 'TWENTY', 'THIRTY', 'FORTY', 'FIFTY', 'SIXTY', 'SEVENTY', 'EIGHTY', 'NINETY',
    ];

    private static function spellBelow1000(int $n): string
    {
        if ($n === 0) return '';

        if ($n < 20) return self::$ones[$n];

        if ($n < 100) {
            $remainder = $n % 10;
            return self::$tens[(int)($n / 10)] . ($remainder ? ' ' . self::$ones[$remainder] : '');
        }

        $remainder = $n % 100;
        return self::$ones[(int)($n / 100)] . ' HUNDRED' . ($remainder ? ' ' . self::spellBelow1000($remainder) : '');
    }

    public static function spell(int $n): string
    {
        if ($n === 0) return 'ZERO';

        $parts = [];

        $scales = [
            'CRORE'   => 10000000,
            'LAKH'    => 100000,
            'THOUSAND'=> 1000,
            'HUNDRED' => 100,  // handled inside spellBelow1000, kept for structure
        ];

        if ($n >= 10000000) {
            $parts[] = self::spellBelow1000((int)($n / 10000000)) . ' CRORE';
            $n %= 10000000;
        }
        if ($n >= 100000) {
            $parts[] = self::spellBelow1000((int)($n / 100000)) . ' LAKH';
            $n %= 100000;
        }
        if ($n >= 1000) {
            $parts[] = self::spellBelow1000((int)($n / 1000)) . ' THOUSAND';
            $n %= 1000;
        }
        if ($n > 0) {
            $parts[] = self::spellBelow1000($n);
        }

        return implode(' ', $parts);
    }

    public static function convert(float|string $amount): string
    {
        $amount = round((float) $amount, 2);

        [$rupees, $paise] = explode('.', number_format($amount, 2, '.', ''));
        $rupees = (int) str_replace(',', '', $rupees);
        $paise  = (int) $paise;

        $rupeesWords = self::spell($rupees);

        if ($paise > 0) {
            $paiseWords = self::spell($paise);
            return "RUPEES {$rupeesWords} AND {$paiseWords} PAISE ONLY";
        }

        return "RUPEES {$rupeesWords} ONLY";
    }
}