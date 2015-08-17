<?php

class GridSheet extends GridField {
    const CSSClassName = 'gridsheet';

    public function __construct($name, $title = null, SS_List $dataList = null, GridFieldConfig $config = null) {
        parent::__construct($name, $title, $dataList, $config);
        $this->addExtraClass(static::CSSClassName);
    }
}