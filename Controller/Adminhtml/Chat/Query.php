<?php
namespace Genaker\MagentoMcpAi\Controller\Adminhtml\Chat;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\View\Result\LayoutFactory;
use Magento\Framework\Controller\Result\JsonFactory;
use Genaker\MagentoMcpAi\Model\MenuAIAPI;
use Genaker\MagentoMcpAi\Model\Service\OpenAiService;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

/**
 * Class Process
 */
class Query extends Action
{
    /**
     * @var JsonFactory
     */
    protected $resultJsonFactory;

    /**
     * @var MenuAIAPI
     */
    protected $menuAIAPI;

    /**
     * @var OpenAiService
     */
    protected $openAiService;

    /**
     * @var ScopeConfigInterface
     */
    protected $_scopeConfig;

    /**
     * @param Context $context
     * @param JsonFactory $resultJsonFactory
     * @param MenuAIAPI $menuAIAPI
     */
    /**
     * @param Context $context
     * @param JsonFactory $resultJsonFactory
     * @param MenuAIAPI $menuAIAPI
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        Context $context,
        JsonFactory $resultJsonFactory,
        MenuAIAPI $menuAIAPI,
        OpenAiService $openAiService,
        ScopeConfigInterface $scopeConfig
    ) {
        parent::__construct($context);
        $this->resultJsonFactory = $resultJsonFactory;
        $this->menuAIAPI = $menuAIAPI;
        $this->openAiService = $openAiService;
        $this->_scopeConfig = $scopeConfig;
    }

    /**
     * Process chat request
     *
     * @return \Magento\Framework\Controller\Result\Json
     */
    public function execute()
    {
        $query = $this->getRequest()->getParam('query');
        if (!$query) {
            return $this->resultJsonFactory->create()->setData([
                'error' => 'No query provided'
            ]);
        }

        try {
            // Get API key from configuration
            $apiKey = $this->_scopeConfig->getValue(
                'magentomcpai/general/api_key',
                \Magento\Store\Model\ScopeInterface::SCOPE_STORE
            );

            if (!$apiKey) {
                throw new \Exception('API key not configured');
            }
           
            $systemContent = '1.You are Magento 2 Expert and Linux server expert. Please, provide short answer about the question related for magento performace and server configuration rejetc another one if not related to magento and servers ';
                        
            // Create messages array for OpenAI
            $messages = [
                ['role' => 'system', 'content' => $systemContent]
            ];
            
            // Add the current user query
            $messages[] = ['role' => 'user', 'content' => $query];

            // Process the request through OpenAiService with GPT-5 mini model
            $response = $this->openAiService->getChatCompletion($messages, 'gpt-5', $apiKey);
            // Extract the content from the response array
            $messageContent = is_array($response) && isset($response['content']) ? $response['content'] : $response;
            
            // Format the message content with HTML
            $formattedContent = $this->formatResponseContent($messageContent);
            
            // Include usage statistics if available
            $responseData = [
                'completion_tokens' => $this->openAiService->completion_tokens,
                'total_tokens' => $this->openAiService->total_tokens,
                'prompt_tokens_details' => $this->openAiService->prompt_tokens_details,
                'cached_tokens' => $this->openAiService->cached_tokens,
                'audio_tokens' => $this->openAiService->audio_tokens,
                'message' => $formattedContent,
                'hash' => md5($messageContent),
                'url' => null
            ];
            
            // Add usage data if available
            if (is_array($response) && isset($response['usage'])) {
                $responseData['usage'] = $response['usage'];
            }
            
            return $this->resultJsonFactory->create()->setData($responseData);
        } catch (\Exception $e) {
            return $this->resultJsonFactory->create()->setData([
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Check permission via ACL resource
     *
     * @return bool
     */
    protected function _isAllowed()
    {
        return $this->_authorization->isAllowed('Genaker_MagentoMcpAi::mcpai');
    }
    
    /**
     * Format the response content with HTML formatting
     *
     * @param string $content
     * @return string
     */
    private function formatResponseContent($content)
    {
        return $content;
    }
}
