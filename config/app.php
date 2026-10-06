<?php
return [
    'name' => 'سراديب',
    'tagline' => 'ما وراء الظلام… أعظم مما تتخيل',
    'base_url' => '',
    'default_locale' => 'ar',
    'locales' => [
        'ar' => ['name' => 'العربية', 'dir' => 'rtl', 'locale' => 'ar', 'flag' => 'sy'],
        'en' => ['name' => 'English', 'dir' => 'ltr', 'locale' => 'en', 'flag' => 'gb'],
        'es' => ['name' => 'Español', 'dir' => 'ltr', 'locale' => 'es', 'flag' => 'es'],
        'fr' => ['name' => 'Français', 'dir' => 'ltr', 'locale' => 'fr', 'flag' => 'fr'],
        'ru' => ['name' => 'Русский', 'dir' => 'ltr', 'locale' => 'ru', 'flag' => 'ru'],
    ],
    'uploads_dir' => __DIR__ . '/../uploads',
    'max_upload_bytes' => 6 * 1024 * 1024,
    'session_name' => '__Host-saradeeeb_session',
];
