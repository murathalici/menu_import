<?php

namespace Drupal\cb_menu_import\Form;

use Drupal\cb_menu_import\MenuImporter;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form handler for importing menu items.
 */
class ImportMenuForm extends FormBase {

  /**
   * The menu importer service.
   *
   * @var \Drupal\cb_menu_import\MenuImporter
   */
  protected $importer;

  /**
   * Constructs a new ImportMenuForm object.
   *
   * @param \Drupal\cb_menu_import\MenuImporter $importer
   *   The menu importer service.
   */
  public function __construct(MenuImporter $importer) {
    $this->importer = $importer;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('cb_menu_import.importer')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'cb_menu_import_admin_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['endpoint'] = [
      '#type' => 'url',
      '#title' => $this->t('JSONAPI Endpoint'),
      '#required' => TRUE,
      '#description' => $this->t('Enter the URL of the JSONAPI endpoint providing menu data.'),
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Import Menu Items'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $endpoint = $form_state->getValue('endpoint');
    $menu_name = $this->extractMenuNameFromEndpoint($endpoint);

    // Trigger the import process using the importer service.
    $this->importer->importMenus($endpoint, $menu_name);

    $this->messenger()->addMessage($this->t('Menu items have been imported.'));
  }

  /**
   * Extracts the menu name from the JSONAPI endpoint URL.
   *
   * @param string $endpoint
   *   The JSONAPI endpoint URL.
   *
   * @return string|false
   *   The extracted menu name, or false if not found.
   */
  private function extractMenuNameFromEndpoint($endpoint) {
    $path = parse_url($endpoint, PHP_URL_PATH);
    $path_segments = explode('/', trim($path, '/'));
    return end($path_segments);
  }

}
