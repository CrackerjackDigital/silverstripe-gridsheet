<?php

class GridSheet extends GridField {
	const CSSClassName = 'gridsheet';

	const DefaultEditableColumnsClass = 'GridSheetEditableColumnsComponent';

	private static $enable_add_new = false;

	private static $enable_add_new_inline = true;

	/** @var \DataObject|\GridSheetModelExtension $modelInstance */
	protected $modelInstance;

	// set in derived class to name of class to show in grid field
	const ModelClass = '';

	const RelatedModelClass = '';

	/**
	 * GridSheet constructor.
	 *
	 * @param \DataObject           $model
	 * @param null                  $title
	 * @param \SS_List|null         $data
	 * @param \GridFieldConfig|null $config
	 *
	 * @throws \LogicException
	 */
	public function __construct( $model, $title = null, SS_List $data = null, GridFieldConfig $config = null ) {
		$this->modelInstance = is_object( $model ) ? $model : singleton( $model );
		$this->setModelClass( $this->modelInstance->class );

		parent::__construct(
			$this->modelInstance->class,
			$this->modelInstance->i18n_singular_name(),
			$this->gridSheetData(),
			$this->gridSheetConfig()
		);
		$this->addExtraClass( static::CSSClassName );
	}

	/**
	 * @param $relatedID
	 *
	 * @return \GridFieldConfig
	 * @throws \LogicException
	 */
	public function gridSheetConfig( $relatedID = null ) {
		/** @var GridFieldConfig $config */
		$config = GridFieldConfig_RecordEditor::create( 1000 );

		$config->removeComponentsByType( GridFieldDataColumns::class )
			->removeComponentsByType( GridFieldFilterHeader::class )
			->removeComponentsByType( GridFieldSortableHeader::class )
			->removeComponentsByType( 'GridFieldAddNewButton' );

		$editableColumns = $this->gridSheetEditableColumns( $relatedID );

		$config->addComponents(
			$editableColumns,
			new GridFieldSaveRowButton()
		);

		if ( $this->config()->get( 'enable_add_new_inline' ) ) {
			$config->addComponent( new GridSheetAddNewInlineButton( 'toolbar-header-right' ) );
		} elseif ( $this->config()->get( 'enable_add_new' ) ) {
			$config->addComponent( new GridFieldAddNewButton() );
		}

		if ( $this->config()->get( 'enable_add_new' ) || $this->config()->get( 'enable_add_new_inline' ) ) {
			$config->addComponent( new GridSheetSaveAllButton('toolbar-header-right'));
		}

		return $config;
	}

	public function gridSheetEditableColumns( $relatedID = null ) {

		$modelClass = $this->getModelClass();

		$editableColumnsClass = $modelClass . 'EditableColumns';

		/** @var \GridSheetEditableColumnsComponent $editableColumns */
		if ( ! ClassInfo::exists( $editableColumnsClass ) ) {
			$editableColumnsClass = self::DefaultEditableColumnsClass;
		}
		$editableColumns = $editableColumnsClass::create( $this->owner );

		$fieldSpecs = $this->modelInstance->config()->get( 'gridsheet_field_specs' ) ?: array();

		if ( $relatedID ) {
			$this->modelInstance->extend( 'provideRelatedEditableColumns', $relatedClassName, $relatedID, $fieldSpecs );
		} else {
			$this->modelInstance->extend( 'provideEditableColumns', $fieldSpecs );
		}

		if ( $fieldOrder = Config::inst()->get( $modelClass, 'editable_columns_order' ) ) {
			$reordered = array();

			foreach ( $fieldOrder as $fieldName ) {
				if ( isset( $fieldSpecs[ $fieldName ] ) ) {
					$reordered[ $fieldName ] = $fieldSpecs[ $fieldName ];
					unset( $fieldSpecs[ $fieldName ] );
				}
			}
			foreach ( $fieldSpecs as $fieldName => $fieldSpec ) {
				$reordered[ $fieldName ] = $fieldSpec;
			}
			$fieldSpecs = $reordered;
		}

		$editableColumns->setDisplayFields( $fieldSpecs );

		return $editableColumns;
	}

	/**
	 * @param \DataObject $model
	 * @param array       $record
	 *
	 * @return array
	 * @throws \LogicException
	 */
	protected function getUpdateColumns( $model, array $record ) {
		$columns = array();

		if ( $this->getModelClass() == $model->class ) {
			$this->modelInstance->extend( 'provideEditableColumns', $columns );
		} else {
			xdebug_break();
//			$this->model->extend('provideRelatedEditableColumns', static::RelatedModelClass, $record['ID'], $columns );
		}
		$updateColumns = array_intersect_key(
			$record,
			$columns
		);

		return $updateColumns ?: array();
	}

	protected function gridSheetData() {
		$data = new ArrayList();

		$modelClass = $this->getModelClass();

		$this->modelInstance->extend( 'provideGridSheetData', $data, $modelClass, $relatedID );

		return $data;
	}

	public function saveNewRows() {
		if ( $rows = $this->rowData() ) {

			$modelClass = $this->getModelClass();
			$list       = $this->getList();

			$template = array();
			return array_filter(
				array_map(
					function ( $row ) use ( $modelClass, $template, $list ) {
						if ( ! isset( $row['ID'] ) ) {
							$model = $modelClass::create( $template );

							$this->gridSheetHandleNewRow( $model, $row );
							$model->write();

							$list->add( $model );

							return true;
						}
					},
					$rows
				)
			);
		}
	}

	public function saveExistingRows( ) {
		if ( $rows = $this->rowData() ) {

			$modelClass = $this->getModelClass();
			$list       = $this->getList();

			$template = array();
			$names  = array_keys( $rows );
			$values = array_values( $rows );

			return array_filter(
				array_map(
					function ( $id, $row ) use ( $modelClass, $list, $template) {
						/** @var DataObject $model */
						if ( $model = $list->find( 'ID', $id ) ) {
							$this->gridSheetHandleExistingRow( $model, $row );
							$model->write();

							$list->add( $model );

							return true;
						}

					},
					$names,
					$values
				)
			);
		}
	}

	/**
	 * Called for each new row in a grid when it is saved.
	 *
	 * @param DataObject $model
	 * @param array      $record
	 *
	 * @return array
	 *
	 * @throws \LogicException
	 */
	public function gridSheetHandleNewRow( $model, array &$record ) {
		$updateData = $this->getUpdateColumns( $model, $record );
		$model->update( $updateData );

		return $updateData;
	}

	/**
	 * Called to each existing row in a grid when it is saved.
	 *
	 * @param \DataObject $model
	 * @param array       $record
	 *
	 * @return array
	 *
	 * @throws \LogicException
	 */
	public function gridSheetHandleExistingRow( $model, array &$record ) {
		$updateData = $this->getUpdateColumns( $model, $record );
		$model->update( $updateData );

		return $updateData;
	}

	private function rowData() {
		$gridComponentClass = GridSheetAddNewInlineButton::class;

		if ( $this->getConfig()->getComponentByType( $gridComponentClass ) ) {
			$gridData = $this->Value();

			return isset( $gridData[ $gridComponentClass ] ) ? $gridData[ $gridComponentClass ] : array();
		}
	}

	public static function include_requirements() {
		Requirements::javascript( THIRDPARTY_DIR . '/javascript-templates/tmpl.js' );
		Requirements::css( 'gridsheet/css/gridsheet.css' );

		GridFieldExtensions::include_requirements();

	}

}