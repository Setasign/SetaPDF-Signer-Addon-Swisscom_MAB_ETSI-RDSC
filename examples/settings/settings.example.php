<?php

declare(strict_types=1);

return [
    'clientId' => 'your-client-id',
    'secret' => 'your-secret',
    'cert' => realpath(__DIR__ . '/private/your-client-certificate.pem'),
    'privateKey' => realpath(__DIR__ . '/private/your-private-key.key')
];
