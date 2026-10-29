<?php

/**
 * The Russian catalog the `crypto-call-center:localize-catalog` command writes
 * into the database.
 *
 * Krayin renders a field name and an option label from the row itself: the
 * attribute label of a form and of an activity entry comes from
 * `attributes.name`, and an option label comes from the `name` of its lookup
 * table (`lead_sources`, `lead_types`, ...). The activity history even freezes
 * both into `activities.title` and `activities.additional` at the moment the
 * lead is saved, so a translation file cannot reach any of it - an installation
 * seeded in English keeps printing "Sales Owner" and "Phone" on the Russian
 * screens, and the history of an already edited lead stays English forever.
 *
 * The command therefore renames the rows. Every entry keeps the shipped English
 * text next to the Russian one: the English text is the guard, because the
 * command only rewrites a row that still carries it, so a label an
 * administrator has changed by hand survives a re-run unless `--force` is
 * given. `lookups` maps the English option to the Russian one for the same
 * reason - a renamed option is simply not matched anymore.
 *
 * Options of a select field an administrator created are stored in
 * `attribute_options` and are not listed here: they are the customer's own
 * vocabulary, not shipped copy, so guessing a translation for them would be
 * wrong.
 */
return [
    'attributes' => [
        'leads' => [
            'title' => ['en' => 'Title', 'ru' => 'Название'],
            'description' => ['en' => 'Description', 'ru' => 'Описание'],
            'lead_value' => ['en' => 'Estimated Lead Value', 'ru' => 'Ожидаемая стоимость лида'],
            'lead_source_id' => ['en' => 'Source', 'ru' => 'Источник'],
            'lead_type_id' => ['en' => 'Type', 'ru' => 'Тип лида'],
            'user_id' => ['en' => 'Sales Owner', 'ru' => 'Ответственный'],
            'expected_close_date' => ['en' => 'Expected Close Date', 'ru' => 'Ожидаемая дата закрытия'],
            'lead_pipeline_id' => ['en' => 'Pipeline', 'ru' => 'Воронка'],
            'lead_pipeline_stage_id' => ['en' => 'Stage', 'ru' => 'Стадия'],
        ],

        'persons' => [
            'name' => ['en' => 'Name', 'ru' => 'Имя'],
            'emails' => ['en' => 'Emails', 'ru' => 'Электронная почта'],
            'contact_numbers' => ['en' => 'Contact Numbers', 'ru' => 'Номера телефонов'],
            'job_title' => ['en' => 'Job Title', 'ru' => 'Должность'],
            'user_id' => ['en' => 'Sales Owner', 'ru' => 'Ответственный'],
            'organization_id' => ['en' => 'Organization', 'ru' => 'Организация'],
        ],

        'organizations' => [
            'name' => ['en' => 'Name', 'ru' => 'Название'],
            'address' => ['en' => 'Address', 'ru' => 'Адрес'],
            'user_id' => ['en' => 'Sales Owner', 'ru' => 'Ответственный'],
        ],

        'products' => [
            'name' => ['en' => 'Name', 'ru' => 'Название'],
            'description' => ['en' => 'Description', 'ru' => 'Описание'],
            'quantity' => ['en' => 'Quantity', 'ru' => 'Количество'],
            'price' => ['en' => 'Price', 'ru' => 'Цена'],
        ],

        'quotes' => [
            'user_id' => ['en' => 'Sales Owner', 'ru' => 'Ответственный'],
            'subject' => ['en' => 'Subject', 'ru' => 'Тема'],
            'description' => ['en' => 'Description', 'ru' => 'Описание'],
            'billing_address' => ['en' => 'Billing Address', 'ru' => 'Платёжный адрес'],
            'shipping_address' => ['en' => 'Shipping Address', 'ru' => 'Адрес доставки'],
            'discount_percent' => ['en' => 'Discount Percent', 'ru' => 'Скидка, %'],
            'discount_amount' => ['en' => 'Discount Amount', 'ru' => 'Сумма скидки'],
            'tax_amount' => ['en' => 'Tax Amount', 'ru' => 'Сумма налога'],
            'adjustment_amount' => ['en' => 'Adjustment Amount', 'ru' => 'Сумма корректировки'],
            'sub_total' => ['en' => 'Sub Total', 'ru' => 'Промежуточный итог'],
            'grand_total' => ['en' => 'Grand Total', 'ru' => 'Итого'],
            'expired_at' => ['en' => 'Expired At', 'ru' => 'Действует до'],
            'person_id' => ['en' => 'Person', 'ru' => 'Контактное лицо'],
        ],

        'warehouses' => [
            'name' => ['en' => 'Name', 'ru' => 'Название'],
            'description' => ['en' => 'Description', 'ru' => 'Описание'],
            'contact_name' => ['en' => 'Contact Name', 'ru' => 'Контактное лицо'],
            'contact_emails' => ['en' => 'Contact Emails', 'ru' => 'Контактные адреса эл. почты'],
            'contact_numbers' => ['en' => 'Contact Numbers', 'ru' => 'Контактные телефоны'],
            'contact_address' => ['en' => 'Contact Address', 'ru' => 'Адрес контакта'],
        ],
    ],

    /**
     * The option labels of the shipped lookup tables.
     *
     * `users` is deliberately absent: the label of a sales owner is the name of
     * a real person, not copy.
     */
    'lookups' => [
        'lead_sources' => [
            'Email' => 'Электронная почта',
            'Web' => 'Сайт',
            'Web Form' => 'Веб-форма',
            'Phone' => 'Телефон',
            'Direct' => 'Прямое обращение',
        ],

        'lead_types' => [
            'New Business' => 'Новый бизнес',
            'Existing Business' => 'Существующий бизнес',
        ],

        'lead_pipelines' => [
            'Default Pipeline' => 'Стандартная воронка',
        ],

        'lead_pipeline_stages' => [
            'New' => 'Новая',
            'Follow Up' => 'Повторный контакт',
            'Prospect' => 'Потенциальный клиент',
            'Negotiation' => 'Переговоры',
            'Won' => 'Выиграна',
            'Lost' => 'Проиграна',
        ],
    ],
];
