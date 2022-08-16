# SilverStripe Queued Export

Provides a button that will queue the export to a csv file from a gridfield, which can then be found in the site Assets. Good for exporting large numbers of dataobjects without causing timeouts.

## Installation (with composer)

	composer require iliain/silverstripe-queuedexport

## Usage

```php
$config->addComponent(new GridFieldQueueExportButton('buttons-before-left')
```

The following config can be used to alter the behaviour of the export:

```yml
---
Name: myexportconfig
---
Iliain\QueuedExport\Jobs\CSVExportJob:
  storage_directory: 'CSV Exports' # Set the parent folder location where CSV files will be exported to
  increase_memory_limit: true # Enable or disable the environment increasing the time and memory limits for the export process. Default: true
```
A number of extension hooks have been provided to allow further modification of the process if required.