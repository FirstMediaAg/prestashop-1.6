<?php
if (! defined('_PS_VERSION_')) {
    exit();
}



class WalleeDocumentsModuleFrontController extends Wallee_FrontPaymentController
{
	protected $display_header = false;
	protected $display_footer = false;
	
	public $content_only = true;
	
	
	public function postProcess()
	{
	    if (!$this->context->customer->isLogged() && !Tools::getValue('secure_key')) {
	        Tools::redirect('index.php?controller=authentication');
	    }
	    
	    $id_order = (int)Tools::getValue('id_order');
	    if (Validate::isUnsignedId($id_order)) {
	        $order = new Order((int)$id_order);
	    }
	    
	    if (!isset($order) || !Validate::isLoadedObject($order)) {
	        die(Tools::displayError(Wallee_Helper::translatePS('The document was not found.')));
	    }
	    
	    if ((isset($this->context->customer->id) && $order->id_customer != $this->context->customer->id) || (Tools::isSubmit('secure_key') && $order->secure_key != Tools::getValue('secure_key'))) {
	        die(Tools::displayError(Wallee_Helper::translatePS('The document was not found.')));
	    }
	    if ($type = Tools::getValue('type')) {
	        switch($type){
	            case 'invoice':
	                if ((bool) Configuration::get(Wallee::CK_INVOICE)) {
	                   $this->processWalleeInvoice($order);
	                }
	                break;
	            case 'packingSlip':
	                if((bool) Configuration::get(Wallee::CK_PACKING_SLIP)){
	                   $this->processWalleePackingSlip($order);
	                }
	                break;
	        }
	    } 
        die(Tools::displayError(Wallee_Helper::translatePS('The document was not found.')));
	   
	}
	
	private function processWalleeInvoice($order)
	{
        try {
            Wallee_DownloadHelper::downloadInvoice($order);
        } catch (Exception $e) {
            die(Tools::displayError('Could not fetch the document from wallee.'));
        }
	    
	}
	
	private function processWalleePackingSlip($order)
	{
        try {
            Wallee_DownloadHelper::downloadPackingSlip($order);
        } catch (Exception $e) {
            die(Tools::displayError('Could not fetch the document from wallee.'));
        }
	    
	}
	
}