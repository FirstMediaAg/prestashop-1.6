<?php
if (! defined('_PS_VERSION_')) {
    exit();
}

class Wallee_SmartyFunctions
{
    public static function translate($params, $smarty)
    {
        $text = $params['text'];
        return Wallee_Helper::translate($text);
    }
    
    
    /**
     * Returns the URL to the refund detail view in wallee.
     *
     * @return string
     */
    public static function getRefundUrl($params, $smarty){
        $refundJob = $params['refund'];
        return Wallee_Helper::getRefundUrl($refundJob);
    }
    
    public static function getRefundAmount($params, $smarty){
        $refundJob = $params['refund'];
        return Wallee_Backend_StrategyProvider::getStrategy()->getRefundTotal($refundJob->getRefundParameters());
    }
    
    public static function getRefundType($params, $smarty){
        $refundJob = $params['refund'];
        return Wallee_Backend_StrategyProvider::getStrategy()->getWalleeRefundType($refundJob->getRefundParameters());
    }
    
    /**
     * Returns the URL to the completion detail view in wallee.
     *
     * @return string
     */
    public static function getCompletionUrl($params, $smarty){
        $completionJob = $params['completion'];
        return Wallee_Helper::getCompletionUrl($completionJob);
    }
    
    /**
     * Returns the URL to the void detail view in wallee.
     *
     * @return string
     */
    public static function getVoidUrl($params, $smarty){
        $voidJob = $params['void'];
        return Wallee_Helper::getVoidUrl($voidJob);
    }
    
}