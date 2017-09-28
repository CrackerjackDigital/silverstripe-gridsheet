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
	 * @param \DataObject|\Object   $model
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
			->removeComponentsByType( GridFieldEditButton::class )
			->removeComponentsByType( GridFieldDeleteAction::class )
			->removeComponentsByType( 'GridFieldAddNewButton' );

		$editableColumns = $this->gridSheetEditableColumns( $relatedID );

		$config->addComponents(
			$editableColumns,
			new GridFieldDeleteAction(),
			new GridFieldEditButton(),
			new GridFieldSaveRowButton()
		);

		if ( $this->config()->get( 'enable_add_new_inline' ) ) {
			$config->addComponent( new GridSheetAddNewInlineButton( 'toolbar-header-right' ) );
		} elseif ( $this->config()->get( 'enable_add_new' ) ) {
			$config->addComponent( new GridFieldAddNewButton() );
		}

		if ( $this->config()->get( 'enable_add_new' ) || $this->config()->get( 'enable_add_new_inline' ) ) {
			$config->addComponent( new GridSheetSaveAllButton( 'toolbar-header-right' ) );
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

	protected function gridSheetData() {
		$data = new ArrayList();

		$modelClass = $this->getModelClass();

		$this->modelInstance->extend( 'provideGridSheetData', $data, $modelClass, $relatedID );

		return $data;
	}

	public static function include_requirements() {
		Requirements::block( 'gridfieldextensions/javascript/GridFieldExtensions.js' );
		Requirements::javascript( 'gridsheet/js/gridsheet.js' );
		Requirements::javascript( THIRDPARTY_DIR . '/javascript-templates/tmpl.js' );
		Requirements::css( 'gridsheet/css/gridsheet.css' );
	}

}