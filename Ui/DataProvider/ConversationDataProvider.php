<?php
namespace Genaker\MagentoMcpAi\Ui\DataProvider;

use Magento\Framework\Api\Filter;
use Magento\Framework\Api\FilterBuilder;
use Magento\Framework\Api\Search\SearchCriteriaBuilder;
use Magento\Framework\Api\Search\ReportingInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\View\Element\UiComponent\DataProvider\DataProvider;
use Genaker\MagentoMcpAi\Model\ResourceModel\Conversation\CollectionFactory;

class ConversationDataProvider extends DataProvider
{
    /**
     * @var CollectionFactory
     */
    protected $collectionFactory;

    /**
     * @var array
     */
    protected $loadedData;

    /**
     * @param string $name
     * @param string $primaryFieldName
     * @param string $requestFieldName
     * @param ReportingInterface $reporting
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param RequestInterface $request
     * @param FilterBuilder $filterBuilder
     * @param CollectionFactory $collectionFactory
     * @param array $meta
     * @param array $data
     */
    public function __construct(
        $name,
        $primaryFieldName,
        $requestFieldName,
        ReportingInterface $reporting,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        RequestInterface $request,
        FilterBuilder $filterBuilder,
        CollectionFactory $collectionFactory,
        array $meta = [],
        array $data = []
    ) {
        parent::__construct(
            $name,
            $primaryFieldName,
            $requestFieldName,
            $reporting,
            $searchCriteriaBuilder,
            $request,
            $filterBuilder,
            $meta,
            $data
        );
        $this->collectionFactory = $collectionFactory;
    }

    /**
     * Get data
     *
     * @return array
     */
    public function getData()
    {
        if (isset($this->loadedData)) {
            return $this->loadedData;
        }
        $items = $this->getCollection()->getItems();
        $this->loadedData = [];
        foreach ($items as $conversation) {
            // Add the count of messages
            $messages = json_decode($conversation->getConversationData(), true);
            $messageCount = !empty($messages['messages']) ? count($messages['messages']) : 0;
            $conversation->setData('message_count', $messageCount);
            
            $this->loadedData[$conversation->getId()] = $conversation->getData();
        }
        
        return $this->loadedData;
    }

    /**
     * Get collection
     *
     * @return \Genaker\MagentoMcpAi\Model\ResourceModel\Conversation\Collection
     */
    public function getCollection()
    {
        return $this->createCollection();
    }
    
    /**
     * Create collection instance
     *
     * @return \Genaker\MagentoMcpAi\Model\ResourceModel\Conversation\Collection
     */
    protected function createCollection()
    {
        static $collection = null;
        if ($collection === null) {
            $collection = $this->collectionFactory->create();
        }
        return $collection;
    }
}
