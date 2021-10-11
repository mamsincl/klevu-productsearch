<?php

use Magento\Catalog\Api\Data\ProductLinkInterfaceFactory;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Indexer\Product\Price\Processor as IndexerProcessor;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Registry;
use Magento\Store\Model\StoreManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;

$objectManager = Bootstrap::getObjectManager();

/** @var StoreManagerInterface $storeManager */
$storeManager = $objectManager->get(StoreManagerInterface::class);
$defaultStoreView = $storeManager->getDefaultStoreView();
/** @var ProductRepositoryInterface $productRepository */
$productRepository = $objectManager->get(ProductRepositoryInterface::class);
/** @var IndexerProcessor $indexerProcessor */
$indexerProcessor = $objectManager->get(IndexerProcessor::class);
/** @var ProductLinkInterfaceFactory $productLinkFactory */
$productLinkFactory = $objectManager->get(ProductLinkInterfaceFactory::class);

// -------------------------------------------------------------------------------------

/** @var Registry $registry */
$registry = $objectManager->get(Registry::class);
$registry->unregister('isSecureArea');
$registry->register('isSecureArea', true);

$skusToDelete = [
    'klevu_grouped_product_test',
    'klevu_grouped_product_test_simple',
];
foreach ($skusToDelete as $skuToDelete) {
    try {
        $groupedProduct = $productRepository->get($skuToDelete);
        $productRepository->delete($groupedProduct);
    } catch (NoSuchEntityException $e) {
        // This is fine
    }
}

// -------------------------------------------------------------------------------------

$simpleProduct = $objectManager->create(Product::class);
$simpleProduct->isObjectNew(true);
$simpleProduct->addData([
    'sku' => 'klevu_grouped_product_test_simple',
    'type_id' => 'simple',
    'name' => '[Klevu] Grouped Product Test (Simple)',
    'description' => '[Klevu Test Fixtures] Simple child for assigned grouped product',
    'short_description' => '[Klevu Test Fixtures] Simple child for assigned grouped product',
    'attribute_set_id' => 4,
    'website_ids' => [
        $defaultStoreView->getWebsiteId(),
    ],
    'price' => 10.00,
    'special_price' => 4.99,
    'weight' => 1,
    'tax_class_id' => 0,
    'meta_title' => '[Klevu] Grouped Product Test (Simple)',
    'meta_description' => '[Klevu Test Fixtures] Simple child for assigned grouped product',
    'visibility' => Visibility::VISIBILITY_BOTH,
    'status' => Status::STATUS_ENABLED,
    'stock_data' => [
        'use_config_manage_stock'   => 1,
        'qty'                       => 100,
        'is_qty_decimal'            => 0,
        'is_in_stock'               => 1,
    ],
    'url_key' => 'klevu-grouped-product-test-simple-'. md5(rand()),
]);
$simpleProduct = $productRepository->save($simpleProduct);

$groupedProduct = $objectManager->create(Product::class);
$groupedProduct->isObjectNew(true);
$groupedProduct->addData([
    'sku' => 'klevu_grouped_product_test',
    'type_id' => 'grouped',
    'name' => '[Klevu] Grouped Product Test',
    'description' => '[Klevu Test Fixtures] assigned grouped product',
    'short_description' => '[Klevu Test Fixtures] assigned grouped product',
    'attribute_set_id' => 4,
    'website_ids' => [
        $defaultStoreView->getWebsiteId(),
    ],
    'price' => 100.00,
    'special_price' => 49.99,
    'weight' => 1,
    'tax_class_id' => 0,
    'meta_title' => '[Klevu] Grouped Product Test',
    'meta_description' => '[Klevu Test Fixtures] assigned grouped product',
    'visibility' => Visibility::VISIBILITY_BOTH,
    'status' => Status::STATUS_ENABLED,
    'stock_data' => [
        'use_config_manage_stock'   => 1,
        'qty'                       => 100,
        'is_qty_decimal'            => 0,
        'is_in_stock'               => 1,
    ],
    'url_key' => 'klevu-grouped-product-test-'. md5(rand()),
]);

$groupedProduct = $productRepository->save($groupedProduct);
$productRepository->cleanCache();

$simpleProductLink = $productLinkFactory->create();
$simpleProductLink->setSku($groupedProduct->getSku())
    ->setLinkType('associated')
    ->setLinkedProductSku($simpleProduct->getSku())
    ->setLinkedProductType($simpleProduct->getTypeId())
    ->setPosition(1)
    ->getExtensionAttributes()
    ->setQty(1);

$groupedProduct->setProductLinks([
    $simpleProductLink
]);

$indexerProcessor->reindexRow($groupedProduct->getId());
$groupedProduct = $productRepository->save($groupedProduct);
$productRepository->cleanCache();

// -------------------------------------------------------------------------------------

$registry->unregister('isSecureArea');
$registry->register('isSecureArea', false);
