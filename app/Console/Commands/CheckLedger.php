<?php

namespace App\Console\Commands;

use App\Models\Payment;
use App\Support\Money\Ledger;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/** Proves the books are right: every transaction balances and every approved payment is in the ledger exactly once. */
class CheckLedger extends Command
{
    protected $signature = 'iibsom:ledger:check';

    protected $description = 'Check that the money ledger balances';

    public function handle(): int
    {
        $problems = [];

        foreach (Ledger::unbalanced() as $id) {
            $problems[] = "Transaction {$id}: debits and credits are not equal.";
        }

        $approved = Payment::where('status', 'approved')->pluck('amount_cents', 'id');
        $booked = DB::table('ledger_entries')->whereNotNull('payment_id')->where('account', 'revenue:boosts')
            ->groupBy('payment_id')->selectRaw('payment_id, SUM(credit_cents) AS total, COUNT(*) AS n')->get()->keyBy('payment_id');
        foreach ($approved as $id => $cents) {
            $row = $booked->get($id);
            if ($row === null || (int) $row->n !== 1 || (int) $row->total !== (int) $cents) {
                $problems[] = "Payment #{$id}: approved for {$cents} cents but booked ".($row ? "{$row->total} cents in {$row->n} line(s)" : 'nowhere').'.';
            }
        }
        foreach ($booked->keys()->diff($approved->keys()) as $id) {
            $problems[] = "Payment #{$id}: in the ledger but not approved.";
        }

        if ($problems === []) {
            $this->info('Ledger OK: '.count($approved).' approved payment(s), all balanced.');

            return self::SUCCESS;
        }
        foreach ($problems as $p) {
            $this->error($p);
        }

        return self::FAILURE;
    }
}
