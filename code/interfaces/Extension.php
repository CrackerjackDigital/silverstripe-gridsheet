<?php
interface GridSheetExtensionInterface {
    /**
     * Called when a grid sheet is displaying a model directly, e.g. as a model admin managed model.
     * @param array $fieldSpecs
     * @return mixed
     */
    public function provideEditableColumns(array &$fieldSpecs);

    /**
     * Called when a grid sheet is displaying a model related to another model. e.g. as a grid for a models ItemEditForm
     * in ModelAdmin.
     *
     * @param $relatedModelClass
     * @param $relatedID
     * @param array $fieldSpecs
     * @return mixed
     */
    public function provideRelatedEditableColumns($relatedModelClass, $relatedID, array &$fieldSpecs);

    /**
     * Called for each new row in a grid when it is saved.
     * @param $record
     * @return bool
     */
    public function gridSheetHandleNewRow(array &$record);

    /**
     * Called to each existing row in a grid when it is saved.
     * @param $record
     * @return bool
     */
    public function gridSheetHandleExistingRow(array &$record);
}