<?php

namespace Genaker\MagentoMcpAi\Model;

use Genaker\MagentoMcpAi\Api\MenuAIAPIInterface;
use Genaker\MagentoMcpAi\Model\Service\OpenAiService;
use Magento\Framework\Filesystem\DirectoryList;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Psr\Log\LoggerInterface;
use Magento\Framework\Session\SessionManagerInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Backend\Model\UrlInterface;
use Magento\Backend\Helper\Data as Helper;
use Magento\Framework\Data\Form\FormKey;

class MenuAIAPI implements MenuAIAPIInterface
{
    protected $openAiService;
    protected $directoryList;
    protected $scopeConfig;
    protected $logger;
    protected $session;
    protected $request;
    protected $urlBuilder;
    protected $helper;
    protected $formKey;

    const XML_PATH_API_KEY = 'magentomcpai/general/api_key';
    const MENU_MD_FILE = 'menu.md';

    public function __construct(
        OpenAiService $openAiService,
        DirectoryList $directoryList,
        ScopeConfigInterface $scopeConfig,
        LoggerInterface $logger,
        SessionManagerInterface $session,
        RequestInterface $request,
        UrlInterface  $urlBuilder,
        Helper $helper,
        FormKey $formKey
    ) {
        $this->openAiService = $openAiService;
        $this->directoryList = $directoryList;
        $this->scopeConfig = $scopeConfig;
        $this->logger = $logger;
        $this->session = $session;
        $this->request = $request;
        $this->urlBuilder = $urlBuilder;
        $this->helper = $helper;
        $this->formKey = $formKey;
    }

    public function sendRequestToChatGPT($query, $apiKey)
    {
        try {
            $storedApiKey = $this->scopeConfig->getValue(
                self::XML_PATH_API_KEY,
                ScopeInterface::SCOPE_STORE
            );

            if (empty($apiKey) || $apiKey !== $storedApiKey) {
                throw new LocalizedException(__('Invalid API key.'));
            }

            // Determine the path to menu.md
            $moduleDir = dirname(__DIR__); // This gets the current module directory
            $menuFilePath = $moduleDir . '/' . self::MENU_MD_FILE; // Adjust the path as needed

            if (!file_exists($menuFilePath)) {
                throw new LocalizedException(__('Menu file not found. Please check the file path: ' . $menuFilePath . ' or run the menu.py script to generate the ' . $moduleDir . '/menu.md file'));
            }

            $menuContext = file_get_contents($menuFilePath);

            // Add additional context instruction
            $additionalContext = "As a Magento admin interface assistant, your role is to help users navigate and manage the Magento admin panel efficiently. When responding to queries, follow these guidelines:
1. When providing a URL in response to a query, if a specific section of the page is relevant, append the section ID using a # if it exists, and wrap the full URL in double square brackets [[...]]. This helps anchor the link directly to the relevant part of the page.
2. If the query cannot be directly addressed with a URL, ask aditional question about the query to find correct answer.
3. Always aim to enhance the user's understanding of the Magento admin functionalities.
4. If the query is unclear or outside the scope of Magento admin tasks, politely ask for clarification or suggest consulting the official Magento documentation.
5. Maintain a professional and helpful tone in all responses.
5.1 Important respond only what was asked no aditional information but you can provide more information about question.
6. Use documentation MD text next:";
            $menuContext .= "\n" . $additionalContext;

            // Add the current query to the message history
            $messageHistory = []; // Disabling history 
            $this->addToMessageHistory($query);

            // Prepare messages array for API with clear roles
            $messages = [
                ['role' => 'system', 'content' => $menuContext]
            ];

            // Add the last 5 user messages to the messages array
            foreach ($this->getMessageHistory() as $userMessage) {
                $messages[] = ['role' => 'user', 'content' => $userMessage];
            }

            $model = 'gpt-5-nano'; // Use the appropriate model
            $temperature = 0.7;
            $maxTokens = 5000;

            // Use OpenAiService to make the API call
            $response = $this->openAiService->sendChatRequest(
                $messages,
                $model,
                $apiKey,
                $temperature,
                $maxTokens
            );

            // Extract URL from response content
            $content = $response['content'];
            preg_match('/\[\[(.*?)\]\]/', $content, $matches);
            $url = $matches[1] ?? null;

            // Transform {base_url} patterns to actual admin URLs in content using helper method
            $cleanContent = preg_replace_callback(
                '/\[\[{base_url}(\/.*?)\]\]/',
                function($matches) {
                    $path = $matches[1];
                    // Remove leading slash and split path
                    $parts = explode('/', trim($path, '/'));
                    
                    if (count($parts) >= 3) {
                        // Build route: module/controller/action
                        $route = $parts[0] . '/' . $parts[1] . '/' . $parts[2];
                        
                        // Extract parameters if any
                        $params = [];
                        for ($i = 3; $i < count($parts); $i += 2) {
                            if (isset($parts[$i + 1])) {
                                $params[$parts[$i]] = $parts[$i + 1];
                            }
                        }
                        
                        // Generate URL using helper method
                        $url = $this->helper->getUrl($route, $params);
                        // Decode URL-encoded hash fragments for proper anchor links
                        $url = str_replace('%23', '#', $url);
                    } else {
                        // Fallback for malformed paths
                        $url = str_replace('/admin/', '/', $this->helper->getHomePageUrl() . ltrim($path, '/'));
                        // Decode URL-encoded hash fragments for proper anchor links
                        $url = str_replace('%23', '#', $url);
                    }
                    
                    return '<a href="' . $url . '" target="_blank" style="color: #3498db; text-decoration: none; font-weight: bold;">🔗 ' . $url . '</a>';
                },
                $content
            );
            $hash = null;
        
            if ($url) {
                $hash = explode('#', $url)[1] ?? null;
                if($hash){
                    $url = explode('#', $url)[0];
                }
                $parts = explode('/', $url);
                if(count($parts) >= 2){
                
                //dd($parts);
            
            //TODO: I give up magento sucks and AI can't fix it we need make secret key work i will just disable it
             // Step 1: Generate the secret key
             //php bin/magento config:set admin/security/use_form_key 0
            if(isset($parts[1]) && ($parts[1] === 'adminhtml' || $parts[1] === 'admin')){
            $secretKey = $this->urlBuilder->getSecretKey(
                $parts[1] = 'adminhtml',       // frontName
                $parts[2] ?? 'index',   // controller
                $parts[3] ?? 'index'             // action
            );
        }  else {

            $secretKey = $this->urlBuilder->getSecretKey(
                $parts[1],       // frontName
                $parts[2] ?? 'index',   // controller
                $parts[3] ?? 'index'            // action
            );
        }

            $params = [];
            
            for ($i = 4; $i < count($parts); $i += 2) {

                $params[$parts[$i]] = @$parts[$i+1];
            }
            //dd([$parts, $params, $hash]);

           
            // Build URL with security key for admin routes
             if (isset($parts[1]) && strpos($parts[1], 'admin') !== false) {
                if(isset($parts[0]) && $parts[0] === '{base_url}'){
                    unset($parts[0]);
                }
                $parts[1] = 'adminhtml';
                //unset($parts[1]);
                // Remove 'admin/' prefix and get the rest of the route
                $adminRoute = '';
                // Use the admin URL builder with proper parameters
            $router = implode('/', $parts);
            $formKey = $this->formKey->getFormKey();
            #TODO: I give up magento sucks and AI can't fix it we need make secret key work i will just disable it. Will be fun to fix.
            $url = $this->helper->getUrl(
                $router, $params );
            } else {
                $router = ($parts[1] ?? 'admin') . '/' . ($parts[2] ?? 'index') . '/' . ($parts[3] ?? 'index');
                $url = $this->urlBuilder->getUrl($router);
            }
        }
        }

            // Return as an associative array
            return [
                'message' => trim($cleanContent),
                'url' => $url,
                'hash' => $hash
            ];
        } catch (LocalizedException $e) {
            $this->logger->error('LocalizedException: ' . $e->getMessage());
            throw $e;
        } catch (\Exception $e) {
            $this->logger->error('Exception: ' . $e->getMessage());
            throw new LocalizedException(__('An unexpected error occurred: ' . $e->getMessage()));
        }
    }

    private function addToMessageHistory($message)
    {
        // Generate a unique key for the current admin page
        $pageKey = $this->getPageKey();

        // Retrieve the current message history from the session
        $messageHistory = $this->session->getData($pageKey) ?? [];

        // Create structured message with metadata
        $structuredMessage = [
            'content' => $message,
            'timestamp' => time(),
            'type' => 'user_query',
            'page' => $this->request->getFullActionName()
        ];

        // Add the new message to the history
        $messageHistory[] = $structuredMessage;

        // Keep only the last 3 messages
        if (count($messageHistory) > 3) {
            array_shift($messageHistory);
        }

        // Save the updated message history back to the session
        $this->session->setData($pageKey, $messageHistory);
    }

    private function getMessageHistory()
    {
        // Generate a unique key for the current admin page
        $pageKey = $this->getPageKey();

        // Retrieve the message history from the session
        $rawHistory = $this->session->getData($pageKey) ?? [];
        
        // Convert structured messages to AI-friendly format
        $formattedHistory = [];
        foreach ($rawHistory as $index => $messageData) {
            // Handle both old string format and new structured format
            if (is_string($messageData)) {
                $content = $messageData;
                $type = 'legacy';
            } else {
                $content = $messageData['content'] ?? '';
                $type = $messageData['type'] ?? 'unknown';
                $timestamp = $messageData['timestamp'] ?? 0;
                $page = $messageData['page'] ?? 'unknown';
            }
            
            // Add context labeling for older messages (not the current one)
            $totalMessages = count($rawHistory);
            if ($index < $totalMessages - 1) {
                // This is context, not the current question
                $ageInMessages = $totalMessages - $index - 1;
                $contextLabel = "Previous context ({$ageInMessages} message" . ($ageInMessages > 1 ? 's' : '') . " ago)";
                $formattedHistory[] = $contextLabel . ': ' . $content;
            } else {
                // This is the current question
                $formattedHistory[] = $content;
            }
        }
        
        return $formattedHistory;
    }

    private function getPageKey()
    {
        // Use the current request path as a unique key for the admin page
        return 'message_history_' . md5($this->request->getFullActionName());
    }

    /**
     * Clear message history for current page (optional utility method)
     */
    public function clearMessageHistory()
    {
        $pageKey = $this->getPageKey();
        $this->session->unsetData($pageKey);
        return true;
    }

    /**
     * Get debug information about message history (optional utility method)
     */
    public function getHistoryDebugInfo()
    {
        $pageKey = $this->getPageKey();
        $rawHistory = $this->session->getData($pageKey) ?? [];
        
        return [
            'page_key' => $pageKey,
            'message_count' => count($rawHistory),
            'raw_messages' => $rawHistory,
            'formatted_messages' => $this->getMessageHistory()
        ];
    }
}