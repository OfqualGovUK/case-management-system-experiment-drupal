<?php

namespace Drupal\carbon_design_system_content\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\Component\Utility\Html;
use Drupal\Core\Url;

/**
 * Provides a 'Carbon Structured List TOC' block.
 *
 * @Block(
 * id = "carbon_structured_list_toc",
 * admin_label = @Translation("Carbon Structured List - Table of Contents"),
 * category = @Translation("Carbon Design System"),
 * )
 */
class CarbonStructuredListTocBlock extends BlockBase implements ContainerFactoryPluginInterface {

  protected $routeMatch;
  protected $entityDisplayRepository;

  public function __construct(array $configuration, $plugin_id, $plugin_definition, RouteMatchInterface $route_match, EntityDisplayRepositoryInterface $entity_display_repository) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->routeMatch = $route_match;
    $this->entityDisplayRepository = $entity_display_repository;
  }

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('current_route_match'),
      $container->get('entity_display.repository')
    );
  }

  public function build() {
    $entity = NULL;
    // Attempt to find any entity in the current route parameters.
    foreach ($this->routeMatch->getParameters() as $param) {
      if ($param instanceof \Drupal\Core\Entity\EntityInterface) {
        $entity = $param;
        break;
      }
    }

    if (!$entity) {
      return [];
    }

    $entity_type = $entity->getEntityTypeId();
    $bundle = $entity->bundle();

    // Check 'full' first, then fallback to 'default'.
    $display = $this->entityDisplayRepository->getViewDisplay($entity_type, $bundle, 'full');
    $third_party_settings = $display->getThirdPartySettings('field_group');

    // Fallback if 'full' has no field groups.
    if (empty($third_party_settings)) {
      $display = $this->entityDisplayRepository->getViewDisplay($entity_type, $bundle, 'default');
      $third_party_settings = $display->getThirdPartySettings('field_group');
    }

    $toc_items = [];
    if (!empty($third_party_settings)) {
      foreach ($third_party_settings as $group_name => $group_info) {
        if (($group_info['format_type'] ?? '') === 'carbon_structured_list') {
          if (!empty($group_info['format_settings']['show_in_toc'])) {
            $toc_items[] = [
              'label' => $group_info['label'],
              // Ensure this matches the ID generated in the Formatter.
              'anchor' => 'group-' . str_replace('_', '-', $group_name),
              'weight' => $group_info['weight'] ?? 0,
            ];
          }
        }
      }
    }

    if (empty($toc_items)) {
      return [];
    }

    usort($toc_items, fn($a, $b) => $a['weight'] <=> $b['weight']);

    $items = [];
    foreach ($toc_items as $item) {
      $items[] = [
        '#type' => 'link',
        '#title' => $item['label'],
        '#url' => Url::fromUserInput('#' . $item['anchor']),
        '#attributes' => ['class' => ['carbon-toc-link']],
      ];
    }

    return [
      '#theme' => 'item_list',
      '#items' => $items,
      '#attributes' => ['class' => ['carbon-structured-list-toc']],
      '#prefix' => '<nav class="carbon-toc-wrapper" aria-label="' . $this->t('Table of contents') . '">' .
        '<h2 class="carbon-toc-heading">' . $this->t('Contents') . '</h2>',
      '#suffix' => '</nav>',
      '#attached' => ['library' => ['carbon_design_system/structured_list_toc']],
      '#cache' => [
        'tags' => $entity->getCacheTags(),
        'contexts' => ['url.path'],
      ],
    ];
  }
}
