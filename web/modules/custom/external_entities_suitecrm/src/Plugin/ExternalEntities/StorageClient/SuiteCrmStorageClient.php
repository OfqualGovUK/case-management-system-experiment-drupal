<?php

namespace Drupal\external_entities_suitecrm\Plugin\ExternalEntities\StorageClient;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Utility\Token;
use Drupal\external_entities\StorageClient\StorageClientBase;
use Drupal\external_entities\Entity\ExternalEntityInterface;
use Drupal\entra_jwt_token\EntraJwtTokenService;
use GuzzleHttp\ClientInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * SuiteCRM REST storage client with Entra authentication.
 *
 * @StorageClient(
 *   id = "suitecrm_rest",
 *   label = @Translation("SuiteCRM REST (Entra Authenticated)")
 * )
 */
class SuiteCrmStorageClient extends StorageClientBase {

  /**
   * The HTTP client.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected $httpClient;

  /**
   * The Entra JWT token service.
   *
   * @var \Drupal\entra_jwt_token\EntraJwtTokenService
   */
  protected $tokenService;

  /**
   * The cache backend.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected $cacheBackend;

  /**
   * Cached cases data.
   *
   * @var array|null
   */
  protected $casesCache = NULL;

  /**
   * Constructs a SuiteCrmStorageClient object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\StringTranslation\TranslationInterface $string_translation
   *   The string translation service.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   *   The entity field manager.
   * @param \Drupal\Core\Utility\Token $token
   *   The token service.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entity_type_bundle_info
   *   The entity type bundle info.
   * @param mixed $external_entity_type
   *   The external entity type.
   * @param \GuzzleHttp\ClientInterface $http_client
   *   The HTTP client.
   * @param \Drupal\entra_jwt_token\EntraJwtTokenService $token_service
   *   The Entra JWT token service.
   * @param mixed $cache_backend
   *   The cache backend.
   */
  public function __construct(
    array $configuration,
          $plugin_id,
          $plugin_definition,
    TranslationInterface $string_translation,
    LoggerChannelFactoryInterface $logger_factory,
    EntityTypeManagerInterface $entity_type_manager,
    EntityFieldManagerInterface $entity_field_manager,
    Token $token,
    EntityTypeBundleInfoInterface $entity_type_bundle_info,
    $external_entity_type,
    ClientInterface $http_client,
    EntraJwtTokenService $token_service,
    $cache_backend,
  ) {
    parent::__construct(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $string_translation,
      $logger_factory,
      $entity_type_manager,
      $entity_field_manager,
      $token,
      $entity_type_bundle_info,
      $external_entity_type
    );
    $this->httpClient = $http_client;
    $this->tokenService = $token_service;
    $this->cacheBackend = $cache_backend;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('string_translation'),
      $container->get('logger.factory'),
      $container->get('entity_type.manager'),
      $container->get('entity_field.manager'),
      $container->get('token'),
      $container->get('entity_type.bundle.info'),
      $configuration['external_entity_type'] ?? NULL,
      $container->get('http_client'),
      $container->get('entra_jwt_token.token_service'),
      $container->get('cache.default')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'list_endpoint' => '',
      'single_endpoint' => '',
      'push_endpoint' => '',
      'format' => 'json',
      'parameters' => [],
      'response_data_path' => 'data',
      'api_type' => 'Cases',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form['list_endpoint'] = [
      '#type' => 'textfield',
      '#title' => $this->t('List Endpoint (GET)'),
      '#description' => $this->t('API endpoint for reading entities (e.g., https://...suitecrm/custom/oqmodule/Cases)'),
      '#default_value' => $this->configuration['list_endpoint'] ?? '',
    ];

    $form['push_endpoint'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Push Endpoint (PATCH/POST/DELETE)'),
      '#description' => $this->t('API endpoint base URL for write operations (e.g., https://...Api/V8/custom/oqmodule) - Do NOT include module name'),
      '#default_value' => $this->configuration['push_endpoint'] ?? '',
    ];

    $form['single_endpoint'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Single Entity Endpoint Pattern'),
      '#description' => $this->t('API endpoint pattern for loading a single entity. Use {id} as placeholder (e.g., /Cases?filter[case_number][eq]={id})'),
      '#default_value' => $this->configuration['single_endpoint'] ?? '',
    ];

    $form['api_type'] = [
      '#type' => 'textfield',
      '#title' => $this->t('API Resource Type'),
      '#description' => $this->t('The JSON:API resource type - PLURAL form (e.g., Cases, Contacts, Accounts)'),
      '#default_value' => $this->configuration['api_type'] ?? 'Cases',
    ];

    $form['format'] = [
      '#type' => 'select',
      '#title' => $this->t('Response Format'),
      '#options' => ['json' => $this->t('JSON'), 'xml' => $this->t('XML')],
      '#default_value' => $this->configuration['format'] ?? 'json',
    ];

    $form['parameters'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Query Parameters'),
      '#description' => $this->t('One parameter per line in format: key=value'),
      '#default_value' => $this->formatParameters($this->configuration['parameters'] ?? []),
      '#rows' => 5,
    ];

    $form['response_data_path'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Response Data Path'),
      '#description' => $this->t('Path to data in JSON response (e.g., "data" for responses like {"data": [...]})'),
      '#default_value' => $this->configuration['response_data_path'] ?? 'data',
    ];

    $form['apim_key_info'] = [
      '#type' => 'item',
      '#title' => $this->t('APIM Subscription Key'),
      '#markup' => $this->t('The APIM subscription key is configured via the <code>APIM_SUBSCRIPTION_KEY</code> environment variable.'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    // Validate endpoints are URLs
    $list_endpoint = $form_state->getValue('list_endpoint');
    $push_endpoint = $form_state->getValue('push_endpoint');

    if (!empty($list_endpoint) && !filter_var($list_endpoint, FILTER_VALIDATE_URL)) {
      $form_state->setErrorByName('list_endpoint', $this->t('Please enter a valid URL for the list endpoint.'));
    }

    if (!empty($push_endpoint) && !filter_var($push_endpoint, FILTER_VALIDATE_URL)) {
      $form_state->setErrorByName('push_endpoint', $this->t('Please enter a valid URL for the push endpoint.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    // The parent class likely handles this, but let's try both approaches
    // First, try to get values at root level (in case parent flattens them)
    $list_endpoint = $form_state->getValue('list_endpoint');

    if (empty($list_endpoint)) {
      // Values might be nested - try to get the complete form state
      $all_values = $form_state->getValues();
    }

    // Save configuration from form values
    $this->configuration['list_endpoint'] = $form_state->getValue('list_endpoint') ?: '';
    $this->configuration['push_endpoint'] = $form_state->getValue('push_endpoint') ?: '';
    $this->configuration['single_endpoint'] = $form_state->getValue('single_endpoint') ?: '';
    $this->configuration['api_type'] = $form_state->getValue('api_type') ?: 'Cases';
    $this->configuration['format'] = $form_state->getValue('format') ?: 'json';
    $this->configuration['parameters'] = $this->parseParameters($form_state->getValue('parameters') ?: '');
    $this->configuration['response_data_path'] = $form_state->getValue('response_data_path') ?: 'data';
  }

  /**
   * Gets HTTP headers for API requests.
   *
   * @return array
   *   Array of HTTP headers.
   */
  protected function getHttpHeaders() {
    $headers = [
      'Accept' => 'application/vnd.api+json',
      'Content-Type' => 'application/vnd.api+json',
    ];

    // Get JWT token.
    $jwt_token = $this->tokenService->getAccessToken();
    if ($jwt_token) {
      $headers['Authorization'] = 'Bearer ' . $jwt_token;
    }

    // Get APIM subscription key from environment.
    $apim_key = getenv('APIM_SUBSCRIPTION_KEY');
    if ($apim_key) {
      $headers['Ocp-Apim-Subscription-Key'] = $apim_key;
    }
    else {
      $apim_message = 'APIM_SUBSCRIPTION_KEY environment variable not set';
    }

    return $headers;
  }

  /**
   * Makes an HTTP request to the API.
   *
   * @param string $method
   *   The HTTP method.
   * @param string $endpoint
   *   The API endpoint.
   * @param array $params
   *   Query parameters.
   * @param mixed|null $body
   *   Request body.
   *
   * @return array
   *   The response data.
   */
  protected function makeRequest($method, $endpoint, array $params = [], $body = NULL) {
    if (empty($endpoint)) {
      return [];
    }

    $options = ['headers' => $this->getHttpHeaders()];
    if (!empty($params)) {
      $options['query'] = $params;
    }
    if ($body !== NULL) {
      $options['json'] = $body;
    }

    try {
      $response = $this->httpClient->request($method, $endpoint, $options);
      $response_body = (string) $response->getBody();
      $data = json_decode($response_body, TRUE);

      // Extract data from response path if configured.
      if (!empty($this->configuration['response_data_path']) && isset($data[$this->configuration['response_data_path']])) {
        return $data[$this->configuration['response_data_path']];
      }

      return $data;
    }
    catch (\Exception $e) {
      throw $e;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function query(array $parameters = [], array $sorts = [], ?int $start = NULL, ?int $length = NULL): array {
    // Check cache first (5 minute TTL).
    $cache_key = 'suitecrm_cases_all';
    $cached = $this->cacheBackend->get($cache_key);

    if ($cached && !empty($cached->data)) {
      $transformed = $cached->data;
    }
    else {
      // Cache miss - fetch from API.
      $endpoint = $this->configuration['list_endpoint'] ?? '';
      $config_params = $this->configuration['parameters'] ?? [];
      if (!is_array($config_params)) {
        $config_params = [];
      }

      // Filter out parameters that SuiteCRM API doesn't support.
      $filtered_params = [];
      foreach ($parameters as $key => $value) {
        // Skip offset, limit, and sort parameters.
        if (!in_array($key, ['offset', 'limit']) && strpos($key, 'sort') !== 0) {
          $filtered_params[$key] = $value;
        }
      }

      $params = array_merge($config_params, $filtered_params);

      $result = $this->makeRequest('GET', $endpoint, $params);

      // Transform JSON:API format to flat structure.
      $transformed = [];
      if (is_array($result)) {
        foreach ($result as $item) {
          if (isset($item['attributes']) && isset($item['id'])) {
            // Store BOTH case_number AND UUID - CRITICAL for updates!
            $flat = [
              'id' => $item['attributes']['case_number'] ?? $item['id'],
              'uuid' => $item['id'],
            ];

            // Add attributes, decoding HTML entities for text fields
            foreach ($item['attributes'] as $key => $value) {
              if (is_string($value)) {
                // Decode HTML entities (e.g., &lt;p&gt; becomes <p>)
                $flat[$key] = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
              } else {
                $flat[$key] = $value;
              }
            }

            // Use case_number as key so load() can find it.
            $key = $item['attributes']['case_number'] ?? $item['id'];
            $transformed[$key] = $flat;
          }
        }

        // Cache for 5 minutes (300 seconds).
        $this->cacheBackend->set($cache_key, $transformed, time() + 300);
      }
    }

    // Apply sorting in PHP since API doesn't support it.
    if (!empty($sorts)) {
      $sort_field = array_key_first($sorts);
      $sort_direction = $sorts[$sort_field];
      usort($transformed, function ($a, $b) use ($sort_field, $sort_direction) {
        $cmp = ($a[$sort_field] ?? '') <=> ($b[$sort_field] ?? '');
        return $sort_direction === 'DESC' ? -$cmp : $cmp;
      });
    }

    // Apply pagination in PHP.
    if ($start !== NULL || $length !== NULL) {
      $start = $start ?? 0;
      $length = $length ?? count($transformed);
      $transformed = array_slice($transformed, $start, $length, TRUE);
    }

    return $transformed;
  }

  /**
   * {@inheritdoc}
   */
  public function querySource(array $parameters = [], array $sorts = [], ?int $start = NULL, ?int $length = NULL): array {
    return $this->query($parameters, $sorts, $start, $length);
  }

  /**
   * {@inheritdoc}
   */
  public function transliterateDrupalFilters(array $parameters, array $context = []): array {
    $translated = [];
    foreach ($parameters as $filter) {
      $field = $filter['field'] ?? '';
      $value = $filter['value'] ?? '';
      $operator = $filter['operator'] ?? '=';

      switch ($operator) {
        case '=':
          $translated['filter[' . $field . '][eq]'] = $value;
          break;

        default:
          $translated['filter[' . $field . '][eq]'] = $value;
      }
    }
    return $translated;
  }

  /**
   * {@inheritdoc}
   */
  public function load(string|int $id): ?array {
    // Use cached data from loadMultiple to avoid rate limiting.
    if ($this->casesCache === NULL) {
      $this->casesCache = $this->query();
    }

    // Look for the case by ID in the cache.
    foreach ($this->casesCache as $case) {
      if (isset($case['id']) && $case['id'] == $id) {
        return $case;
      }
    }

    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function loadMultiple(?array $ids = NULL): array {
    // Always fetch all cases in one API call to avoid rate limiting.
    $all_cases = $this->query();

    // If no specific IDs requested, return all.
    if ($ids === NULL) {
      return $all_cases;
    }

    // Filter to only requested IDs.
    $filtered = [];
    foreach ($ids as $id) {
      if (isset($all_cases[$id])) {
        $filtered[$id] = $all_cases[$id];
      }
    }
    return $filtered;
  }

  /**
   * {@inheritdoc}
   */
  public function save(ExternalEntityInterface $entity): int {
    $id = $entity->id();
    $is_new = $entity->isNew();

    try {
      // Prepare the data to send to the API.
      $data = $this->prepareEntityData($entity);

      // The module type goes in the JSON body, NOT in the URL!
      $endpoint = $this->configuration['push_endpoint'] ?? '';

      if ($is_new) {
        $method = 'POST';
      }
      else {
        $method = 'PATCH';
      }

      $result = $this->makeRequest($method, $endpoint, [], $data);

      // Clear cache after save (regardless of response)
      $this->clearCache();

      if (!empty($result)) {
        return $is_new ? SAVED_NEW : SAVED_UPDATED;
      }
      else {
        return SAVED_UPDATED;
      }
    }
    catch (\Exception $e) {
      throw $e;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function delete(ExternalEntityInterface $entity): void {
    $id = $entity->id();

    try {
      // For DELETE, we also use the push_endpoint with the data in body
      $endpoint = $this->configuration['push_endpoint'] ?? '';

      $entity_data = $this->load($id);
      if (!isset($entity_data['uuid'])) {
        throw new \Exception('Cannot delete entity: UUID not found for ID ' . $id);
      }

      $api_type = $this->configuration['api_type'] ?? 'Cases';

      $data = [
        'data' => [
          'type' => $api_type,
          'id' => $entity_data['uuid'],
        ],
      ];

      $this->makeRequest('DELETE', $endpoint, [], $data);

      // Clear cache when deleting.
      $this->clearCache();
    }
    catch (\Exception $e) {
      throw $e;
    }
  }

  /**
   * Prepares entity data for API submission.
   *
   * @param \Drupal\external_entities\Entity\ExternalEntityInterface $entity
   *   The entity to prepare.
   *
   * @return array
   *   The prepared data array in JSON:API format.
   */
  protected function prepareEntityData(ExternalEntityInterface $entity) {
    $api_type = $this->configuration['api_type'] ?? 'Cases';

    $data = [
      'data' => [
        'type' => $api_type,
        'attributes' => [],
      ],
    ];

    // Get field definitions.
    $field_definitions = $entity->getFieldDefinitions();

    // Fields to always skip (system fields + fields that shouldn't be updated)
    $skip_fields = [
      'id',
      'uuid',
      'type',
      'langcode',
      'default_langcode',
      'created',
      'changed',
      'field_date',           // date_entered - shouldn't update creation date
      'field_last_modified',  // date_modified - API should set this
    ];

    foreach ($field_definitions as $field_name => $field_definition) {
      // Skip computed, read-only, and excluded fields
      if (in_array($field_name, $skip_fields) || $field_definition->isComputed() || $field_definition->isReadOnly()) {
        continue;
      }

      // Get field value
      if ($entity->hasField($field_name)) {
        $field_item_list = $entity->get($field_name);

        if (!$field_item_list->isEmpty()) {
          $field_value = $field_item_list->value;

          if ($field_value !== NULL && $field_value !== '') {
            // Map Drupal field names to API field names
            $api_field_name = $this->mapFieldName($field_name);
            $data['data']['attributes'][$api_field_name] = $field_value;
          }
        }
      }
    }

    // Add the UUID for updates.
    if (!$entity->isNew()) {
      $entity_data = $this->load($entity->id());
      if (isset($entity_data['uuid'])) {
        $data['data']['id'] = $entity_data['uuid'];
      }
    }

    return $data;
  }

  /**
   * Maps Drupal field names to API field names.
   *
   * @param string $drupal_field_name
   *   The Drupal field name.
   *
   * @return string
   *   The API field name.
   */
  protected function mapFieldName($drupal_field_name) {
    // Map Drupal field names to SuiteCRM API field names
    $mapping = [
      'title' => 'name',
      'field_name' => 'name',
      'field_case_number' => 'case_number',
      'field_date' => 'date_entered',
      'field_last_modified' => 'date_modified',
      'field_description' => 'description',
      'field_priority' => 'priority',
      'field_status' => 'status',
      'default_langcode' => 'langcode', // Keep but probably won't be used by SuiteCRM
    ];

    return $mapping[$drupal_field_name] ?? $drupal_field_name;
  }

  /**
   * Clears the cached cases data.
   */
  public function clearCache() {
    $cache_key = 'suitecrm_cases_all';
    $this->cacheBackend->delete($cache_key);
    $this->casesCache = NULL;
  }

  /**
   * Parses parameters from text format.
   *
   * @param string $text
   *   Text with parameters in key=value format.
   *
   * @return array
   *   Parsed parameters array.
   */
  protected function parseParameters($text) {
    if (empty($text)) {
      return [];
    }

    $parameters = [];
    $lines = explode("\n", $text);

    foreach ($lines as $line) {
      $line = trim($line);
      if (empty($line) || strpos($line, '#') === 0) {
        continue;
      }

      if (strpos($line, '=') !== FALSE) {
        [$key, $value] = explode('=', $line, 2);
        $parameters[trim($key)] = trim($value);
      }
    }

    return $parameters;
  }

  /**
   * Formats parameters array to text format.
   *
   * @param mixed $parameters
   *   Parameters array or string.
   *
   * @return string
   *   Formatted parameters string.
   */
  protected function formatParameters($parameters) {
    // Handle string input (from config).
    if (is_string($parameters)) {
      return $parameters;
    }

    if (empty($parameters) || !is_array($parameters)) {
      return '';
    }

    $lines = [];
    foreach ($parameters as $key => $value) {
      $lines[] = $key . '=' . $value;
    }

    return implode("\n", $lines);
  }

}
