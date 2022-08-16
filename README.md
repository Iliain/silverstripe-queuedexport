# SilverStripe Queued Export

Provides a button that will queue the export to a csv file from a gridfield, which can then be found in the site Assets. Good for exporting large numbers of dataobjects without causing timeouts.

## Installation (with composer)

	composer require iliain/silverstripe-queuedexport

## Usage

```php
$config->addComponent(new GridFieldQueueExportButton('buttons-before-left')
```
