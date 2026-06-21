<?php

return [
    'connection' => [
        'created' => 'Подключение создано',
        'updated' => 'Подключение обновлено',
        'deleted' => 'Подключение удалено',
        'not_found' => 'Подключение не найдено',
        'test_success' => 'Подключение успешно',
        'test_failed' => 'Ошибка подключения: %s',
        'test_invalid_url' => 'Некорректный URL Confluence',
        'test_auth_failed' => 'Ошибка аутентификации. Проверьте email и API токен.',
    ],
    'job' => [
        'created' => 'Задача импорта создана',
        'started' => 'Задача запущена',
        'paused' => 'Задача приостановлена',
        'resumed' => 'Задача возобновлена',
        'cancelled' => 'Задача отменена',
        'not_found' => 'Задача не найдена',
        'invalid_status' => 'Некорректный переход статуса с %s на %s',
        'no_spaces' => 'Не указаны пространства',
    ],
    'mapping' => [
        'saved' => 'Сопоставление сохранено',
        'not_found' => 'Сопоставление не найдено',
    ],
    'settings' => [
        'saved' => 'Настройки сохранены',
    ],
    'error' => [
        'not_authenticated' => 'Не авторизован',
        'insufficient_permissions' => 'Недостаточно прав',
        'validation' => 'Ошибка валидации: %s',
        'internal' => 'Внутренняя ошибка сервера',
        'connection_test_required' => 'Сначала проверьте подключение',
    ],
];
