<?php

declare(strict_types=1);

return [
    'name' => 'JhutLedger BD',
    'base_url' => rtrim(getenv('JHUTLEDGER_BASE_URL') ?: '/jhutledger', '/'),
    'admin_emails' => array_values(array_filter(array_map(
        static fn (string $email): string => strtolower(trim($email)),
        explode(',', getenv('JHUTLEDGER_ADMIN_EMAILS') ?: 'admin@jhutledger.local')
    ))),
];

