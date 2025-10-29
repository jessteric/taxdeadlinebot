<?php

return [
    'welcome' => "Привет! Я помогу с дедлайнами по отчётности.\n\nКоманды:\n• /addcompany — добавить компанию/ИП\n• /companies — управление компаниями\n• /next — ближайшие дедлайны\n• /tax — быстрый расчёт налога по периоду\n• /reminders — напоминания",

    'addcompany' => [
        'start' => "Добавим компанию. Отправьте название компании:",
        'ask_name_invalid' => "Пожалуйста, отправьте корректное название (текст, не команда).",
        'ask_country' => "Код страны (ISO-2), напр. BE:",
        'ask_regime' => "Режим отчётности: monthly | quarterly | annual",
        'ask_timezone' => "Часовой пояс (IANA), напр. Europe/Brussels:",
        'saved' => "Компания сохранена:\n• :name\n• Страна: :country\n• Режим: :regime\n• Часовой пояс: :tz\n\nТеперь используйте /next, чтобы посмотреть дедлайны (после генерации).",
        'ask_subject_type' => "Кого регистрируем? Отправьте одно: company | sole_prop | self_employed",
        'ask_subject_type_invalid' => "Допустимо: company | sole_prop | self_employed",
        'ask_person_name' => "ФИО для субъекта (например, Иван Иванов):",
        'ask_tax_id' => "Налоговый номер (опционально). Можно отправить '-' чтобы пропустить:",
    ],

    'next' => [
        'empty' => "Ближайших дедлайнов нет. Добавьте компанию командой /addcompany.",
        'header' => "Ближайшие дедлайны:",
        'line' => "• :title — <b>:due</b> (период :from–:to, :company)",
    ],
];
