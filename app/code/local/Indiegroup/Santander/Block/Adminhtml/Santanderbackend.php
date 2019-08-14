<?php

class Indiegroup_Santander_Block_Adminhtml_Santanderbackend extends Mage_Adminhtml_Block_Template {

    /**
     * Getting URL for importing the Santander Prices
     * @return string
     */
    public function getImportSantanderUrl() {
        return $this->getUrl('santander/adminhtml_santanderbackend/importsantanderprice');
    }
    
}