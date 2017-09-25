<?php
class GridSheetAddNewInlineButton extends GridFieldAddNewInlineButton {

	/**
	 * @param GridField $grid
	 *
	 * @return array
	 * @throws \Exception
	 * @throws \UnexpectedValueException
	 */
    public function getHTMLFragments($grid) {
        $modelInstance = singleton($grid->getModelClass());

        if($grid->getList() && !$modelInstance->canCreate()) {
            return array();
        }

        $fragment = $this->getFragment();

        $editableClass = GridSheetEditableColumnsComponent::class;

        if(!$editable = $grid->getConfig()->getComponentByType($editableClass)) {
            throw new Exception('Inline adding requires the editable columns component');
        }

        GridSheet::include_requirements();

        $data = new ArrayData(array(
            'Title'  => $this->getTitle(),
            'SingularName' => $modelInstance->i18n_singular_name()
        ));

        return array(
            $fragment => $data->renderWith(static::class),
            'after'   => $this->getRowTemplate($grid, $editable)
        );
    }

	public function handleSave( GridField $grid, DataObjectInterface $record ) {
		$list  = $grid->getList();
		$value = $grid->Value();

		if ( ! isset( $value[ __CLASS__ ] ) || ! is_array( $value[ __CLASS__ ] ) ) {
			return;
		}

		$class = $grid->getModelClass();
		/** @var GridFieldEditableColumns $editable */
		$editable = $grid->getConfig()->getComponentByType( 'GridFieldEditableColumns' );
		/** @var GridFieldOrderableRows $sortable */
		$sortable = $grid->getConfig()->getComponentByType( 'GridFieldOrderableRows' );

		if ( ! singleton( $class )->canCreate() ) {
			return;
		}

		foreach ( $value[ __CLASS__ ] as $fields ) {
			$item  = $class::create();
			$extra = array();

			$form = $editable->getForm( $grid, $item );
			$form->loadDataFrom( $fields, Form::MERGE_CLEAR_MISSING );
			$form->saveInto( $item );

			// Check if we are also sorting these records
			if ( $sortable ) {
				$sortField = $sortable->getSortField();
				$item->setField( $sortField, $fields[ $sortField ] );
			}

			if ( $list instanceof ManyManyList ) {
				$extra = array_intersect_key( $form->getData(), (array) $list->getExtraFields() );
			}

			$item->write();
			$list->add( $item, $extra );
		}
	}

    private function getRowTemplate(GridField $grid, GridSheetEditableColumnsComponent $editable) {
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