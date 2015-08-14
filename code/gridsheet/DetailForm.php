<?php
class GridSheetDetailForm extends GridFieldDetailForm {

}

/**
 * Alter ItemEditForm to replace GridFields with GridSheets where the displayed
 * model extends a GridSheetModelExtension
 */
class GridSheetDetailForm_ItemRequest extends GridFieldDetailForm_ItemRequest {
    private static $allowed_actions = array(
        'edit',
        'view',
        'ItemEditForm'
    );
    public function ItemEditForm() {
        $form = parent::ItemEditForm();

        //$this->gridSheetItemEditForm($form);

        return $form;
    }

    /**
     * Replace ItemEditForm grid fields with editable grid fields.
     *
     * @param Form $form
     */
    public function gridSheetItemEditForm(Form $form) {
        $hasMany = $this->record->has_many();
        $fields = $form->Fields();

        foreach ($hasMany as $relationshipName => $modelClass) {

            if ($gridField = $fields->dataFieldByName($modelClass)) {
                $model = singleton($modelClass);

                foreach ($model->getExtensionInstances() as $extension) {
                    if ($extension instanceof GridSheetModelExtension) {
                        /** @var GridSheet $gridSheet */
                        if ($gridSheet = $model->makeGridSheet($modelClass, $this->record->ID)) {

                            $gridSheet->setForm($form);

                            $fields->replaceField(
                                $modelClass,
                                $gridSheet
                            );
                        }
                    }
                }
            }
        }
    }
}