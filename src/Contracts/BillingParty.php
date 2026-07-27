<?php

declare(strict_types=1);

namespace HoceineEl\UsageBilling\Contracts;

/**
 * The legal identity printed on a facture. Implemented by the subscriber and
 * snapshotted onto the invoice at issue, so later corrections to the record
 * never rewrite documents already sent.
 *
 * @phpstan-type BuyerSnapshot array{
 *     buyer_name: string,
 *     buyer_ice: ?string,
 *     buyer_identifiant_fiscal: ?string,
 *     buyer_address: ?string,
 *     buyer_email: ?string,
 * }
 */
interface BillingParty
{
    public function billingName(): string;

    public function billingIce(): ?string;

    public function billingIdentifiantFiscal(): ?string;

    public function billingAddress(): ?string;

    public function billingEmail(): ?string;
}
