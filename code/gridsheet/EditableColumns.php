<?php
/**
 * Allows inline editing of grid field records without having to load a separate
 * edit interface.
 *
 * The form fields used can be configured by setting the value in {@link setDisplayFields()} to one
 * of the following forms:
 *   - A Closure which returns the field instance.
 *   - An array with a `callback` key pointing to a function which returns the field.
 *   - An array with a `field` key->response specifying the field class to use.
 */
class GridSheetEditableColumns extends GridSheetDataColumns implements
    GridField_HTMLProvider,
    GridField_SaveHandler,
    GridField_URLHandler
{

    private static $allowed_actions = array(
        'handleForm',
        'handleSave'
    );

    /**
     * @var Form[]
     */
    protected $forms = array();

    public function handleSave(GridField $gridField, DataObjectInterface $record) {
        GridSheetModule::save_new_rows($gridField);

        GridSheetModule::save_existing_rows($gridField);

        $this->save($gridField, $record);
    }

    /**
     * Override in implementation to save particular rows if required.
     *
     * @param GridField           $grid
     * @param DataObjectInterface $record
     */
    protected function save(GridField $grid, DataObjectInterface $record) {
        $list  = $grid->getList();
        $value = $grid->Value();

        if(!isset($value[__CLASS__]) || !is_array($value[__CLASS__])) {
            return;
        }

        $form = $this->getForm($grid, $record);

        foreach($value[__CLASS__] as $id => $fields) {
            if(!is_numeric($id) || !is_array($fields)) {
                continue;
            }

            $item = $list->byID($id);

            if(!$item || !$item->canEdit()) {
                continue;
            }

            $extra = array();

            $form->loadDataFrom($fields, Form::MERGE_CLEAR_MISSING);
            $form->saveInto($item);

            if($list instanceof ManyManyList) {
                $extra = array_intersect_key($form->getData(), (array) $list->getExtraFields());
            }

            $item->write();
            $list->add($item, $extra);
        }
    }

    public function getColumnContent($grid, $record, $col) {
        if(!$record->canEdit()) {
            return parent::getColumnContent($grid, $record, $col);
        }

        $fields = $this->getForm($grid, $record)->Fields();
        $value  = $grid->getDataFieldValue($record, $col);
        $rel = (strpos($col,'.') === false); // field references a relation value
        $field = ($rel) ? clone $fields->fieldByName($col) : new ReadonlyField($col);

        if(!$field) {
            throw new Exception("Could not find the field '$col'");
        }

        if(array_key_exists($col, $this->fieldCasting)) {
            $value = $grid->getCastedValue($value, $this->fieldCasting[$col]);
        }

        $value = $this->formatValue($grid, $record, $col, $value);

        $field->setName($this->getFieldName($field->getName(), $grid, $record));
        $field->setValue($value);

        return $field->Field();
    }

    public function getHTMLFragments($grid) {
        GridFieldExtensions::include_requirements();
        $grid->addExtraClass('ss-gridfield-editable');
    }

    public function handleForm(GridField $grid, $request) {
        $id   = $request->param('ID');
        $list = $grid->getList();

        if(!ctype_digit($id)) {
            throw new SS_HTTPResponse_Exception(null, 400);
        }

        if(!$record = $list->byID($id)) {
            throw new SS_HTTPResponse_Exception(null, 404);
        }

        $form = $this->getForm($grid, $record);

        foreach($form->Fields() as $field) {
            $field->setName($this->getFieldName($field->getName(), $grid, $record));
        }

        return $form;
    }

    public function getURLHandlers($grid) {
        return array(
            'editable/form/$ID' => 'handleForm'
        );
    }

    /**
     * Gets the field list for a record.
     *
     * @param GridField $grid
     * @param DataObjectInterface $record
     * @return FieldList
     */
    public function getFields(GridField $grid, DataObjectInterface $record) {
        $cols   = $this->getDisplayFields($grid);
        $fields = new FieldList();

        $list   = $grid->getList();
        $class  = $list ? $list->dataClass() : null;

        foreach($cols as $col => $info) {
            $field = null;

            if($info instanceof Closure) {
                $field = call_user_func($info, $record, $col, $grid);
            } elseif(is_array($info)) {
                if(isset($info['callback'])) {
                    $field = call_user_func($info['callback'], $record, $col, $grid);
                } elseif(isset($info['field'])) {
                    if ($info['field'] == 'LiteralField') {
                        $field = new $info['field']($col, null);
                    }else{
                        $field = new $info['field']($col);
                    }
                }

                if(!$field instanceof FormField) {
                    throw new Exception(sprintf(
                        'The field for column "%s" is not a valid form field',
                        $col
                    ));
                }
            }

            if(!$field && $list instanceof ManyManyList) {
                $extra = $list->getExtraFields();

                if($extra && array_key_exists($col, $extra)) {
                    $field = Object::create_from_string($extra[$col], $col)->scaffoldFormField();
                }
            }

            if(!$field) {
                if($class && $obj = singleton($class)->dbObject($col)) {
                    $field = $obj->scaffoldFormField();
                } else {
                    $field = new ReadonlyField($col);
                }
            }

            if(!$field instanceof FormField) {
                throw new Exception(sprintf(
                    'Invalid form field instance for column "%s"', $col
                ));
            }

            $fields->push($field);
        }

        return $fields;
    }


    /**
     * Additional metadata about the column which can be used by other components,
     * e.g. to set a title for a search column header.
     *
     * @param GridField $gridField
     * @param string $columnName
     * @return array - Map of arbitrary metadata identifiers to their values.
     */
    public function getColumnMetadata($gridField, $columnName) {
        $columns = $this->getDisplayFields($gridField);

        $title = null;
        $extraClasses = null;

        if(is_string($columns[$columnName])) {
            $title = $columns[$columnName];
        } elseif (is_array($columns[$columnName])) {
            $title = isset($columns[$columnName]['title'])
                ? $columns[$columnName]['title']
                : '';

            $extraClasses = isset($columns[$columnName]['extraClasses'])
                ? $columns[$columnName]['extraClasses']
                : '';
        }
        return array(
            'title' => $title,
            'visibility' => empty($title) ? 'hidden' : 'visible',
            'extraClasses' => $extraClasses
        );
    }


    /**
     * Attributes for the element containing the content returned by {@link getColumnContent()}. Merges in all 'data-'
     * values from getColumnMetadata call for the column.
     *
     * @param  GridField $gridField
     * @param  DataObject $record displayed in this row
     * @param  string $columnName
     * @return array
     */
    public function getColumnAttributes($gridField, $record, $columnName) {
        $metaData = $this->getColumnMetadata($gridField, $columnName);

        $classes = implode(' ', array(
            'col-' . preg_replace('/[^\w]/', '-', $columnName),
            $metaData['extraClasses']
        ));

        return array(
            'class' => $classes,
            'data-visibility' => $metaData['visibility']
        );
    }

    /**
     * Gets the form instance for a record.
     *
     * @param GridField $grid
     * @param DataObjectInterface $record
     * @return Form
     */
    public function getForm(GridField $grid, DataObjectInterface $record) {
        $fields = $this->getFields($grid, $record);

        $form = new Form($this, null, $fields, new FieldList());
        $form->loadDataFrom($record);

        $form->setFormAction(Controller::join_links(
            $grid->Link(), 'editable/form', $record->ID
        ));

        return $form;
    }

    protected function getFieldName($name,  GridField $grid, DataObjectInterface $record) {
        return sprintf(
            '%s[%s][%s][%s]', $grid->getName(), __CLASS__, $record->ID, $name
        );
    }

}
