<?php

/**
 * Add to GridSheetModelAdmins which will be dealing with GridSheet enabled models. Can't use ModelAdmin itself because
 * the $modelClass is a protected member variable which we expose via GridSheetModelAdmin.getModelClass().
 */
class GridSheetModelAdminExtension extends DataExtension {

    /**
     * @param array $data
     * @param Form $form
     * @param SS_HTTPRequest|null $request
     * @return SS_HTTPResponse|void
     */
    public function saveGridSheet(array $data, Form $form, SS_HTTPRequest $request = null) {
        $this->save($data, $form, false, $request);
    }

    /**
     * @param array $data
     * @param Form $form
     * @param SS_HTTPRequest|null $request
     * @return SS_HTTPResponse|void
     */
    public function saveAndPublishGridSheet(array $data, Form $form, SS_HTTPRequest $request = null) {
        $this->save($data, $form, true, $request);
    }

    protected function save(array $data, Form $form, $publish = false, SS_HTTPRequest $request = null) {
        $modelClass = $this->getModelClass();

        if (isset($data[$modelClass])) {
            if ($gridField = $form->Fields()->fieldByName($modelClass)) {
                if ($gridField instanceof GridField) {

                    GridSheetModule::save_new_rows($gridField, $publish);

                    GridSheetModule::save_existing_rows($gridField, $publish);
                }
            }
        }
        if (isset($data['BackURL'])) {
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