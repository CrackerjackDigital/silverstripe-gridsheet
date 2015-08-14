<?php
abstract class GridSheetModelExtension extends CrackerJackDataExtension
    implements GridSheetExtensionInterface
{
    const DefaultEditableColumnsClass = 'GridSheetEditableColumns';

    // set in derived class to name of class to show in grid field
    const ModelClass = '';

    const RelatedModelClass = '';

    private static $default_field_specs = array();

    private static $tab_name = 'Root.Main';

    private static $enable_add_new = false;

    private static $enable_add_new_inline = true;

    private static $default_editable_columns_class = self::DefaultEditableColumnsClass;

    public function updateCMSFields(FieldList $fields) {
        if (static::enabled()) {
            $modelClass = $this->getRelatedModelClass();

            // don't add a grid to your own form
            if ($modelClass != $this->owner->class) {

                if ($gridSheet = $this->makeGridSheet($modelClass, $this->owner->ID)) {

                    $fields->removeByName($modelClass, true);

                    $fields->addFieldToTab(
                        static::own_config('tab_name') ?: static::DefaultTabName,
                        $gridSheet
                    );
                }
            }
        }
    }


    public function updateEditForm(Form $form) {
        if (static::enabled()) {
            $modelClass = $this->getModelClass();
            $fields = $form->Fields();

            if ($gridSheet = $this->makeGridSheet($modelClass, $this->owner->ID)) {
                $gridSheet->setForm($form);

                $fields->replaceField(
                    $modelClass,
                    $gridSheet
                );
                $form->Actions()->push(
                    FormAction::create('saveGridSheet', 'Save')
                        ->addExtraClass('ss-ui-action-constructive ss-ui-button ui-button ui-widget ui-state-default ui-corner-all ui-button-text-icon-primary')
                        ->setAttribute('data-icon', 'save')
                );
            }
        }
    }

    protected function defaultSaveAddNewInlineColumns(GridField $gridField, array $data) {
        $modelClass = $gridField->getModelClass();

        if (static::ModelClass == $modelClass || static::RelatedModelClass == $modelClass) {
            if ($gridField->getConfig()->getComponentByType('GridSheetAddNewInlineButton')) {
                $value = $gridField->Value();

                if (isset($value['GridSheetAddNewInlineButton'])) {
                    $list = $gridField->getList();
                    if (!$list) {
                        $list = new ArrayList();
                        $gridField->setList($list);
                    }

                    foreach ($value['GridSheetAddNewInlineButton'] as $index => &$record) {
                        if ($updateColumns = $this->getUpdateColumns($modelClass, $record)) {

                            /** @var DataObject $model */
                            $model = $modelClass::create($record);
                            $model->write(false, true);

                            $list->add($model);
                        }

                    }
                }
            }
        }
    }

    protected function defaultSaveEditableColumns(GridField $gridField, array $data) {
        $modelClass = $gridField->getModelClass();

        if (static::ModelClass == $modelClass || static::RelatedModelClass == $modelClass) {


            if ($gridField->getConfig()->getComponentByType('GridSheetEditableColumns')) {
                $value = $gridField->Value();

                if (isset($value['GridSheetEditableColumns'])) {
                    $list = $gridField->getList();
                    if (!$list) {
                        $list = new ArrayList();
                        $gridField->setList($list);
                    }

                    foreach ($value['GridSheetEditableColumns'] as $index => &$record) {
                        if ($updateColumns = $this->getUpdateColumns($modelClass, $record)) {

                            if (!$model = $list->find('ID', $record['ID'])) {
                                $model = $modelClass::create($updateColumns);
                            } else {
                                $model->update($updateColumns);
                            }
                            $model->write();
                            $list->add($model);
                        }
                    }
                }
            }
        }
    }

    protected function getUpdateColumns($modelClass, array $record) {
        $columns = array();

        static::ModelClass == $modelClass
            ?   $this->provideEditableColumns($columns)
            :   $this->provideRelatedEditableColumns(static::RelatedModelClass, $record['ID'], $columns);

        $updateColumns = array_intersect_key(
            $record,
            $columns
        );
        return $updateColumns ?: array();
    }

    /**
     * Create a gridsheet for the provided modelClass if the modelClass matches the extended class.
     *
     * @param $modelClass
     * @return GridField
     */
    public function makeGridSheet($modelClass, $relatedID) {
        return $this->gridSheet($modelClass, $relatedID);
    }

    /**
     * Returns a configured gridfield for editing products in place.
     *
     * @param string $modelClass the model class to show in the grid
     * @return GridField
     *
     * @extends calls owner.provideEditablGridFields to get additional editable columns
     */
    protected function gridSheet($modelClass, $relatedID) {
        $data = $this->gridSheetData($modelClass, $relatedID);

        /** @var GridField $gridField */
        $gridField = GridField::create(
            $modelClass,
            $this->fieldLabel(get_class($this), singleton($modelClass)->i18n_plural_name()),
            $data,
            $this->gridSheetConfig($relatedID)
        )->addExtraClass('gridsheet');

        $gridField->setModelClass($modelClass);

        return $gridField;
    }

    protected function gridSheetData($modelClass, $relatedID) {
        $maybeData = $this->owner->extend('provideGridSheetData', $modelClass, $relatedID);

        $data = array_reduce(
            $maybeData,
            function ($prev, $item) {
                return $prev ?: $item;
            }
        );
        return $data;
    }

    /**
     * @return GridFieldConfig
     */
    protected function gridSheetConfig($relatedID) {
        $editableColumns = $this->gridSheetEditableColumns($relatedID);

        /** @var GridFieldConfig $config */
        $config = GridFieldConfig_RecordEditor::create(1000);
        $config->removeComponentsByType('GridFieldDataColumns')
            ->removeComponentsByType('GridFieldFilterHeader')
            ->addComponent($editableColumns);

        if (!static::get_config_setting('enable_add_new')) {
            $config->removeComponentsByType('GridFieldAddNewButton');
        }

        if (static::get_config_setting('enable_add_new_inline')) {
            $config->removeComponentsByType('GridFieldAddNewButton');
            $config->addComponent(new GridSheetAddNewInlineButton());
        }

        return $config;
    }

    protected function gridSheetEditableColumns($relatedID) {
        $modelClass = $this->getModelClass();
        $relatedModelClass = $this->getRelatedModelClass();

        $editableColumnsClass = $modelClass . 'EditableColumns';

        /** @var GridSheetEditableColumns $editableColumns */
        if (!ClassInfo::exists($editableColumnsClass)) {
            $editableColumnsClass = self::own_config('default_editable_columns_class') ?: self::DefaultEditableColumnsClass;
        }
        $editableColumns = $editableColumnsClass::create($this->owner);

        $fieldSpecs = self::own_config('default_field_specs') ?: array();

        if ($relatedID) {
            $this->owner->extend('provideRelatedEditableColumns', $relatedModelClass, $relatedID, $fieldSpecs);
        } else {
            $this->owner->extend('provideEditableColumns', $fieldSpecs);
        }

        if ($fieldOrder = Config::inst()->get($modelClass, 'editable_columns_order')) {
            $reordered = array();

            foreach ($fieldOrder as $fieldName) {
                if (isset($fieldSpecs[$fieldName])) {
                    $reordered[ $fieldName ] = $fieldSpecs[ $fieldName ];
                    unset($fieldSpecs[ $fieldName ]);
                }
            }
            foreach ($fieldSpecs as $fieldName => $fieldSpec) {
                $reordered[$fieldName] = $fieldSpec;
            }
            $fieldSpecs = $reordered;
        }

        $editableColumns->setDisplayFields($fieldSpecs);

        return $editableColumns;
    }

    protected function getRelatedModelClass() {
        return static::RelatedModelClass;
    }

    protected function getModelClass() {
        return $this->owner->class;
    }

}