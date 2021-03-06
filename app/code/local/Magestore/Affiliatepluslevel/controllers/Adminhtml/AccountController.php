<?php

class Magestore_Affiliatepluslevel_Adminhtml_AccountController extends Mage_Adminhtml_Controller_Action {

    public function tierAction() {
        /* hainh edit 25-04-2014 */
        if (!Mage::helper('affiliatepluslevel')->isPluginEnabled()) {
            return;
        }
        if (!Mage::helper('magenotification')->checkLicenseKeyAdminController($this)) {
            return;
        }
        $this->loadLayout();
        $this->renderLayout();
    }

    public function tierGridAction() {
        /* hainh edit 25-04-2014 */
        if (!Mage::helper('affiliatepluslevel')->isPluginEnabled()) {
            return;
        }
        if (!Mage::helper('magenotification')->checkLicenseKeyAdminController($this)) {
            return;
        }
        $this->loadLayout();
        $this->renderLayout();
    }

    public function toptierAction() {
        /* hainh edit 25-04-2014 */
        if (!Mage::helper('affiliatepluslevel')->isPluginEnabled()) {
            return;
        }
        if (!Mage::helper('magenotification')->checkLicenseKeyAdminController($this)) {
            return;
        }
        $this->loadLayout();
        $this->renderLayout();
    }

    public function toptierGridAction() {
        /* hainh edit 25-04-2014 */
        if (!Mage::helper('affiliatepluslevel')->isPluginEnabled()) {
            return;
        }
        if (!Mage::helper('magenotification')->checkLicenseKeyAdminController($this)) {
            return;
        }
        $this->loadLayout();
        $this->renderLayout();
    }

    public function changeToptierAction() {
        /* hainh edit 25-04-2014 */
        if (!Mage::helper('affiliatepluslevel')->isPluginEnabled()) {
            return;
        }
        if (!Mage::helper('magenotification')->checkLicenseKeyAdminController($this)) {
            return;
        }
        $accountId = $this->getRequest()->getParam('account_id');
        $account = Mage::getModel('affiliateplus/account')->load($accountId);
        $tier = Mage::getModel('affiliatepluslevel/tier')->getCollection()
                ->addFieldToFilter('tier_id', $accountId)
                ->getFirstItem();
        if ($tier && $tier->getId())
            $level = $tier->getLevel();
        else
            $level = 0;

        $html = '';
        $html .= '<input type="hidden" id="map_toptier_name" value="' . $account->getName() . '" />';
        $html .= '<input type="hidden" id="map_toptier_id" value="' . $account->getId() . '" />';
        $html .= '<input type="hidden" id="map_toptier_level" value="' . $level . '" />';
        $this->getResponse()->setHeader('Content-type', 'application/x-json');
        $this->getResponse()->setBody($html);
    }

    /**
     * Check for is allowed
     *
     * @return boolean
     */
    protected function _isAllowed() {
        return Mage::getSingleton('admin/session')->isAllowed('affiliateplus/account');
    }

}
