<?php
if (! defined('_PS_VERSION_')) {
    exit();
}

/**
 * wallee Prestashop
 *
 * This Prestashop module enables to process payments with wallee (https://www.wallee.com).
 *
 * @author customweb GmbH (http://www.customweb.com/)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache Software License (ASL 2.0)
 */

class AdminWalleeCronJobsController extends ModuleAdminController
{
    
    public function __construct()
    {
        parent::__construct();
        $this->context->smarty->addTemplateDir($this->getTemplatePath());
        $this->tpl_folder = 'cronjob/';
        $this->bootstrap = true;
    }
    
    public function initContent()
    {
        $this->handleList();
        parent::initContent();
    }

    private function handleList()
    {
        $this->display = 'list';
        $this->context->smarty->assign('jobs', Wallee_Cron::getAllCronJobs());
    }
    
}
    