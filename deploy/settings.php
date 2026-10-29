<?php

/**
 * @file
 * Drupal settings for goingsolo.ie.
 *
 * Secrets are not kept in the repository. The deploy script writes them to
 * ../private/secrets.php on the server, outside the web root.
 */

$databases = [];
$settings['config_sync_directory'] = '../config/sync';
$settings['file_private_path'] = dirname(DRUPAL_ROOT) . '/private/files';
$settings['update_free_access'] = FALSE;
$settings['rebuild_access'] = FALSE;
$settings['file_scan_ignore_directories'] = ['node_modules', 'bower_components'];
$settings['entity_update_batch_size'] = 50;
$settings['entity_update_backup'] = TRUE;
$settings['migrate_node_migrate_type_classic'] = FALSE;
$settings['state_cache'] = TRUE;
$settings['trusted_host_patterns'] = [];

$secrets = dirname(DRUPAL_ROOT) . '/private/secrets.php';
if (file_exists($secrets)) {
  include $secrets;
}

if (file_exists($app_root . '/' . $site_path . '/settings.local.php')) {
  include $app_root . '/' . $site_path . '/settings.local.php';
}
