<?php

namespace Drupal\cases\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * Provides a 'Cases Table' block.
 *
 * @Block(
 *   id = "cases_table_block",
 *   admin_label = @Translation("Cases Table Block")
 * )
 */
class CasesTableBlock extends BlockBase {

  /**
   *
   */
  public function build() {
    $cases = [
      [
        'cells' => ['1', 'Recognition application', 'Acorn Awards', 'Received', 'Davina Forrest', '2025-10-08'],
      ],
      [
        'cells' => ['2', 'Event Notification', 'Drupal Diplomas', 'Triage', 'James Pliny', '2025-10-02'],
      ],
      [
        'cells' => ['3', 'Expansion', 'Quiet Quals', 'Triage', 'Mary Makeba', '2025-09-19'],
      ],
    ];

    $header = ['CaseID', 'Title', 'Case type', 'Submitted by', 'Date', 'Status'];

    return [
      '#type' => 'inline_template',
      '#template' => "{% include 'carbonv1:cds-data-table' with props only %}",
      '#context' => [
        'props' => [
          'headers' => $header,
          'rows' => $cases,
          'searchable' => TRUE,
          'sortable' => TRUE,
          'paginated' => TRUE,
          'page_size' => 10,
          'page_sizes' => [10, 20, 50],
          'column_types' => ['string', 'string', 'string', 'string', 'date', 'string', 'string'],
        ],
      ],
    ];
  }

}
