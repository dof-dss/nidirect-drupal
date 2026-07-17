<?php

namespace Drupal\nidirect_proni\Drush\Commands;

use Drupal\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
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
   * @var \Drupal\redirect\RedirectRepository
   */
  protected RedirectRepository $redirectRepository;

  /**
   * Class constructor.
   */
  public function __construct(
    EntityTypeManagerInterface $entityTypeManager,
    RedirectRepository $redirectRepository,
  ) {
    parent::__construct();
    $this->entityTypeManager = $entityTypeManager;
    $this->redirectRepository = $redirectRepository;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('redirect.repository'),
    );
  }

  /**
   * Creates redirects for all PRONI nodes and taxonomy terms.
   *
   * @command nidirect:create-proni-redirects
   * @aliases proni-redirects
   */
  public function createRedirects(): void {
    $this->createNodeRedirects();
    $this->createTermRedirects();
  }

  /**
   * Creates redirects for all PRONI terms.
   */
  public function createTermRedirects(): void {
    $term_ids = $this->getProniTermIds();
    $created = 0;
    $skipped = 0;

    $this->logger()->notice(dt('Processing @count PRONI terms.', ['@count' => count($term_ids)]));

    foreach ($term_ids as $tid) {
      $source_path = 'taxonomy/term/' . $tid;
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
      $this->logger()->info(dt('Created redirect: @source (tid: @tid)', [
        '@source' => $source_path,
        '@tid' => $tid,
      ]));
      $created++;
    }

    $this->logger()->success(dt('Terms done. Created: @created, Skipped: @skipped.', [
      '@created' => $created,
      '@skipped' => $skipped,
    ]));
  }

  /**
   * Creates redirects for all nodes tagged with the PRONI terms.
   */
  public function createNodeRedirects(): void {
    $term_ids = $this->getProniTermIds();
    $node_ids = $this->getProniNodeIds($term_ids);
    $created = 0;
    $skipped = 0;

    $this->logger()->notice(dt('Processing @count PRONI nodes.', ['@count' => count($node_ids)]));

    foreach ($node_ids as $nid) {
      $source_path = 'node/' . $nid;
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
      $this->logger()->info(dt('Created redirect: @source (nid: @nid)', [
        '@source' => $source_path,
        '@nid' => $nid,
      ]));
      $created++;
    }

    $this->logger()->success(dt('Nodes done. Created: @created, Skipped: @skipped.', [
      '@created' => $created,
      '@skipped' => $skipped,
    ]));
  }

  /**
   * Returns all PRONI term IDs.
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
   * Returns node IDs that reference PRONI terms.
   *
   * @param int[] $term_ids
   *   List of proni term IDs.
   * @return int[]
   *   List of node IDs that reference any of the term IDs.
   */
  protected function getProniNodeIds(array $term_ids): array {
    $node_storage = $this->entityTypeManager->getStorage('node');

    $subtheme_ids = $node_storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('field_subtheme.target_id', $term_ids, 'IN')
      ->execute();

    $top_theme_ids = $node_storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('field_top_level_theme.target_id', $term_ids, 'IN')
      ->execute();

    return array_values(array_unique(array_merge($subtheme_ids, $top_theme_ids)));
  }

  /**
   * Creates a redirect to the PRONI site.
   */
  private function createRedirect(string $source_path): void {
    $redirect = Redirect::create();
    $redirect->setSource($source_path);
    $redirect->setRedirect(self::REDIRECT_URL);
    $redirect->setStatusCode(301);
    $redirect->save();
  }

}
