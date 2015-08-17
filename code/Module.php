<?php

class GridSheetModule extends CrackerjackModule {


    public static function add_new_component() {
        return Injector::inst()->get('GridSheetAddNewComponent');
    }

    public static function editable_columns_component() {
        return Injector::inst()->get('GridSheetEditableColumnsComponent');
    }

    /**
     * Determines the model from the gridfield's $modelClass, creates an instance and then
     * requests the model to populate itself via extend.gridSheetAddNew call. The created
     * instance has its relationship ID set from the relatedModel's ID if relatedModel is provided.
     *
     * @param GridField $gridField
     * @param DataObject $relatedModel
     * @return array
     * @throws Exception
     */
    public static function save_new_rows(GridField $gridField, DataObject $relatedModel = null) {
        $modelClass = $gridField->getModelClass();
        $list = $gridField->getList();

        $template = array();

        if ($relatedModel) {
            $relatedKeyName = $relatedModel->getRemoteJoinField($modelClass);

            $template += array(
                $relatedKeyName => $relatedModel->ID
            );
        }

        $rows = self::row_data($gridField, self::add_new_component()->class) ?: array();

        return array_filter(
            array_map(
                function($row) use ($modelClass, $template, $list) {
                    $model = $modelClass::create($template);

                    if (array_filter(
                        $model->extend('gridSheetHandleNewRow', $row)
                    )) {
                        $model->write();

                        $list->add($model);

                        return true;
                    }
                    return false;
                },
                $rows
            )
        );
    }

    public static function save_existing_rows(GridField $gridField, DataObject $relatedModel = null) {
        $modelClass = $gridField->getModelClass();
        $list = $gridField->getList();

        $rows = self::row_data($gridField, self::editable_columns_component()->class) ?: array();

        $template = array();

        if ($relatedModel) {
            $relatedKeyName = $relatedModel->getRemoteJoinField($modelClass);

            $template += array(
                $relatedKeyName => $relatedModel->ID
            );
        }

        return array_filter(
            array_map(
                function($id, $row) use ($modelClass, $list, $template) {
                    /** @var DataObject $model */
                    if (!$model = $list->find('ID', $id)) {
                        throw new GridSheetException("No such '$modelClass' with id '$id'");
                    }

                    $model->update($template);

                    if (array_filter(
                        $model->extend('gridSheetHandleExisitingRow', $row)
                    )) {
                        $model->write();

                        $list->add($model);

                        return true;
                    }
                    return false;
                },
                array_keys($rows),
                array_values($rows)
            )
        );


    }

    private static function row_data(GridField $gridField, Form $form, $gridComponentClass) {
        $modelClass = $gridField->getModelClass();

        if ($gridField = $form->Fields()->fieldByName($modelClass)) {

            if ($gridField instanceof GridField) {

                if ($gridField->getConfig()->getComponentByType($gridComponentClass)) {
                    $gridData = $gridField->Value();

                    return $gridData[$gridComponentClass];
                }
            }
        }
    }
}