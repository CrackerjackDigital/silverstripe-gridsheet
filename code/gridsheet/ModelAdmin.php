<?php
class GridSheetModelAdmin extends ModelAdmin {
    /**
     * Expose modelClass as needed by save handler
     * @return String
     */
    public function getModelClass() {
        return $this->modelClass;
    }

    public function getEditForm($id = null, $fields = null) {
        $list = $this->getList();

        $buttonAfter = new GridFieldButtonRow('after');
        $exportButton = new GridFieldExportButton('buttons-after-left');
        $exportButton->setExportColumns($this->getExportFields());

        $fieldConfig = GridFieldConfig_RecordEditor::create($this->stat('page_length'))
            ->addComponent($buttonAfter)
            ->addComponent($exportButton)
            ->removeComponent('GridFieldItemEditForm')
            ->addComponent('GridSheetItemEditForm');

        $gridField = new GridField(
            $this->sanitiseClassName($this->modelClass),
            false,
            $list,
            $fieldConfig
        );

        /** @var GridFieldDetailForm $detailForm */
        $detailForm = $gridField->getConfig()->getComponentByType('GridFieldDetailForm');

        // Validation
        if (singleton($this->modelClass)->hasMethod('getCMSValidator')) {
            $detailValidator = singleton($this->modelClass)->getCMSValidator();
            $detailForm->setValidator($detailValidator);
        }

        $form = new Form(
            $this,
            'EditForm',
            new FieldList($gridField),
            new FieldList()
        );
        $form->addExtraClass('cms-edit-form cms-panel-padded center');
        $form->setTemplate($this->getTemplatesWithSuffix('_EditForm'));
        $form->setFormAction(Controller::join_links($this->Link($this->sanitiseClassName($this->modelClass)), 'EditForm'));
        $form->setAttribute('data-pjax-fragment', 'CurrentForm');

        $this->extend('updateEditForm', $form);

        // ask the model's extensions to update the edit form too
        singleton($this->modelClass)->extend('updateEditForm', $form);

        return $form;
    }

}