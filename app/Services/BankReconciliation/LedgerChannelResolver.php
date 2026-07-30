<?php

namespace App\Services\BankReconciliation;

/**
 * Infers a donations.php payment method from a raw bank statement description
 * (e.g. "BDO OVER THE COUNTER ICTSI FND" -> bank_transfer). Kept separate from
 * the matcher so future statement-import code can reuse the same channel rules.
 */
class LedgerChannelResolver
{
    public function resolve(string $description): ?string
    {
        $normalized = strtolower($description);

        foreach (config('bank_reconciliation.channel_keywords') as $keyword => $paymentMethod) {
            if (str_contains($normalized, $keyword)) {
                return $paymentMethod;
            }
        }

        return null;
    }
}
