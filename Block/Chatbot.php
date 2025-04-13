<?php
namespace Genaker\MagentoMcpAi\Block;

use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\Registry;
use Magento\Framework\Serialize\Serializer\Json;

class Chatbot extends Template
{
    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;
    
    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;
    
    /**
     * @var Registry
     */
    protected $registry;
    
    /**
     * @var Json
     */
    protected $json;

    /**
     * @param Context $context
     * @param ScopeConfigInterface $scopeConfig
     * @param StoreManagerInterface $storeManager
     * @param Registry $registry
     * @param Json $json
     * @param array $data
     */
    public function __construct(
        Context $context,
        ScopeConfigInterface $scopeConfig,
        StoreManagerInterface $storeManager,
        Registry $registry,
        Json $json,
        array $data = []
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->storeManager = $storeManager;
        $this->registry = $registry;
        $this->json = $json;
        parent::__construct($context, $data);
    }

    /**
     * Check if chatbot is enabled
     *
     * @return bool
     */
    public function isEnabled()
    {
        return $this->scopeConfig->isSetFlag(
            'magentomcpai/chatbot/enabled',
            ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * Get theme type
     *
     * @return string
     */
    public function getThemeType()
    {
        return $this->scopeConfig->getValue(
            'magentomcpai/chatbot/theme_type',
            ScopeInterface::SCOPE_STORE
        ) ?: 'standard';
    }

    /**
     * Check if Hyva theme should be used
     *
     * @return bool
     */
    public function isHyvaTheme()
    {
        return $this->getThemeType() === 'hyva';
    }

    /**
     * Get chatbot title
     *
     * @return string
     */
    public function getChatbotTitle()
    {
        return $this->scopeConfig->getValue(
            'magentomcpai/chatbot/title',
            ScopeInterface::SCOPE_STORE
        ) ?: 'Virtual Assistant';
    }

    /**
     * Get welcome message
     *
     * @return string
     */
    public function getWelcomeMessage()
    {
        return $this->scopeConfig->getValue(
            'magentomcpai/chatbot/welcome_message',
            ScopeInterface::SCOPE_STORE
        ) ?: 'Hey there 👋 I\'m here to help you find what you need.';
    }

    /**
     * Get suggested queries
     *
     * @return array
     */
    public function getSuggestedQueries()
    {
        $queries = $this->scopeConfig->getValue(
            'magentomcpai/chatbot/suggested_queries',
            ScopeInterface::SCOPE_STORE
        );
        
        if (!$queries) {
            return [
                'I\'m looking for product information',
                'How can I track my order?',
                'What\'s your return policy?'
            ];
        }
        
        return explode("\n", $queries);
    }

    /**
     * Get OpenAI API key
     *
     * @return string
     */
    public function getApiKey()
    {
        return $this->scopeConfig->getValue(
            'magentomcpai/general/api_key',
            ScopeInterface::SCOPE_STORE
        );
    }
    
    /**
     * Get chatbot logo URL
     *
     * @return string
     */
    public function getLogoUrl()
    {
        $mediaUrl = $this->_storeManager->getStore()->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_MEDIA);
        $customLogo = $this->scopeConfig->getValue(
            'magentomcpai/chatbot/logo',
            ScopeInterface::SCOPE_STORE
        );
        
        if ($customLogo) {
            return $mediaUrl . 'magentomcpai/chatbot/' . $customLogo;
        }
        
        return $this->getViewFileUrl('Genaker_MagentoMcpAi::images/chatbot-logo.png');
    }
    
    /**
     * Get current product ID if on product page
     *
     * @return int|null
     */
    public function getCurrentProductId()
    {
        $product = $this->registry->registry('current_product');
        return $product ? $product->getId() : null;
    }
    
    /**
     * Get current product name if on product page
     *
     * @return string|null
     */
    public function getCurrentProductName()
    {
        $product = $this->registry->registry('current_product');
        return $product ? $product->getName() : null;
    }
    
    /**
     * Get store information for context
     *
     * @return array
     */
    public function getStoreContext()
    {
        $storeName = $this->scopeConfig->getValue(
            'general/store_information/name',
            ScopeInterface::SCOPE_STORE
        );
        
        $storePhone = $this->scopeConfig->getValue(
            'general/store_information/phone',
            ScopeInterface::SCOPE_STORE
        );
        
        $storeEmail = $this->scopeConfig->getValue(
            'trans_email/ident_general/email',
            ScopeInterface::SCOPE_STORE
        );
        
        return [
            'name' => $storeName,
            'phone' => $storePhone,
            'email' => $storeEmail
        ];
    }

    /**
     * Get store context as JSON for use in JavaScript
     *
     * @return string
     */
    public function getStoreContextJson()
    {
        $store = $this->storeManager->getStore();
        $storeContext = [
            'name' => $store->getName(),
            'phone' => $this->scopeConfig->getValue('general/store_information/phone', ScopeInterface::SCOPE_STORE),
            'email' => $this->scopeConfig->getValue('trans_email/ident_support/email', ScopeInterface::SCOPE_STORE),
            'product_id' => $this->getCurrentProductId(),
            'product_name' => $this->getCurrentProductName()
        ];
        
        return $this->json->serialize($storeContext);
    }

    /**
     * Get suggested queries as JSON
     * 
     * @return string
     */
    public function getSuggestedQueriesJson()
    {
        return json_encode($this->getSuggestedQueries());
    }

    /**
     * Get chat button text
     * 
     * @return string
     */
    public function getChatButtonText()
    {
        return __('Try our virtual assistant');
    }
}
