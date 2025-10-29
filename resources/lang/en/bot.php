<?php

return [
    'welcome' => "Hi! I’ll help you with reporting deadlines.\n\nCommands:\n• /addcompany — add a company or sole proprietor\n• /companies — manage your companies\n• /next — upcoming deadlines\n• /tax — quick tax calculator by period\n• /reminders — reminders",

    'addcompany' => [
        'start' => "Let's add a company. Please send the company name:",
        'ask_name_invalid' => "Please send a valid name (text, not a command).",
        'ask_country' => "Country code (ISO-2), e.g. BE:",
        'ask_regime' => "Reporting regime: monthly | quarterly | annual",
        'ask_timezone' => "Timezone (IANA), e.g. Europe/Brussels:",
        'saved' => "Company saved:\n• :name\n• Country: :country\n• Regime: :regime\n• Timezone: :tz\n\nUse /next to view deadlines (after generation).",
        'ask_subject_type' => "Who are you registering? Send one: company | sole_prop | self_employed",
        'ask_subject_type_invalid' => "Allowed: company | sole_prop | self_employed",
        'ask_person_name' => "Personal full name for the subject (e.g., John Smith):",
        'ask_tax_id' => "Tax ID (optional). You may send '-' to skip:",
    ],

    'next' => [
        'empty' => "No upcoming deadlines yet. Add a company via /addcompany.",
        'header' => "Upcoming deadlines:",
        'line' => "• :title — <b>:due</b> (period :from–:to, :company)",
    ],
];
