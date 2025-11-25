<?php

namespace Drupal\cases\Controller;

use Drupal\Core\Controller\ControllerBase;

class CasesController extends ControllerBase
{
    /**
     * Renders the cases page with a data table of cases from the external API.
     *
     * @return array
     *   Render array for the cases data table.
     */
    public function casesPage()
    {
        // Define required environment variables.
        $requiredEnvVars = [
            'AZURE_CLIENT_ID',
            'AZURE_CLIENT_SECRET',
            'AZURE_TENANT_ID',
            'AZURE_SCOPE',
            'APIM_SUBSCRIPTION_KEY',
            'APIM_CASES_URL',
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

            $tokenData = json_decode($response->getBody(), true);
            $accessToken = $tokenData['access_token'] ?? null;

            // If access token is received, call the external Cases API.
            if ($accessToken) {
                $apiResponse = $httpClient->get($env['apim_cases_url'], [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $accessToken,
                        'Ocp-Apim-Subscription-Key' => $env['apim_subscription_key'],
                        'Accept' => 'application/json',
                    ],
                ]);

                $apiData = json_decode($apiResponse->getBody(), true);

                // Parse the API response and build the cases array for the table rows.
                if (isset($apiData['data']) && is_array($apiData['data'])) {
                    foreach ($apiData['data'] as $item) {
                        $attr = $item['attributes'] ?? [];
                        $cases[] = [
                            'cells' => [
                                $item['id'] ?? '',
                                $attr['name'] ?? '',
                                $attr['type'] ?? '',
                                $attr['status'] ?? '',
                                $attr['assigned_user_name'] ?? '',
                                $attr['date_entered'] ?? '',
                            ],
                        ];
                    }
                }
            }
        } catch (\Exception $e) {
            \Drupal::logger('cases')->error('API error: @message', ['@message' => $e->getMessage()]);
            \Drupal::messenger()->addError('Could not load cases.');
        }

        // Define the table headers for the data table.
        $header = ['CaseID', 'Title', 'Case type', 'Status', 'Submitted by', 'Date'];

        // Return a render array using the CarbonV1 data table component.
        return [
            '#type' => 'inline_template',
            '#template' => "{% include 'carbonv1:cds-data-table' with props only %}",
            '#context' => [
                'props' => [
                    'headers' => $header,
                    'rows' => $cases,
                    'searchable' => true,
                    'sortable' => true,
                    'paginated' => true,
                    'page_size' => 10,
                    'page_sizes' => [10, 20, 50],
                    'column_types' => ['string', 'string', 'string', 'string', 'string', 'date'],
                ],
            ],
        ];
    }
}