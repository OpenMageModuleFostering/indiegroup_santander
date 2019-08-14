<?php

class Indiegroup_Santander_IndexController extends Mage_Core_Controller_Front_Action {

    public function IndexAction() {

        $this->loadLayout();
        $this->getLayout()->getBlock("head")->setTitle($this->__("Santander"));
        $breadcrumbs = $this->getLayout()->getBlock("breadcrumbs");
        $breadcrumbs->addCrumb("home", array(
            "label" => $this->__("Home Page"),
            "title" => $this->__("Home Page"),
            "link" => Mage::getBaseUrl()
        ));

        $breadcrumbs->addCrumb("santander", array(
            "label" => $this->__("Santander"),
            "title" => $this->__("Santander")
        ));

        $this->renderLayout();
    }

}