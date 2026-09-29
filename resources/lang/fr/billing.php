<?php

declare(strict_types=1);

return [

    'nav' => [
        'group' => 'Facturation',
    ],

    'subscription_status' => [
        'pending_payment' => 'En attente de règlement',
        'trialing' => 'Essai',
        'active' => 'Actif',
        'past_due' => 'Impayé',
        'suspended' => 'Suspendu',
        'cancelled' => 'Résilié',
        'expired' => 'Expiré',
    ],

    'invoice_status' => [
        'draft' => 'Brouillon',
        'issued' => 'Émise',
        'partially_paid' => 'Partiellement réglée',
        'paid' => 'Réglée',
        'overdue' => 'En retard',
        'cancelled' => 'Annulée',
    ],

    'payment_method' => [
        'virement' => 'Virement bancaire',
        'cheque' => 'Chèque',
        'especes' => 'Espèces',
        'card' => 'Carte bancaire',
    ],

    'payment_status' => [
        'pending' => 'En attente de validation',
        'validated' => 'Validé',
        'rejected' => 'Rejeté',
    ],

    'gate_reason' => [
        'allowed' => 'Autorisé',
        'no_subscription' => 'Aucun abonnement',
        'subscription_inactive' => 'Abonnement inactif',
        'module_not_in_plan' => 'Non inclus dans la formule',
        'ceiling_reached' => 'Plafond atteint',
    ],

    'event_type' => [
        'created' => 'Abonnement créé',
        'plan_changed' => 'Formule modifiée',
        'status_changed' => 'Statut modifié',
        'renewed' => 'Renouvelé',
        'cancelled' => 'Résilié',
        'usage_threshold' => 'Seuil de consommation atteint',
        'invoice_issued' => 'Facture émise',
        'payment_validated' => 'Paiement validé',
        'invoice_cancelled' => 'Facture annulée',
    ],

    'reset_period' => [
        'month' => 'Mensuel',
        'day' => 'Quotidien',
    ],

    'denied' => [
        'no_subscription' => 'Cette fonctionnalité nécessite un abonnement actif.',
        'subscription_inactive' => 'Votre abonnement est inactif. Réglez la facture en attente pour continuer.',
        'module_not_in_plan' => 'Cette fonctionnalité n\'est pas incluse dans votre formule.',
        'ceiling_reached' => 'Vous avez atteint le plafond de :ceiling pour ce mois. Changez de formule pour continuer.',
        'allowed' => '',
    ],

    'errors' => [
        'module_unavailable' => 'Le module [:module] est indisponible (:reason).',
    ],

    'lines' => [
        'term' => ':plan — du :from au :to',
        'upgrade' => 'Passage de :from à :to — jusqu\'au :until',
        'base_plan' => ':plan — :period',
        'overage' => ':module — :unit au-delà des :allowance incluses',
    ],

    'plan' => [
        'label' => 'Formule',
        'plural' => 'Formules',
        'tva_suffix' => 'HT · TVA :rate%',
        'sections' => [
            'identity' => 'Identité',
            'pricing' => 'Tarification',
            'modules' => 'Modules inclus',
            'availability' => 'Disponibilité',
        ],
        'fields' => [
            'slug' => 'Identifiant',
            'name' => 'Nom',
            'description' => 'Description',
            'sort_order' => 'Ordre',
            'price_ht' => 'Prix HT par terme',
            'term_months' => 'Durée du terme',
            'tva_rate' => 'Taux de TVA',
            'currency' => 'Devise',
            'trial_days' => 'Période d\'essai',
            'renewal_notice_days' => 'Préavis de renouvellement',
            'payment_term_days' => 'Délai de paiement',
            'grace_days' => 'Délai de grâce',
            'module' => 'Module',
            'included_quantity' => 'Quota inclus',
            'unit_price_ht' => 'Prix unitaire au-delà',
            'hard_ceiling' => 'Plafond bloquant',
            'summary' => 'Ce que ça donne',
            'is_active' => 'Active',
            'is_public' => 'Visible publiquement',
            'modules_count' => 'Modules',
            'subscribers' => 'Abonnés',
        ],
        'price_breakdown' => 'Soit :ttc :currency TTC pour :months mois · :monthly :currency HT par mois',
        'units' => [
            'days' => 'jours',
            'months' => 'mois',
        ],
        'placeholders' => [
            'unlimited' => 'Illimité',
            'blocks' => 'Bloque au quota',
            'no_ceiling' => 'Aucun',
        ],
        'help' => [
            'slug' => 'Utilisé dans le code et les URL. Ne le modifiez plus une fois la formule vendue.',
            'sort_order' => 'Position dans la grille tarifaire, du plus petit au plus grand.',
            'description' => 'La promesse de la formule, en une phrase, telle qu\'elle s\'affiche sur la page publique.',
            'renewal_notice_days' => 'Jours avant l\'échéance où la facture de renouvellement est émise.',
            'grace_days' => 'Jours d\'accès maintenus après l\'échéance d\'une facture impayée.',
            'modules' => 'Un module absent de la formule est simplement désactivé pour ses abonnés.',
            'included_quantity' => 'Vide = illimité, jamais facturé.',
            'unit_price_ht' => 'Vide = l\'usage s\'arrête au quota au lieu d\'être facturé.',
            'hard_ceiling' => 'Vide = le dépassement n\'est jamais bloqué.',
            'is_public' => 'Décochez pour une formule négociée, réservée à certains cabinets.',
        ],
        'summary' => [
            'unlimited' => 'illimité',
            'capped' => ':included max',
            'capped_monthly' => ':included max / mois · :yearly / an',
            'metered' => ':included inclus, puis :price :currency',
            'metered_monthly' => ':included / mois · :yearly / an, puis :price :currency',
            'capped_daily' => ':included max / jour',
            'metered_daily' => ':included / jour, puis :price :currency',
        ],
        'actions' => [
            'add_module' => 'Ajouter un module',
        ],
        'empty' => [
            'heading' => 'Aucune formule',
            'description' => 'Créez une formule pour commencer à facturer.',
        ],
    ],

    'module' => [
        'label' => 'Module',
        'plural' => 'Modules',
        'fields' => [
            'key' => 'Module',
            'class' => 'Classe',
            'plans_count' => 'Formules',
            'is_active' => 'Actif',
        ],
        'actions' => [
            'sync' => 'Synchroniser',
        ],
        'notifications' => [
            'synced' => 'Registre synchronisé',
            'synced_body' => ':created créé(s), :updated mis à jour, :deactivated désactivé(s).',
        ],
        'empty' => [
            'heading' => 'Aucun module enregistré',
            'description' => 'Déclarez vos modules dans la configuration puis synchronisez.',
        ],
    ],

    'subscription' => [
        'label' => 'Abonnement',
        'plural' => 'Abonnements',
        'deleted_subscriber' => 'Abonné supprimé',
        'grace_until' => 'Grâce jusqu\'au :date',
        'sections' => [
            'overview' => 'Vue d\'ensemble',
        ],
        'fields' => [
            'subscriber' => 'Abonné',
            'plan' => 'Formule',
            'status' => 'Statut',
            'starts_at' => 'Début',
            'trial_ends_at' => 'Fin d\'essai',
            'grace_ends_at' => 'Fin de grâce',
            'cancelled_at' => 'Résilié le',
            'invoices' => 'Factures',
            'with_trial' => 'Ouvrir un essai',
        ],
        'tabs' => [
            'attention' => 'À traiter',
            'active' => 'Actifs',
            'all' => 'Tous',
        ],
        'actions' => [
            'start' => 'Créer un abonnement',
            'issue_term' => 'Facturer le terme',
            'upgrade' => 'Monter de formule',
            'extend_trial' => 'Prolonger l\'essai',
            'grant_grace' => 'Accorder un délai de grâce',
            'change_plan' => 'Changer de formule',
            'cancel' => 'Résilier',
        ],
        'help' => [
            'start' => 'La facture du terme part immédiatement. L\'accès s\'ouvre au règlement.',
            'with_trial' => 'L\'abonné accède au service pendant l\'essai, avant même le règlement.',
            'issue_term' => 'Émet la facture du prochain terme. Une facture déjà en attente est réutilisée.',
            'upgrade' => 'La différence est facturée au prorata des mois restants. La nouvelle formule s\'applique au règlement.',
            'change_plan' => 'Bascule administrative immédiate, sans facturation. Utilisez « Monter de formule » pour vendre un changement.',
            'cancel' => 'L\'abonné perd l\'accès. Le terme déjà réglé n\'est pas remboursé.',
            'extend_trial' => 'L\'accès continue jusqu\'à la nouvelle date. La facture d\'essai en cours devient exigible à cette même date.',
            'grant_grace' => 'Rouvre l\'accès pendant le nombre de jours choisi, le temps de régler le paiement. Aucune somme due n\'est annulée.',
        ],
        'notifications' => [
            'started' => 'Abonnement créé',
            'already_subscribed' => 'Cet abonné a déjà un abonnement en cours',
            'term_invoiced' => 'Facture :number émise',
            'upgrade_invoiced' => 'Complément :number émis',
            'no_upgrade' => 'Aucun complément à facturer pour cette formule',
            'plan_changed' => 'Formule modifiée',
            'cancelled' => 'Abonnement résilié',
            'trial_extended' => 'Essai prolongé',
            'grace_granted' => 'Accès rouvert',
        ],
        'empty' => [
            'heading' => 'Aucun abonnement',
            'description' => 'Les abonnements apparaîtront ici dès le premier cabinet inscrit.',
        ],
    ],

    'invoice' => [
        'label' => 'Facture',
        'plural' => 'Factures',
        'title' => 'Facture',
        'draft_placeholder' => 'Brouillon',
        'balance_short' => 'Reste :amount',
        'no_usage' => 'Aucune consommation enregistrée sur la période.',
        'unattributed' => 'Non attribué',
        'sections' => [
            'buyer' => 'Client',
            'totals' => 'Totaux',
            'lines' => 'Détail',
            'usage' => 'Consommation par client',
        ],
        'fields' => [
            'number' => 'N°',
            'period' => 'Période',
            'status' => 'Statut',
            'issued_at' => 'Date d\'émission',
            'due_at' => 'Échéance',
            'ice' => 'ICE',
            'identifiant_fiscal' => 'Identifiant fiscal',
            'address' => 'Adresse',
            'description' => 'Désignation',
            'quantity' => 'Qté',
            'unit_price' => 'P.U. HT',
            'amount' => 'Montant HT',
            'amount_ht' => 'Montant HT',
            'subtotal_ht' => 'Total HT',
            'tva' => 'TVA (:rate%)',
            'total_ttc' => 'Total TTC',
            'amount_paid' => 'Réglé',
            'balance_due' => 'Reste à payer',
        ],
        'tabs' => [
            'outstanding' => 'À encaisser',
            'overdue' => 'En retard',
            'paid' => 'Réglées',
            'all' => 'Toutes',
        ],
        'actions' => [
            'record_payment' => 'Enregistrer un règlement',
            'download' => 'Télécharger',
        ],
        'cancel' => [
            'action' => 'Annuler la facture',
            'heading' => 'Annuler la facture :number',
            'description' => 'La facture garde son numéro et reste dans l\'historique comme annulée. L\'abonnement est ajusté à ce qui reste dû.',
            'confirm' => 'Annuler la facture',
            'reason' => 'Motif',
            'done' => 'Facture annulée',
            'refused' => 'Cette facture ne peut plus être annulée : un paiement a été déclaré ou reçu.',
            'bulk_outcome' => ':cancelled annulée(s), :skipped ignorée(s).',
        ],
        'help' => [
            'usage' => 'Figée à l\'émission : ce détail ne change plus, même si un client est renommé ensuite.',
        ],
        'empty' => [
            'heading' => 'Aucune facture',
            'description' => 'Les factures sont générées à la clôture de chaque mois.',
        ],
        'billed_to' => 'Facturé à',
        'usage_annex' => 'Détail de la consommation',
        'client' => 'Client',
        'consumption' => 'Consommation',
    ],

    'payment' => [
        'label' => 'Règlement',
        'plural' => 'Règlements',
        'fields' => [
            'from' => 'Cabinet',
            'amount' => 'Montant',
            'method' => 'Mode',
            'status' => 'Statut',
            'paid_at' => 'Date du règlement',
            'reference' => 'Référence',
            'rejection_reason' => 'Motif du rejet',
            'notes' => 'Notes',
        ],
        'tabs' => [
            'pending' => 'À valider',
            'validated' => 'Validés',
            'all' => 'Tous',
        ],
        'actions' => [
            'validate' => 'Valider',
            'reject' => 'Rejeter',
            'receipt' => 'Justificatif',
        ],
        'help' => [
            'validate' => 'Confirmez avoir vu :amount sur le compte pour :buyer. La facture sera mise à jour.',
            'reject' => 'Le motif est visible par le cabinet.',
            'reference' => 'N° de virement ou de chèque, pour le rapprochement.',
        ],
        'notifications' => [
            'recorded' => 'Règlement enregistré',
            'validated' => 'Règlement validé',
            'rejected' => 'Règlement rejeté',
        ],
        'empty' => [
            'heading' => 'Aucun règlement en attente',
            'description' => 'Les virements déclarés par les cabinets arrivent ici.',
        ],
    ],

    'tenant' => [
        'nav' => 'Facturation',
        'title' => 'Abonnement et facturation',
        'subheading' => 'Période en cours : :period',
        'empty' => [
            'heading' => 'Aucun abonnement pour le moment',
            'description' => 'Votre compte n\'a pas encore de formule. Contactez-nous pour en activer une.',
        ],
        'summary' => [
            'plan' => 'Formule',
            'price' => 'Prix de l\'abonnement',
            'per_term' => '{1} par mois, HT|[2,*] pour :months mois, HT',
            'term_ends' => 'Réglé jusqu\'au',
            'days_left' => '{0} Se termine aujourd\'hui|{1} 1 jour restant|[2,*] :count jours restants',
            'not_started' => 'Terme pas encore commencé',
        ],
        'term' => [
            'awaiting_heading' => 'En attente de paiement',
            'awaiting_description' => 'Votre abonnement démarre dès que votre paiement est confirmé.',
            'trial_heading' => 'Essai gratuit jusqu\'au :date',
            'trial_description' => 'Réglez la facture ci-dessous avant la fin de l\'essai pour garder votre accès.',
            'renewal_heading' => '{0} Renouvellement aujourd\'hui|{1} Renouvellement demain|[2,*] Renouvellement dans :count jours',
            'renewal_description' => 'La facture du prochain terme vous parviendra avant la fin de celui-ci.',
            'renewal_invoiced' => 'La facture du prochain terme est disponible ci-dessous.',
        ],
        'modules' => [
            'heading' => 'Ce qui est inclus',
            'description' => 'Les quotas mensuels se renouvellent le 1er de chaque mois, les quotas journaliers à minuit.',
            'this_month' => 'ce mois-ci',
            'today' => 'aujourd\'hui',
            'unlimited' => 'Illimité',
            'remaining' => ':count :unit restants',
            'over_by' => ':count :unit au-delà du quota',
            'month_total' => ':count :unit ce mois-ci',
            'near_limit' => 'Presque épuisé. Pensez à une formule supérieure.',
        ],
        'breakdown' => [
            'heading' => 'Détail de la consommation',
            'description' => 'Ce que chaque élément a consommé ce mois-ci. À titre indicatif : la facture reste au niveau du compte.',
        ],
        'payment_instructions' => [
            'heading' => 'Comment régler la facture :number',
            'description' => 'Virez :amount avant le :date en indiquant le numéro de facture comme référence.',
            'contact' => 'Réglez :amount avant le :date avec l\'un des moyens ci-dessous.',
            'beneficiary' => 'Bénéficiaire',
            'bank' => 'Banque',
            'rib' => 'RIB',
            'reference' => 'Référence',
        ],
        'invoices' => [
            'heading' => 'Vos factures',
            'empty_heading' => 'Aucune facture pour le moment',
            'empty_description' => 'Vos factures apparaissent ici dès leur émission.',
        ],
        'fields' => [
            'receipt' => 'Justificatif de paiement',
        ],
        'actions' => [
            'pay_online' => 'Payer en ligne',
            'declare_payment' => 'J\'ai payé',
            'send_declaration' => 'Envoyer',
        ],
        'help' => [
            'declare_payment' => 'Signalez-nous un virement ou un chèque. La facture passe à réglée dès que nous constatons l\'encaissement.',
            'reference' => 'Numéro de virement ou de chèque.',
            'receipt' => 'Avis de virement ou photo du chèque. PDF ou image, 5 Mo max.',
        ],
        'notifications' => [
            'payment_declared' => 'Paiement déclaré',
            'payment_declared_body' => 'Nous le vérifions et mettons la facture à jour rapidement.',
        ],
        'locked' => [
            'heading' => 'Accès suspendu',
            'contact_support' => 'Nous contacter',
            'no_subscription' => 'Votre compte n\'a pas encore d\'abonnement. Contactez-nous pour en activer un.',
            'pending_payment' => 'Votre abonnement démarre dès le règlement de sa première facture.',
            'past_due' => 'Une facture reste impayée. Réglez-la pour retrouver votre accès.',
            'suspended' => 'Votre abonnement est suspendu. Merci de nous contacter.',
            'cancelled' => 'Votre abonnement a été résilié.',
            'expired' => 'Votre abonnement a expiré. Réglez la facture de renouvellement pour continuer.',
        ],
    ],

    'event' => [
        'plural' => 'Historique',
        'fields' => [
            'at' => 'Date',
            'type' => 'Événement',
            'change' => 'Détail',
        ],
        'threshold_line' => ':module a atteint :threshold% du quota en :period',
        'empty' => [
            'heading' => 'Aucun événement',
        ],
    ],

];
