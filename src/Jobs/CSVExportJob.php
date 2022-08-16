<?php

namespace Iliain\QueuedExport\Jobs;

use SilverStripe\Assets\File;
use SilverStripe\Assets\Folder;
use SilverStripe\Core\Environment;
use SilverStripe\Security\InheritedPermissions;
use Symbiote\QueuedJobs\Services\AbstractQueuedJob;

class CSVExportJob extends AbstractQueuedJob
{
    public function getTitle()
    {
        return 'CSV Export';
    }
    
    public function __construct($items = null, $columns = null, $class = null, $customFields = null, $csvSeparator = ',')
    {
        if ($items === null || $columns === null || $class === null || $customFields === null) {
            return;
        }

        $this->items = $items;
        $this->columns = $columns;
        $this->class = $class;
        $this->customFields = $customFields;
        $this->csvSeparator = $csvSeparator;
    }

    public function setTempFile($fileName): void
    {
        $this->tempFile = $fileName;
    }

    public function getTempFile($fileName): string
    {
        return $this->tempFile;
    }

    public function process(): void
    {
        Environment::increaseMemoryLimitTo();
        Environment::increaseTimeLimitTo();
        $fileName = $this->getFileName();
        $this->beginExport($fileName);
        unlink(TEMP_FOLDER . "$fileName");
        $this->isComplete = true;
    }

    public function getFileName()
    {
        $now = Date("d-m-Y-H-i");
        return "export-$this->class-$now.csv";
    }

    public function beginExport($fileName): void
    {
        $data = $this->generateExportFileData($fileName);
        $this->currentStep += 1;

        // Create folder and protect from public access
        $folder = Folder::find_or_make('CSV Exports');
        if ($folder->CanViewType !== InheritedPermissions::INHERIT) {
            $folder->CanViewType = InheritedPermissions::INHERIT;
            $folder->CanEditType = InheritedPermissions::LOGGED_IN_USERS;
            $folder->protectFile();
            $folder->write();
        }

        $file = File::create();
        $file->setFromString($data, $fileName);

        $file->Title = $fileName;
        $file->ParentID = $folder->ID;
        $file->writeToStage('Live');
        $this->currentStep += 1;
    }

    public function generateExportFileData($fileName)
    {
        $items = $this->items;
        $csvColumns = $this->columns;

        $csvFile = fopen(TEMP_FOLDER . "$fileName", 'w');

        // Set headers
        $headers = [];
        foreach ($csvColumns as $columnSource => $columnHeader) {
            if (is_array($columnHeader) && array_key_exists('title', $columnHeader)) {
                $headers[] = $columnHeader['title'];
            } else {
                $headers[] = (!is_string($columnHeader) && is_callable($columnHeader)) ? $columnSource : $columnHeader;
            }
        }
        fputcsv($csvFile, $headers);
        unset($headers);

        // Set data
        foreach ($items->limit(null) as $item) {
            if (!$item->hasMethod('canView') || $item->canView()) {
                $columnData = [];

                foreach ($csvColumns as $columnSource => $columnHeader) {
                    if (!is_string($columnHeader) && is_callable($columnHeader)) {
                        if ($item->hasMethod($columnSource)) {
                            $relObj = $item->{$columnSource}();
                        } else {
                            $relObj = $item->relObject($columnSource);
                        }

                        $value = $columnHeader($relObj);
                    // @todo Implement alternative for following code
                    // } elseif ($gridFieldColumnsComponent && array_key_exists($columnSource, $columnsHandled)) {
                    //     $value = strip_tags(
                    //         $gridFieldColumnsComponent->getColumnContent($gridField, $item, $columnSource)
                    //     );
                    } else {
                        $value = $this->getDataFieldValue($item, $columnSource);

                        if ($value === null) {
                            $value = $this->getDataFieldValue($item, $columnHeader);
                        }
                    }

                    $columnData[] = $value;
                }

                fputcsv($csvFile, $columnData);
                unset($columnData);
            }
        }

        fclose($csvFile);
        return (string)file_get_contents(TEMP_FOLDER . "$fileName");
    }

    public function getDataFieldValue($record, $fieldName)
    {
        if (isset($this->customFields[$fieldName])) {
            $callback = $this->customFields[$fieldName];

            return $callback($record);
        }

        if ($record->hasMethod('relField')) {
            return $record->relField($fieldName);
        }

        if ($record->hasMethod($fieldName)) {
            return $record->$fieldName();
        }

        return $record->$fieldName;
    }
}
