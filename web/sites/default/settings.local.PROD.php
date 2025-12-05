<?php

// phpcs:ignoreFile

/**
 * Disable CSS and JS aggregation.
 */
$config['system.performance']['css']['preprocess'] = TRUE;
$config['system.performance']['js']['preprocess'] = TRUE;

/**
 * Load correct config split
 */
$config['config_split.config_split.production']['status'] = TRUE;

/**
 * Set Environment Indicator
 */
$config['environment_indicator.indicator']['bg_color'] = '#F5F5DC';
$config['environment_indicator.indicator']['fg_color'] = '#004D40';
$config['environment_indicator.indicator']['name'] = 'Ofqual Production';
