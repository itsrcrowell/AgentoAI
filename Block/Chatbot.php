<?php
namespace Genaker\MagentoMcpAi\Block;

use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Store\Model\ScopeInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\Registry;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Psr\Log\LoggerInterface;

class Chatbot extends Template
{
    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @var Registry
     */
    protected $registry;

    /**
     * @var ProductRepositoryInterface
     */
    protected $productRepository;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @param Context $context
     * @param ScopeConfigInterface $scopeConfig
     * @param Registry $registry
     * @param ProductRepositoryInterface $productRepository
     * @param StoreManagerInterface $storeManager
     * @param LoggerInterface $logger
     * @param array $data
     */
    public function __construct(
        Context $context,
        ScopeConfigInterface $scopeConfig,
        Registry $registry,
        ProductRepositoryInterface $productRepository,
        StoreManagerInterface $storeManager,
        LoggerInterface $logger,
        array $data = []
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->registry = $registry;
        $this->productRepository = $productRepository;
        $this->storeManager = $storeManager;
        $this->logger = $logger;
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
        $mediaUrl = $this->storeManager->getStore()->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_MEDIA);
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
     * Get store context as JSON
     *
     * @return string
     */
    public function getStoreContextJson()
    {
        $storeInfo = [
            'name' => $this->storeManager->getStore()->getName(),
            'phone' => $this->scopeConfig->getValue('general/store_information/phone', ScopeInterface::SCOPE_STORE),
            'email' => $this->scopeConfig->getValue('trans_email/ident_general/email', ScopeInterface::SCOPE_STORE),
        ];
        
        return json_encode($storeInfo);
    }
    
    /**
     * Get current product if on a product page
     *
     * @return \Magento\Catalog\Api\Data\ProductInterface|null
     */
    public function getCurrentProduct()
    {
        return $this->registry->registry('current_product');
    }
    
    /**
     * Get product context as JSON
     *
     * @return string
     */
    public function getProductContextJson()
    {
        $product = $this->getCurrentProduct();
        $productData = [];
        
        if ($product) {
            try {
                // Load full product to ensure we have all attributes
                $product = $this->productRepository->getById($product->getId());
                
                // Get blacklisted attributes from configuration
                $blacklistedAttributes = $this->getBlacklistedAttributes();
                
                // Get all product attributes
                $attributes = $product->getAttributes();
                $attributeData = [];
                
                foreach ($attributes as $attribute) {
                    $attributeCode = $attribute->getAttributeCode();
                    
                    // Skip internal attributes and blacklisted attributes
                    $skipAttributes = ['category_ids', 'options', 'media_gallery', 'tier_price', 'quantity_and_stock_status'];
                    if (in_array($attributeCode, $skipAttributes) || in_array($attributeCode, $blacklistedAttributes)) {
                        continue;
                    }
                    
                    // Include all attributes, even those not visible in frontend
                    if ($product->getData($attributeCode) !== null) {
                        $value = $product->getData($attributeCode);
                        $label = $attribute->getFrontendLabel() ?: $attributeCode; // Use code if no label
                        
                        // Handle select attributes (get option text instead of ID)
                        if ($attribute->usesSource() && $value !== '') {
                            try {
                                $optionText = $attribute->getSource()->getOptionText($value);
                                if ($optionText) {
                                    $value = $optionText;
                                }
                            } catch (\Exception $e) {
                                // Just use the raw value if there's an issue
                            }
                        }
                        
                        // Include even attributes without frontend labels
                        if ($value !== '' && $value !== null) {
                            $attributeData[$attributeCode] = [
                                'label' => $label,
                                'value' => $value,
                                'is_visible' => (bool)$attribute->getIsVisibleOnFront()
                            ];
                        }
                    }
                }
                
                // Basic product data
                $productData = [
                    'id' => $product->getId(),
                    'sku' => $product->getSku(),
                    'name' => $product->getName(),
                    'type' => $product->getTypeId(),
                    'url' => $product->getProductUrl(),
                    'price' => $product->getFinalPrice(),
                    'description' => strip_tags($product->getDescription() ?: ''),
                    'short_description' => strip_tags($product->getShortDescription() ?: ''),
                    'in_stock' => $product->isAvailable(),
                    'attributes' => $attributeData
                ];
            } catch (\Exception $e) {
                // Log error but continue
                $this->logger->error('Error fetching product context for chatbot: ' . $e->getMessage());
            }
        }
        
        return json_encode($productData);
    }

    /**
     * Get blacklisted product attributes
     *
     * @return array
     */
    private function getBlacklistedAttributes()
    {
        $blacklistString = $this->scopeConfig->getValue(
            'magentomcpai/chatbot/product_attributes_blacklist',
            ScopeInterface::SCOPE_STORE
        );
        
        if (!$blacklistString) {
            // Default blacklist if none is configured
            return ['cost', 'supplier_id', 'supplier_code', 'internal_notes', 'special_price_from_date', 
                   'special_price_to_date', 'msrp', 'msrp_display_actual_price_type'];
        }
        
        // Parse the blacklist string (one attribute code per line)
        $blacklist = array_map('trim', explode("\n", $blacklistString));
        return array_filter($blacklist); // Remove empty entries
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

    /**
     * Check if email support is enabled
     *
     * @return bool
     */
    public function isEmailSupportEnabled()
    {
        return $this->scopeConfig->isSetFlag('magentomcpai/chatbot/enable_email_support', ScopeInterface::SCOPE_STORE);
    }
    
    /**
     * Get support email address
     *
     * @return string
     */
    public function getSupportEmail()
    {
        return $this->scopeConfig->getValue('magentomcpai/chatbot/support_email', ScopeInterface::SCOPE_STORE)
            ?: $this->scopeConfig->getValue('trans_email/ident_support/email', ScopeInterface::SCOPE_STORE);
    }
    
    /**
     * Get email subject
     *
     * @return string
     */
    public function getEmailSubject()
    {
        return $this->scopeConfig->getValue('magentomcpai/chatbot/email_subject', ScopeInterface::SCOPE_STORE)
            ?: 'Chatbot Conversation Transcript';
    }
}
