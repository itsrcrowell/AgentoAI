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
     * @param RequestInterface $request
     * @param JsonFactory $resultJsonFactory
     * @param ScopeConfigInterface $scopeConfig
     * @param OpenAiService $openAiService
     * @param LoggerInterface $logger
     * @param CacheInterface $cache
     * @param Json $json
     */
    public function __construct(
        RequestInterface $request,
        JsonFactory $resultJsonFactory,
        ScopeConfigInterface $scopeConfig,
        OpenAiService $openAiService,
        LoggerInterface $logger,
        CacheInterface $cache,
        Json $json
    ) {
        $this->request = $request;
        $this->resultJsonFactory = $resultJsonFactory;
        $this->scopeConfig = $scopeConfig;
        $this->openAiService = $openAiService;
        $this->logger = $logger;
        $this->cache = $cache;
        $this->json = $json;
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
}
