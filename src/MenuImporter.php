<?php

namespace Drupal\cb_menu_import;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Menu\MenuLinkManagerInterface;
use Drupal\menu_link_content\Entity\MenuLinkContent;
use GuzzleHttp\ClientInterface;

/**
 * Service to import menu items from a JSONAPI endpoint.
 */
class MenuImporter {
  /**
   * The HTTP client to fetch the menu data.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected $httpClient;

  /**
   * The entity type manager interface.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The menu link manager interface.
   *
   * @var \Drupal\Core\Menu\MenuLinkManagerInterface
   */
  protected $menuLinkManager;

  /**
   * Constructs a new MenuImporter.
   *
   * @param \GuzzleHttp\ClientInterface $httpClient
   *   The HTTP client.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager, for menu item operations.
   * @param \Drupal\Core\Menu\MenuLinkManagerInterface $menuLinkManager
   *   The menu link manager, for parent-child relationships.
   */
  public function __construct(
    ClientInterface $httpClient,
    EntityTypeManagerInterface $entityTypeManager,
    MenuLinkManagerInterface $menuLinkManager
  ) {
    $this->httpClient = $httpClient;
    $this->entityTypeManager = $entityTypeManager;
    $this->menuLinkManager = $menuLinkManager;
  }

  /**
   * Imports menu items from a JSONAPI endpoint.
   *
   * @param string $endpoint
   *   The endpoint URL.
   * @param string $menuName
   *   The menu machine name.
   */
  public function importMenus($endpoint, $menuName) {
    try {
      // Fetch menu data from the JSONAPI endpoint.
      $response = $this->httpClient->request('GET', $endpoint);
      $data = json_decode($response->getBody());

      // Check if data is an array of items.
      if (!is_array($data->data)) {
        throw new \Exception('Invalid JSON response format.');
      }

      // Get the UUIDs of menu items in the JSON API payload.
      $payloadUuids = array_column($data->data, 'id');

      // Load all menu items in the specified menu.
      $menuItems = $this->entityTypeManager
        ->getStorage('menu_link_content')
        ->loadByProperties(['menu_name' => $menuName]);

      // Iterate over menu items and delete those not in the payload.
      foreach ($menuItems as $menuItem) {
        if (!in_array($menuItem->uuid(), $payloadUuids)) {
          $menuItem->delete();
        }
      }

      // Temporary storage for items to establish parent-child relationships.
      $items = [];
      $itemParents = [];

      // First pass: Create or update menu items.
      foreach ($data->data as $itemData) {
        $uuid = $itemData->id;
        $parentUuid = isset($itemData->attributes->parent) ? str_replace('menu_link_content:', '', $itemData->attributes->parent) : NULL;

        $menuLink = $this->processMenuItem($itemData, $menuName, $uuid);
        $items[$uuid] = $menuLink;
        $itemParents[$uuid] = $parentUuid;
      }

      // Second pass: Establish parent-child relationships.
      foreach ($itemParents as $childUuid => $parentUuid) {
        if ($parentUuid !== NULL && isset($items[$parentUuid])) {
          $child = $items[$childUuid];
          $parent = $items[$parentUuid];

          // Set parent.
          $this->menuLinkManager->updateDefinition($child->getPluginId(), ['parent' => $parent->getPluginId()]);
        }
      }

    }
    catch (\Exception $e) {
      // Log any exceptions encountered during import.
      \Drupal::logger('cb_menu_import')->error($e->getMessage());
    }
  }

  /**
   * Processes a single menu item.
   *
   * @param object $itemData
   *   The item data from JSONAPI.
   * @param string $menuName
   *   The menu name.
   * @param string $uuid
   *   The UUID of the item.
   *
   * @return \Drupal\menu_link_content\MenuLinkContentInterface
   *   The created or updated menu link content entity.
   */
  private function processMenuItem($itemData, $menuName, $uuid) {
    $menuLinkStorage = $this->entityTypeManager->getStorage('menu_link_content');
    $existingMenuItem = $menuLinkStorage->loadByProperties(['uuid' => $uuid]);
    $menuItem = reset($existingMenuItem);

    if (!$menuItem) {
      $menuItem = MenuLinkContent::create([
        'menu_name' => $menuName,
        'uuid' => $uuid,
      ]);
    }

    // Set the menu link title from the JSON API response.
    $menuItem->title = $itemData->attributes->title;

    // Set all attributes dynamically.
    foreach ($itemData->attributes as $attributeName => $attributeValue) {
      // Check if the attribute exists on the menu item entity.
      if ($menuItem->hasField($attributeName)) {
        // Set the field value.
        $menuItem->{$attributeName}->value = $attributeValue;
      }
      else {
        // Set non-field attributes directly.
        $menuItem->{$attributeName} = $attributeValue;
      }
    }

    // Check if the menu item has a valid link.
    $linkUri = $itemData->attributes->link->uri ?? '';
    if (!empty($linkUri)) {
      $menuItem->link = ['uri' => $linkUri];
      $menuItem->save();
    }
    else {
      // Log an error if the menu item doesn't have a valid link.
      \Drupal::logger('cb_menu_import')->error('Menu item with UUID %uuid does not have a valid link.', ['%uuid' => $uuid]);
    }

    return $menuItem;
  }

}
