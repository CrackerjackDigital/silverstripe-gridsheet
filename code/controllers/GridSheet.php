<?php

abstract class GridSheet_Controller extends ContentController {
	const ModelClass = '';
	const URLSegment = '';

	private static $allowed_actions = [
		'edit'     => true,
		'view'     => true,
		'field'    => true,
	];

	private static $url_handlers = [
		'//edit/field/$Name/$Action' => 'field',
		'//edit'                     => 'edit',
		'//view'                     => 'view',
	];

	public function canView() {
		return Permission::check( 'CAN_VIEW_' . self::ModelClass );
	}

	public function canEdit() {
		return Permission::check( 'CAN_EDIT_' . self::ModelClass );
	}

	public function Form() {
		$grid = new FrontEndGridSheet(
			singleton( static::ModelClass ),
			Permission::check( 'CAN_EDIT_' . self::ModelClass ),
			$this->gridsheetData(),
			new FrontEndGridFieldConfig_RelationEditor(10)
		);
		$form = new Form(
			$this,
			'Form',
			new FieldList( [
				$grid,
			] ),
			new FieldList()
		);
		$form->setFormAction( '/' . Controller::join_links(static::URLSegment, 'edit'));

		return $form;
	}

	abstract public function gridsheetData();

	public function field( SS_HTTPRequest $request ) {
		$fieldName = $request->param( 'Name' );

		/** @var \GridField $gridField */
		$gridField = $this->Form()->Fields()->dataFieldByName( $fieldName );

		return $gridField;
	}

	public function edit( SS_HTTPRequest $request ) {
		if ( $request->isPOST() ) {
		}

		return [];
	}

	public function view( SS_HTTPRequest $request ) {

	}


}
