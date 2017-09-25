<?php
abstract class GridSheetModelExtension extends DataExtension
    implements GridSheetExtensionInterface
{
    const GridSheetClassName = GridSheet::class;

    const FrontEndGridSheetClassName = FrontEndGridSheet::class;

    private static $default_field_specs = array();

    private static $tab_name = 'Root.Main';

	private static $gridsheet_enabled = true;

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

                if ($gridSheet = $this->makeGridSheet()) {

                    $fields->removeByName($modelClass, true);

                    $fields->addFieldToTab(
                        'Root.Main',
                        $gridSheet
                    );
                }
            }
        }
    }


    public function updateEditForm(Form $form) {
        if ($this->enabled()) {
            $modelClass = $this->owner->class;

            $fields = $form->Fields();

            if ($gridSheet = $this->makeGridSheet()) {
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

	/**
	 * Create a gridsheet for the provided modelClass if the modelClass matches the extended class.
	 *
	 * @return GridSheet
	 */
	public function makeGridSheet($frontEnd = false) {
		$className = $frontEnd ? static::FrontEndGridSheetClassName : static::GridSheetClassName;

		return $className::create(
			$this->owner
		);
	}
}