# Модуль crm.position-example — пример позиции встраивания

Минимальный модуль-образец, показывающий, как самодостаточный модуль встраивает контент в страницу ядра **без правок ядра**.

## Что демонстрирует

- **Именованная позиция (slot)** — модуль рендерит панель в позиции `gantt.content.after` (страница «Гант»). Позиция объявлена в `manifest.json` в секции `positions`, а ядро вызывает её хелпером `module_position('gantt.content.after', $context)`.
- **Рендерер** — `web/position/GanttDemoPanel.php`, публичный статический метод `render(array $context): string`, возвращающий HTML.
- **Скоуп ассетов** — стили (`css_routes`) и скрипт (`js_routes`) загружаются **только на маршруте `gantt`**, а не глобально на всех страницах.

## Структура

```
crm.position-example/
├── manifest.json                     ← positions + scoped assets
├── README.md
└── web/
    ├── position/GanttDemoPanel.php   ← рендерер позиции gantt.content.after
    └── assets/
        ├── css/position-example.css  ← стили (только gantt)
        └── js/position-example.js    ← скрипт (только gantt)
```

## Как это работает

1. Ядро на странице «Гант» вызывает `module_position('gantt.content.after', ['route' => 'gantt'])`.
2. `PositionRegistry` находит зарегистрированный для этой позиции рендерер (из `manifest.json`) и вызывает его.
3. Возвращённый HTML вставляется в страницу.
4. Заголовок страницы подключает `position-example.css` и `position-example.js`, потому что текущий маршрут `gantt` совпадает с ключами `css_routes`/`js_routes`.

## Ограничения

- Модуль не имеет API-маршрутов, веб-маршрутов, миграций и service provider — позиции регистрируются декларативно из манифеста.
- Вывод рендерера — готовый HTML; любой пользовательский ввод внутри него должен экранироваться (`htmlspecialchars`).
