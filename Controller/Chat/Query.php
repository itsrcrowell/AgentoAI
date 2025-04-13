<?php
namespace Genaker\MagentoMcpAi\Controller\Chat;

use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Genaker\MagentoMcpAi\Model\Service\OpenAiService;
use Psr\Log\LoggerInterface;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\FilterBuilder;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Helper\Image as ImageHelper;

class Query implements HttpPostActionInterface
{
    /**
     * @var RequestInterface
     */
    private $request;
    
    /**
     * @var JsonFactory
     */
    private $resultJsonFactory;
    
    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;
    
    /**
     * @var OpenAiService
     */
    private $openAiService;
    
    /**
     * @var LoggerInterface
     */
    private $logger;
    
    /**
     * @var CacheInterface
     */
    private $cache;
    
    /**
     * @var Json
     */
    private $json;
    
    /**
     * @var OrderRepositoryInterface
     */
    private $orderRepository;
    
    /**
     * @var SearchCriteriaBuilder
     */
    private $searchCriteriaBuilder;
    
    /**
     * @var FilterBuilder
     */
    private $filterBuilder;
    
    /**
     * @var ProductRepositoryInterface
     */
    private $productRepository;
    
    /**
     * @var ImageHelper
     */
    private $imageHelper;

    /**
     * @param RequestInterface $request
     * @param JsonFactory $resultJsonFactory
     * @param ScopeConfigInterface $scopeConfig
     * @param OpenAiService $openAiService
     * @param LoggerInterface $logger
     * @param CacheInterface $cache
     * @param Json $json
     * @param OrderRepositoryInterface $orderRepository
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param FilterBuilder $filterBuilder
     * @param ProductRepositoryInterface $productRepository
     * @param ImageHelper $imageHelper
     */
    public function __construct(
        RequestInterface $request,
        JsonFactory $resultJsonFactory,
        ScopeConfigInterface $scopeConfig,
        OpenAiService $openAiService,
        LoggerInterface $logger,
        CacheInterface $cache,
        Json $json,
        OrderRepositoryInterface $orderRepository,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        FilterBuilder $filterBuilder,
        ProductRepositoryInterface $productRepository,
        ImageHelper $imageHelper
    ) {
        $this->request = $request;
        $this->resultJsonFactory = $resultJsonFactory;
        $this->scopeConfig = $scopeConfig;
        $this->openAiService = $openAiService;
        $this->logger = $logger;
        $this->cache = $cache;
        $this->json = $json;
        $this->orderRepository = $orderRepository;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->filterBuilder = $filterBuilder;
        $this->productRepository = $productRepository;
        $this->imageHelper = $imageHelper;
    }

    /**
     * Execute action based on request and return result
     *
     * @return \Magento\Framework\Controller\ResultInterface
     */
    public function execute()
    {
        $resultJson = $this->resultJsonFactory->create();

        try {
            $requestData = $this->json->unserialize($this->request->getContent());
            
            if (empty($requestData['query'])) {
                throw new LocalizedException(__('Query is required'));
            }
            
            $userQuery = $requestData['query'];
            $storeContext = $requestData['context'] ?? [];
            $conversationHistory = $requestData['history'] ?? [];
            $conversationSummary = $requestData['summary'] ?? '';
            
            // Check if this is an order status lookup query
            $orderLookupResponse = $this->handleOrderLookupQuery($userQuery, $conversationHistory);
            if ($orderLookupResponse) {
                return $resultJson->setData([
                    'success' => true,
                    'message' => $orderLookupResponse
                ]);
            }
            
            // Check if the response is cached
            $cacheKey = 'chatbot_response_' . md5($userQuery);
            $cachedResponse = $this->cache->load($cacheKey);
            
            if ($cachedResponse) {
                $response = $this->json->unserialize($cachedResponse);
            } else {
                $response = $this->getAiResponse($userQuery, $storeContext, $conversationHistory, $conversationSummary);
                
                // Cache the response for frequently asked questions (1 hour)
                $this->cache->save(
                    $this->json->serialize($response),
                    $cacheKey,
                    ['CHATBOT_CACHE'],
                    3600
                );
            }
            
            return $resultJson->setData([
                'success' => true,
                'message' => $response
            ]);
        } catch (LocalizedException $e) {
            $this->logger->error('Chatbot error: ' . $e->getMessage());
            return $resultJson->setData([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Chatbot error: ' . $e->getMessage());
            return $resultJson->setData([
                'success' => false,
                'message' => __('An error occurred while processing your request.')
            ]);
        }
    }
    
    /**
     * Get AI response for a query
     * 
     * @param string $query
     * @param array $storeContext
     * @param array $conversationHistory
     * @param string $conversationSummary
     * @return string
     */
    private function getAiResponse($query, $storeContext, $conversationHistory, $conversationSummary = '')
    {
        $apiKey = $this->scopeConfig->getValue(
            'magentomcpai/general/api_key',
            ScopeInterface::SCOPE_STORE
        );
        
        if (!$apiKey) {
            throw new LocalizedException(__('API key is not configured'));
        }
        
        $model = $this->scopeConfig->getValue(
            'magentomcpai/chatbot/model',
            ScopeInterface::SCOPE_STORE
        ) ?: 'gpt-3.5-turbo';
        
        // Build system prompt with store context and FAQs
        $systemPrompt = $this->buildSystemPrompt($storeContext);
        
        // Add conversation summary if provided
        if (!empty($conversationSummary)) {
            $systemPrompt .= "\n\n" . $conversationSummary;
        }
        
        // Get FAQs
        $faqs = $this->getFaqs();
        
        $messages = [
            [
                'role' => 'system',
                'content' => $systemPrompt
            ]
        ];
        
        // Add FAQs to context if available
        if (!empty($faqs)) {
            $messages[] = [
                'role' => 'system',
                'content' => "Here are some common questions and answers to reference:\n" . $faqs
            ];
        }
        
        // Add conversation history
        foreach ($conversationHistory as $history) {
            $messages[] = [
                'role' => $history['role'],
                'content' => $history['content']
            ];
        }
        
        // Add user query
        $messages[] = [
            'role' => 'user',
            'content' => $query
        ];
        
        $response = $this->openAiService->sendChatRequest(
            $messages,
            $model,
            $apiKey
        );
        
        return $response['content'];
    }
    
    /**
     * Build system prompt with store context
     * 
     * @param array $storeContext
     * @return string
     */
    private function buildSystemPrompt($storeContext)
    {
        $storeName = $storeContext['name'] ?? 'our store';
        $storePhone = $storeContext['phone'] ?? 'our customer service';
        $storeEmail = $storeContext['email'] ?? 'our customer service email';
        
        $prompt = "You are a helpful customer service assistant for {$storeName}. ";
        $prompt .= "Your job is to provide friendly, concise, and accurate responses to customer questions. ";
        $prompt .= "If a customer asks about contacting customer service, you can provide the phone number ({$storePhone}) ";
        $prompt .= "or email address ({$storeEmail}) if appropriate. ";
        $prompt .= "Keep your responses friendly, helpful, and to the point. ";
        $prompt .= "If you don't know the answer to a question, suggest that the customer contact customer service directly. ";
        $prompt .= "Do not make up information you don't have.";
        
        // Add customer chatbot specific AI rules if configured
        $aiRules = $this->scopeConfig->getValue(
            'magentomcpai/chatbot/ai_rules',
            ScopeInterface::SCOPE_STORE
        );
        
        if (!empty($aiRules)) {
            $prompt .= "\n\nFollow these specific rules when responding to customers:\n" . $aiRules;
        }
        
        // Add customer chatbot specific documentation if configured
        $documentation = $this->scopeConfig->getValue(
            'magentomcpai/chatbot/documentation',
            ScopeInterface::SCOPE_STORE
        );
        
        if (!empty($documentation)) {
            $prompt .= "\n\nHere is additional information about our products and services:\n" . $documentation;
        }
        
        return $prompt;
    }
    
    /**
     * Get FAQs from configuration
     * 
     * @return string
     */
    private function getFaqs()
    {
        return $this->scopeConfig->getValue(
            'magentomcpai/chatbot/faqs',
            ScopeInterface::SCOPE_STORE
        ) ?: '';
    }
    
    /**
     * Securely retrieve order status
     *
     * @param string $orderNumber Order number/increment ID
     * @param string $customerEmail Email associated with order
     * @return array|false Order status information or false if validation fails
     */
    private function getSecureOrderStatus($orderNumber, $customerEmail)
    {
        // Validate input
        if (empty($orderNumber) || empty($customerEmail) || !filter_var($customerEmail, FILTER_VALIDATE_EMAIL)) {
            return false;
        }
        
        // Check if order status lookup is enabled
        $orderLookupEnabled = $this->scopeConfig->isSetFlag(
            'magentomcpai/chatbot/enable_order_lookup',
            ScopeInterface::SCOPE_STORE
        );
        
        if (!$orderLookupEnabled) {
            return false;
        }
        
        // Implement rate limiting for security
        $cacheKey = 'chatbot_order_lookup_' . md5($customerEmail . '_' . date('YmdH'));
        $lookupCount = (int)$this->cache->load($cacheKey);
        
        // Limit to 5 attempts per hour per email
        if ($lookupCount >= 5) {
            return ['error' => 'rate_limit_exceeded'];
        }
        
        try {
            // Get the order by increment ID and email
            $this->searchCriteriaBuilder
                ->addFilter('increment_id', $orderNumber, 'eq')
                ->addFilter('customer_email', $customerEmail, 'eq');
            
            $searchCriteria = $this->searchCriteriaBuilder->create();
            $orders = $this->orderRepository->getList($searchCriteria)->getItems();
            
            // Update rate limit counter
            $this->cache->save(
                (string)($lookupCount + 1),
                $cacheKey,
                ['CHATBOT_CACHE'],
                3600 // 1 hour
            );
            
            // If we have a matching order
            if (count($orders) > 0) {
                /** @var OrderInterface $order */
                $order = reset($orders);
                
                // Get shipping info if available
                $shippingInfo = '';
                if ($order->getShippingDescription()) {
                    $shippingInfo = $order->getShippingDescription();
                    
                    // Include tracking info if available
                    $tracks = $order->getTracksCollection();
                    if ($tracks && $tracks->getSize() > 0) {
                        $trackingNumbers = [];
                        foreach ($tracks as $track) {
                            $trackingNumbers[] = $track->getTrackNumber();
                        }
                        $shippingInfo .= ' (Tracking: ' . implode(', ', $trackingNumbers) . ')';
                    }
                }
                
                // Get order items summary (limited to first 3 items)
                $itemsSummary = [];
                $itemsCollection = $order->getAllVisibleItems();
                $itemCount = 0;
                foreach ($itemsCollection as $item) {
                    if ($itemCount < 3) {
                        $itemsSummary[] = $item->getName() . ' x ' . (int)$item->getQtyOrdered();
                        $itemCount++;
                    } else {
                        $remainingItems = count($itemsCollection) - 3;
                        if ($remainingItems > 0) {
                            $itemsSummary[] = "and {$remainingItems} more items";
                        }
                        break;
                    }
                }
                
                // Format dates for better readability
                $createdAt = new \DateTime($order->getCreatedAt());
                $formattedCreatedAt = $createdAt->format('F j, Y');
                
                // Return limited, safe information
                return [
                    'success' => true,
                    'order_number' => $orderNumber,
                    'status' => $order->getStatus(),
                    'status_label' => $order->getStatusLabel(),
                    'created_at' => $formattedCreatedAt,
                    'total_items' => count($itemsCollection),
                    'items_summary' => implode(', ', $itemsSummary),
                    'shipping_info' => $shippingInfo,
                    'grand_total' => $order->getGrandTotal(),
                    'currency' => $order->getOrderCurrencyCode()
                ];
            }
            
            return ['error' => 'order_not_found'];
        } catch (\Exception $e) {
            $this->logger->error('Chatbot secure order query error: ' . $e->getMessage());
            return ['error' => 'system_error'];
        }
    }
    
    /**
     * Handle order lookup query
     *
     * @param string $query
     * @param array $conversationHistory
     * @return string|null
     */
    private function handleOrderLookupQuery($query, $conversationHistory)
    {
        // Check if the query is an order lookup request
        if (strpos(strtolower($query), 'order status') !== false || strpos(strtolower($query), 'track order') !== false) {
            // Extract order number and email from conversation history
            $orderNumber = null;
            $customerEmail = null;
            foreach ($conversationHistory as $history) {
                if ($history['role'] === 'user') {
                    $message = $history['content'];
                    if (preg_match('/#(\d+)/', $message, $matches)) {
                        $orderNumber = $matches[1];
                    }
                    if (preg_match('/([a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,})/', $message, $matches)) {
                        $customerEmail = $matches[1];
                    }
                }
            }
            
            // If we have an order number and email, retrieve order status
            if ($orderNumber && $customerEmail) {
                $orderStatus = $this->getSecureOrderStatus($orderNumber, $customerEmail);
                if ($orderStatus) {
                    return $this->formatOrderStatusResponse($orderStatus);
                }
            }
        }
        
        return null;
    }
    
    /**
     * Format order status response
     *
     * @param array $orderStatus
     * @return string
     */
    private function formatOrderStatusResponse($orderStatus)
    {
        if ($orderStatus['success']) {
            $response = "Your order #{$orderStatus['order_number']} is currently {$orderStatus['status_label']}. ";
            $response .= "It was placed on {$orderStatus['created_at']} and contains {$orderStatus['total_items']} items. ";
            $response .= "The order total is {$orderStatus['grand_total']} {$orderStatus['currency']}. ";
            if (!empty($orderStatus['shipping_info'])) {
                $response .= "The shipping information is: {$orderStatus['shipping_info']}.";
            }
            return $response;
        } else {
            return "Sorry, we couldn't find your order. Please try again or contact our customer service.";
        }
    }
}
