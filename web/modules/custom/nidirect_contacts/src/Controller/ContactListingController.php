<?php

namespace Drupal\nidirect_contacts\Controller;

use Drupal\Core\Block\BlockManagerInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\nidirect_common\ViewsMetatagManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Controller for display Contact A-Z block and View.
 */
class ContactListingController extends ControllerBase {

  /**
   * Drupal\Core\Entity\EntityTypeManagerInterface definition.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Drupal\Core\Block\BlockManagerInterface definition.
   *
   * @var \Drupal\Core\Block\BlockManagerInterface
   */
  protected $blockManager;

  /**
   * Symfony\Component\HttpFoundation\RequestStack definition.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $request;

  /**
   * ViewMetatagManager service.
   *
   * @var \Drupal\nidirect_common\ViewsMetatagManager
   */
  protected $viewsMetaTagManager;

  /**
   * Constructs a new ContactListingController object.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager,
                              BlockManagerInterface $block_manager,
                              RequestStack $request,
                              ViewsMetatagManager $views_metatag_manager) {

    $this->entityTypeManager = $entity_type_manager;
    $this->blockManager = $block_manager;
    $this->request = $request;
    $this->viewsMetaTagManager = $views_metatag_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('plugin.manager.block'),
      $container->get('request_stack'),
      $container->get('nidirect_common.views_metatags_manager')
    );
  }

  /**
   * Controller callback for the page title.
   *
   * Use this to examine route parameters/any other conditions
   * and vary the string that is returned.
   *
   * @return string
   *   The page title.
   */
  public function getTitle($route_type) {
    if ($route_type == 'contacts') {
      // Is there a text search string?
      $search_string = \Drupal::request()->get('query_contacts_az');
      if (!empty($search_string)) {
        return t('Contacts search');
      }
      else {
        return t('Contacts');
      }
    }
    elseif ($route_type == 'contacts_letter') {
      // A letter has been selected from the A-Z.
      $letter = \Drupal::routeMatch()->getParameter('letter');
      return t('Contacts - under :letter', [':letter' => strtoupper($letter)]);
    }
    return t('Contacts');
  }

  /**
   * Default presentation of the contacts listing page.
   *
   * @return array
   *   Render array for Drupal to convert to HTML.
   */
  public function default() {
    $content = [];

    // Create a render array from a views display.
    $content['contact_form'] = [
      '#type' => 'view',
      '#name' => 'contacts',
      '#display_id' => 'contact_search',
    ];

    $q = $this->request->getCurrentRequest()->query->all();

    if (empty($q['query_contacts_az'])) {
      // Hide the A-Z block if we have a search term.
      $az_block = $this->blockManager->createInstance('contact_az_block', []);
      $content['contact_az_block'] = $az_block->build();
    }

    // Append any configured metatags for the page head element.
    $tags = $this->viewsMetaTagManager->getMetatagsForView('contacts', 'contact_search');
    $content = $this->viewsMetaTagManager->addTagsToPageRender($content, $tags);

    return $content;
  }

  /**
   * Contacts when filtered by letter.
   *
   * @return array
   *   Render array for Drupal to convert to HTML.
   */
  public function filterByLetter(string $letter = NULL) {
    // Trim letter parameter if, for whatever reason, it's > 1.
    if (strlen($letter) > 1) {
      $letter = substr($letter, 0, 1);
    }

    $content['contacts_a_z'] = [
      '#type' => 'view',
      '#name' => 'contacts_a_z',
      '#display_id' => 'contacts_by_letter',
      '#arguments' => [$letter],
    ];

    // Append any configured metatags for the page head element.
    $tags = $this->viewsMetaTagManager->getMetatagsForView('contacts_a_z', 'contacts_by_letter');
    $content = $this->viewsMetaTagManager->addTagsToPageRender($content, $tags);

    return $content;
  }

}
