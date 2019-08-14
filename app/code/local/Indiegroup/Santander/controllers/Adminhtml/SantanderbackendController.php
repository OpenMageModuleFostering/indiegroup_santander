<?php

class Indiegroup_Santander_Adminhtml_SantanderbackendController extends Mage_Adminhtml_Controller_Action {

    public function indexAction() {
        $this->loadLayout();
        $this->_title($this->__("Santander"));
        $this->renderLayout();
    }

    /**
     * Action to create Cron Job to Import Santander Prices for all the products in the Shop
     */
    public function importsantanderpriceAction() {
        $this->createCronJobAction('santander_cron');
        $from_email = Mage::getStoreConfig('trans_email/ident_general/email');
        Mage::getSingleton('core/session')->addSuccess($this->__("The import of Santander Prices has been added to the queue.") . '<br/>' . $this->__("An e-mail will be send to: %s once the indexation is finished.", $from_email));
        $this->_redirect('santander/adminhtml_santanderbackend/index');
    }

    /**
     * Create Cron Job
     * @param string $jobCode
     * @throws Exception
     */
    public function createCronJobAction($jobCode) {
        $timecreated = strftime("%Y-%m-%d %H:%M:%S", mktime(date("H"), date("i"), date("s"), date("m"), date("d"), date("Y")));
        $timescheduled = strftime("%Y-%m-%d %H:%M:%S", mktime(date("H"), date("i"), date("s"), date("m"), date("d"), date("Y")));
        try {

            $schedule = Mage::getModel('cron/schedule');
            $schedule->setJobCode($jobCode)
                    ->setCreatedAt($timecreated)
                    ->setScheduledAt($timescheduled)
                    ->setStatus(Mage_Cron_Model_Schedule::STATUS_PENDING)
                    ->save();
        } catch (Exception $e) {
            throw new Exception(Mage::helper('cron')->__('Unable to save Cron expression'));
        }
    }

}