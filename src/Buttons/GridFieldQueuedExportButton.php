<?php

namespace Iliain\QueuedExport\Buttons;

use SilverStripe\Control\Controller;
use SilverStripe\ORM\FieldType\DBDatetime;
use SilverStripe\Forms\GridField\GridField;
use Symbiote\QueuedJobs\Services\QueuedJobService;
use SilverStripe\Forms\GridField\GridFieldPaginator;
use SilverStripe\Forms\GridField\GridField_FormAction;
use SilverStripe\Forms\GridField\GridFieldExportButton;
use SilverStripe\Forms\GridField\GridFieldFilterHeader;
use SilverStripe\Forms\GridField\GridFieldSortableHeader;

class GridFieldQueueExportButton extends GridFieldExportButton
{
    public function getHTMLFragments($gridField)
    {
        $button = new GridField_FormAction(
            $gridField,
            'schduleexport',
            'Schedule Export to CSV',
            'schduleexport',
            null
        );
        $button->addExtraClass('btn btn-secondary no-ajax font-icon-down-circled action_export');
        $button->setForm($gridField->getForm());
        return [
            $this->targetFragment => $button->Field(),
        ];
    }

    public function getActions($gridField)
    {
        return ['schduleexport'];
    }

    public function handleAction(GridField $gridField, $actionName, $arguments, $data)
    {
        if ($actionName == 'schduleexport') {
            return $this->handleExport($gridField);
        }
        return null;
    }

    public function getURLHandlers($gridField)
    {
        return [
            'schduleexport' => 'handleExport',
        ];
    }

    public function handleExport($gridField, $request = null)
    {
        $gridField->getConfig()->removeComponentsByType(GridFieldPaginator::class);

        $items = $gridField->getManipulatedList();
        foreach ($gridField->getConfig()->getComponents() as $component) {
            if ($component instanceof GridFieldFilterHeader || $component instanceof GridFieldSortableHeader) {
                $items = $component->getManipulatedData($gridField, $items);
            }
        }

        $export = new CSVExportJob($items, $this->getExportColumnsForGridField($gridField), $gridField->getModelClass(), $gridField->customDataFields, ',');

        // Add back the paginator
        $gridField->getConfig()->addComponent(new GridFieldPaginator);

        // Queue job
        QueuedJobService::singleton()
            ->queueJob($export, DBDatetime::create()->setValue(DBDatetime::now()->getTimestamp())->Rfc2822());

        // Redirect back
        // @todo implement confirmation message 
        Controller::curr()->redirect(Controller::curr()->Link());
        return null;
    }
}
