<?php

/**
 * @file
 * Post update functions for tide_demo_content.
 */

/**
 * Removes old paragraph types.
 */
function tide_demo_content_post_update_add_sites() {
  if (\Drupal::moduleHandler()->moduleExists('tide_site_restriction')) {
    /** @var \Drupal\tide_site\TideSiteHelper $site_helper */
    $site_helper = \Drupal::service('tide_site.helper');
    $sites = $site_helper->getAllSites();
    $usernames = [
      'editor1.test@example.com',
      'approver1.test@example.com',
      'previewer1.test@example.com',
    ];
    foreach ($usernames as $username) {
      $user = user_load_by_name($username);
      if ($user->hasField('field_name')) {
        $user->set('field_name', $username);
      }
      if ($user->hasField('field_last_name')) {
        $user->set('field_last_name', $username);
      }
      $user->set('field_user_site', $sites);
      $user->save();
    }
  }
}
