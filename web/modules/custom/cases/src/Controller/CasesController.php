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
        // Retrieve Azure and API credentials from environment variables.
        $clientId = getenv('AZURE_CLIENT_ID');
        $clientSecret = getenv('AZURE_CLIENT_SECRET');
        $tenantId = getenv('AZURE_TENANT_ID');
        $scope = getenv('AZURE_SCOPE');
        $subscriptionKey = getenv('APIM_SUBSCRIPTION_KEY');
        $casesUrl = getenv('APIM_CASES_URL');

        $cases = [];
        try {
            // Get the HTTP client service from Drupal.
            $httpClient = \Drupal::httpClient();

            // Request an OAuth2 access token from Azure AD.
            $response = $httpClient->post("https://login.microsoftonline.com/$tenantId/oauth2/v2.0/token", [
                'form_params' => [
                    'grant_type' => 'client_credentials',
                    'client_id' => $clientId,
                    'client_secret' => $clientSecret,
                    'scope' => $scope,
                ],
            ]);
            $tokenData = json_decode($response->getBody(), true);
            $accessToken = $tokenData['access_token'] ?? null;

            // If access token is received, call the external Cases API using env vars for URL and subscription key.
            if ($accessToken) {
                $apiResponse = $httpClient->get($casesUrl, [
                    'headers' => [
                        // Bearer token for authentication.
                        'Authorization' => 'Bearer ' . $accessToken,
                        // Azure API Management subscription key from env var.
                        'Ocp-Apim-Subscription-Key' => $subscriptionKey,
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
                                $item['id'] ?? '', // Case ID
                                $attr['name'] ?? '', // Title
                                $attr['type'] ?? '', // Case type
                                $attr['status'] ?? '', // Status
                                $attr['assigned_user_name'] ?? '', // Submitted by
                                $attr['date_entered'] ?? '', // Date
                            ],
                        ];
                    }
                }
            }
        } catch (\Exception $e) {
            // Log the error and show a message to the user if the API call fails.
            \Drupal::logger('cases')->error($e->getMessage());
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