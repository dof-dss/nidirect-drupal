<?php

namespace Drupal\nidirect_health_conditions\Controller;

use Drupal\Core\Block\BlockManagerInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Controller for health conditions listings.
 */
class HealthConditionsListingController extends ControllerBase {

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
   * Constructs a new HealthConditionsListingController object.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, BlockManagerInterface $block_manager, RequestStack $request) {
    $this->entityTypeManager = $entity_type_manager;
    $this->blockManager = $block_manager;
    $this->request = $request;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('plugin.manager.block'),
      $container->get('request_stack')
    );
  }

  /**
   * Content when filtered by letter.
   *
   * @return array
   *   Render array for Drupal to convert to HTML.
   */
  public function filterByLetter(string $letter = NULL) {
    // Trim letter parameter if, for whatever reason, it's > 1.
    if (strlen($letter) > 1) {
      $letter = substr($letter, 0, 1);
    }

    $content['heathconditions_a_z'] = [
      '#type' => 'view',
      '#name' => 'health_conditions_a_to_z',
      '#display_id' => 'health_conditions_by_letter',
      '#arguments' => [$letter],
    ];

    return $content;
  }

}
