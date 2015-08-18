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
        $rows = self::component_row_data($gridField, get_class(self::add_new_component())) ?: array();

        if ($rows) {

            $modelClass = $gridField->getModelClass();
            $list = $gridField->getList();

            $template = array();

            if ($relatedModel) {
                $relatedKeyName = $relatedModel->getRemoteJoinField($modelClass);

                $template += array(
                    $relatedKeyName => $relatedModel->ID
                );
            }

            return array_filter(
                array_map(
                    function ($row) use ($modelClass, $template, $list) {
                        $model = $modelClass::create($template);

                        $model->extend('gridSheetHandleNewRow', $row);
                        $model->write();

                        if ($model->hasExtension('Versioned')) {
                            $model->writeToStage('Stage');
                        }

                        $list->add($model);

                        return true;
                    },
                    $rows
                )
            );
        }
    }

    public static function save_existing_rows(GridField $gridField, DataObject $relatedModel = null) {
        $rows = self::component_row_data($gridField, get_class(self::editable_columns_component())) ?: array();

        if ($rows) {

            $modelClass = $gridField->getModelClass();
            $list = $gridField->getList();

            $template = array();

            if ($relatedModel) {
                $relatedKeyName = $relatedModel->getRemoteJoinField($modelClass);

                $template += array(
                    $relatedKeyName => $relatedModel->ID
                );
            }
            $names = array_keys($rows);
            $values = array_values($rows);

            return array_filter(
                array_map(
                    function($id, $row) use ($modelClass, $list, $template) {
                        /** @var DataObject $model */
                        if (!$model = $list->find('ID', $id)) {
                            throw new GridSheetException("No such '$modelClass' with id '$id'");
                        }

                        $model->extend('gridSheetHandleExistingRow', $row);
                        $model->write();

                        if ($model->hasExtension('Versioned')) {
                            $model->writeToStage('Stage');
                        }

                        $list->add($model);

                        return true;
                    },
                    $names,
                    $values
                )
            );
        }

    }

    private static function component_row_data(GridField $gridField, $gridComponentClass) {
        if ($gridField->getConfig()->getComponentByType($gridComponentClass)) {
            $gridData = $gridField->Value();

            return isset($gridData[$gridComponentClass]) ? $gridData[$gridComponentClass] : array();
        }
    }
}