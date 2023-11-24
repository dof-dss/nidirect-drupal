<?php declare(strict_types = 1);

namespace Drupal\nidirect_common\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ControllerResolverInterface;

/**
 * Creates a list of node and entity content types for the Add Content page.
 */
final class AddContentPageController extends ControllerBase {

  protected ControllerResolverInterface $controllerResolver;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The controller constructor.
   */
  public function __construct(ControllerResolverInterface $controllerResolver, EntityTypeManagerInterface $entity_type_manager) {
    $this->controllerResolver = $controllerResolver;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get('controller_resolver'),
      $container->get('entity_type.manager'),
    );
  }

  /**
   * Builds a list of content types to add.
   */
  public function addContentList(): array {

    $request = new Request([], [], ['_controller' => '\Drupal\node\Controller\NodeController::addPage']);
    $node_controller = $this->controllerResolver->getController($request);

    $build = call_user_func_array($node_controller, []);

    $entities = $this->entityTypeManager->getDefinitions();


    $entity = $this->entityTypeManager->getDefinition('gp');
    $build['#content'][$entity->id()] = $entity;


    $build['#theme'] = 'content_add_list';

    return $build;
  }

}
