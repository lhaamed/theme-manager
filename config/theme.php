<?php

return [
    // Preview controller also checks the environment on every request (route-cache safe).
    'preview' => true,
    // Relative to resources/views. Generated manifests are discovered here.
    'directory' => 'themes',
    'default_unit' => 'web',
    'page_title_separator' => '|',
    // Explicit entries override discovered themes and their defaults.
    'all_available_units' => [],
];
