<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Banner\Controller\Adminhtml\Banner;

use Magento\Backend\App\Action;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Redirect;
use Zero\Banner\Model\BannerFactory;

class Delete extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Zero_Banner::banner_delete';

    public function __construct(
        Action\Context $context,
        private readonly BannerFactory $bannerFactory
    ) {
        parent::__construct($context);
    }

    /**
     * @return Redirect
     */
    public function execute()
    {
        $resultRedirect = $this->resultRedirectFactory->create();
        $id = (int) $this->getRequest()->getParam('banner_id');

        if ($id) {
            try {
                $banner = $this->bannerFactory->create();
                $banner->load($id);
                $banner->delete();
                $this->messageManager->addSuccessMessage(__('The banner has been deleted.'));
            } catch (\Exception $e) {
                $this->messageManager->addErrorMessage(__('Something went wrong while deleting the banner.'));

                return $resultRedirect->setPath('zero_banner/banner/edit', ['banner_id' => $id]);
            }
        }

        return $resultRedirect->setPath('zero_banner/banner/index');
    }
}
