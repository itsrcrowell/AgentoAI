<?php
namespace Genaker\MagentoMcpAi\Model\Service;

use Magento\Framework\HTTP\Client\Curl;
use Magento\Framework\Json\Helper\Data as JsonHelper;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Filesystem\Io\File;

class OpenAiService
{
    const API_ENDPOINT = 'https://api.openai.com/v1/chat/completions';
    const FILES_API_ENDPOINT = 'https://api.openai.com/v1/files';
    const ANSWERS_API_ENDPOINT = 'https://api.openai.com/v1/answers'; // Depricated
    const COMPLETIONS_API_ENDPOINT = 'https://api.openai.com/v1/completions';
    const ASSISTANTS_API_ENDPOINT = 'https://api.openai.com/v1/assistants';
    const EMBEDDINGS_API_ENDPOINT = 'https://api.openai.com/v1/embeddings';
    const GOOGLE_SPEECH_API_ENDPOINT = 'https://speech.googleapis.com/v1/speech:recognize';
    const GOOGLE_VISION_API_ENDPOINT = 'https://vision.googleapis.com/v1/images:annotate';

    /**
     * @var Curl
     */
    private $curl;

    /**
     * @var JsonHelper
     */
    private $jsonHelper;
    
    /**
     * @var File
     */
    private $file;

    /**
     * @param Curl $curl
     * @param JsonHelper $jsonHelper
     * @param File $file
     */
    public function __construct(
        Curl $curl,
        JsonHelper $jsonHelper,
        File $file
    ) {
        $this->curl = $curl;
        $this->jsonHelper = $jsonHelper;
        $this->file = $file;
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
    
    /**
     * Upload a file to OpenAI API
     *
     * @param string $filePath Path to the file to upload
     * @param string $purpose Purpose of the file ('assistants', 'fine-tune', etc.)
     * @param string $apiKey OpenAI API key
     * @return array Response with file ID and other details
     * @throws LocalizedException
     */
    public function uploadFile(
        string $filePath,
        string $purpose = 'assistants',
        string $apiKey
    ): array {
        try {
            // Validate file exists
            if (!$this->file->fileExists($filePath)) {
                throw new LocalizedException(
                    __('File does not exist: %1', $filePath)
                );
            }
            
            // Get file info
            $fileInfo = $this->file->getPathInfo($filePath);
            $fileName = $fileInfo['basename'];
            
            // Read file contents
            $fileContents = file_get_contents($filePath);
            if ($fileContents === false) {
                throw new LocalizedException(
                    __('Unable to read file: %1', $filePath)
                );
            }
            
            // Prepare multipart boundary
            $boundary = '-------------' . uniqid();
            
            // Build multipart request body
            $body = '';
            
            // Add purpose field
            $body .= '--' . $boundary . "\r\n";
            $body .= 'Content-Disposition: form-data; name="purpose"' . "\r\n\r\n";
            $body .= $purpose . "\r\n";
            
            // Add file data
            $body .= '--' . $boundary . "\r\n";
            $body .= 'Content-Disposition: form-data; name="file"; filename="' . $fileName . '"' . "\r\n";
            $body .= 'Content-Type: application/octet-stream' . "\r\n\r\n";
            $body .= $fileContents . "\r\n";
            
            // Close multipart body
            $body .= '--' . $boundary . '--';
            
            // Setup headers
            $this->curl->addHeader('Authorization', 'Bearer ' . $apiKey);
            $this->curl->addHeader('Content-Type', 'multipart/form-data; boundary=' . $boundary);
            $this->curl->addHeader('Content-Length', strlen($body));
            
            // Send request
            $this->curl->post(self::FILES_API_ENDPOINT, $body);
            
            // Get response
            $response = $this->curl->getBody();
            $statusCode = $this->curl->getStatus();
            
            $responseData = $this->jsonHelper->jsonDecode($response, true);
            
            if ($statusCode >= 400 || isset($responseData['error'])) {
                $errorMessage = isset($responseData['error']) 
                    ? $responseData['error']['message'] 
                    : "HTTP Error: $statusCode";
                throw new LocalizedException(
                    __('OpenAI API Error: %1', $errorMessage)
                );
            }
            
            return $responseData;
        } catch (\Exception $e) {
            throw new LocalizedException(
                __('Failed to upload file to OpenAI API: %1', $e->getMessage())
            );
        }
    }
    
    /**
     * List all files uploaded to OpenAI
     *
     * @param string $apiKey OpenAI API key
     * @return array List of files
     * @throws LocalizedException
     */
    public function listFiles(string $apiKey): array
    {
        try {
            $this->curl->addHeader('Authorization', 'Bearer ' . $apiKey);
            $this->curl->addHeader('Content-Type', 'application/json');
            $this->curl->get(self::FILES_API_ENDPOINT);
            
            $response = $this->jsonHelper->jsonDecode($this->curl->getBody(), true);
            
            if (isset($response['error'])) {
                throw new LocalizedException(
                    __('OpenAI API Error: %1', $response['error']['message'])
                );
            }
            
            return $response;
        } catch (\Exception $e) {
            throw new LocalizedException(
                __('Failed to list files from OpenAI API: %1', $e->getMessage())
            );
        }
    }
    
    /**
     * Delete a file from OpenAI
     *
     * @param string $fileId ID of the file to delete
     * @param string $apiKey OpenAI API key
     * @return array Response confirming deletion
     * @throws LocalizedException
     */
    public function deleteFile(string $fileId, string $apiKey): array
    {
        try {
            $this->curl->addHeader('Authorization', 'Bearer ' . $apiKey);
            $this->curl->addHeader('Content-Type', 'application/json');
            $this->curl->addHeader('X-HTTP-Method-Override', 'DELETE');
            
            // Using POST with X-HTTP-Method-Override since Magento Curl doesn't directly support DELETE
            $this->curl->post(self::FILES_API_ENDPOINT . '/' . $fileId, '');
            
            $response = $this->curl->getBody();
            $statusCode = $this->curl->getStatus();
            
            $responseData = $this->jsonHelper->jsonDecode($response, true);
            
            if ($statusCode >= 400 || isset($responseData['error'])) {
                $errorMessage = isset($responseData['error']) 
                    ? $responseData['error']['message'] 
                    : "HTTP Error: $statusCode";
                throw new LocalizedException(
                    __('OpenAI API Error: %1', $errorMessage)
                );
            }
            
            return $responseData;
        } catch (\Exception $e) {
            throw new LocalizedException(
                __('Failed to delete file from OpenAI API: %1', $e->getMessage())
            );
        }
    }
    
    /**
     * Create a chat completion with file reference
     * 
     * @param array $messages The messages to send
     * @param string $fileId The file ID to reference
     * @param string $model The model to use
     * @param string $apiKey OpenAI API key
     * @param float $temperature Temperature parameter
     * @param int $maxTokens Maximum tokens to generate
     * @return array Response from OpenAI
     * @throws LocalizedException
     */
    public function sendFileReferenceChatRequest(
        array $messages,
        string $fileId,
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
                'max_tokens' => $maxTokens,
                'file_id' => $fileId
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
                __('Failed to communicate with OpenAI API for file-referenced chat: %1', $e->getMessage())
            );
        }
    }

    /**
     * Get answers to questions about a file by using the OpenAI Answers API
     *
     * @param string $question The question to ask about the file content
     * @param string|array $fileId The ID of the uploaded file(s) to analyze
     * @param string $apiKey OpenAI API key
     * @param string $model The model to use for generating the answer (e.g., text-davinci-003)
     * @param string $searchModel The model to use for searching the file (e.g., davinci)
     * @param int $maxRerank Maximum number of documents to re-rank
     * @param int $maxTokens Maximum tokens to generate
     * @param bool $returnMetadata Whether to return metadata about the search
     * @return array Response from OpenAI with answers
     * @throws LocalizedException
     */
    public function getFileAnswers(
        string $question,
        $fileId,
        string $apiKey,
        string $model = 'text-davinci-003',
        string $searchModel = 'davinci',
        int $maxRerank = 10,
        int $maxTokens = 150,
        bool $returnMetadata = true
    ): array {
        try {
            // Convert single file ID to array for consistent handling
            $fileIds = is_array($fileId) ? $fileId : [$fileId];
            
            // Check if we have multiple files
            if (count($fileIds) > 1) {
                // For multiple files, use different approach
                $data = [
                    'model' => $model,
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => 'You are a helpful assistant that analyzes documents.'
                        ],
                        [
                            'role' => 'user',
                            'content' => $question
                        ]
                    ],
                    'file_ids' => $fileIds,
                    'temperature' => 0.7,
                    'max_tokens' => $maxTokens
                ];
                
                $endpoint = self::API_ENDPOINT;
            } else {
                // For single file, use the original approach
                $data = [
                    'model' => $model,
                    'question' => $question,
                    'file' => $fileIds[0],
                    'search_model' => $searchModel,
                    'max_rerank' => $maxRerank,
                    'max_tokens' => $maxTokens,
                    'return_metadata' => $returnMetadata
                ];
                
                $endpoint = self::ANSWERS_API_ENDPOINT;
            }
            
            $this->curl->addHeader('Authorization', 'Bearer ' . $apiKey);
            $this->curl->addHeader('Content-Type', 'application/json');
            $this->curl->post($endpoint, $this->jsonHelper->jsonEncode($data));
            
            $response = $this->curl->getBody();
            $statusCode = $this->curl->getStatus();
            
            $responseData = $this->jsonHelper->jsonDecode($response, true);
            
            if ($statusCode >= 400 || isset($responseData['error'])) {
                // If the API call fails, try the alternative approach
                if (count($fileIds) === 1) {
                    return $this->getFileCompletionAlternative($question, $fileIds[0], $apiKey, $model, $maxTokens);
                } else {
                    // For multiple files, create a normalized response format
                    return $this->getMultipleFilesCompletionAlternative($question, $fileIds, $apiKey, $model, $maxTokens);
                }
            }
            
            // For chat completions API, normalize the response format
            if (count($fileIds) > 1 && isset($responseData['choices'][0]['message']['content'])) {
                return [
                    'answers' => [$responseData['choices'][0]['message']['content']],
                    'file_ids' => $fileIds,
                    'usage' => $responseData['usage'] ?? []
                ];
            }
            
            return $responseData;
        } catch (\Exception $e) {
            throw new LocalizedException(
                __('Failed to get answers from OpenAI API: %1', $e->getMessage())
            );
        }
    }
    
    /**
     * Alternative method for getting answers about multiple files
     * Used as a fallback when the standard API call fails
     *
     * @param string $question The question to ask
     * @param array $fileIds The file IDs to reference
     * @param string $apiKey OpenAI API key
     * @param string $model The model to use
     * @param int $maxTokens Maximum tokens to generate
     * @return array Response with answers
     * @throws LocalizedException
     */
    private function getMultipleFilesCompletionAlternative(
        string $question, 
        array $fileIds, 
        string $apiKey,
        string $model = 'gpt-3.5-turbo',
        int $maxTokens = 150
    ): array {
        try {
            // Get information about each file
            $fileInfo = [];
            foreach ($fileIds as $id) {
                try {
                    $info = $this->getFileContent($id, $apiKey);
                    $fileInfo[] = $info;
                } catch (\Exception $e) {
                    // Continue even if we can't get info for one file
                    $fileInfo[] = ['id' => $id, 'error' => $e->getMessage()];
                }
            }
            
            // Create a context-aware message for ChatGPT
            $fileIdsString = implode(', ', $fileIds);
            
            $messages = [
                [
                    'role' => 'system',
                    'content' => 'You are an assistant that analyzes multiple documents. Please provide a comprehensive answer based on all files provided.'
                ],
                [
                    'role' => 'user',
                    'content' => "I have uploaded multiple documents with IDs: $fileIdsString. Here is my question about them: $question"
                ]
            ];
            
            // Add file info if available
            if (!empty($fileInfo)) {
                $messages[1]['content'] .= "\n\nHere is information about the files: " . json_encode($fileInfo);
            }
            
            // Use the chat completion API
            $data = [
                'model' => $model,
                'messages' => $messages,
                'max_tokens' => $maxTokens,
                'temperature' => 0.7
            ];
            
            $this->curl->addHeader('Authorization', 'Bearer ' . $apiKey);
            $this->curl->addHeader('Content-Type', 'application/json');
            $this->curl->post(self::API_ENDPOINT, $this->jsonHelper->jsonEncode($data));
            
            $response = $this->curl->getBody();
            $responseData = $this->jsonHelper->jsonDecode($response, true);
            
            if (isset($responseData['error'])) {
                throw new LocalizedException(
                    __('OpenAI API Error: %1', $responseData['error']['message'])
                );
            }
            
            // Format response similar to answers API
            return [
                'answers' => [$responseData['choices'][0]['message']['content'] ?? ''],
                'file_ids' => $fileIds,
                'usage' => $responseData['usage'] ?? [],
                'method' => 'alternative'
            ];
        } catch (\Exception $e) {
            throw new LocalizedException(
                __('Failed to get multiple file completion: %1', $e->getMessage())
            );
        }
    }
    
    /**
     * Get file content or metadata from OpenAI
     *
     * @param string $fileId The ID of the uploaded file
     * @param string $apiKey OpenAI API key
     * @return array File information
     * @throws LocalizedException
     */
    public function getFileContent(string $fileId, string $apiKey): array
    {
        try {
            $this->curl->addHeader('Authorization', 'Bearer ' . $apiKey);
            $this->curl->addHeader('Content-Type', 'application/json');
            $this->curl->get(self::FILES_API_ENDPOINT . '/' . $fileId);
            
            $response = $this->jsonHelper->jsonDecode($this->curl->getBody(), true);
            
            if (isset($response['error'])) {
                throw new LocalizedException(
                    __('OpenAI API Error: %1', $response['error']['message'])
                );
            }
            
            return $response;
        } catch (\Exception $e) {
            // Just return empty array if we can't get the file content
            return [];
        }
    }
    
    /**
     * Use OpenAI Completions API to get answers directly
     * 
     * @param string $prompt The prompt to complete
     * @param string $apiKey OpenAI API key
     * @param string $model The model to use
     * @param int $maxTokens Maximum tokens to generate
     * @param float $temperature Temperature parameter
     * @return array Response from OpenAI
     * @throws LocalizedException
     */
    public function getCompletion(
        string $prompt,
        string $apiKey,
        string $model = 'text-davinci-003',
        int $maxTokens = 150,
        float $temperature = 0.7
    ): array {
        try {
            $data = [
                'model' => $model,
                'prompt' => $prompt,
                'max_tokens' => $maxTokens,
                'temperature' => $temperature
            ];
            
            $this->curl->addHeader('Authorization', 'Bearer ' . $apiKey);
            $this->curl->addHeader('Content-Type', 'application/json');
            $this->curl->post(self::COMPLETIONS_API_ENDPOINT, $this->jsonHelper->jsonEncode($data));
            
            $response = $this->jsonHelper->jsonDecode($this->curl->getBody(), true);
            
            if (isset($response['error'])) {
                throw new LocalizedException(
                    __('OpenAI API Error: %1', $response['error']['message'])
                );
            }
            
            return [
                'completion' => $response['choices'][0]['text'] ?? '',
                'usage' => $response['usage'] ?? [
                    'prompt_tokens' => 0,
                    'completion_tokens' => 0,
                    'total_tokens' => 0
                ]
            ];
        } catch (\Exception $e) {
            throw new LocalizedException(
                __('Failed to get completion from OpenAI API: %1', $e->getMessage())
            );
        }
    }

    /**
     * Create a chat completion with multiple file references
     * 
     * @param array $messages The messages to send
     * @param array $fileIds Array of file IDs to reference
     * @param string $model The model to use
     * @param string $apiKey OpenAI API key
     * @param float $temperature Temperature parameter
     * @param int $maxTokens Maximum tokens to generate
     * @return array Response from OpenAI
     * @throws LocalizedException
     */
    public function sendMultipleFilesChatRequest(
        array $messages,
        array $fileIds,
        string $model,
        string $apiKey,
        float $temperature = 1,
        int $maxTokens = 4000
    ): array {
        try {
            // Validate that fileIds is not empty
            if (empty($fileIds)) {
                throw new LocalizedException(
                    __('No file IDs provided for the multi-file chat request')
                );
            }
            
            $data = [
                'model' => $model,
                'messages' => $messages,
                'temperature' => $temperature,
                'max_tokens' => $maxTokens,
                'file_ids' => $fileIds
            ];
            
            $this->curl->addHeader('Authorization', 'Bearer ' . $apiKey);
            $this->curl->addHeader('Content-Type', 'application/json');
            $this->curl->post(self::API_ENDPOINT, $this->jsonHelper->jsonEncode($data));
            
            $response = $this->curl->getBody();
            $statusCode = $this->curl->getStatus();
            
            $responseData = $this->jsonHelper->jsonDecode($response, true);
            
            if ($statusCode >= 400 || isset($responseData['error'])) {
                // Check if we need to try a different approach
                if (isset($responseData['error']) && (
                    strpos($responseData['error']['message'], 'file_ids') !== false ||
                    strpos($responseData['error']['message'], 'not supported') !== false
                )) {
                    // Try using the Assistant API which better supports multiple files
                    return $this->createMultiFileAssistantChat($messages, $fileIds, $model, $apiKey, $temperature, $maxTokens);
                }
                
                $errorMessage = isset($responseData['error']) 
                    ? $responseData['error']['message'] 
                    : "HTTP Error: $statusCode";
                throw new LocalizedException(
                    __('OpenAI API Error: %1', $errorMessage)
                );
            }
            
            return [
                'content' => $responseData['choices'][0]['message']['content'] ?? '',
                'usage' => $responseData['usage'] ?? [
                    'prompt_tokens' => 0,
                    'completion_tokens' => 0,
                    'total_tokens' => 0
                ],
                'file_ids' => $fileIds
            ];
        } catch (\Exception $e) {
            throw new LocalizedException(
                __('Failed to communicate with OpenAI API for multi-file chat: %1', $e->getMessage())
            );
        }
    }
    
    /**
     * Create an assistant with multiple files and use it for a chat
     * For models that don't support file_ids directly in chat completions
     * 
     * @param array $messages The messages to send
     * @param array $fileIds Array of file IDs to attach to the assistant
     * @param string $model The model to use
     * @param string $apiKey OpenAI API key
     * @param float $temperature Temperature parameter
     * @param int $maxTokens Maximum tokens to generate
     * @return array Response from the assistant
     * @throws LocalizedException
     */
    protected function createMultiFileAssistantChat(
        array $messages,
        array $fileIds,
        string $model,
        string $apiKey,
        float $temperature = 1,
        int $maxTokens = 4000
    ): array {
        try {
            // Step 1: Create a temporary assistant with the files attached
            $assistantData = [
                'model' => $model,
                'name' => 'Temporary Multi-File Assistant ' . uniqid(),
                'description' => 'Temporary assistant created for multi-file processing',
                'instructions' => 'You are a helpful assistant that has access to multiple files. '
                               . 'Analyze and reference these files to answer user questions accurately.',
                'tools' => [
                    ['type' => 'retrieval'] // Enable file retrieval tool
                ],
                'file_ids' => $fileIds,
                'temperature' => $temperature,
                'max_tokens' => $maxTokens
            ];
            
            // Create the assistant
            $this->curl->addHeader('Authorization', 'Bearer ' . $apiKey);
            $this->curl->addHeader('Content-Type', 'application/json');
            $this->curl->post(self::ASSISTANTS_API_ENDPOINT, $this->jsonHelper->jsonEncode($assistantData));
            
            $assistantResponse = $this->jsonHelper->jsonDecode($this->curl->getBody(), true);
            
            if (isset($assistantResponse['error'])) {
                throw new LocalizedException(
                    __('Error creating assistant: %1', $assistantResponse['error']['message'])
                );
            }
            
            $assistantId = $assistantResponse['id'];
            
            // Step 2: Create a thread
            $this->curl->addHeader('Authorization', 'Bearer ' . $apiKey);
            $this->curl->addHeader('Content-Type', 'application/json');
            $this->curl->post(
                'https://api.openai.com/v1/threads', 
                $this->jsonHelper->jsonEncode([])
            );
            
            $threadResponse = $this->jsonHelper->jsonDecode($this->curl->getBody(), true);
            $threadId = $threadResponse['id'];
            
            // Step 3: Add messages to the thread
            $threadMessages = [];
            foreach ($messages as $message) {
                $threadMessage = [
                    'role' => $message['role'] ?? 'user',
                    'content' => $message['content']
                ];
                
                $this->curl->addHeader('Authorization', 'Bearer ' . $apiKey);
                $this->curl->addHeader('Content-Type', 'application/json');
                $this->curl->post(
                    "https://api.openai.com/v1/threads/{$threadId}/messages",
                    $this->jsonHelper->jsonEncode($threadMessage)
                );
                
                $messageResponse = $this->jsonHelper->jsonDecode($this->curl->getBody(), true);
                $threadMessages[] = $messageResponse;
            }
            
            // Step 4: Run the assistant on the thread
            $runData = [
                'assistant_id' => $assistantId
            ];
            
            $this->curl->addHeader('Authorization', 'Bearer ' . $apiKey);
            $this->curl->addHeader('Content-Type', 'application/json');
            $this->curl->post(
                "https://api.openai.com/v1/threads/{$threadId}/runs",
                $this->jsonHelper->jsonEncode($runData)
            );
            
            $runResponse = $this->jsonHelper->jsonDecode($this->curl->getBody(), true);
            $runId = $runResponse['id'];
            
            // Step 5: Poll for run completion
            $maxAttempts = 30; // Maximum wait time = 30 * 2 seconds = 60 seconds
            $attempts = 0;
            $runStatus = '';
            
            while ($attempts < $maxAttempts) {
                $this->curl->addHeader('Authorization', 'Bearer ' . $apiKey);
                $this->curl->get("https://api.openai.com/v1/threads/{$threadId}/runs/{$runId}");
                
                $runStatusResponse = $this->jsonHelper->jsonDecode($this->curl->getBody(), true);
                $runStatus = $runStatusResponse['status'];
                
                if (in_array($runStatus, ['completed', 'failed', 'cancelled', 'expired'])) {
                    break;
                }
                
                // Wait 2 seconds before checking again
                sleep(2);
                $attempts++;
            }
            
            if ($runStatus !== 'completed') {
                throw new LocalizedException(
                    __('Assistant run did not complete successfully. Status: %1', $runStatus)
                );
            }
            
            // Step 6: Get the assistant's messages
            $this->curl->addHeader('Authorization', 'Bearer ' . $apiKey);
            $this->curl->get("https://api.openai.com/v1/threads/{$threadId}/messages?limit=1");
            
            $messagesResponse = $this->jsonHelper->jsonDecode($this->curl->getBody(), true);
            $assistantMessage = '';
            
            foreach ($messagesResponse['data'] as $message) {
                if ($message['role'] === 'assistant') {
                    // Extract the content
                    foreach ($message['content'] as $content) {
                        if ($content['type'] === 'text') {
                            $assistantMessage .= $content['text']['value'] . "\n";
                        }
                    }
                    break;
                }
            }
            
            // Step 7: Clean up by deleting the assistant (optional, can be commented out if you want to keep the assistant)
            $this->curl->addHeader('Authorization', 'Bearer ' . $apiKey);
            $this->curl->addHeader('X-HTTP-Method-Override', 'DELETE');
            $this->curl->post(self::ASSISTANTS_API_ENDPOINT . '/' . $assistantId, '');
            
            // Format the response to match the chat completion API
            return [
                'content' => $assistantMessage,
                'usage' => [
                    'prompt_tokens' => 0, // Not provided by Assistants API
                    'completion_tokens' => 0,
                    'total_tokens' => 0
                ],
                'assistant_id' => $assistantId,
                'thread_id' => $threadId,
                'file_ids' => $fileIds,
                'method' => 'assistants_api' // Flag to indicate which API was used
            ];
        } catch (\Exception $e) {
            throw new LocalizedException(
                __('Failed to process multi-file request with Assistants API: %1', $e->getMessage())
            );
        }
    }
    
    /**
     * Get answers to questions about multiple files
     *
     * @param string $question The question to ask about the file content
     * @param array $fileIds Array of file IDs to use
     * @param string $apiKey OpenAI API key
     * @param string $model The model to use
     * @param int $maxTokens Maximum tokens to generate
     * @return array Response with answers
     * @throws LocalizedException
     */
    public function getMultiFileAnswers(
        string $question,
        array $fileIds,
        string $apiKey,
        string $model = 'gpt-3.5-turbo',
        int $maxTokens = 1000
    ): array {
        // Create a message list with system and user messages
        $messages = [
            [
                'role' => 'system',
                'content' => 'You are a helpful assistant that analyzes multiple documents. '
                           . 'Use the information from all files to provide comprehensive answers.'
            ],
            [
                'role' => 'user',
                'content' => $question
            ]
        ];
        
        // Use the multi-file chat method
        return $this->sendMultipleFilesChatRequest($messages, $fileIds, $model, $apiKey, 0.7, $maxTokens);
    }
    
    /**
     * Batch upload multiple files to OpenAI API
     *
     * @param array $filePaths Array of file paths to upload
     * @param string $purpose Purpose of the files ('assistants', 'fine-tune', etc.)
     * @param string $apiKey OpenAI API key
     * @return array Array of file IDs and their responses
     * @throws LocalizedException
     */
    public function batchUploadFiles(
        array $filePaths,
        string $purpose,
        string $apiKey
    ): array {
        $results = [];
        $errors = [];
        
        foreach ($filePaths as $index => $filePath) {
            try {
                $fileData = $this->uploadFile($filePath, $purpose, $apiKey);
                $results[$filePath] = $fileData;
            } catch (\Exception $e) {
                $errors[$filePath] = $e->getMessage();
            }
        }
        
        return [
            'successful' => $results,
            'failed' => $errors,
            'file_ids' => array_column($results, 'id'),
            'total_uploaded' => count($results),
            'total_failed' => count($errors)
        ];
    }

    /**
     * Convert speech to text using Google Cloud Speech-to-Text API
     *
     * @param string $audioFilePath Path to the audio file to transcribe
     * @param string $googleAccessToken Google Cloud access token
     * @param string $languageCode Language code (e.g., 'en-US')
     * @param string $encoding Audio encoding (e.g., 'LINEAR16', 'MP3')
     * @param int $sampleRateHertz Audio sample rate in Hertz
     * @return array Transcription results
     * @throws LocalizedException
     */
    public function speechToText(
        string $audioFilePath,
        string $googleAccessToken,
        string $languageCode = 'en-US',
        string $encoding = 'LINEAR16',
        int $sampleRateHertz = 16000
    ): array {
        try {
            // Validate file exists
            if (!$this->file->fileExists($audioFilePath)) {
                throw new LocalizedException(
                    __('Audio file does not exist: %1', $audioFilePath)
                );
            }
            
            // Read audio file and encode to base64
            $audioContent = file_get_contents($audioFilePath);
            if ($audioContent === false) {
                throw new LocalizedException(
                    __('Unable to read audio file: %1', $audioFilePath)
                );
            }
            
            $audioBase64 = base64_encode($audioContent);
            
            // Prepare request data
            $data = [
                'config' => [
                    'encoding' => $encoding,
                    'sampleRateHertz' => $sampleRateHertz,
                    'languageCode' => $languageCode,
                    'enableAutomaticPunctuation' => true,
                    'model' => 'default'
                ],
                'audio' => [
                    'content' => $audioBase64
                ]
            ];
            
            // Set up headers
            $this->curl->addHeader('Authorization', 'Bearer ' . $googleAccessToken);
            $this->curl->addHeader('Content-Type', 'application/json');
            
            // Send request to Google Cloud Speech-to-Text API
            $this->curl->post(self::GOOGLE_SPEECH_API_ENDPOINT, $this->jsonHelper->jsonEncode($data));
            
            // Get response
            $response = $this->curl->getBody();
            $statusCode = $this->curl->getStatus();
            
            $responseData = $this->jsonHelper->jsonDecode($response, true);
            
            if ($statusCode >= 400 || isset($responseData['error'])) {
                $errorMessage = isset($responseData['error']) 
                    ? $responseData['error']['message'] 
                    : "HTTP Error: $statusCode";
                throw new LocalizedException(
                    __('Google Speech-to-Text API Error: %1', $errorMessage)
                );
            }
            
            // Process and format response
            $transcript = '';
            $confidence = 0;
            
            if (isset($responseData['results']) && !empty($responseData['results'])) {
                foreach ($responseData['results'] as $result) {
                    if (isset($result['alternatives']) && !empty($result['alternatives'])) {
                        // Use the top alternative
                        $topAlternative = $result['alternatives'][0];
                        $transcript .= $topAlternative['transcript'] . ' ';
                        
                        // Average out confidence if multiple results
                        if (isset($topAlternative['confidence'])) {
                            $confidence += $topAlternative['confidence'];
                        }
                    }
                }
                
                if (count($responseData['results']) > 0 && $confidence > 0) {
                    $confidence = $confidence / count($responseData['results']);
                }
            }
            
            return [
                'transcript' => trim($transcript),
                'confidence' => $confidence,
                'raw_response' => $responseData,
                'language_code' => $languageCode
            ];
        } catch (\Exception $e) {
            throw new LocalizedException(
                __('Failed to convert speech to text: %1', $e->getMessage())
            );
        }
    }
    
    /**
     * Convert speech to text and then use OpenAI to analyze or respond
     *
     * @param string $audioFilePath Path to the audio file
     * @param string $googleAccessToken Google Cloud access token
     * @param string $openAiApiKey OpenAI API key
     * @param string $promptPrefix Additional prompt text to prepend before the transcript
     * @param string $model The OpenAI model to use
     * @param string $languageCode Language code for speech recognition
     * @return array OpenAI response with transcript
     * @throws LocalizedException
     */
    public function speechToTextWithAiResponse(
        string $audioFilePath,
        string $googleAccessToken,
        string $openAiApiKey,
        string $promptPrefix = "Please analyze this transcription: ",
        string $model = 'gpt-3.5-turbo',
        string $languageCode = 'en-US'
    ): array {
        // First, convert speech to text
        $transcription = $this->speechToText($audioFilePath, $googleAccessToken, $languageCode);
        
        if (empty($transcription['transcript'])) {
            throw new LocalizedException(
                __('No speech was recognized in the audio file')
            );
        }
        
        // Create messages for OpenAI
        $messages = [
            [
                'role' => 'system',
                'content' => 'You are a helpful assistant that processes voice transcriptions.'
            ],
            [
                'role' => 'user',
                'content' => $promptPrefix . $transcription['transcript']
            ]
        ];
        
        // Get AI response
        $aiResponse = $this->sendChatRequest($messages, $model, $openAiApiKey);
        
        // Combine the results
        return [
            'transcript' => $transcription['transcript'],
            'confidence' => $transcription['confidence'],
            'ai_response' => $aiResponse['content'],
            'language_code' => $transcription['language_code']
        ];
    }

    /**
     * Recognize image content using Google Cloud Vision API
     *
     * @param string $imageFilePath Path to the image file
     * @param string $googleAccessToken Google Cloud access token
     * @param array $featureTypes Types of detection to perform (LABEL_DETECTION, TEXT_DETECTION, etc)
     * @param int $maxResults Maximum number of results per feature
     * @return array Recognition results
     * @throws LocalizedException
     */
    public function recognizeImage(
        string $imageFilePath,
        string $googleAccessToken,
        array $featureTypes = ['LABEL_DETECTION', 'TEXT_DETECTION', 'OBJECT_LOCALIZATION'],
        int $maxResults = 10
    ): array {
        try {
            // Validate file exists
            if (!$this->file->fileExists($imageFilePath)) {
                throw new LocalizedException(
                    __('Image file does not exist: %1', $imageFilePath)
                );
            }
            
            // Read image file and encode to base64
            $imageContent = file_get_contents($imageFilePath);
            if ($imageContent === false) {
                throw new LocalizedException(
                    __('Unable to read image file: %1', $imageFilePath)
                );
            }
            
            $imageBase64 = base64_encode($imageContent);
            
            // Prepare features array
            $features = [];
            foreach ($featureTypes as $featureType) {
                $features[] = [
                    'type' => $featureType,
                    'maxResults' => $maxResults
                ];
            }
            
            // Prepare request data
            $data = [
                'requests' => [
                    [
                        'image' => [
                            'content' => $imageBase64
                        ],
                        'features' => $features
                    ]
                ]
            ];
            
            // Set up headers
            $this->curl->addHeader('Authorization', 'Bearer ' . $googleAccessToken);
            $this->curl->addHeader('Content-Type', 'application/json');
            
            // Send request to Google Cloud Vision API
            $this->curl->post(self::GOOGLE_VISION_API_ENDPOINT, $this->jsonHelper->jsonEncode($data));
            
            // Get response
            $response = $this->curl->getBody();
            $statusCode = $this->curl->getStatus();
            
            $responseData = $this->jsonHelper->jsonDecode($response, true);
            
            if ($statusCode >= 400 || isset($responseData['error'])) {
                $errorMessage = isset($responseData['error']) 
                    ? $responseData['error']['message'] 
                    : "HTTP Error: $statusCode";
                throw new LocalizedException(
                    __('Google Vision API Error: %1', $errorMessage)
                );
            }
            
            // Process and format response into a more user-friendly structure
            $result = [
                'labels' => [],
                'text' => '',
                'objects' => [],
                'raw_response' => $responseData
            ];
            
            if (isset($responseData['responses']) && !empty($responseData['responses'])) {
                $response = $responseData['responses'][0];
                
                // Process label annotations
                if (isset($response['labelAnnotations'])) {
                    foreach ($response['labelAnnotations'] as $label) {
                        $result['labels'][] = [
                            'description' => $label['description'] ?? '',
                            'score' => $label['score'] ?? 0,
                            'topicality' => $label['topicality'] ?? 0
                        ];
                    }
                }
                
                // Process text annotations
                if (isset($response['textAnnotations']) && !empty($response['textAnnotations'])) {
                    // The first element typically contains the entire text
                    $result['text'] = $response['textAnnotations'][0]['description'] ?? '';
                    $result['text_items'] = [];
                    
                    // Add individual text items
                    foreach ($response['textAnnotations'] as $index => $text) {
                        if ($index > 0) { // Skip the first one as it's the complete text
                            $result['text_items'][] = [
                                'text' => $text['description'] ?? '',
                                'locale' => $text['locale'] ?? '',
                                'boundingPoly' => $text['boundingPoly'] ?? []
                            ];
                        }
                    }
                }
                
                // Process object localizations
                if (isset($response['localizedObjectAnnotations'])) {
                    foreach ($response['localizedObjectAnnotations'] as $object) {
                        $result['objects'][] = [
                            'name' => $object['name'] ?? '',
                            'score' => $object['score'] ?? 0,
                            'boundingPoly' => $object['boundingPoly'] ?? []
                        ];
                    }
                }
            }
            
            return $result;
        } catch (\Exception $e) {
            throw new LocalizedException(
                __('Failed to recognize image: %1', $e->getMessage())
            );
        }
    }
    
    /**
     * Recognize image and send its content to OpenAI for analysis
     *
     * @param string $imageFilePath Path to the image file
     * @param string $googleAccessToken Google Cloud access token
     * @param string $openAiApiKey OpenAI API key
     * @param string $promptTemplate Template for prompting OpenAI with image data
     * @param string $model The OpenAI model to use
     * @return array OpenAI response with image recognition data
     * @throws LocalizedException
     */
    public function recognizeImageWithAiAnalysis(
        string $imageFilePath,
        string $googleAccessToken,
        string $openAiApiKey,
        string $promptTemplate = "Analyze this image content. Labels: {{LABELS}}. Text detected: {{TEXT}}. Objects detected: {{OBJECTS}}.",
        string $model = 'gpt-3.5-turbo'
    ): array {
        // First, recognize the image
        $recognition = $this->recognizeImage($imageFilePath, $googleAccessToken);
        
        // Format the labels as a comma-separated list
        $labelsText = implode(', ', array_map(function ($label) {
            return $label['description'] . ' (' . round($label['score'] * 100) . '%)';
        }, $recognition['labels']));
        
        // Format the objects as a comma-separated list
        $objectsText = implode(', ', array_map(function ($object) {
            return $object['name'] . ' (' . round($object['score'] * 100) . '%)';
        }, $recognition['objects']));
        
        // Replace placeholders in the prompt template
        $prompt = str_replace(
            ['{{LABELS}}', '{{TEXT}}', '{{OBJECTS}}'],
            [$labelsText, $recognition['text'], $objectsText],
            $promptTemplate
        );
        
        // Create messages for OpenAI
        $messages = [
            [
                'role' => 'system',
                'content' => 'You are a helpful assistant that analyzes image content.'
            ],
            [
                'role' => 'user',
                'content' => $prompt
            ]
        ];
        
        // Get AI response
        $aiResponse = $this->sendChatRequest($messages, $model, $openAiApiKey);
        
        // Combine the results
        return [
            'recognition' => [
                'labels' => $recognition['labels'],
                'text' => $recognition['text'],
                'objects' => $recognition['objects']
            ],
            'ai_analysis' => $aiResponse['content']
        ];
    }
    
    /**
     * Extract and analyze text from an image
     *
     * @param string $imageFilePath Path to the image file
     * @param string $googleAccessToken Google Cloud access token
     * @param bool $fullTextAnnotation Whether to request full text annotation (document OCR)
     * @return array Text extraction results
     * @throws LocalizedException
     */
    public function extractTextFromImage(
        string $imageFilePath,
        string $googleAccessToken,
        bool $fullTextAnnotation = true
    ): array {
        try {
            // Validate file exists
            if (!$this->file->fileExists($imageFilePath)) {
                throw new LocalizedException(
                    __('Image file does not exist: %1', $imageFilePath)
                );
            }
            
            // Read image file and encode to base64
            $imageContent = file_get_contents($imageFilePath);
            if ($imageContent === false) {
                throw new LocalizedException(
                    __('Unable to read image file: %1', $imageFilePath)
                );
            }
            
            $imageBase64 = base64_encode($imageContent);
            
            // Prepare features for text detection
            $features = [
                [
                    'type' => 'TEXT_DETECTION',
                    'maxResults' => 100
                ]
            ];
            
            // Add DOCUMENT_TEXT_DETECTION if full text annotation is requested
            if ($fullTextAnnotation) {
                $features[] = [
                    'type' => 'DOCUMENT_TEXT_DETECTION',
                    'maxResults' => 100
                ];
            }
            
            // Prepare request data
            $data = [
                'requests' => [
                    [
                        'image' => [
                            'content' => $imageBase64
                        ],
                        'features' => $features
                    ]
                ]
            ];
            
            // Set up headers
            $this->curl->addHeader('Authorization', 'Bearer ' . $googleAccessToken);
            $this->curl->addHeader('Content-Type', 'application/json');
            
            // Send request to Google Cloud Vision API
            $this->curl->post(self::GOOGLE_VISION_API_ENDPOINT, $this->jsonHelper->jsonEncode($data));
            
            // Get response
            $response = $this->curl->getBody();
            $statusCode = $this->curl->getStatus();
            
            $responseData = $this->jsonHelper->jsonDecode($response, true);
            
            if ($statusCode >= 400 || isset($responseData['error'])) {
                $errorMessage = isset($responseData['error']) 
                    ? $responseData['error']['message'] 
                    : "HTTP Error: $statusCode";
                throw new LocalizedException(
                    __('Google Vision API Error: %1', $errorMessage)
                );
            }
            
            // Process and format response for text extraction
            $result = [
                'text' => '',
                'language' => '',
                'blocks' => [],
                'is_handwritten' => false
            ];
            
            if (isset($responseData['responses']) && !empty($responseData['responses'])) {
                $response = $responseData['responses'][0];
                
                // Basic text from TEXT_DETECTION
                if (isset($response['textAnnotations']) && !empty($response['textAnnotations'])) {
                    $result['text'] = $response['textAnnotations'][0]['description'] ?? '';
                    $result['language'] = $response['textAnnotations'][0]['locale'] ?? '';
                }
                
                // Detailed text from DOCUMENT_TEXT_DETECTION
                if (isset($response['fullTextAnnotation'])) {
                    // Override basic text with full text if available
                    $result['text'] = $response['fullTextAnnotation']['text'] ?? $result['text'];
                    
                    // Process text blocks for structured content
                    if (isset($response['fullTextAnnotation']['pages'])) {
                        foreach ($response['fullTextAnnotation']['pages'] as $page) {
                            if (isset($page['blocks'])) {
                                foreach ($page['blocks'] as $block) {
                                    $blockText = '';
                                    $blockType = $block['blockType'] ?? 'TEXT';
                                    
                                    // Check if this block might be handwritten
                                    $handwrittenConfidence = 0;
                                    if (isset($block['property']['detectedBreak']['handwritten'])) {
                                        $handwrittenConfidence = $block['property']['detectedBreak']['handwritten'];
                                        $result['is_handwritten'] = $handwrittenConfidence > 0.5 ? true : $result['is_handwritten'];
                                    }
                                    
                                    // Extract paragraphs from the block
                                    if (isset($block['paragraphs'])) {
                                        foreach ($block['paragraphs'] as $paragraph) {
                                            if (isset($paragraph['words'])) {
                                                foreach ($paragraph['words'] as $word) {
                                                    if (isset($word['symbols'])) {
                                                        foreach ($word['symbols'] as $symbol) {
                                                            $blockText .= $symbol['text'] ?? '';
                                                        }
                                                        $blockText .= ' ';
                                                    }
                                                }
                                            }
                                        }
                                    }
                                    
                                    $result['blocks'][] = [
                                        'text' => trim($blockText),
                                        'type' => $blockType,
                                        'confidence' => $block['confidence'] ?? 0,
                                        'boundingBox' => $block['boundingBox'] ?? []
                                    ];
                                }
                            }
                        }
                    }
                }
            }
            
            return $result;
        } catch (\Exception $e) {
            throw new LocalizedException(
                __('Failed to extract text from image: %1', $e->getMessage())
            );
        }
    }
    
    /**
     * Generate embeddings for text or multiple texts using OpenAI Embeddings API
     *
     * @param string|array $input Single text string or array of texts to generate embeddings for
     * @param string $apiKey OpenAI API key
     * @param string $model The embedding model to use
     * @return array Response with embedding vectors
     * @throws LocalizedException
     */
    public function generateEmbeddings(
        $input,
        string $apiKey,
        string $model = 'text-embedding-ada-002'
    ): array {
        try {
            // Validate input
            if (is_array($input) && empty($input)) {
                throw new LocalizedException(
                    __('No texts provided for embedding generation')
                );
            }
            
            // Prepare request data
            $data = [
                'model' => $model,
                'input' => $input
            ];
            
            // Set up headers
            $this->curl->addHeader('Authorization', 'Bearer ' . $apiKey);
            $this->curl->addHeader('Content-Type', 'application/json');
            
            // Send request to OpenAI Embeddings API
            $this->curl->post(self::EMBEDDINGS_API_ENDPOINT, $this->jsonHelper->jsonEncode($data));
            
            // Get response
            $response = $this->curl->getBody();
            $statusCode = $this->curl->getStatus();
            
            $responseData = $this->jsonHelper->jsonDecode($response, true);
            
            if ($statusCode >= 400 || isset($responseData['error'])) {
                $errorMessage = isset($responseData['error']) 
                    ? $responseData['error']['message'] 
                    : "HTTP Error: $statusCode";
                throw new LocalizedException(
                    __('OpenAI Embeddings API Error: %1', $errorMessage)
                );
            }
            
            return $responseData;
        } catch (\Exception $e) {
            throw new LocalizedException(
                __('Failed to generate embeddings: %1', $e->getMessage())
            );
        }
    }
} 