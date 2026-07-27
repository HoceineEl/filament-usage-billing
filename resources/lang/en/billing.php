<?php

declare(strict_types=1);

return [

    'nav' => [
        'group' => 'Billing',
    ],

    'subscription_status' => [
        'pending_payment' => 'Awaiting payment',
        'trialing' => 'Trial',
        'active' => 'Active',
        'past_due' => 'Past due',
        'suspended' => 'Suspended',
        'cancelled' => 'Cancelled',
        'expired' => 'Expired',
    ],

    'invoice_status' => [
        'draft' => 'Draft',
        'issued' => 'Issued',
        'partially_paid' => 'Partially paid',
        'paid' => 'Paid',
        'overdue' => 'Overdue',
        'cancelled' => 'Cancelled',
    ],

    'payment_method' => [
        'virement' => 'Bank transfer',
        'cheque' => 'Cheque',
        'especes' => 'Cash',
        'card' => 'Card',
    ],

    'payment_status' => [
        'pending' => 'Awaiting validation',
        'validated' => 'Validated',
        'rejected' => 'Rejected',
    ],

    'gate_reason' => [
        'allowed' => 'Allowed',
        'no_subscription' => 'No subscription',
        'subscription_inactive' => 'Subscription inactive',
        'module_not_in_plan' => 'Not included in plan',
        'ceiling_reached' => 'Limit reached',
    ],

    'event_type' => [
        'created' => 'Subscription created',
        'plan_changed' => 'Plan changed',
        'status_changed' => 'Status changed',
        'renewed' => 'Renewed',
        'cancelled' => 'Cancelled',
        'usage_threshold' => 'Usage threshold reached',
        'invoice_issued' => 'Invoice issued',
        'payment_validated' => 'Payment validated',
    ],

    'denied' => [
        'no_subscription' => 'This feature needs an active subscription.',
        'subscription_inactive' => 'Your subscription is inactive. Settle the outstanding invoice to continue.',
        'module_not_in_plan' => 'This feature is not included in your current plan.',
        'ceiling_reached' => 'You have reached this month\'s limit of :ceiling. Upgrade your plan to continue.',
        'allowed' => '',
    ],

    'errors' => [
        'module_unavailable' => 'Module [:module] is unavailable (:reason).',
    ],

    'lines' => [
        'term' => ':plan — :from to :to',
        'upgrade' => 'Upgrade from :from to :to — until :until',
        'base_plan' => ':plan — :period',
        'overage' => ':module — :unit beyond the :allowance included',
    ],

    'plan' => [
        'label' => 'Plan',
        'plural' => 'Plans',
        'tva_suffix' => 'excl. tax · VAT :rate%',
        'sections' => [
            'identity' => 'Identity',
            'pricing' => 'Pricing',
            'modules' => 'Included modules',
            'availability' => 'Availability',
        ],
        'fields' => [
            'slug' => 'Slug',
            'name' => 'Name',
            'sort_order' => 'Order',
            'price_ht' => 'Monthly price excl. tax',
            'tva_rate' => 'VAT rate',
            'currency' => 'Currency',
            'trial_days' => 'Trial period',
            'payment_term_days' => 'Payment term',
            'grace_days' => 'Grace period',
            'module' => 'Module',
            'included_quantity' => 'Included allowance',
            'unit_price_ht' => 'Overage unit price',
            'hard_ceiling' => 'Blocking ceiling',
            'is_active' => 'Active',
            'is_public' => 'Publicly listed',
            'modules_count' => 'Modules',
            'subscribers' => 'Subscribers',
        ],
        'units' => [
            'days' => 'days',
        ],
        'placeholders' => [
            'unlimited' => 'Unlimited',
            'blocks' => 'Blocks at allowance',
            'no_ceiling' => 'None',
        ],
        'help' => [
            'slug' => 'Used in code and URLs. Do not change it once the plan has been sold.',
            'grace_days' => 'Days of continued access after an unpaid invoice falls due.',
            'modules' => 'A module left off a plan is simply switched off for its subscribers.',
            'included_quantity' => 'Empty means unlimited and never billed.',
            'unit_price_ht' => 'Empty means usage stops at the allowance instead of billing.',
            'hard_ceiling' => 'Empty means overage is never blocked.',
            'is_public' => 'Uncheck for a negotiated plan offered to specific accounts only.',
        ],
        'summary' => [
            'unlimited' => 'unlimited',
            'capped' => ':included max',
            'metered' => ':included included, then :price :currency',
        ],
        'actions' => [
            'add_module' => 'Add a module',
        ],
        'empty' => [
            'heading' => 'No plans yet',
            'description' => 'Create a plan to start billing.',
        ],
    ],

    'module' => [
        'label' => 'Module',
        'plural' => 'Modules',
        'fields' => [
            'key' => 'Module',
            'class' => 'Class',
            'plans_count' => 'Plans',
            'is_active' => 'Active',
        ],
        'actions' => [
            'sync' => 'Sync',
        ],
        'notifications' => [
            'synced' => 'Registry synced',
            'synced_body' => ':created created, :updated updated, :deactivated deactivated.',
        ],
        'empty' => [
            'heading' => 'No modules registered',
            'description' => 'Declare your modules in the config, then sync.',
        ],
    ],

    'subscription' => [
        'label' => 'Subscription',
        'plural' => 'Subscriptions',
        'deleted_subscriber' => 'Deleted subscriber',
        'grace_until' => 'Grace until :date',
        'sections' => [
            'overview' => 'Overview',
        ],
        'fields' => [
            'subscriber' => 'Subscriber',
            'plan' => 'Plan',
            'status' => 'Status',
            'starts_at' => 'Started',
            'trial_ends_at' => 'Trial ends',
            'grace_ends_at' => 'Grace ends',
            'cancelled_at' => 'Cancelled',
            'invoices' => 'Invoices',
            'with_trial' => 'Open a trial',
        ],
        'tabs' => [
            'attention' => 'Needs attention',
            'active' => 'Active',
            'all' => 'All',
        ],
        'actions' => [
            'start' => 'Create a subscription',
            'issue_term' => 'Invoice the term',
            'upgrade' => 'Move up a plan',
            'change_plan' => 'Change plan',
            'cancel' => 'Cancel',
        ],
        'help' => [
            'start' => 'The term invoice goes out immediately. Access opens on payment.',
            'with_trial' => 'The subscriber gets access during the trial, before paying.',
            'issue_term' => 'Issues the invoice for the next term. An invoice already outstanding is reused.',
            'upgrade' => 'The difference is invoiced pro rata for the months left. The new plan applies on payment.',
            'change_plan' => 'An immediate administrative switch, with no invoice. Use "Move up a plan" to sell a change.',
            'cancel' => 'The subscriber loses access. A term already paid for is not refunded.',
        ],
        'notifications' => [
            'started' => 'Subscription created',
            'already_subscribed' => 'This subscriber already has a running subscription',
            'term_invoiced' => 'Invoice :number issued',
            'upgrade_invoiced' => 'Supplement :number issued',
            'no_upgrade' => 'Nothing to invoice for that plan',
            'plan_changed' => 'Plan changed',
            'cancelled' => 'Subscription cancelled',
        ],
        'empty' => [
            'heading' => 'No subscriptions',
            'description' => 'Subscriptions appear here as soon as the first account signs up.',
        ],
    ],

    'invoice' => [
        'label' => 'Invoice',
        'plural' => 'Invoices',
        'title' => 'Invoice',
        'draft_placeholder' => 'Draft',
        'balance_short' => ':amount due',
        'no_usage' => 'No usage recorded this period.',
        'unattributed' => 'Unattributed',
        'sections' => [
            'buyer' => 'Customer',
            'totals' => 'Totals',
            'lines' => 'Detail',
            'usage' => 'Usage by client',
        ],
        'fields' => [
            'number' => 'Number',
            'period' => 'Period',
            'status' => 'Status',
            'issued_at' => 'Issue date',
            'due_at' => 'Due date',
            'ice' => 'ICE',
            'identifiant_fiscal' => 'Tax ID',
            'address' => 'Address',
            'description' => 'Description',
            'quantity' => 'Qty',
            'unit_price' => 'Unit price',
            'amount' => 'Amount',
            'amount_ht' => 'Amount excl. tax',
            'subtotal_ht' => 'Subtotal excl. tax',
            'tva' => 'VAT (:rate%)',
            'total_ttc' => 'Total incl. tax',
            'amount_paid' => 'Paid',
            'balance_due' => 'Balance due',
        ],
        'tabs' => [
            'outstanding' => 'Outstanding',
            'overdue' => 'Overdue',
            'paid' => 'Paid',
            'all' => 'All',
        ],
        'actions' => [
            'record_payment' => 'Record a payment',
            'download' => 'Download',
        ],
        'help' => [
            'usage' => 'Frozen at issue: this breakdown will not change, even if a client is renamed later.',
        ],
        'empty' => [
            'heading' => 'No invoices',
            'description' => 'Invoices are generated when each month closes.',
        ],
        'billed_to' => 'Billed to',
        'usage_annex' => 'Usage detail',
        'client' => 'Client',
        'consumption' => 'Consumption',
    ],

    'payment' => [
        'label' => 'Payment',
        'plural' => 'Payments',
        'fields' => [
            'from' => 'Account',
            'amount' => 'Amount',
            'method' => 'Method',
            'status' => 'Status',
            'paid_at' => 'Payment date',
            'reference' => 'Reference',
            'rejection_reason' => 'Rejection reason',
            'notes' => 'Notes',
        ],
        'tabs' => [
            'pending' => 'To validate',
            'validated' => 'Validated',
            'all' => 'All',
        ],
        'actions' => [
            'validate' => 'Validate',
            'reject' => 'Reject',
            'receipt' => 'Receipt',
        ],
        'help' => [
            'validate' => 'Confirm you have seen :amount arrive for :buyer. The invoice will be updated.',
            'reject' => 'The reason is visible to the subscriber.',
            'reference' => 'Transfer or cheque number, for reconciliation.',
        ],
        'notifications' => [
            'recorded' => 'Payment recorded',
            'validated' => 'Payment validated',
            'rejected' => 'Payment rejected',
        ],
        'empty' => [
            'heading' => 'Nothing awaiting validation',
            'description' => 'Transfers declared by subscribers land here.',
        ],
    ],

    'event' => [
        'plural' => 'History',
        'fields' => [
            'at' => 'Date',
            'type' => 'Event',
            'change' => 'Detail',
        ],
        'threshold_line' => ':module reached :threshold% of its allowance in :period',
        'empty' => [
            'heading' => 'No events',
        ],
    ],

];
