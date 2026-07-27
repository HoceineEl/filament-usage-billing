<?php

declare(strict_types=1);

return [

    'nav' => [
        'group' => 'الفوترة',
    ],

    'subscription_status' => [
        'pending_payment' => 'في انتظار الأداء',
        'trialing' => 'تجريبي',
        'active' => 'نشط',
        'past_due' => 'غير مؤدى',
        'suspended' => 'موقوف',
        'cancelled' => 'ملغى',
        'expired' => 'منتهي',
    ],

    'invoice_status' => [
        'draft' => 'مسودة',
        'issued' => 'صادرة',
        'partially_paid' => 'مؤداة جزئيا',
        'paid' => 'مؤداة',
        'overdue' => 'متأخرة',
        'cancelled' => 'ملغاة',
    ],

    'payment_method' => [
        'virement' => 'تحويل بنكي',
        'cheque' => 'شيك',
        'especes' => 'نقدا',
        'card' => 'بطاقة بنكية',
    ],

    'payment_status' => [
        'pending' => 'في انتظار التأكيد',
        'validated' => 'مؤكد',
        'rejected' => 'مرفوض',
    ],

    'gate_reason' => [
        'allowed' => 'مسموح',
        'no_subscription' => 'لا يوجد اشتراك',
        'subscription_inactive' => 'الاشتراك غير نشط',
        'module_not_in_plan' => 'غير مدرج في الصيغة',
        'ceiling_reached' => 'تم بلوغ الحد الأقصى',
    ],

    'event_type' => [
        'created' => 'تم إنشاء الاشتراك',
        'plan_changed' => 'تم تغيير الصيغة',
        'status_changed' => 'تم تغيير الحالة',
        'renewed' => 'تم التجديد',
        'cancelled' => 'تم الإلغاء',
        'usage_threshold' => 'تم بلوغ عتبة الاستهلاك',
        'invoice_issued' => 'تم إصدار الفاتورة',
        'payment_validated' => 'تم تأكيد الأداء',
    ],

    'denied' => [
        'no_subscription' => 'تتطلب هذه الخاصية اشتراكا نشطا.',
        'subscription_inactive' => 'اشتراككم غير نشط. يرجى أداء الفاتورة المعلقة للمتابعة.',
        'module_not_in_plan' => 'هذه الخاصية غير مدرجة في صيغتكم الحالية.',
        'ceiling_reached' => 'لقد بلغتم الحد الأقصى :ceiling لهذا الشهر. غيّروا الصيغة للمتابعة.',
        'allowed' => '',
    ],

    'errors' => [
        'module_unavailable' => 'الوحدة [:module] غير متاحة (:reason).',
    ],

    'lines' => [
        'term' => ':plan — من :from إلى :to',
        'upgrade' => 'الانتقال من :from إلى :to — إلى غاية :until',
        'base_plan' => ':plan — :period',
        'overage' => ':module — :unit تتجاوز :allowance المدرجة',
    ],

    'plan' => [
        'label' => 'صيغة',
        'plural' => 'الصيغ',
        'tva_suffix' => 'دون احتساب الضريبة · ض.ق.م :rate%',
        'sections' => [
            'identity' => 'التعريف',
            'pricing' => 'التسعير',
            'modules' => 'الوحدات المدرجة',
            'availability' => 'الإتاحة',
        ],
        'fields' => [
            'slug' => 'المعرف',
            'name' => 'الاسم',
            'sort_order' => 'الترتيب',
            'price_ht' => 'الثمن الشهري دون الضريبة',
            'tva_rate' => 'نسبة الضريبة',
            'currency' => 'العملة',
            'trial_days' => 'المدة التجريبية',
            'payment_term_days' => 'أجل الأداء',
            'grace_days' => 'مهلة الإمهال',
            'module' => 'الوحدة',
            'included_quantity' => 'الحصة المدرجة',
            'unit_price_ht' => 'ثمن الوحدة بعد الحصة',
            'hard_ceiling' => 'السقف المانع',
            'is_active' => 'نشطة',
            'is_public' => 'ظاهرة للعموم',
            'modules_count' => 'الوحدات',
            'subscribers' => 'المشتركون',
        ],
        'units' => [
            'days' => 'يوم',
        ],
        'placeholders' => [
            'unlimited' => 'غير محدود',
            'blocks' => 'يتوقف عند الحصة',
            'no_ceiling' => 'بدون',
        ],
        'help' => [
            'slug' => 'يستعمل في الكود والروابط. لا تغيّروه بعد بيع الصيغة.',
            'grace_days' => 'أيام استمرار الولوج بعد حلول أجل فاتورة غير مؤداة.',
            'modules' => 'الوحدة غير المدرجة في الصيغة تكون معطّلة عند المشتركين فيها.',
            'included_quantity' => 'الفراغ يعني غير محدود ولا يفوتر أبدا.',
            'unit_price_ht' => 'الفراغ يعني توقف الاستعمال عند الحصة عوض فوترته.',
            'hard_ceiling' => 'الفراغ يعني عدم منع التجاوز أبدا.',
            'is_public' => 'ألغوا التحديد لصيغة متفاوض عليها خاصة ببعض المكاتب.',
        ],
        'summary' => [
            'unlimited' => 'غير محدود',
            'capped' => ':included كحد أقصى',
            'metered' => ':included مدرجة، ثم :price :currency',
        ],
        'actions' => [
            'add_module' => 'إضافة وحدة',
        ],
        'empty' => [
            'heading' => 'لا توجد صيغ',
            'description' => 'أنشئوا صيغة لبدء الفوترة.',
        ],
    ],

    'module' => [
        'label' => 'وحدة',
        'plural' => 'الوحدات',
        'fields' => [
            'key' => 'الوحدة',
            'class' => 'الصنف',
            'plans_count' => 'الصيغ',
            'is_active' => 'نشطة',
        ],
        'actions' => [
            'sync' => 'مزامنة',
        ],
        'notifications' => [
            'synced' => 'تمت مزامنة السجل',
            'synced_body' => ':created منشأة، :updated محدّثة، :deactivated معطّلة.',
        ],
        'empty' => [
            'heading' => 'لا توجد وحدات مسجلة',
            'description' => 'صرّحوا بوحداتكم في الإعدادات ثم أجروا المزامنة.',
        ],
    ],

    'subscription' => [
        'label' => 'اشتراك',
        'plural' => 'الاشتراكات',
        'deleted_subscriber' => 'مشترك محذوف',
        'grace_until' => 'إمهال إلى :date',
        'sections' => [
            'overview' => 'نظرة عامة',
        ],
        'fields' => [
            'subscriber' => 'المشترك',
            'plan' => 'الصيغة',
            'status' => 'الحالة',
            'starts_at' => 'البداية',
            'trial_ends_at' => 'نهاية التجربة',
            'grace_ends_at' => 'نهاية الإمهال',
            'cancelled_at' => 'تاريخ الإلغاء',
            'invoices' => 'الفواتير',
            'with_trial' => 'فتح فترة تجريبية',
        ],
        'tabs' => [
            'attention' => 'تتطلب تدخلا',
            'active' => 'نشطة',
            'all' => 'الكل',
        ],
        'actions' => [
            'start' => 'إنشاء اشتراك',
            'issue_term' => 'فوترة المدة',
            'upgrade' => 'الانتقال لصيغة أعلى',
            'change_plan' => 'تغيير الصيغة',
            'cancel' => 'إلغاء',
        ],
        'help' => [
            'start' => 'تصدر فاتورة المدة فورا. يفتح الولوج عند الأداء.',
            'with_trial' => 'يلج المشترك للخدمة خلال الفترة التجريبية، قبل الأداء.',
            'issue_term' => 'تصدر فاتورة المدة المقبلة. تعاد الفاتورة المعلقة إن وجدت.',
            'upgrade' => 'يفوتر الفرق بحسب الأشهر المتبقية. تسري الصيغة الجديدة عند الأداء.',
            'change_plan' => 'تبديل إداري فوري دون فوترة. استعملوا «الانتقال لصيغة أعلى» لبيع التغيير.',
            'cancel' => 'يفقد المشترك الولوج. المدة المؤداة لا ترد.',
        ],
        'notifications' => [
            'started' => 'تم إنشاء الاشتراك',
            'already_subscribed' => 'لهذا المشترك اشتراك جار بالفعل',
            'term_invoiced' => 'صدرت الفاتورة :number',
            'upgrade_invoiced' => 'صدر التكميل :number',
            'no_upgrade' => 'لا شيء للفوترة في هذه الصيغة',
            'plan_changed' => 'تم تغيير الصيغة',
            'cancelled' => 'تم إلغاء الاشتراك',
        ],
        'empty' => [
            'heading' => 'لا توجد اشتراكات',
            'description' => 'ستظهر الاشتراكات هنا بمجرد تسجيل أول مكتب.',
        ],
    ],

    'invoice' => [
        'label' => 'فاتورة',
        'plural' => 'الفواتير',
        'title' => 'فاتورة',
        'draft_placeholder' => 'مسودة',
        'balance_short' => 'الباقي :amount',
        'no_usage' => 'لا يوجد استهلاك مسجل خلال الفترة.',
        'unattributed' => 'غير منسوب',
        'sections' => [
            'buyer' => 'الزبون',
            'totals' => 'المجاميع',
            'lines' => 'التفصيل',
            'usage' => 'الاستهلاك حسب الزبون',
        ],
        'fields' => [
            'number' => 'رقم',
            'period' => 'الفترة',
            'status' => 'الحالة',
            'issued_at' => 'تاريخ الإصدار',
            'due_at' => 'تاريخ الاستحقاق',
            'ice' => 'المعرف الموحد للمقاولة',
            'identifiant_fiscal' => 'التعريف الجبائي',
            'address' => 'العنوان',
            'description' => 'البيان',
            'quantity' => 'الكمية',
            'unit_price' => 'الثمن الوحدوي',
            'amount' => 'المبلغ دون الضريبة',
            'amount_ht' => 'المبلغ دون الضريبة',
            'subtotal_ht' => 'المجموع دون الضريبة',
            'tva' => 'الضريبة على القيمة المضافة (:rate%)',
            'total_ttc' => 'المجموع مع الضريبة',
            'amount_paid' => 'المؤدى',
            'balance_due' => 'الباقي',
        ],
        'tabs' => [
            'outstanding' => 'قيد التحصيل',
            'overdue' => 'متأخرة',
            'paid' => 'مؤداة',
            'all' => 'الكل',
        ],
        'actions' => [
            'record_payment' => 'تسجيل أداء',
            'download' => 'تحميل',
        ],
        'help' => [
            'usage' => 'مجمّد عند الإصدار: لا يتغير هذا التفصيل حتى لو تغير اسم الزبون لاحقا.',
        ],
        'empty' => [
            'heading' => 'لا توجد فواتير',
            'description' => 'تصدر الفواتير عند إقفال كل شهر.',
        ],
        'billed_to' => 'فوترت إلى',
        'usage_annex' => 'تفصيل الاستهلاك',
        'client' => 'الزبون',
        'consumption' => 'الاستهلاك',
    ],

    'payment' => [
        'label' => 'أداء',
        'plural' => 'الأداءات',
        'fields' => [
            'from' => 'المكتب',
            'amount' => 'المبلغ',
            'method' => 'الوسيلة',
            'status' => 'الحالة',
            'paid_at' => 'تاريخ الأداء',
            'reference' => 'المرجع',
            'rejection_reason' => 'سبب الرفض',
            'notes' => 'ملاحظات',
        ],
        'tabs' => [
            'pending' => 'في انتظار التأكيد',
            'validated' => 'مؤكدة',
            'all' => 'الكل',
        ],
        'actions' => [
            'validate' => 'تأكيد',
            'reject' => 'رفض',
            'receipt' => 'الإثبات',
        ],
        'help' => [
            'validate' => 'أكّدوا توصلكم بمبلغ :amount من :buyer. ستحدَّث الفاتورة.',
            'reject' => 'السبب ظاهر للمكتب.',
            'reference' => 'رقم التحويل أو الشيك، للمقاربة.',
        ],
        'notifications' => [
            'recorded' => 'تم تسجيل الأداء',
            'validated' => 'تم تأكيد الأداء',
            'rejected' => 'تم رفض الأداء',
        ],
        'empty' => [
            'heading' => 'لا شيء في انتظار التأكيد',
            'description' => 'تصل هنا التحويلات المصرّح بها من طرف المكاتب.',
        ],
    ],

    'event' => [
        'plural' => 'السجل',
        'fields' => [
            'at' => 'التاريخ',
            'type' => 'الحدث',
            'change' => 'التفصيل',
        ],
        'threshold_line' => ':module بلغت :threshold% من الحصة في :period',
        'empty' => [
            'heading' => 'لا توجد أحداث',
        ],
    ],

];
