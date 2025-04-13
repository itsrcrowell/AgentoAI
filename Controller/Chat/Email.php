<?php
namespace Genaker\MagentoMcpAi\Controller\Chat;

use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Psr\Log\LoggerInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Framework\Translate\Inline\StateInterface;
use Magento\Store\Model\StoreManagerInterface;

class Email implements HttpPostActionInterface
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
     * @var LoggerInterface
     */
    private $logger;
    
    /**
     * @var Json
     */
    private $json;
    
    /**
     * @var TransportBuilder
     */
    private $transportBuilder;
    
    /**
     * @var StateInterface
     */
    private $inlineTranslation;
    
    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @param RequestInterface $request
     * @param JsonFactory $resultJsonFactory
     * @param ScopeConfigInterface $scopeConfig
     * @param LoggerInterface $logger
     * @param Json $json
     * @param TransportBuilder $transportBuilder
     * @param StateInterface $inlineTranslation
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        RequestInterface $request,
        JsonFactory $resultJsonFactory,
        ScopeConfigInterface $scopeConfig,
        LoggerInterface $logger,
        Json $json,
        TransportBuilder $transportBuilder,
        StateInterface $inlineTranslation,
        StoreManagerInterface $storeManager
    ) {
        $this->request = $request;
        $this->resultJsonFactory = $resultJsonFactory;
        $this->scopeConfig = $scopeConfig;
        $this->logger = $logger;
        $this->json = $json;
        $this->transportBuilder = $transportBuilder;
        $this->inlineTranslation = $inlineTranslation;
        $this->storeManager = $storeManager;
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
            // Check if email is enabled
            $emailEnabled = $this->scopeConfig->isSetFlag(
                'magentomcpai/chatbot/enable_email_chat',
                ScopeInterface::SCOPE_STORE
            );
            
            if (!$emailEnabled) {
                throw new LocalizedException(__('Email chat history is not enabled.'));
            }
            
            $requestData = $this->json->unserialize($this->request->getContent());
            
            if (empty($requestData['email']) || !filter_var($requestData['email'], FILTER_VALIDATE_EMAIL)) {
                throw new LocalizedException(__('Valid email address is required.'));
            }
            
            if (empty($requestData['history']) || !is_array($requestData['history'])) {
                throw new LocalizedException(__('Chat history is required.'));
            }
            
            $customerEmail = $requestData['email'];
            $chatHistory = $requestData['history'];
            
            // Get support email from config or fallback to default
            $supportEmail = $this->scopeConfig->getValue(
                'magentomcpai/chatbot/support_email',
                ScopeInterface::SCOPE_STORE
            );
            
            if (!$supportEmail) {
                $supportEmail = $this->scopeConfig->getValue(
                    'trans_email/ident_support/email',
                    ScopeInterface::SCOPE_STORE
                );
            }
            
            // Get email subject from config or use default
            $emailSubject = $this->scopeConfig->getValue(
                'magentomcpai/chatbot/email_subject',
                ScopeInterface::SCOPE_STORE
            );
            
            $storeName = $this->storeManager->getStore()->getName();
            if ($emailSubject) {
                $emailSubject = str_replace('{store_name}', $storeName, $emailSubject);
            } else {
                $emailSubject = 'Chat History from ' . $storeName . ' Virtual Assistant';
            }
            
            // Format chat history for email
            $formattedChat = $this->formatChatHistory($chatHistory);
            
            // Send email
            $this->sendEmail($supportEmail, $customerEmail, $emailSubject, $formattedChat);
            
            return $resultJson->setData([
                'success' => true,
                'message' => __('Chat history has been sent to our support team.')
            ]);
        } catch (LocalizedException $e) {
            $this->logger->error('Chatbot email error: ' . $e->getMessage());
            return $resultJson->setData([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Chatbot email error: ' . $e->getMessage());
            return $resultJson->setData([
                'success' => false,
                'message' => __('An error occurred while sending the email.')
            ]);
        }
    }
    
    /**
     * Format chat history for email
     *
     * @param array $chatHistory
     * @return string
     */
    private function formatChatHistory($chatHistory)
    {
        $formattedChat = '<h2>Chat History</h2>';
        $formattedChat .= '<div style="margin-top: 20px; border: 1px solid #e0e0e0; padding: 15px; border-radius: 5px;">';
        
        foreach ($chatHistory as $message) {
            $role = isset($message['isUser']) && $message['isUser'] ? 'Customer' : 'Virtual Assistant';
            $text = isset($message['text']) ? $message['text'] : '';
            $time = isset($message['timestamp']) ? date('Y-m-d H:i:s', $message['timestamp'] / 1000) : '';
            
            $style = $role === 'Customer' 
                ? 'background-color: #f0f9ff; border-left: 4px solid #3b82f6;' 
                : 'background-color: #f9fafb; border-left: 4px solid #6b7280;';
            
            $formattedChat .= '<div style="margin-bottom: 15px; padding: 10px; ' . $style . '">';
            $formattedChat .= '<strong>' . $role . '</strong>';
            if ($time) {
                $formattedChat .= ' <span style="color: #6b7280; font-size: 12px;">(' . $time . ')</span>';
            }
            $formattedChat .= '<div style="margin-top: 5px;">' . nl2br(htmlspecialchars($text)) . '</div>';
            $formattedChat .= '</div>';
        }
        
        $formattedChat .= '</div>';
        return $formattedChat;
    }
    
    /**
     * Send email with chat history
     *
     * @param string $supportEmail
     * @param string $customerEmail
     * @param string $subject
     * @param string $emailContent
     * @return void
     * @throws \Exception
     */
    private function sendEmail($supportEmail, $customerEmail, $subject, $emailContent)
    {
        $store = $this->storeManager->getStore()->getId();
        $supportName = $this->scopeConfig->getValue(
            'trans_email/ident_support/name',
            ScopeInterface::SCOPE_STORE
        );
        
        try {
            $this->inlineTranslation->suspend();
            
            $transport = $this->transportBuilder
                ->setTemplateIdentifier('chatbot_email_template')
                ->setTemplateOptions([
                    'area' => \Magento\Framework\App\Area::AREA_FRONTEND,
                    'store' => $store
                ])
                ->setTemplateVars([
                    'chat_history' => $emailContent,
                    'customer_email' => $customerEmail,
                    'subject' => $subject
                ])
                ->setFromByScope([
                    'name' => $supportName,
                    'email' => $supportEmail
                ])
                ->addTo($supportEmail, $supportName)
                ->addCc($customerEmail)
                ->getTransport();
            
            $transport->sendMessage();
            $this->inlineTranslation->resume();
        } catch (\Exception $e) {
            $this->inlineTranslation->resume();
            throw $e;
        }
    }
}
