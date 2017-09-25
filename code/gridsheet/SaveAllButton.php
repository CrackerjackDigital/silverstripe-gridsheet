<?php
/**
 * THANKS TO: Milkyway Multimedia
 * SaveAllButton.php
 *
 * @package milkyway-multimedia/ss-gridfield-utils
 * @author  Mellisa Hankins <mell@milkywaymultimedia.com.au>
 */

class GridSheetSaveAllButton implements GridField_HTMLProvider {
	protected $targetFragment;
	protected $actionName = 'saveallrecords';

	public $buttonName;

	public $publish = true;

	public $completeMessage;

	public $removeChangeFlagOnFormOnSave = false;

	public function __construct( $targetFragment = 'before', $publish = true, $action = 'saveallrecords' ) {
		$this->targetFragment = $targetFragment;
		$this->publish        = $publish;
		$this->actionName     = $action;
	}

	/**
	 * @param GridField $gridField
	 *
	 * @return array
	 * @throws \LogicException
	 */
	public function getHTMLFragments( $gridField ) {
		$singleton = singleton( $gridField->getModelClass() );

		if ( ! $singleton->canEdit() && ! $singleton->canCreate() ) {
			return [];
		}

		if ( ! $this->buttonName ) {
			if ( $this->publish && $singleton->hasExtension( 'Versioned' ) ) {
				$this->buttonName = _t( 'GridField.SAVE_ALL_AND_PUBLISH', 'Save all and publish' );
			} else {
				$this->buttonName = _t( 'GridField.SAVE_ALL', 'Save all' );
			}
		}

		$button = GridField_FormAction::create(
			$gridField,
			$this->actionName,
			$this->buttonName,
			$this->actionName,
			null
		);

		$button->setAttribute( 'data-icon', 'disk' )->addExtraClass( 'new new-link ui-button-text-icon-primary' );

		if ( $this->removeChangeFlagOnFormOnSave ) {
			$button->addExtraClass( 'js-mwm-gridfield--saveall' );
		}

		return [
			$this->targetFragment => $button->Field(),
		];
	}


	public function setButtonName( $name ) {
		$this->buttonName = $name;

		return $this;
	}

	public function setRemoveChangeFlagOnFormOnSave( $flag ) {
		$this->removeChangeFlagOnFormOnSave = $flag;

		return $this;
	}

	/**
	protected function saveAllRecords( GridField $grid, $arguments, $data ) {
		if ( isset( $data[ $grid->Name ] ) ) {
			$currValue = $grid->Value();
			$grid->setValue( $data[ $grid->Name ] );
			$model = singleton( $grid->List->dataClass() );

			foreach ( $grid->getConfig()->getComponents() as $component ) {
				if ( $component instanceof GridField_SaveHandler ) {
					$component->handleSave( $grid, $model );
				}
			}

			if ( $this->publish ) {
				// Only use the viewable list items, since bulk publishing can take a toll on the system
				$list = ( $paginator = $grid->getConfig()->getComponentByType( 'GridFieldPaginator' ) ) ? $paginator->getManipulatedData( $grid, $grid->List )
					: $grid->List;

				$list->each(
					function ( $item ) {
						if ( $item->hasExtension( 'Versioned' ) ) {
							$item->writeToStage( 'Stage' );
							$item->publish( 'Stage', 'Live' );
						}
					}
				);
			}

			if ( $model->exists() ) {
				$model->delete();
				$model->destroy();
			}

			$grid->setValue( $currValue );

			if ( Controller::curr() && $response = Controller::curr()->Response ) {
				if ( ! $this->completeMessage ) {
					$this->completeMessage = _t( 'GridField.DONE', 'Done.' );
				}

				$response->addHeader( 'X-Status', rawurlencode( $this->completeMessage ) );
			}
		}
	}
 */
}