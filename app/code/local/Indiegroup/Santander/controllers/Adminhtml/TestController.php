<?php

class Indiegroup_Santander_Adminhtml_TestController extends Mage_Adminhtml_Controller_Action {

    public function indexAction() {
        $this->loadLayout();
        $this->_title($this->__("Santander"));
        $this->renderLayout();
    }

}