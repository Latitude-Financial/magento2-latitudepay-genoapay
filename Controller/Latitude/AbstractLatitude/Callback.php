<?php
/**
 *
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Latitude\Payment\Controller\Latitude\AbstractLatitude;

use Magento\Framework\Controller\ResultFactory;

class Callback extends \Latitude\Payment\Controller\Latitude\AbstractLatitude
{
    /**
     * Callback Latitudepay Checkout
     *
     * @return \Magento\Framework\Controller\Result\Redirect
     */
    public function execute()
    {
        $result = [];
        try {
            $this->_initToken(false);
            // Log payload callback
            $post = $this->getRequest()->getPostValue();
            if(!$this->_getApi()->validateSignature($post)){
                $result = ['error' => false];
                /** @var \Magento\Framework\Controller\Result\Json $result */
                $resultJson = $this->resultJsonFactory->create();
                $resultJson->setData($result);
                return $resultJson;
            }
            $this->logger->info('Callback received');
            $this->logCallback($post);
            $result = ['success' => true];
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage(), array("errors" => __('Unable to get callback message')));
            $result = ['error' => false];
        }

        /** @var \Magento\Framework\Controller\Result\Json $result */
        $resultJson = $this->resultJsonFactory->create();
        $resultJson->setData($result);
        return $resultJson;
    }
}
