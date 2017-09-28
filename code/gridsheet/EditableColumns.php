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
class GridSheetEditableColumnsComponent extends GridSheetDataColumns implements
	GridField_HTMLProvider,
	GridField_SaveHandler,
	GridField_URLHandler {

	private static $allowed_actions = array(
		'handleForm',
		'handleSave',
		'save',
	);

	/**
	 * @var Form[]
	 */
	protected $forms = array();

	public function getURLHandlers( $gridField ) {
		return [
			'POST /' => 'save',
		];
	}

	public function getHTMLFragments( $grid ) {
		Requirements::javascript( 'gridsheet/js/gridsheet.js' );
		$grid->addExtraClass( 'ss-gridfield-editable' );
	}

	/**
	 * @param \GridField|\GridSheet $grid
	 * @param \SS_HTTPRequest       $request
	 *
	 * @throws \LogicException
	 */
	public function save( GridField $grid, SS_HTTPRequest $request ) {
		$data = $request->postVars();

		if ( isset( $data[ $grid->Name ] ) ) {
			$currValue = $grid->Value();
			$grid->setValue( $data[ $grid->Name ] );

			/** @var DataObject $model */
			$model = singleton( $grid->getModelClass() );

			foreach ( $grid->getConfig()->getComponents() as $component ) {
				if ( $component instanceof GridField_SaveHandler ) {
					// will call back to this component
					$component->handleSave( $grid, $model );
				}
			}

			$grid->setValue( $currValue );

			if ( Controller::curr() && $response = Controller::curr()->getResponse() ) {
				$response->addHeader( 'X-Status', rawurlencode( _t( 'GridSheet.DONE', 'Done.' ) ) );
			}
		}
	}

	/**
	 * @param \GridField|\GridSheet $grid
	 * @param \DataObjectInterface  $model
	 *
	 */
	public function handleSave( GridField $grid, DataObjectInterface $model ) {
		$data = $grid->Value();
		if ( isset( $data[ GridSheetAddNewInlineButton::class ] ) ) {
			$this->saveNewRows( $grid, $data[ GridSheetAddNewInlineButton::class ], get_class( $model ) );
		}
		$this->saveExistingRows( $grid, $model );
	}

	/**
	 * @param GridField $grid
	 * @param           $rows
	 * @param           $modelClass
	 *
	 * @return array
	 */
	public function saveNewRows( GridField $grid, array &$rows, $modelClass ) {
		$list = $grid->getList();

		/** @var GridFieldOrderableRows $sortable */
		$sortable = $grid->getConfig()->getComponentByType( 'GridFieldOrderableRows' );

		// put any init data here for model fields when one is created
		$template = [];

		return array_filter(
			array_map(
				function ( $row ) use ( $grid, $modelClass, $template, $list, $sortable ) {
					if ( ! isset( $row['ID'] ) ) {
						/** @var \DataObject $model */
						$model = $modelClass::create( $template );
						if ($model->canCreate()) {

							$this->gridSheetHandleNewRow( $grid, $model, $row );

							// Check if we are also sorting these records
							if ( $sortable ) {
								$sortField = $sortable->getSortField();
								$model->setField( $sortField, $row[ $sortField ] );
							}
							$model->write();

							$extra = ( $list instanceof ManyManyList )
								? array_intersect_key( $row, (array) $list->getExtraFields() )
								: [];

							$list->add( $model, $extra );

							return true;
						}
					}
				},
				$rows
			)
		);
	}

	/**
	 * Called for each new row in a grid when it is saved.
	 *
	 * @param \GridField $grid
	 * @param DataObject $model
	 * @param array      $row
	 *
	 * @throws \Exception
	 * @internal param array $record
	 *
	 */
	public function gridSheetHandleNewRow( GridField $grid, $model, array &$row ) {
		$form = $this->getForm( $grid, $model );
		$form->loadDataFrom( $row, Form::MERGE_CLEAR_MISSING );
		$form->saveInto( $model );
	}

	public function saveExistingRows( GridField $grid, $model ) {
		if ( $rows = $grid->Value() ) {

			$modelClass = $grid->getModelClass();
			$list       = $grid->getList();

			// put any init data here for model fields when one is updated
			$template = [];

			$names  = array_keys( $rows );
			$values = array_values( $rows );

			/** @var GridFieldOrderableRows $sortable */
			$sortable = $grid->getConfig()->getComponentByType( 'GridFieldOrderableRows' );

			return array_filter(
				array_map(
					function ( $id, $row ) use ( $grid, $modelClass, $list, $template, $sortable ) {
						/** @var DataObject $model */
						if ( $model = $list->find( 'ID', $id ) ) {
							if ($model->canEdit()) {

								$row = array_merge(
									$template,
									$row
								);
								$this->gridSheetHandleExistingRow( $grid, $model, $row );

								// Check if we are also sorting these records
								if ( $sortable ) {
									$sortField = $sortable->getSortField();
									$model->setField( $sortField, $row[ $sortField ] );
								}
								if ( $model->isChanged() ) {
									$model->write();
								}
								$extra = ( $list instanceof ManyManyList )
									? array_intersect_key( $row, (array) $list->getExtraFields() )
									: [];

								$list->add( $model, $extra );
								return true;
							}

						}

					},
					$names,
					$values
				)
			);
		}
	}

	/**
	 * Called to each existing row in a grid when it is saved.
	 *
	 * @param \GridField  $grid
	 * @param \DataObject $model
	 * @param array       $row
	 *
	 * @return array
	 * @throws \Exception
	 * @internal param array $record
	 *
	 */
	public function gridSheetHandleExistingRow( GridField $grid, $model, array &$row ) {
		$form = $this->getForm( $grid, $model );
		$form->loadDataFrom( $row, Form::MERGE_CLEAR_MISSING );
		$form->saveInto( $model );
	}

	public function getColumnContent( $grid, $record, $col ) {
		if ( ! $record->canEdit() ) {
			return parent::getColumnContent( $grid, $record, $col );
		}

		$fields = $this->getForm( $grid, $record )->Fields();
		$value  = $grid->getDataFieldValue( $record, $col );
		$rel    = ( strpos( $col, '.' ) === false ); // field references a relation value
		$field  = ( $rel ) ? clone $fields->fieldByName( $col ) : new ReadonlyField( $col );

		if ( ! $field ) {
			throw new Exception( "Could not find the field '$col'" );
		}

		if ( array_key_exists( $col, $this->fieldCasting ) ) {
			$value = $grid->getCastedValue( $value, $this->fieldCasting[ $col ] );
		}

		$value = $this->formatValue( $grid, $record, $col, $value );

		$field->setName( $this->getFieldName( $field->getName(), $grid, $record ) );
		$field->setValue( $value );

		return $field->Field();
	}

	public function handleForm( GridField $grid, $request ) {
		$id   = $request->param( 'ID' );
		$list = $grid->getList();

		if ( ! ctype_digit( $id ) ) {
			throw new SS_HTTPResponse_Exception( null, 400 );
		}

		if ( ! $record = $list->byID( $id ) ) {
			throw new SS_HTTPResponse_Exception( null, 404 );
		}

		$form = $this->getForm( $grid, $record );

		foreach ( $form->Fields() as $field ) {
			$field->setName( $this->getFieldName( $field->getName(), $grid, $record ) );
		}

		return $form;
	}

	/**
	 * Gets the field list for a record.
	 *
	 * @param GridField           $grid
	 * @param DataObjectInterface $record
	 *
	 * @return \FieldList
	 * @throws \Exception
	 */
	public function getFields( GridField $grid, DataObjectInterface $record ) {
		$cols   = $this->getDisplayFields( $grid );
		$fields = new FieldList();

		$list  = $grid->getList();
		$class = $list ? $list->dataClass() : null;

		foreach ( $cols as $col => $info ) {
			$field = null;

			if ( $info instanceof Closure ) {
				$field = call_user_func( $info, $record, $col, $grid );
			} elseif ( is_array( $info ) ) {
				if ( isset( $info['callback'] ) ) {
					$field = call_user_func( $info['callback'], $record, $col, $grid );

				} elseif ( isset( $info['field'] ) ) {
					if ( $info['field'] == 'LiteralField' ) {
						$field = new $info['field']( $col, null );
					} else {
						$field = new $info['field']( $col );
					}
				}
				/*
								if(!$field instanceof FormField) {
									throw new Exception(sprintf(
										'The field for column "%s" is not a valid form field',
										$col
									));
								}
				*/
			}

			if ( ! $field && $list instanceof ManyManyList ) {
				$extra = $list->getExtraFields();

				if ( $extra && array_key_exists( $col, $extra ) ) {
					$field = Object::create_from_string( $extra[ $col ], $col )->scaffoldFormField();
				}
			}

			if ( ! $field ) {
				if ( $class && $obj = singleton( $class )->dbObject( $col ) ) {
					$field = $obj->scaffoldFormField();
				} else {
					$field = new ReadonlyField( $col );
				}
			}

			if ( ! $field instanceof FormField ) {
				throw new Exception( sprintf(
					'Invalid form field instance for column "%s"', $col
				) );
			}
			$field->addExtraClass( 'editable-column-field' );

			$fields->push( $field );
		}

		return $fields;
	}

	/**
	 * Additional metadata about the column which can be used by other components,
	 * e.g. to set a title for a search column header.
	 *
	 * @param GridField $gridField
	 * @param string    $columnName
	 *
	 * @return array - Map of arbitrary metadata identifiers to their values.
	 */
	public function getColumnMetadata( $gridField, $columnName ) {
		$columns = $this->getDisplayFields( $gridField );

		$title        = null;
		$extraClasses = null;

		if ( is_string( $columns[ $columnName ] ) ) {
			$title = $columns[ $columnName ];
		} elseif ( is_array( $columns[ $columnName ] ) ) {
			$title = isset( $columns[ $columnName ]['title'] )
				? $columns[ $columnName ]['title']
				: '';

			$extraClasses = isset( $columns[ $columnName ]['extraClasses'] )
				? $columns[ $columnName ]['extraClasses']
				: '';
		}

		return array(
			'title'        => $title,
			'visibility'   => empty( $title ) ? 'hidden' : 'visible',
			'extraClasses' => $extraClasses,
		);
	}

	/**
	 * Attributes for the element containing the content returned by {@link getColumnContent()}. Merges in all 'data-'
	 * values from getColumnMetadata call for the column.
	 *
	 * @param  GridField  $gridField
	 * @param  DataObject $record displayed in this row
	 * @param  string     $columnName
	 *
	 * @return array
	 */
	public function getColumnAttributes( $gridField, $record, $columnName ) {
		$metaData = $this->getColumnMetadata( $gridField, $columnName );

		$classes = implode( ' ', array(
			'col-' . preg_replace( '/[^\w]/', '-', $columnName ),
			$metaData['extraClasses'],
		) );

		return array(
			'class'           => $classes,
			'data-visibility' => $metaData['visibility'],
		);
	}

	/**
	 * Gets the form instance for a record.
	 *
	 * @param GridField           $grid
	 * @param DataObjectInterface $record
	 *
	 * @return \Form
	 * @throws \Exception
	 */
	public function getForm( GridField $grid, DataObjectInterface $record ) {
		$fields = $this->getFields( $grid, $record );

		$form = new Form( $this, null, $fields, new FieldList() );
		$form->loadDataFrom( $record );

		$form->setFormAction( Controller::join_links(
			$grid->Link(), 'editable/form', $record->ID
		) );

		return $form;
	}

	protected function getFieldName( $name, GridField $grid, DataObjectInterface $record ) {
		return sprintf(
			'%s[%s][%s][%s]', $grid->getName(), __CLASS__, $record->ID, $name
		);
	}

}
