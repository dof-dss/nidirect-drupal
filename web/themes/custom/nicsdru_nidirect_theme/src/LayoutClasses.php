<?php

namespace Drupal\nicsdru_nidirect_theme;

/**
 * Common CSS class groups for layouts.
 */
class LayoutClasses {
  // Layout classes for pages with a main and no aside.
  const MAIN = ['grid', 'layout--main'];

  // Layout classes for pages with a main and no aside where main has a
  // limited width.
  const MAIN_LIMITED = ['grid', 'layout--main-limited'];

  // Layout classes for pages with a main and an aside.
  const MAIN_SIDE = ['grid', 'layout--main-n-side'];
  const SIDE_MAIN = ['grid', 'layout--side-n-main'];

}
