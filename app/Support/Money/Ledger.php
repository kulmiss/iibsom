<?php

namespace App\Support\Money;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Double-entry book-keeping in whole cents. Every transaction is a set of lines
 * whose debits equal its credits; lines are only ever added, never changed.
 */
class Ledger
{
    /**
     * @param  list<array{account: string, debit?: int, credit?: int}>  $lines
     * @return string the transaction id
     */
    public static function post(array $lines, bool $isTest, ?int $paymentId = null, ?string $memo = null, string $currency = 'USD'): string
    {
        if (count($lines) < 2) {
            throw new InvalidArgumentException('A ledger transaction needs at least two lines.');
        }
        $debits = 0;
        $credits = 0;
        foreach ($lines as $line) {
            $debit = $line['debit'] ?? 0;
            $credit = $line['credit'] ?? 0;
            if ($debit < 0 || $credit < 0 || ($debit > 0) === ($credit > 0)) {
                throw new InvalidArgumentException('Each line is either a debit or a credit, in positive whole cents.');
            }
            if (preg_match('/^[a-z]+:[a-z_]+$/', $line['account']) !== 1) {
                throw new InvalidArgumentException("Bad account name [{$line['account']}].");
            }
            $debits += $debit;
            $credits += $credit;
        }
        if ($debits !== $credits) {
            throw new InvalidArgumentException("Debits ({$debits}) and credits ({$credits}) must be equal.");
        }

        $id = (string) Str::uuid();
        DB::table('ledger_entries')->insert(array_map(fn (array $line) => [
            'transaction_id' => $id,
            'account' => $line['account'],
            'debit_cents' => $line['debit'] ?? 0,
            'credit_cents' => $line['credit'] ?? 0,
            'currency' => $currency,
            'payment_id' => $paymentId,
            'is_test' => $isTest,
            'memo' => $memo,
            'created_at' => now(),
        ], $lines));

        return $id;
    }

    /**
     * Balance of each account (debits − credits) for real or test money.
     *
     * @return array<string, int>
     */
    public static function balances(bool $isTest): array
    {
        return DB::table('ledger_entries')->where('is_test', $isTest)->groupBy('account')->orderBy('account')
            ->selectRaw('account, CAST(SUM(debit_cents) AS SIGNED) - CAST(SUM(credit_cents) AS SIGNED) AS balance')
            ->pluck('balance', 'account')->map(fn ($v) => (int) $v)->all();
    }

    /**
     * Transactions whose debits and credits are not equal (must always be none).
     *
     * @return list<string>
     */
    public static function unbalanced(): array
    {
        return array_values(DB::table('ledger_entries')->groupBy('transaction_id')
            ->havingRaw('SUM(debit_cents) <> SUM(credit_cents)')->pluck('transaction_id')->map(fn ($v) => (string) $v)->all());
    }
}
