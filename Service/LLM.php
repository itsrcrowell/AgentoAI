<?php
namespace Genaker\MagentoMcpAi\Service;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\LocalizedException;
use Genaker\MagentoMcpAi\Model\Service\OpenAiService;

class LLM
{
    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    private $apiKey;

    private $aiService;

    /**
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        OpenAiService $aiService
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->aiService = $aiService;
    }

    /**
     * Get the OpenAI API key from config
     *
     * @return string
     * @throws LocalizedException
     */
    public function getApiKey(): string
    {
        if($this->apiKey){
            return $this->apiKey;
        }

        $this->apiKey = $this->scopeConfig->getValue(
            'magentomcpai/general/api_key',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );

        if(!$this->apiKey ){
            throw new LocalizedException(
                __('OpenAI API key is not set in the admin configuration')
            );
        }

        return $this->apiKey;
    }


    /**
     * Send a chat request to the OpenAI API
     *
     * @param string $string
     * @param string $model
     * @return string
     */
    public function LLM($query, $model = 'gpt-5-nano', $temperature = 1): array
    {
        $messages = [];
        if(is_array($query)){
            $messages = $query;
        } else {
            $messages[] = [
                'role' => 'user',
                'content' => $query
            ];
        }

        $result = $this->aiService->sendChatRequest($messages, $model, $this->getApiKey(), $temperature);
        return $result;
    }

}
