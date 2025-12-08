<?php

namespace Drupal\cases\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 *
 */
class CasesController extends ControllerBase {

  /**
   * Renders the cases page with a data table of cases from the external API.
   *
   * @return array
   *   Render array for the cases data table.
   */
  public function casesPage() {
    // Define required environment variables.
    $requiredEnvVars = [
      'AZURE_CLIENT_ID',
      'AZURE_CLIENT_SECRET',
      'AZURE_TENANT_ID',
      'AZURE_SCOPE',
      'APIM_SUBSCRIPTION_KEY',
      'APIM_API_URL',
    ];

    $env = [];
    foreach ($requiredEnvVars as $var) {
      $value = getenv($var);
      if (empty($value)) {
        \Drupal::logger('cases')->error('Missing environment variable: @var', ['@var' => $var]);
        \Drupal::messenger()->addError('Configuration error: Missing ' . $var);
        return [
          '#markup' => '<p>Configuration error. Please contact the administrator.</p>',
        ];
      }
      $env[strtolower($var)] = $value;
    }

    $cases = [];
    try {
      // Get the HTTP client service from Drupal.
      $httpClient = \Drupal::httpClient();

      // Request an OAuth2 access token from Azure AD.
      $response = $httpClient->post('https://login.microsoftonline.com/' . $env['azure_tenant_id'] . '/oauth2/v2.0/token', [
        'form_params' => [
          'grant_type' => 'client_credentials',
          'client_id' => $env['azure_client_id'],
          'client_secret' => $env['azure_client_secret'],
          'scope' => $env['azure_scope'],
        ],
      ]);

      $tokenData = json_decode($response->getBody(), TRUE);
      $accessToken = $tokenData['access_token'] ?? NULL;

      // If access token is received, call the external Cases API.
      if ($accessToken) {
        $apiResponse = $httpClient->get($env['apim_api_url'] . '/suitecrm/custom/oqmodule/Cases', [
          'headers' => [
            'Authorization' => 'Bearer ' . $accessToken,
            'Ocp-Apim-Subscription-Key' => $env['apim_subscription_key'],
            'Accept' => 'application/json',
          ],
        ]);

        $apiData = json_decode($apiResponse->getBody(), TRUE);

        // Parse the API response and build the cases array for the table rows.
        if (isset($apiData['data']) && is_array($apiData['data'])) {
          foreach ($apiData['data'] as $item) {
            $attr = $item['attributes'] ?? [];
            $caseId = $item['id'] ?? '';

            // Make second API call for detailed case info.
            $detailResponse = $httpClient->get($env['apim_api_url'] . '/suitecrm/custom/oqmodule/Cases/' . $caseId, [
              'headers' => [
                'Authorization' => 'Bearer ' . $accessToken,
                'Ocp-Apim-Subscription-Key' => $env['apim_subscription_key'],
                'Accept' => 'application/json',
              ],
            ]);

            $detailData = json_decode($detailResponse->getBody(), TRUE);
            $detailAttr = $detailData['data']['attributes'] ?? [];

            // Put all attributes in the last cell as JSON.
            $cases[] = [
              'cells' => [
                $caseId,
                $attr['name'] ?? '',
                $attr['type'] ?? '',
                $attr['status'] ?? '',
                $attr['assigned_user_name'] ?? '',
                $attr['date_entered'] ?? '',
                json_encode($detailAttr, JSON_PRETTY_PRINT),
              ],
            ];
          }
        }
      }
    }
    catch (\Exception $e) {
      \Drupal::logger('cases')->error('API error: @message', ['@message' => $e->getMessage()]);
      \Drupal::messenger()->addError('Could not load cases.');
    }

    // Define the table headers for the data table.
    $header = ['CaseID', 'Title', 'Case type', 'Status', 'Submitted by', 'Date', 'All Attributes'];

    // Return a render array using the CarbonV1 data table component.
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
          'column_types' => ['string', 'string', 'string', 'string', 'string', 'date', 'string'],
        ],
      ],
    ];
  }

}
