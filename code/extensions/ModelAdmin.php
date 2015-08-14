<?php

/**
 * Add to GridSheetModelAdmins which will be dealing with GridSheet enabled models. Can't use ModelAdmin itself because
 * the $modelClass is a protected member variable which we expose via GridSheetModelAdmin.getModelClass().
 */
class GridSheetModelAdminExtension extends CrackerJackDataExtension {

    /**
     * @param array $data
     * @param Form $form
     * @param SS_HTTPRequest|null $request
     * @return SS_HTTPResponse|void
     */
    public function saveGridSheet(array $data, Form $form, SS_HTTPRequest $request = null) {
        $modelClass = $this->getModelClass();

        if (isset($data[$modelClass])) {
            /** @var GridField $gridField */
            if ($gridField = $form->Fields()->fieldByName($modelClass)) {
                if ($gridField instanceof GridField) {

                    $model = singleton($modelClass);

                    $model->extend('saveAddNewInlineColumns', $gridField, $data);

                    $model->extend('saveEditableColumns', $gridField, $data);
                }
            }
            $this->owner->redirect($data['BackURL']);
        }
    }
    protected function getModelClass() {
        if (!$this->owner instanceof GridSheetModelAdmin) {
            throw new Exception("GridSheetModelAdminExtension should only extend GridSheetModelAdmin derived classes");
        }
        return $this->owner->getModelClass();
    }


}