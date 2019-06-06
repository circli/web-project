<?php

$templateConfig = include __DIR__ . '/circli/template-extension.php';
$availableConfigs = include __DIR__ . '/available-configs.php';
$packageConfigs = [];

foreach ($availableConfigs as $package => $files) {
    if (in_array('templates.php', $files, true)) {
        $packageConfigs[] = (array)(include __DIR__ . '/' . $package . '/templates.php');
    }
}

$config = array_merge_recursive($templateConfig, ...$packageConfigs);
$base = dirname(__DIR__);

$config['template_paths'][] = $base . '/templates';
$config['asset_path'] = $base . '/public/assets';

return $config;
