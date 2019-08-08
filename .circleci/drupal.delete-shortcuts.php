<?php
$query = \Drupal::entityTypeManager()->getStorage('shortcut')->getQuery();
$nids = $query->condition('shortcut_set', 'default')->execute();
$shortcuts = \Drupal::entityTypeManager()->getStorage("shortcut")->loadMultiple($nids);

if ($shortcuts) {
  foreach ($shortcuts as $shortcut) {
    $shortcut->delete();
  }
}
