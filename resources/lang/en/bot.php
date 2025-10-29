<?php

return [
    'welcome' => "Hi! I’ll help you track reporting deadlines.\n\nCommands:\n"
        . "• /addcompany — add a company/self-employed\n"
        . "• /companies — manage companies\n"
        . "• /next — upcoming deadlines\n"
        . "• /tax — quick period tax calc\n"
        . "• /tax_history — last calculations (Free: 5)\n"
        . "• /setcurrency — company currency\n"
        . "• /setrate_default — default tax rate\n"
        . "• /reminders — reminders\n"
        . "• /plan — plan & limits\n"
        . "• /features — features (incl. Pro)\n",

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
        'who' => "What are we registering? Choose:",
        'type_company' => "Company (LLC/JSC etc.)",
        'type_sole_prop' => "Sole proprietor/Individual entrepreneur",
        'type_self_employed' => "Self-employed",
        'ask_name' => "Send company name / full name:",
    ],

    'next' => [
        'empty' => "No upcoming deadlines yet. Add a company via /addcompany.",
        'header' => "Upcoming deadlines:",
        'line' => "• :title — <b>:due</b> (period :from–:to, :company)",
    ],
];
