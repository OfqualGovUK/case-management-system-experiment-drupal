<?php

// phpcs:ignoreFile

/**
 * Disable CSS and JS aggregation.
 */
$config['system.performance']['css']['preprocess'] = FALSE;
$config['system.performance']['js']['preprocess'] = FALSE;

/**
 * Load correct config split and enable errors
 */
$config['system.logging']['error_level'] = 'verbose';
$config['config_split.config_split.dev']['status'] = TRUE;

/**
 * Set Environment Indicator
 */
$config['environment_indicator.indicator']['bg_color'] = '#004d40';
$config['environment_indicator.indicator']['fg_color'] = '#fde4c4';
$config['environment_indicator.indicator']['name'] = 'Ofqual DDEV';
