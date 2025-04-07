<?php
namespace Genaker\MagentoMcpAi\Model\Service;

use Magento\Framework\HTTP\Client\Curl;
use Magento\Framework\Json\Helper\Data as JsonHelper;
use Magento\Framework\Exception\LocalizedException;

class OpenAiService
{
    const API_ENDPOINT = 'https://api.openai.com/v1/chat/completions';

    /**
     * @var Curl
     */
    private $curl;

    /**
     * @var JsonHelper
     */
    private $jsonHelper;

    /**
     * @param Curl $curl
     * @param JsonHelper $jsonHelper
     */
    public function __construct(
        Curl $curl,
        JsonHelper $jsonHelper
    ) {
        $this->curl = $curl;
        $this->jsonHelper = $jsonHelper;
    }

    /**
     * Send request to OpenAI API
     *
     * @param array $messages
     * @param string $model
     * @param string $apiKey
     * @param float $temperature
     * @param int $maxTokens
     * @return array
     * @throws LocalizedException
     */
    public function sendChatRequest(
        array $messages,
        string $model,
        string $apiKey,
        float $temperature = 1,
        int $maxTokens = 4000
    ): array {
        try {
            $data = [
                'model' => $model,
                'messages' => $messages,
                'temperature' => $temperature,
                'max_tokens' => $maxTokens
            ];

            $this->curl->addHeader('Authorization', 'Bearer ' . $apiKey);
            $this->curl->addHeader('Content-Type', 'application/json');
            $this->curl->post(self::API_ENDPOINT, $this->jsonHelper->jsonEncode($data));

            $response = $this->jsonHelper->jsonDecode($this->curl->getBody(), true);

            if (isset($response['error'])) {
                throw new LocalizedException(
                    __('OpenAI API Error: %1', $response['error']['message'])
                );
            }

            return [
                'content' => $response['choices'][0]['message']['content'] ?? '',
                'usage' => $response['usage'] ?? [
                    'prompt_tokens' => 0,
                    'completion_tokens' => 0,
                    'total_tokens' => 0
                ]
            ];
        } catch (\Exception $e) {
            throw new LocalizedException(
                __('Failed to communicate with OpenAI API: %1', $e->getMessage())
            );
        }
    }
} 