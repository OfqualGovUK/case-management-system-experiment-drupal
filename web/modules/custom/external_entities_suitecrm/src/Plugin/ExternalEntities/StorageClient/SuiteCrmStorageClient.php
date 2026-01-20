<?php

namespace Drupal\external_entities_suitecrm\Plugin\ExternalEntities\StorageClient;

use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\Exception\ClientException;
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
 * id = "suitecrm_rest",
 * label = @Translation("SuiteCRM REST (Entra Authenticated)")
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
   * The messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

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
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
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
    $messenger,
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
    $this->messenger = $messenger;
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
      $container->get('cache.default'),
      $container->get('messenger')
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
      'read_only_fields' => [],
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

    $form['read_only_fields'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Read-Only Fields'),
      '#description' => $this->t('Field names that should never be sent in PATCH/POST requests (one per line). System fields like id, uuid, type are always excluded. Example: field_date, field_last_modified'),
      '#default_value' => $this->formatReadOnlyFields($this->configuration['read_only_fields'] ?? []),
      '#rows' => 5,
      '#placeholder' => "field_date\nfield_last_modified",
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
    // Validate endpoints are URLs.
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
    // Call parent first.
    parent::submitConfigurationForm($form, $form_state);

    // Save configuration from form values.
    $this->configuration['list_endpoint'] = $form_state->getValue('list_endpoint') ?: '';
    $this->configuration['push_endpoint'] = $form_state->getValue('push_endpoint') ?: '';
    $this->configuration['single_endpoint'] = $form_state->getValue('single_endpoint') ?: '';
    $this->configuration['api_type'] = $form_state->getValue('api_type') ?: 'Cases';
    $this->configuration['format'] = $form_state->getValue('format') ?: 'json';
    $this->configuration['parameters'] = $this->parseParameters($form_state->getValue('parameters') ?: '');
    $this->configuration['response_data_path'] = $form_state->getValue('response_data_path') ?: 'data';
    $this->configuration['read_only_fields'] = $this->parseReadOnlyFields($form_state->getValue('read_only_fields') ?: '');
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
    catch (ClientException $e) {
      $status_code = $e->getResponse()->getStatusCode();
      $response_body = (string) $e->getResponse()->getBody();

      // Log the error.
      $this->logger->error('SuiteCRM API ClientException (@code): @message. Response: @response', [
        '@code' => $status_code,
        '@message' => $e->getMessage(),
        '@response' => $response_body,
      ]);

      // Handle specific error codes.
      if ($status_code === 401) {
        // Use injected token service.
        $token = $this->tokenService->getJwtToken();

        if ($token) {
          $this->messenger->addError($this->t('Authentication token expired. Please <a href="@login">log in again</a>.', [
            '@login' => '/user/login',
          ]));
        }
        else {
          $this->messenger->addError($this->t('Authentication required. Please <a href="@login">log in</a>.', [
            '@login' => '/user/login',
          ]));
        }

        return [];
      }
      elseif ($status_code === 403) {
        $this->messenger->addError($this->t('Access denied. No permission for this action.'));
        return [];
      }
      elseif ($status_code === 404) {
        $this->messenger->addWarning($this->t('The requested resource was not found.'));
        return [];
      }
      elseif ($status_code === 422) {
        $error_data = json_decode($response_body, TRUE);
        if (isset($error_data['message'])) {
          $this->messenger->addError($this->t('Validation error: @message', ['@message' => $error_data['message']]));
        }
        else {
          $this->messenger->addError($this->t('The data provided was invalid.'));
        }
        return [];
      }
      else {
        $this->messenger->addError($this->t('SuiteCRM Communication Error (@code).', [
          '@code' => $status_code,
        ]));
        return [];
      }
    }
    catch (ServerException $e) {
      $status_code = $e->getResponse()->getStatusCode();
      $this->logger->error('SuiteCRM API ServerException (@code): @message', [
        '@code' => $status_code,
        '@message' => $e->getMessage(),
      ]);
      $this->messenger->addError($this->t('SuiteCRM issues (Error @code). Please try later.', [
        '@code' => $status_code,
      ]));
      return [];
    }
    catch (ConnectException $e) {
      $this->logger->error('SuiteCRM API ConnectException: @message', ['@message' => $e->getMessage()]);
      $this->messenger->addError($this->t('Unable to connect to SuiteCRM. Check network.'));
      return [];
    }
    catch (\Exception $e) {
      $this->logger->error('SuiteCRM API unexpected error: @message', ['@message' => $e->getMessage()]);
      $this->messenger->addError($this->t('An unexpected error occurred.'));
      return [];
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
            $flat = [
              'id' => $item['attributes']['case_number'] ?? $item['id'],
              'uuid' => $item['id'],
            ];

            foreach ($item['attributes'] as $key => $value) {
              if (is_string($value)) {
                $flat[$key] = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
              }
              else {
                $flat[$key] = $value;
              }
            }

            $key = $item['attributes']['case_number'] ?? $item['id'];
            $transformed[$key] = $flat;
          }
        }
        $this->cacheBackend->set($cache_key, $transformed, time() + 300);
      }
    }

    $result = $transformed;

    // Apply sorting in PHP.
    if (!empty($sorts)) {
      $sort_field = array_key_first($sorts);
      $sort_direction = $sorts[$sort_field];
      $field_manager = \Drupal::service('entity_field.manager');
      $field_definitions = $field_manager->getFieldDefinitions('suitecrm_case', 'suitecrm_case');

      $is_numeric_field = FALSE;
      if (isset($field_definitions[$sort_field])) {
        $field_type = $field_definitions[$sort_field]->getType();
        $is_numeric_field = in_array($field_type, ['integer', 'decimal', 'float', 'number']);
      }

      uasort($result, function ($a, $b) use ($sort_field, $sort_direction, $is_numeric_field) {
        $a_val = $a[$sort_field] ?? '';
        $b_val = $b[$sort_field] ?? '';

        if ($is_numeric_field && is_numeric($a_val) && is_numeric($b_val)) {
          $cmp = (float) $a_val <=> (float) $b_val;
        }
        else {
          $cmp = $a_val <=> $b_val;
        }

        return $sort_direction === 'DESC' ? -$cmp : $cmp;
      });
    }

    if ($start !== NULL || $length !== NULL) {
      $start = $start ?? 0;
      $length = $length ?? count($result);
      $result = array_slice($result, $start, $length, TRUE);
    }

    return $result;
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
  public function countQuery(array $parameters = []): int {
    $all_entities = $this->query($parameters);
    return count($all_entities);
  }

  /**
   * {@inheritdoc}
   */
  public function countQuerySource(array $parameters = []): int {
    return $this->countQuery($parameters);
  }

  /**
   * {@inheritdoc}
   */
  public function transliterateDrupalFilters(array $parameters, array $context = []): array {
    $translated = [];
    foreach ($parameters as $filter) {
      $field = $filter['field'] ?? '';
      $value = $filter['value'] ?? '';
      $translated['filter[' . $field . '][eq]'] = $value;
    }
    return $translated;
  }

  /**
   * {@inheritdoc}
   */
  public function load(string|int $id): ?array {
    $all_cases = $this->query();
    return $all_cases[$id] ?? NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function loadMultiple(?array $ids = NULL): array {
    $all_cases = $this->query();
    if ($ids === NULL) {
      return $all_cases;
    }
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
    $is_new = $entity->isNew();

    try {
      $data = $this->prepareEntityData($entity);
      $endpoint = $this->configuration['push_endpoint'] ?? '';
      $method = $is_new ? 'POST' : 'PATCH';

      $result = $this->makeRequest($method, $endpoint, [], $data);

      if ($is_new && !empty($result) && is_array($result)) {
        if (isset($result['attributes']['case_number'])) {
          $entity->set('field_case_number', $result['attributes']['case_number']);
        }
        if (isset($result['id'])) {
          $entity->set('id', $result['attributes']['case_number'] ?? $result['id']);
        }
        $entity->enforceIsNew(FALSE);
      }

      $this->clearCache();
      return $is_new ? SAVED_NEW : SAVED_UPDATED;
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
      $this->clearCache();
    }
    catch (\Exception $e) {
      throw $e;
    }
  }

  /**
   * Prepares entity data for API submission.
   */
  protected function prepareEntityData(ExternalEntityInterface $entity) {
    $api_type = $this->configuration['api_type'] ?? 'Cases';
    $data = ['data' => ['type' => $api_type, 'attributes' => []]];
    $field_definitions = $entity->getFieldDefinitions();

    $system_fields = ['id', 'uuid', 'type', 'langcode', 'default_langcode', 'created', 'changed'];
    $read_only_fields = $this->configuration['read_only_fields'] ?? [];
    $skip_fields = array_merge($system_fields, is_array($read_only_fields) ? $read_only_fields : []);

    foreach ($field_definitions as $field_name => $field_definition) {
      if (in_array($field_name, $skip_fields) || $field_definition->isComputed() || $field_definition->isReadOnly()) {
        continue;
      }

      if ($entity->hasField($field_name)) {
        $field_item_list = $entity->get($field_name);
        if (!$field_item_list->isEmpty()) {
          $field_value = $field_item_list->value;
          if ($field_value !== NULL && $field_value !== '') {
            $api_field_name = $this->mapFieldName($field_name);
            if ($api_field_name === 'case_number' && $entity->isNew()) {
              continue;
            }
            $data['data']['attributes'][$api_field_name] = $field_value;
          }
        }
      }
    }

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
   */
  protected function mapFieldName($drupal_field_name) {
    if ($this->externalEntityType) {
      $field_mappers = $this->externalEntityType->get('field_mappers');
      if (isset($field_mappers[$drupal_field_name])) {
        $field_mapper = $field_mappers[$drupal_field_name];
        $config_mapping = $field_mapper['config']['property_mappings']['value']['config']['mapping'] ?? NULL;
        if (!empty($config_mapping)) {
          return $config_mapping;
        }
      }
    }
    if (strpos($drupal_field_name, 'field_') === 0) {
      return substr($drupal_field_name, 6);
    }
    $static_mapping = ['title' => 'name', 'default_langcode' => 'langcode'];
    return $static_mapping[$drupal_field_name] ?? $drupal_field_name;
  }

  /**
   * Clears the cached cases data.
   */
  public function clearCache() {
    $this->cacheBackend->delete('suitecrm_cases_all');
  }

  /**
   * Parses parameters from text format.
   */
  protected function parseParameters($text) {
    if (empty($text)) {
      return [];
    }
    $parameters = [];
    foreach (explode("\n", $text) as $line) {
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
   */
  protected function formatParameters($parameters) {
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

  /**
   * Parses read-only fields from text format.
   */
  protected function parseReadOnlyFields($text) {
    if (empty($text)) {
      return [];
    }
    $fields = [];
    foreach (explode("\n", $text) as $line) {
      $line = trim($line);
      if (!empty($line) && strpos($line, '#') !== 0) {
        $fields[] = $line;
      }
    }
    return $fields;
  }

  /**
   * Formats read-only fields array to text format.
   */
  protected function formatReadOnlyFields($fields) {
    if (is_string($fields)) {
      return $fields;
    }
    if (empty($fields) || !is_array($fields)) {
      return '';
    }
    return implode("\n", $fields);
  }

}
