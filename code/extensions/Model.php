<?php
abstract class GridSheetModelExtension extends DataExtension
    implements GridSheetExtensionInterface
{
    const DefaultEditableColumnsClass = 'GridSheetEditableColumnsComponent';

    // set in derived class to name of class to show in grid field
    const ModelClass = '';

    const RelatedModelClass = '';

    private static $default_field_specs = array();

    private static $tab_name = 'Root.Main';

	private static $gridsheet_enabled = true;

	private static $enable_add_new = false;

    private static $enable_add_new_inline = true;

    private static $default_editable_columns_class = self::DefaultEditableColumnsClass;

    public function enabled($set = null) {
    	$old = $this->owner->config()->get('gridsheet_enabled');
    	if (!is_null($set)) {
    		\Config::inst()->update($this->owner->class, 'gridsheet_enabled', $set);
	    }
	    return $old;
    }

    public function updateCMSFields(FieldList $fields) {
        if ($this->enabled()) {
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
        if ($this->enabled()) {
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

                if (singleton($modelClass)->hasExtension('Versioned')) {
                    $form->Actions()->push(
                        FormAction::create('saveAndPublishGridSheet', 'Save And Publish')
                            ->addExtraClass('ss-ui-action-constructive ss-ui-button ui-button ui-widget ui-state-default ui-corner-all ui-button-text-icon-primary')
                            ->setAttribute('data-icon', 'save')
                    );

                }
            }
        }
    }

    protected function getUpdateColumns($modelClass, array $record) {
        $columns = array();

        if (static::ModelClass == $modelClass) {
	        $this->provideEditableColumns( $columns );
        } else {
	        $this->provideRelatedEditableColumns( static::RelatedModelClass, $record['ID'], $columns );
        }


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

        /** @var GridSheet $gridField */
        $gridField = GridField::create(
            $modelClass,
            singleton($modelClass)->i18n_plural_name(),
            $data,
            $this->gridSheetConfig($relatedID)
        );

        $gridField->setModelClass($modelClass);

        return $gridField;
    }

    protected function gridSheetData($modelClass, $relatedID) {
    	$data = new ArrayList();

        $this->owner->extend('provideGridSheetData', $data, $modelClass, $relatedID);

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

        if (!$this->owner->config()->get('enable_add_new')) {
            $config->removeComponentsByType('GridFieldAddNewButton');
        }

        if ( $this->owner->config()->get('enable_add_new_inline')) {
            $config->removeComponentsByType('GridFieldAddNewButton');
            $config->addComponent(GridSheetModule::add_new_component());
        }

        return $config;
    }

    protected function gridSheetEditableColumns($relatedID) {
        $modelClass = $this->getModelClass();
        $relatedModelClass = $this->getRelatedModelClass();

        $editableColumnsClass = $modelClass . 'EditableColumns';

        /** @var GridSheetEditableColumns $editableColumns */
        if (!ClassInfo::exists($editableColumnsClass)) {
            $editableColumnsClass = self::DefaultEditableColumnsClass;
        }
        $editableColumns = $editableColumnsClass::create($this->owner);

        $fieldSpecs = $this->owner->config()->get('gridsheet_field_specs') ?: array();

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