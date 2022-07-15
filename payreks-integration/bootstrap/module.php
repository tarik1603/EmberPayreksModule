<?php
use App\Module;
use App\Store;

Module::registerModule([
    'name' => 'PAYREKS',
    'identifier' => 'payreks-integration',
    'version' => '1.0.0',
    'description' => 'Payreks Sanal Pos Entegrasyonu'
]);
// PANELDE GÖZÜKEN KISIM
Store::registerPaymentProcessor([
    'name' => 'payreks',
    'identifier' => 'payreks',
    'icon' => 'far fa-credit-card',
    'payment_dashboard_url' => '',
    'process_script' => file_get_contents(__DIR__ . '/../resources/js/process.js'),
    'settings' => [
        [
            'name' => 'payreks_api_key',
            'label' => 'API Key',
            'icon' => ['fas', 'fa-eye'],
            'placeholder' => 'XXXX-XXXXXXX-XXXXXXX-XXXX'
        ],
        [
            'name' => 'payreks_api_secret',
            'class' => 'col-12 col-md-5',
            'label' => 'API Secret',
            'icon' => ['fas', 'fa-key'],
            'placeholder' => 'XXXXXXXXXXXXX'
        ],
        [
            'name' => 'payreks_success_url',
            'label' => 'Tamamlandı URL',
            'placeholder' => 'https://mywebsite.com/success',
            'icon' => ['fas', 'fa-link']
        ],
        [
            'name' => 'payreks_fail_url',
            'label' => 'Hata URL',
            'placeholder' => 'https://mywebsite.com/fail',
            'icon' => ['fas', 'fa-link']
        ],
        [
            'name' => 'payreks_callback_url',
            'label' => 'Geri Dönüş URL',
            'placeholder' => 'https://mywebsite.com/store/payreks/callback',
            'icon' => ['fas', 'fa-link']
        ],
        [
            'name' => 'payreks_mobil_komisyon',
            'label' => 'Mobil Komisyon',
            'placeholder' => '0',
            'icon' => ['fas', 'fa-coins']
        ],
        [
            'name' => 'payreks_payment_1',
            'label' => 'Kredi Kartı',
            'icon' => ['fas', 'fa-coins'],
            'type' => 'checkbox'
        ],
        [
            'name' => 'payreks_payment_2',
            'label' => 'Banka Transferi',
            'icon' => ['fas', 'fa-coins'],
            'type' => 'checkbox'
        ],
        [
            'name' => 'payreks_payment_3',
            'label' => 'Mobil Ödeme',
            'icon' => ['fas', 'fa-coins'],
            'type' => 'checkbox'
        ],
        [
            'name' => 'payreks_payment_4',
            'label' => 'İninal Ödeme',
            'icon' => ['fas', 'fa-coins'],
            'type' => 'checkbox'
        ],
        [
            'name' => 'payreks_payment_commission_type',
            'class' => 'col-12 col-md-5',
            'label' => 'Komisyonu Müşteriye Yansıt',
            'icon' => ['fas', 'fa-money-bill'],
            'type' => 'checkbox'
        ]
    ]
]);

Module::registerController(PayreksIntegration\Controllers\PayreksController::class, 'PayreksController');
