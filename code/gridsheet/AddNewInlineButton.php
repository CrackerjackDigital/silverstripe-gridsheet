<?php
class GridSheetAddNewInlineButton extends GridFieldAddNewInlineButton {

    public function getHTMLFragments($grid) {
        $modelInstance = singleton($grid->getModelClass());

        if($grid->getList() && !$modelInstance->canCreate()) {
            return array();
        }

        $fragment = $this->getFragment();

        if(!$editable = $grid->getConfig()->getComponentByType('GridSheetEditableColumns')) {
            throw new Exception('Inline adding requires the editable columns component');
        }

        Requirements::javascript(THIRDPARTY_DIR . '/javascript-templates/tmpl.js');
        Requirements::css('gridsheet/css/gridsheet.css');

        GridFieldExtensions::include_requirements();

        $data = new ArrayData(array(
            'Title'  => $this->getTitle(),
            'SingularName' => $modelInstance->i18n_singular_name()
        ));

        return array(
            $fragment => $data->renderWith(__CLASS__),
            'after'   => $this->getRowTemplate($grid, $editable)
        );
    }

    private function getRowTemplate(GridField $grid, GridFieldEditableColumns $editable) {
        $columns = new ArrayList();
        $handled = array_keys($editable->getDisplayFields($grid));

        if($grid->getList()) {
            $record = Object::create($grid->getModelClass());
        } else {
            $record = null;
        }

        $fields = $editable->getFields($grid, $record);

        $gridColumns = $grid->getColumns();

        foreach($gridColumns as $column) {
            if(in_array($column, $handled)) {
                if ($field = $fields->dataFieldByName($column)) {
                    // we only care about data fields
                    $field->setName(sprintf(
                        '%s[%s][{%%=o.num%%}][%s]', $grid->getName(), __CLASS__, $field->getName()
                    ));

                    $content = $field->Field();
                } elseif ($field = $fields->fieldByName($column)) {
                    $content = $field->Field();
                }
            } else {
                $content = null;
            }

            $attrs = '';

            foreach($grid->getColumnAttributes($record, $column) as $attr => $val) {
                $attrs .= sprintf(' %s="%s"', $attr, Convert::raw2att($val));
            }

            $columns->push(new ArrayData(array(
                'Content'    => $content,
                'Attributes' => $attrs,
                'IsActions'  => $column == 'Actions'
            )));
        }

        return $columns->renderWith('GridFieldAddNewInlineRow');
    }
}