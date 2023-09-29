<?php

namespace Drupal\nidirect_gp\Controller;

use Drupal\Component\Utility\Xss;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\nidirect_gp\Entity\GpInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class GpController.
 *
 *  Returns responses for GP routes.
 */
class GpController extends ControllerBase {

  /**
   * Date Formatter object.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * Constructs an GpController object.
   *
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
   *   The form builder.
   */
  public function __construct(DateFormatterInterface $date_formatter) {
    $this->dateFormatter = $date_formatter;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static($container
      ->get('date.formatter')
    );
  }

  /**
   * Displays a GP  revision.
   *
   * @param int $gp_revision
   *   The GP  revision ID.
   *
   * @return array
   *   An array suitable for drupal_render().
   */
  public function revisionShow($gp_revision) {
    $gp = $this->entityTypeManager()->getStorage('gp')->loadRevision($gp_revision);
    $view_builder = $this->entityTypeManager()->getViewBuilder('gp');

    return $view_builder->view($gp);
  }

  /**
   * Page title callback for a GP revision.
   *
   * @param int $gp_revision
   *   The GP  revision ID.
   *
   * @return string
   *   The page title.
   */
  public function revisionPageTitle(int $gp_revision) {
    /** @var \Drupal\nidirect_gp\Entity\Gp $gp */
    $gp = $this->entityTypeManager()->getStorage('gp')->loadRevision($gp_revision);

    return $this->t('Revision of %title from %date', [
      '%title' => $gp->label(),
      '%date' => $this->dateFormatter->format($gp->getRevisionCreationTime()),
    ]);
  }

  /**
   * Generates an overview table of older revisions of a GP .
   *
   * @param \Drupal\nidirect_gp\Entity\GpInterface $gp
   *   A GP  object.
   *
   * @return array
   *   An array as expected by drupal_render().
   */
  public function revisionOverview(GpInterface $gp) {
    $account = $this->currentUser();
    $langcode = $gp->language()->getId();
    $langname = $gp->language()->getName();
    $languages = $gp->getTranslationLanguages();
    $has_translations = (count($languages) > 1);

    /** @var \Drupal\nidirect_gp\GpStorageInterface $gp_storage */
    $gp_storage = $this->entityTypeManager()->getStorage('gp');

    $build['#title'] = $has_translations ? $this->t('@langname revisions for %title', [
      '@langname' => $langname,
      '%title' => $gp->label(),
    ]) : $this->t('Revisions for %title', ['%title' => $gp->label()]);
    $header = [$this->t('Revision'), $this->t('Operations')];
    $revert_permission = (($account->hasPermission("revert all gp revisions") || $account->hasPermission('administer gp entities')));
    $delete_permission = (($account->hasPermission("delete all gp revisions") || $account->hasPermission('administer gp entities')));

    $rows = [];

    $vids = $gp_storage->revisionIds($gp);

    $latest_revision = TRUE;

    foreach (array_reverse($vids) as $vid) {
      /** @var \Drupal\nidirect_gp\Entity\Gp $revision */
      $revision = $gp_storage->loadRevision($vid);
      // Only show revisions that are affected by the language that is being
      // displayed.
      if ($revision->hasTranslation($langcode) && $revision->getTranslation($langcode)->isRevisionTranslationAffected()) {
        $username = [
          '#theme' => 'username',
          '#account' => $revision->getRevisionUser(),
        ];

        // Use revision link to revisions that are not active.
        $date = $this->dateFormatter->format($revision->getRevisionCreationTime(), 'short');
        if ($vid != $gp->getRevisionId()) {
          $link = Link::fromTextAndUrl($date, new Url('entity.gp.revision', [
            'gp' => $gp->id(),
            'gp_revision' => $vid,
          ]));
        }
        else {
          $link = $gp->toLink($date);
        }

        $row = [];
        $column = [
          'data' => [
            '#type' => 'inline_template',
            '#template' => '{% trans %}{{ date }} by {{ username }}{% endtrans %}{% if message %}<p class="revision-log">{{ message }}</p>{% endif %}',
            '#context' => [
              'date' => $link,
              'username' => \Drupal::service('renderer')->renderPlain($username),
              'message' => [
                '#markup' => $revision->getRevisionLogMessage(),
                '#allowed_tags' => Xss::getHtmlTagList(),
              ],
            ],
          ],
        ];
        $row[] = $column;

        if ($latest_revision) {
          $row[] = [
            'data' => [
              '#prefix' => '<em>',
              '#markup' => $this->t('Current revision'),
              '#suffix' => '</em>',
            ],
          ];
          foreach ($row as &$current) {
            $current['class'] = ['revision-current'];
          }
          $latest_revision = FALSE;
        }
        else {
          $links = [];
          if ($revert_permission) {
            $links['revert'] = [
              'title' => $this->t('Revert'),
              'url' => $has_translations ?
              Url::fromRoute('entity.gp.translation_revert', [
                'gp' => $gp->id(),
                'gp_revision' => $vid,
                'langcode' => $langcode,
              ]) :
              Url::fromRoute('entity.gp.revision_revert', [
                'gp' => $gp->id(),
                'gp_revision' => $vid,
              ]),
            ];
          }

          if ($delete_permission) {
            $links['delete'] = [
              'title' => $this->t('Delete'),
              'url' => Url::fromRoute('entity.gp.revision_delete', [
                'gp' => $gp->id(),
                'gp_revision' => $vid,
              ]),
            ];
          }

          $row[] = [
            'data' => [
              '#type' => 'operations',
              '#links' => $links,
            ],
          ];
        }

        $rows[] = $row;
      }
    }

    $build['gp_revisions_table'] = [
      '#theme' => 'table',
      '#rows' => $rows,
      '#header' => $header,
    ];

    return $build;
  }

}
