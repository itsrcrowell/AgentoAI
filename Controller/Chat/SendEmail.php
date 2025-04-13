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

class SendEmail implements HttpPostActionInterface
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
            $requestData = $this->json->unserialize($this->request->getContent());
            
            if (empty($requestData['customerEmail']) || !filter_var($requestData['customerEmail'], FILTER_VALIDATE_EMAIL)) {
                throw new LocalizedException(__('Valid customer email is required'));
            }
            
            if (empty($requestData['chatHistory'])) {
                throw new LocalizedException(__('Chat history is required'));
            }
            
            // Check if email support is enabled
            $emailSupportEnabled = $this->scopeConfig->isSetFlag(
                'magentomcpai/chatbot/enable_email_support',
                ScopeInterface::SCOPE_STORE
            );
            
            if (!$emailSupportEnabled) {
                throw new LocalizedException(__('Email support is not enabled'));
            }
            
            // Get support email
            $supportEmail = $this->scopeConfig->getValue(
                'magentomcpai/chatbot/support_email',
                ScopeInterface::SCOPE_STORE
            ) ?: $this->scopeConfig->getValue(
                'trans_email/ident_support/email',
                ScopeInterface::SCOPE_STORE
            );
            
            if (!$supportEmail) {
                throw new LocalizedException(__('Support email is not configured'));
            }
            
            // Prepare email template variables
            $storeId = $this->storeManager->getStore()->getId();
            $storeName = $this->storeManager->getStore()->getName();
            
            $customerEmail = $requestData['customerEmail'];
            $customerName = $requestData['customerName'] ?? 'Customer';
            $chatHistory = $requestData['chatHistory'];
            $additionalComments = $requestData['additionalComments'] ?? '';
            
            // Format chat history for email
            $formattedHistory = $this->formatChatHistory($chatHistory);
            
            // Get email subject
            $emailSubject = $this->scopeConfig->getValue(
                'magentomcpai/chatbot/email_subject',
                ScopeInterface::SCOPE_STORE
            ) ?: 'Chatbot Conversation Transcript';
            
            // Send email
            $this->sendEmail(
                $supportEmail,
                $customerEmail,
                $customerName,
                $formattedHistory,
                $additionalComments,
                $emailSubject,
                $storeName
            );
            
            return $resultJson->setData([
                'success' => true,
                'message' => __('Your conversation has been sent to our support team.')
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
                'message' => __('An error occurred while sending your conversation.')
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
        $formattedHistory = '';
        
        foreach ($chatHistory as $message) {
            $sender = $message['isUser'] ? 'Customer' : 'Chatbot';
            $text = $message['text'];
            $time = isset($message['time']) ? date('Y-m-d H:i:s', $message['time'] / 1000) : '';
            
            $formattedHistory .= "$sender ($time): $text\n\n";
        }
        
        return $formattedHistory;
    }
    
    /**
     * Send email with conversation history
     *
     * @param string $supportEmail
     * @param string $customerEmail
     * @param string $customerName
     * @param string $chatHistory
     * @param string $additionalComments
     * @param string $subject
     * @param string $storeName
     * @return void
     */
    private function sendEmail($supportEmail, $customerEmail, $customerName, $chatHistory, $additionalComments, $subject, $storeName)
    {
        try {
            $this->inlineTranslation->suspend();
            
            $senderName = $this->scopeConfig->getValue(
                'trans_email/ident_support/name',
                ScopeInterface::SCOPE_STORE
            ) ?: 'Support';
            
            $sender = [
                'name' => $senderName,
                'email' => $supportEmail
            ];
            
            $templateVars = [
                'store_name' => $storeName,
                'customer_name' => $customerName,
                'customer_email' => $customerEmail,
                'chat_history' => nl2br($chatHistory),
                'additional_comments' => $additionalComments
            ];
            
            $transport = $this->transportBuilder
                ->setTemplateIdentifier('magentomcpai_chatbot_transcript')
                ->setTemplateOptions([
                    'area' => \Magento\Framework\App\Area::AREA_FRONTEND,
                    'store' => $this->storeManager->getStore()->getId()
                ])
                ->setTemplateVars($templateVars)
                ->setFrom($sender)
                ->addTo($supportEmail)
                ->addCc($customerEmail)
                ->getTransport();
                
            $transport->sendMessage();
            
            $this->inlineTranslation->resume();
        } catch (\Exception $e) {
            $this->logger->error('Error sending chatbot transcript email: ' . $e->getMessage());
            throw $e;
        }
    }
}
