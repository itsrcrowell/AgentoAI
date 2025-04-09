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

class MenuAIAPI implements MenuAIAPIInterface
{
    protected $openAiService;
    protected $directoryList;
    protected $scopeConfig;
    protected $logger;
    protected $session;
    protected $request;
    protected $urlBuilder;

    const XML_PATH_API_KEY = 'magentomcpai/general/api_key';
    const MENU_MD_FILE = 'menu.md';

    public function __construct(
        OpenAiService $openAiService,
        DirectoryList $directoryList,
        ScopeConfigInterface $scopeConfig,
        LoggerInterface $logger,
        SessionManagerInterface $session,
        RequestInterface $request,
        \Magento\Backend\Model\UrlInterface  $urlBuilder
    ) {
        $this->openAiService = $openAiService;
        $this->directoryList = $directoryList;
        $this->scopeConfig = $scopeConfig;
        $this->logger = $logger;
        $this->session = $session;
        $this->request = $request;
        $this->urlBuilder = $urlBuilder;
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
6. Use documentation MD text next:";
            $menuContext .= "\n" . $additionalContext;

            // Add the current query to the message history
            $this->addToMessageHistory($query);

            // Prepare messages array for API with clear roles
            $messages = [
                ['role' => 'system', 'content' => $menuContext]
            ];

            // Add the last 5 user messages to the messages array
            foreach ($this->getMessageHistory() as $userMessage) {
                $messages[] = ['role' => 'user', 'content' => $userMessage];
            }

            $model = 'gpt-4o-mini'; // Use the appropriate model
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

            // Remove URL from content
            $cleanContent = $content;
            $hash = null;
            if ($url) {
                $hash = explode('#', $url)[1] ?? null;
                if($hash){
                    $url = explode('#', $url)[0];
                }
                $parts = explode('/', $url);
                if(count($parts) > 3){
                
                //dd($parts);
            
            //TODO: I geva up magento sucks and AI can't fix it we need make secret key work i will just disable it
             // Step 1: Generate the secret key
             //php bin/magento config:set admin/security/use_form_key 0
            $secretKey = $this->urlBuilder->getSecretKey(
                $parts[1] = 'adminhtml',       // frontName
                $parts[2],   // controller
                $parts[3]             // action
            );

            $params = [];
            
            for ($i = 4; $i < count($parts); $i += 2) {
                $params[$parts[$i]] = $parts[$i+1];
            }
            //dd([$parts, $params, $hash]);

            $url = $this->urlBuilder->getUrl($parts[1].'/'.$parts[2].'/'.$parts[3], $params);
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

        // Add the new message to the history
        $messageHistory[] = $message;

        // Keep only the last 5 messages
        if (count($messageHistory) > 5) {
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
        return $this->session->getData($pageKey) ?? [];
    }

    private function getPageKey()
    {
        // Use the current request path as a unique key for the admin page
        return 'message_history_' . md5($this->request->getFullActionName());
    }
}