<?php

return [
    // Backend-enforced download limits (exam rules: guest 5, registered 10).
    'guest_download_limit'  => (int) env('GUEST_DOWNLOAD_LIMIT', 5),
    'member_download_limit' => (int) env('MEMBER_DOWNLOAD_LIMIT', 10),
];
