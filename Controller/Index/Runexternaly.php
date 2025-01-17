<?php

namespace Klevu\Search\Controller\Index;

class Runexternaly extends \Magento\Framework\App\Action\Action
{
    /**
     * @var \Magento\Indexer\Model\IndexerFactory
     */
    protected $_indexerFactory;
    /**
     * @var \Magento\Indexer\Model\Indexer\CollectionFactory
     */
    protected $_indexerCollectionFactory;

    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Framework\App\Cache\TypeListInterface $cacheTypeList,
        \Magento\Framework\App\Cache\StateInterface $cacheState,
        \Magento\Framework\App\Cache\Frontend\Pool $cacheFrontendPool,
        \Magento\Framework\View\Result\PageFactory $resultPageFactory,
        \Klevu\Search\Model\Product\Sync $modelProductSync,
        \Magento\Framework\Filesystem $magentoFrameworkFilesystem,
        \Klevu\Search\Model\Api\Action\Debuginfo $apiActionDebuginfo,
        \Klevu\Search\Model\Session $frameworkModelSession,
        \Klevu\Search\Helper\Config $searchHelperConfig,
        \Magento\Framework\App\Filesystem\DirectoryList $directoryList,
        \Magento\Indexer\Model\IndexerFactory $indexerFactory,
        \Magento\Indexer\Model\Indexer\CollectionFactory $indexerCollectionFactory
    ) {
        parent::__construct($context);
        $this->_cacheTypeList = $cacheTypeList;
        $this->_cacheState = $cacheState;
        $this->_cacheFrontendPool = $cacheFrontendPool;
        $this->resultPageFactory = $resultPageFactory;
        $this->_modelProductSync = $modelProductSync;
        $this->_magentoFrameworkFilesystem = $magentoFrameworkFilesystem;
        $this->_apiActionDebuginfo = $apiActionDebuginfo;
        $this->_frameworkModelSession = $frameworkModelSession;
        $this->_searchHelperConfig = $searchHelperConfig;
        $this->_directoryList = $directoryList;
        $this->_indexerFactory = $indexerFactory;
        $this->_indexerCollectionFactory = $indexerCollectionFactory;
    }

    /**
     * @return \Magento\Framework\App\ResponseInterface|\Magento\Framework\Controller\ResultInterface|void
     * @throws \Magento\Framework\Exception\FileSystemException
     */
    public function execute()
    {
        $restAPI = $this->_searchHelperConfig->getRestApiKey();
        $debugapi = $this->_modelProductSync->getApiDebug();
        $line = 100;

        // send last few lines of klevu log files
        $logdir = $this->_directoryList->getPath('log');
        $path = $logdir . "/Klevu_Search.log";
        if ($this->getRequest()->getParam('lines')) {
            $line = $this->getRequest()->getParam('lines');
        } elseif ($this->getRequest()->getParam('sync')) {
            if ($this->getRequest()->getParam('sync') == 1) {
                $this->_modelProductSync->run();
                $this->getResponse()->setBody("Data has been sent to klevu server");

                return;
            }
        } else {
            $line = 100;
        }
        $content = "";
        $content .= $this->getLastlines($path, $line, true);
        $content .= "</br>";
        // Get the all indexing status
        $indexer = $this->_indexerFactory->create();
        $indexerCollection = $this->_indexerCollectionFactory->create();

        $ids = $indexerCollection->getAllIds();
        foreach ($ids as $id) {
            $idx = $indexer->load($id);
            $content .= "</br>" . $idx->getTitle() . ":" . $idx->getStatus();
        }

        $response = $this->_apiActionDebuginfo->debugKlevu([
            'apiKey' => $restAPI,
            'klevuLog' => $content,
            'type' => 'index'
        ]);

        $this->_view->loadLayout();
        $this->_view->getLayout()->initMessages();
        $this->_view->renderLayout();
    }

    public function getLastlines($filepath, $lines, $adaptive = true)
    {
        // Open file
        $f = fopen($filepath, "rb");

        if ($f === false) {
            return false;
        }
        // Sets buffer size
        if (!$adaptive) {
            $buffer = 4096;
        } else {
            $buffer = ($lines < 2 ? 64 : ($lines < 10 ? 512 : 4096));
        }
        // Jump to last character
        fseek($f, -1, SEEK_END);
        // Read it and adjust line number if necessary
        // (Otherwise the result would be wrong if file doesn't end with a blank line)
        if (fread($f, 1) != "\n") {
            $lines -= 1;
        }
        // Start reading
        $output = '';
        $chunk = '';
        // While we would like more
        while (ftell($f) > 0 && $lines >= 0) {
            // Figure out how far back we should jump
            $seek = min(ftell($f), $buffer);
            // Do the jump (backwards, relative to where we are)
            fseek($f, -$seek, SEEK_CUR);
            // Read a chunk and prepend it to our output
            $output = ($chunk = fread($f, $seek)) . $output;
            // Jump back to where we started reading
            fseek($f, -mb_strlen((string)$chunk, '8bit'), SEEK_CUR);
            // Decrease our line counter
            $lines -= substr_count($chunk, "\n");
        }
        // While we have too many lines
        // (Because of buffer size we might have read too many)
        while ($lines++ < 0) {
            // Find first newline and remove all text before that
            $output = substr($output, strpos($output, "\n") + 1);
        }
        // Close file and return
        fclose($f);

        return trim($output);
    }
}
