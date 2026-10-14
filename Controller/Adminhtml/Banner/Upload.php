<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Banner\Controller\Adminhtml\Banner;

use Magento\Backend\App\Action;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Exception\LocalizedException;
use Psr\Log\LoggerInterface;
use Zero\Banner\Model\ImageUploader;
use Zero\Banner\Model\Slide;

/**
 * Upload endpoint for the slide image fields. The file arrives under the
 * field's input name, also sent as `param_name`: "slides[2][image]" from the
 * banner form's dynamic rows (the uploader resolves such nested names).
 */
class Upload extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Zero_Banner::banner_save';

    public function __construct(
        Action\Context $context,
        private readonly JsonFactory $resultJsonFactory,
        private readonly ImageUploader $imageUploader,
        private readonly LoggerInterface $logger
    ) {
        parent::__construct($context);
    }

    /**
     * @return Json
     */
    public function execute()
    {
        $resultJson = $this->resultJsonFactory->create();

        try {
            $result = $this->imageUploader->saveFileToTmpDir($this->fileId());
        } catch (LocalizedException $e) {
            $result = ['error' => $e->getMessage(), 'errorcode' => $e->getCode()];
        } catch (\Exception $e) {
            $this->logger->critical($e);
            $result = ['error' => __('Something went wrong while saving the file.'), 'errorcode' => 0];
        }

        return $resultJson->setData($result);
    }

    /**
     * The posted `param_name` if it's one of the slide image fields, else "image".
     *
     * @return string
     */
    private function fileId(): string
    {
        $fileId = (string) $this->getRequest()->getParam('param_name');
        $fields = implode('|', array_map('preg_quote', Slide::IMAGE_FIELDS));
        if (in_array($fileId, Slide::IMAGE_FIELDS, true)
            || preg_match('/^slides\[\d+\]\[(' . $fields . ')\]$/', $fileId)
        ) {
            return $fileId;
        }

        return 'image';
    }
}
