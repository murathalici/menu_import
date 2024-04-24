# CB Menu Import Module

## Introduction

The Menu Import module provides functionality to import menu items from a JSONAPI endpoint into a Drupal menu.

## Functionality

- Imports menu items from a JSON data source into the specified Drupal menu.
- Creates new menu items for items that don't exist.
- Updates existing menu items with new data from the JSON source.
- Deletes menu items that are not present in the JSON source.

## Usage

1. Navigate to the menu import form located at /admin/config/content/cb-menu-import.
2. Enter the URL of the JSONAPI endpoint providing menu data.
3. Click on the "Import Menu Items" button.
4. Menu items will be imported into the specified Drupal menu based on the provided JSONAPI data.

## Troubleshooting

- If you encounter any issues during the import process, check the Drupal logs (/admin/reports/dblog) for error messages.
- Ensure that the provided JSONAPI endpoint URL is correct and accessible.
- Make sure that the menu structure in the JSONAPI response matches the expected format.
