<?php

namespace Drupal\nidirect_proni\Drush\Commands;

use Drupal\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\path_alias\AliasManagerInterface;
use Drupal\redirect\Entity\Redirect;
use Drupal\redirect\RedirectRepository;
use Drush\Commands\DrushCommands;

/**
 * Drush commands for the nidirect_proni module.
 */
class NidirectProniCommands extends DrushCommands {

  const string REDIRECT_URL = 'https://www.nidirect.gov.uk/articles/public-record-office-northern-ireland';

  /**
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * @var \Drupal\path_alias\AliasManagerInterface
   */
  protected AliasManagerInterface $aliasManager;

  /**
   * @var \Drupal\redirect\RedirectRepository
   */
  protected RedirectRepository $redirectRepository;

  /**
   * Class constructor
   */
  public function __construct(
    EntityTypeManagerInterface $entityTypeManager,
    AliasManagerInterface $aliasManager,
    RedirectRepository $redirectRepository,
  ) {
    parent::__construct();
    $this->entityTypeManager = $entityTypeManager;
    $this->aliasManager = $aliasManager;
    $this->redirectRepository = $redirectRepository;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('path_alias.manager'),
      $container->get('redirect.repository'),
    );
  }

  /**
   * Creates redirects for all PRONI nodes and taxonomy terms.
   *
   * @command nidirect:create-proni-redirects
   * @aliases proni-redirects
   */
  public function createRedirects() {
    $this->createNodeRedirects();
    $this->createTermRedirects();
  }

  /**
   * Creates redirects for all nodes tagged with the PRONI theme or its children.
   */
  public function createTermRedirects(): void {
    $term_ids = $this->getProniTermIds();

    $this->logger()->notice(dt('Processing @count PRONI terms.', ['@count' => count($term_ids)]));

    $created = 0;
    $skipped = 0;

    foreach ($term_ids as $tid) {
      $system_path = '/taxonomy/term/' . $tid;
      $alias = $this->aliasManager->getAliasByPath($system_path);

      // Use the alias if one exists, otherwise fall back to the system path.
      $source_path = ltrim($alias ?: $system_path, '/');

      $existing = $this->redirectRepository->findBySourcePath($source_path);

      if (!empty($existing)) {
        $this->logger()->info(dt('Skipping tid @tid as a redirect already exists for "@path".', [
          '@tid' => $tid,
          '@path' => $source_path,
        ]));
        $skipped++;
        continue;
      }

      $this->createRedirect($source_path);
      $created++;
    }

    $this->logger()->success(dt('Finished processing. Created: @created, Skipped: @skipped.', [
      '@created' => $created,
      '@skipped' => $skipped,
    ]));
  }

  /**
   * Creates redirects for all nodes tagged with the PRONI theme or its children.
   */
  public function createNodeRedirects(): void {
    $term_ids = $this->getProniTermIds();

    $node_ids = $this->getProniNodeIds($term_ids);

    $this->logger()->notice(dt('Processing @count PRONI nodes.', ['@count' => count($node_ids)]));

    $created = 0;
    $skipped = 0;

    foreach ($node_ids as $nid) {
      $system_path = '/node/' . $nid;
      $alias = $this->aliasManager->getAliasByPath($system_path);

      // Use the alias if one exists, otherwise fall back to the system path.
      $source_path = ltrim($alias ?: $system_path, '/');

      $existing = $this->redirectRepository->findBySourcePath($source_path);

      if (!empty($existing)) {
        $this->logger()->info(dt('Skipping nid @nid as a redirect already exists for "@path".', [
          '@nid' => $nid,
          '@path' => $source_path,
        ]));
        $skipped++;
        continue;
      }

      $this->createRedirect($source_path);
      $created++;
    }

    $this->logger()->success(dt('Finished processing. Created: @created, Skipped: @skipped.', [
      '@created' => $created,
      '@skipped' => $skipped,
    ]));
  }

  /**
   * Returns all PRONI term ID's.
   */
  protected function getProniTermIds(): array {
    $term_storage = $this->entityTypeManager->getStorage('taxonomy_term');
    $children = $term_storage->loadTree('site_themes', '362', NULL, TRUE);

    $tids = [362];
    foreach ($children as $child) {
      $tids[] = (int) $child->id();
    }

    return $tids;
  }

  /**
   * Returns node IDs whose field_subtheme or field_top_level_theme target is in $term_ids.
   *
   * @param int[] $term_ids
   *   List of proni term ID's.
   * @return int[]
   *   List of nodes that reference any of the term ID's.
   */
  protected function getProniNodeIds(array $term_ids): array {
    $node_storage = $this->entityTypeManager->getStorage('node');

    $subtheme_query = $node_storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('field_subtheme.target_id', $term_ids, 'IN');

    $top_theme_query = $node_storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('field_top_level_theme.target_id', $term_ids, 'IN');

    $subtheme_ids = $subtheme_query->execute();
    $top_theme_ids = $top_theme_query->execute();

    return array_values(array_unique(array_merge($subtheme_ids, $top_theme_ids)));
  }

  /**
   * Generate the Redirect.
   *
   * @param string $source_path
   *   Redirect source path.
   *
   */
  private function createRedirect(string $source_path): void {
    $redirect = Redirect::create();
    $redirect->setSource($source_path);
    $redirect->setRedirect(self::REDIRECT_URL);
    $redirect->setStatusCode(301);
    $redirect->setLanguage('und');
    $redirect->save();
  }

}
