# Agento - Magento MCP AI Assistant Agent

A powerful AI assistant for Magento 2 that helps you interact with your store's data using natural language queries.

## Features

### 1. Natural Language to SQL
- Convert natural language questions into SQL queries
- Execute SELECT and DESCRIBE queries safely
- No DDL and DML commands are allowed by default however you can change it 
- Open chat in new window

### 2. Token Usage Analytics
- Real-time token usage tracking
- Cost calculation based on model type
- Detailed statistics for:
  - Current message tokens (prompt, completion, total)
  - Session cumulative tokens
  - Cost breakdown per request
  - Total session cost
- Support for different OpenAI models with respective pricing:
  - GPT-3.5 Turbo
  - GPT-4
  - GPT-4 Turbo
  - GPT-4 32k

### 3. Session Management
- Persistent conversation history
- Session-based context maintenance
- Automatic session cleanup
- Session ID tracking for debugging

### 4. Security Features
- API key configuration
- Query validation
- Safe SQL execution
- Session-based authentication

### 5. Error Handling
- Clear error messages
- Automatic error fixing suggestions
- SQL error analysis
- Table structure inspection

### 6. Architecture
- Decoupled OpenAI service for better maintainability
- Clean separation of concerns
- Extensible design
- Easy to customize and extend

## Installation

1. Install the module using Composer:
```bash
composer require genaker/magento-mcp-ai
```

2. Enable the module:
```bash
bin/magento module:enable Genaker_MagentoMcpAi
```

3. Run setup upgrade:
```bash
bin/magento setup:upgrade
```

4. Clear cache:
```bash
bin/magento cache:clean
```

## Configuration

### 1. API Keys
- Navigate to Stores > Configuration > Genaker > Magento MCP AI
- Enter your OpenAI API key
- Save configuration

### 2. AI Rules
- Configure custom rules for the AI assistant
- Define query generation behavior
- Set response formatting rules

### 3. Documentation
- Add store-specific documentation
- Include table structures
- Document custom attributes

### 4. Database Connection
- Configure custom database connection in `app/etc/env.php`:
```php
'db' => [
    'ai_connection' => [
        'host' => 'your_host',
        'dbname' => 'your_database',
        'username' => 'your_username',
        'password' => 'your_password'
    ]
]
```
 - you can add aditional read only connection or with user has read only prevelegy:
 
## Database Configuration

### Adding a Read-Only MySQL User

To create a read-only MySQL user for Magento, follow these steps:

1. Connect to MySQL as root:
```bash
mysql -u root -p
```

2. Create a new read-only user (replace `username` and `password` with your desired values):
```sql
CREATE USER 'username'@'localhost' IDENTIFIED BY 'password';
```

3. Grant read-only privileges to the Magento database (replace `magento_db` with your database name):
```sql
GRANT SELECT ON magento_db.* TO 'username'@'localhost';
```

4. For remote access (if needed), create the user with host '%':
```sql
CREATE USER 'username'@'%' IDENTIFIED BY 'password';
GRANT SELECT ON magento_db.* TO 'username'@'%';
```

5. Flush privileges to apply changes:
```sql
FLUSH PRIVILEGES;
```

6. Verify the user's privileges:
```sql
SHOW GRANTS FOR 'username'@'localhost';
```

### Using the Read-Only User in env.php

Add the read-only user credentials to your `app/etc/env.php` file:

```php
'db' => [
    'connection' => [
        'default' => [
            'host' => 'localhost',
            'dbname' => 'magento_db',
            'username' => 'readonly_user',
            'password' => 'your_password',
            'model' => 'mysql4',
            'engine' => 'innodb',
            'initStatements' => 'SET NAMES utf8;',
            'active' => '1'
        ],
        'ai_connection' => [
            'host' => 'localhost',
            'dbname' => 'magento_db',
            'username' => 'readonly_user',
            'password' => 'your_password',
            'model' => 'mysql4',
            'engine' => 'innodb',
            'initStatements' => 'SET NAMES utf8;',
            'active' => '1'
        ]
    ]
]
```

### Security Considerations

1. Always use strong passwords
2. Restrict access to specific IP addresses if possible
3. Regularly audit user privileges
4. Consider using SSL for remote connections
5. Monitor database access logs

## Usage

### 1. Accessing the Assistant
- Navigate to System > AI Assistant > MCP AI Assistant
- Or use the direct URL: `/admin/magentomcpai/chat/index`

### 2. Making Queries
- Type your question in natural language
- The assistant will convert it to SQL
- View results in the table below
- Export results to CSV if needed

### 3. Managing Conversations
- Use the "Clear" button to start a new conversation
- Export chat history using "Save Chat"
- Open chat in new window for better visibility

### 4. Monitoring Token Usage
- View real-time token statistics in the expandable panel
- Track costs for each interaction
- Monitor cumulative session usage
- See breakdown by:
  - Prompt tokens and cost
  - Completion tokens and cost
  - Total tokens and cost
- Costs automatically calculated based on selected model

### 5. Error Handling
- If a query fails, click "Fix in Chat"
- The assistant will analyze the error
- It may suggest checking table structures
- Follow the suggested fixes

## Architecture

### 1. OpenAI Service
The module now uses a dedicated OpenAI service class (`OpenAiService`) that:
- Handles all API communication
- Manages request formatting
- Processes responses
- Handles error cases
- Provides consistent response format

Benefits:
- Better separation of concerns
- Easier to test and maintain
- More flexible for customization
- Cleaner code organization
- Simplified error handling

### 2. Token Usage Tracking
The token tracking system:
- Monitors API usage in real-time
- Calculates costs based on current model
- Maintains session statistics
- Provides detailed usage breakdown
- Supports all OpenAI model pricing tiers

## Fine-Tuning the LLM

### 1. System Message Customization
The AI assistant uses a customizable system message to define its behavior. You can modify this in the admin configuration:

```text
You are a SQL query generator for Magento 2 database. Your role is to assist with database queries while maintaining security. Rules:

1. Generate only SELECT or DESCRIBE queries
2. Validate and explain each generated query
3. Start responses with SQL in triple backticks: ```sql SELECT * FROM table; ```
4. Reject any non-SELECT/DESCRIBE queries
5. Maintain conversation context for better assistance
6. Provide clear explanations of query results
```

### 2. Custom Rules Configuration
Add your own rules in the admin configuration:
1. Navigate to Stores > Configuration > Genaker > Magento MCP AI
2. In the "AI Rules" field, add your custom rules
3. Each rule should be on a new line
4. Rules will override the default system message

Example custom rules:
```text
- Always include table aliases in queries
- Explain the purpose of each JOIN
- Provide alternative query suggestions
- Include performance considerations
```

### 3. Documentation Context
Add store-specific documentation to improve query accuracy:
1. Navigate to Stores > Configuration > Genaker > Magento MCP AI
2. In the "Documentation" field, add your store's specific information
3. Include:
   - Table structures and relationships
   - Custom attributes and their usage
   - Business logic and rules
   - Common query patterns

Example documentation:
```text
Table: sales_order
- Contains order information
- Key fields: entity_id, increment_id, customer_id
- Related tables: sales_order_item, sales_order_address

Custom Attributes:
- product_custom_type: string, values: 'simple', 'configurable', 'bundle'
- order_priority: integer, values: 1-5
```

### 4. Fine-Tuning Best Practices

#### a. Rule Structure
- Be specific and clear in your rules
- Use consistent formatting
- Include examples where helpful
- Prioritize security rules

#### b. Documentation Format
- Use clear headings for each section
- Include field types and constraints
- Document relationships between tables
- Add examples of common queries

#### c. Context Management
- Keep documentation up to date
- Review and update rules regularly
- Monitor query performance
- Adjust based on user feedback

#### d. Testing and Validation
- Test new rules thoroughly
- Validate query results
- Check performance impact
- Monitor error rates

## Troubleshooting

### 1. API Key Problems
- Verify API key in configuration
- Check API key permissions
- Ensure proper formatting

### 2. Query Errors
- Use the "Fix in Chat" feature
- Check table structures
- Verify column names
- Review SQL syntax

### 3. Performance Issues
- Clear conversation history
- Check database connection
- Monitor API usage in the 
- Optimize queries

## Support

For support, please contact:
- Email: egorshitikov@gmail.com

## License

This module is licensed under the [MIT License](LICENSE).

## Contributing

1. Fork the repository
2. Create your feature branch
3. Commit your changes
4. Push to the branch
5. Create a Pull Request

## Advanced Media Processing Features

### 1. Image Recognition (Google Cloud Vision)
- Recognize objects, labels, and text in images
- Multiple detection types in a single request:
  - LABEL_DETECTION: Identify objects, locations, activities
  - TEXT_DETECTION: Extract text from images
  - OBJECT_LOCALIZATION: Find and locate objects with bounding boxes
  - DOCUMENT_TEXT_DETECTION: OCR optimized for dense text
- AI analysis of recognized content
- Comprehensive response formatting with confidence scores
- Support for handwritten text detection

#### Usage Examples:
```php
// Basic image recognition
$imageData = $openAiService->recognizeImage(
    '/path/to/image.jpg',
    $googleAccessToken
);

// Extract text from documents (OCR)
$textData = $openAiService->extractTextFromImage(
    '/path/to/document.jpg',
    $googleAccessToken
);

// AI analysis of image content
$analysis = $openAiService->recognizeImageWithAiAnalysis(
    '/path/to/image.jpg',
    $googleAccessToken,
    $openAiApiKey,
    'Describe what you see in this image. Labels detected: {{LABELS}}. Text found: {{TEXT}}.'
);
```

### 2. Speech-to-Text Processing (Google Cloud Speech)
- Convert audio files to text transcripts
- Multiple audio format support (LINEAR16, MP3, etc.)
- Language detection and multi-language support
- Automatic punctuation insertion
- Confidence scoring for transcription accuracy
- AI analysis of transcribed content

#### Usage Examples:
```php
// Basic speech-to-text conversion
$transcript = $openAiService->speechToText(
    '/path/to/audio.mp3',
    $googleAccessToken,
    'en-US',
    'MP3',
    44100
);

// Speech transcription with AI analysis
$analysis = $openAiService->speechToTextWithAiResponse(
    '/path/to/audio.mp3',
    $googleAccessToken,
    $openAiApiKey,
    "Please summarize this transcription: ",
    'gpt-3.5-turbo',
    'en-US'
);
```

### 3. File Processing (OpenAI Files API)
- Upload files to OpenAI for analysis and reference
- Support for multiple file formats (PDF, TXT, DOCX, etc.)
- Batch upload for multiple files
- File-based question answering
- Multi-file analysis and comparison
- Comprehensive error handling with detailed messages
- Assistants API integration for complex file operations

#### Usage Examples:
```php
// Upload a single file
$fileData = $openAiService->uploadFile(
    '/path/to/document.pdf',
    'assistants',
    $openAiApiKey
);

// Upload multiple files in batch
$batchResults = $openAiService->batchUploadFiles(
    ['/path/to/doc1.pdf', '/path/to/doc2.pdf'],
    'assistants',
    $openAiApiKey
);

// Ask questions about a file
$answer = $openAiService->getFileAnswers(
    'What is the main topic discussed in this document?',
    $fileData['id'],
    $openAiApiKey
);

// Compare information across multiple files
$comparison = $openAiService->getFileAnswers(
    'What are the key differences between these documents?',
    [$fileId1, $fileId2, $fileId3],
    $openAiApiKey
);
```

### 4. Text Embeddings (OpenAI Embeddings API)
- Generate vector embeddings from text for semantic similarity comparison
- Supports state-of-the-art embedding models (text-embedding-ada-002)
- Used for semantic search, content recommendations, and similarity matching
- Handles both single text inputs and batch processing in a unified way

#### Basic cURL Example:
```bash
curl https://api.openai.com/v1/embeddings \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_API_KEY" \
  -d '{
    "input": "I love cats",
    "model": "text-embedding-ada-002"
  }'
```

#### Batch Processing Example:
```bash
curl https://api.openai.com/v1/embeddings \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_API_KEY" \
  -d '{
    "input": ["I love cats", "I love dogs", "Pets are great"],
    "model": "text-embedding-ada-002"
  }'
```

The embeddings API returns vector representations of text that can be used to measure 
semantic similarity between pieces of text. This is useful for:
- Building semantic search systems
- Classifying content by similarity
- Recommending products based on descriptions
- Clustering similar content

### 5. Image Generation (DALL-E)
- Create custom product images, promotional materials, and marketing content
- Support for both DALL-E 2 and DALL-E 3 models
- Adjustable image sizes, quality, and style
- Options for vivid or natural aesthetics
- Perfect for:
  - Creating product imagery without professional photography
  - Generating promotional banners and social media content
  - Visualizing custom product configurations
  - Creating lifestyle images showing products in use

#### Basic cURL Example:
```bash
curl https://api.openai.com/v1/images/generations \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_API_KEY" \
  -d '{
    "prompt": "Promotional image of a high-security gray TL-30 burglary safe on orange background with price $4,250",
    "n": 1,
    "size": "1024x1024"
  }'
```

The image generation API creates vivid, detailed images based on your text prompts, opening up new possibilities for e-commerce visual content creation without the need for expensive photography or design resources.

### 6. Integration Capabilities
- Seamless workflow between different media types
- Extract text from images and analyze with AI
- Transcribe audio and generate intelligent responses
- Combine file analysis with image recognition
- Multi-modal processing pipeline support

#### Cross-Modal Examples:
```php
// Extract text from image and use it with file reference
$textData = $openAiService->extractTextFromImage('/path/to/image.jpg', $googleAccessToken);
$messages = [
    ['role' => 'system', 'content' => 'Compare the text from this image with the uploaded document.'],
    ['role' => 'user', 'content' => 'Image text: ' . $textData['text']]
];
$comparison = $openAiService->sendFileReferenceChatRequest($messages, $fileId, 'gpt-4', $openAiApiKey);

// Convert speech to text and then analyze with AI
$transcript = $openAiService->speechToText('/path/to/audio.mp3', $googleAccessToken);
$messages = [
    ['role' => 'system', 'content' => 'You are an expert content analyzer.'],
    ['role' => 'user', 'content' => 'Analyze this transcript: ' . $transcript['transcript']]
];
$analysis = $openAiService->sendChatRequest($messages, 'gpt-3.5-turbo', $openAiApiKey);
```

### 7. Cache-Augmented Generation (CAG)
- Combines the power of LLMs with cached results for efficiency and accuracy
- Reduces API usage costs by reusing previous responses for similar queries
- Improves response speed for common or repeated questions
- Perfect for:
  - Product information retrieval scenarios
  - Customer service FAQ responses
  - Category description generation
  - Order status explanations

#### Implementation Areas:
- **Customer Service Chatbots**: Cache common product questions and support responses
- **Product Description Generation**: Store and reuse stylistically similar descriptions
- **Search Query Understanding**: Cache interpretations of similar search queries
- **Content Recommendations**: Store previously generated recommendations for similar user profiles

#### Benefits in Magento:
- Significantly reduced API costs for repetitive operations
- Lower latency for customer-facing AI features
- Consistent responses for similar queries
- Ability to handle higher request volumes without throttling
- Fine-tuned control over when to generate new content vs. use cached responses

Cache-Augmented Generation works by:
1. Checking if a similar query exists in the cache
2. Retrieving and adapting cached responses when appropriate
3. Only calling the AI API for truly novel requests
4. Gradually building a knowledge repository specific to your store

This approach is especially valuable in e-commerce where many customer questions follow predictable patterns and where response speed directly impacts conversion rates.

### 8. Retrieval-Augmented Generation (RAG)
- Enhances AI responses with real-time data retrieved from your Magento database and documents
- Combines the reasoning capabilities of LLMs with factual, store-specific information
- Ensures AI responses reflect your current product catalog, inventory, and policies
- Reduces hallucinations and increases accuracy of AI-generated content

#### Implementation Areas:
- **Product Information Assistants**: Provide accurate, up-to-date product details by retrieving data from your catalog
- **Order Support**: Pull real customer order data to answer shipping, return, and order status questions
- **Policy Compliance**: Ground responses in your actual store policies by retrieving relevant documentation
- **Inventory-Aware Recommendations**: Suggest alternatives based on current stock levels

#### How RAG Works in Magento:
1. Customer query is analyzed to identify information needs
2. Relevant data is retrieved from your Magento database or document store
3. Retrieved information is fed to the LLM as context alongside the query
4. AI generates a response that incorporates the retrieved facts
5. Result is an answer that's both helpful and factually accurate about your specific store

#### Benefits Over Standard LLM Responses:
- **Up-to-date information**: Responses reflect your current catalog and pricing
- **Store-specific accuracy**: AI answers reference your actual policies and procedures
- **Reduced hallucinations**: Minimizes AI tendency to generate plausible but incorrect information
- **Custom knowledge**: Incorporates your unique product knowledge and business rules
- **Better customer experience**: More precise and trustworthy assistance

RAG is particularly valuable for Magento stores with:
- Large or frequently changing product catalogs
- Complex product specifications
- Custom policies or shipping rules
- Need for personalized customer service at scale

### 9. Function Calling
- Enables AI to call specific Magento functions based on user requests
- Bridges natural language requests with structured API calls
- Perfect for enabling AI assistants to take actions within your store
- Provides structured data extraction from natural language queries

#### How Function Calling Works:
1. Define functions that represent actions in your Magento store
2. The AI analyzes user requests to determine which function to call
3. AI extracts parameters from natural language and formats them correctly
4. Your application receives structured function calls rather than raw text
5. Execute the function with the provided parameters and return results

#### Implementation Areas:
- **Product Search**: Convert natural language to structured search parameters
- **Order Operations**: Process return requests, order lookups, or shipping changes
- **Customer Account Management**: Update preferences, addresses, or subscriptions
- **Inventory Queries**: Check stock levels or availability based on conversational requests

#### Example Function Call:
```bash
curl https://api.openai.com/v1/chat/completions \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_API_KEY" \
  -d '{
    "model": "gpt-4",
    "messages": [
      {
        "role": "user",
        "content": "Find me a gun safe under $2000"
      }
    ],
    "functions": [
      {
        "name": "search_safes",
        "description": "Search safes by type and price",
        "parameters": {
          "type": "object",
          "properties": {
            "type": {
              "type": "string",
              "description": "The type of safe (gun, jewelry, document)"
            },
            "max_price": {
              "type": "number",
              "description": "Maximum price in USD"
            }
          },
          "required": ["type", "max_price"]
        }
      }
    ],
    "function_call": "auto"
  }'
```

AI Response (converts natural language to structured function call):
```json
{
  "function_call": {
    "name": "search_safes",
    "arguments": "{\"type\":\"gun\",\"max_price\":2000}"
  }
}
```

Function calling bridges the gap between conversational AI and your Magento backend systems, enabling rich, action-oriented customer experiences while maintaining control over business logic and data access.

### 10. Audio Transcription (Whisper)
- Convert spoken content into text with high accuracy
- Support for multiple output formats (JSON, text, SRT, VTT)
- Language detection and multi-language support
- Perfect for:
  - Transcribing customer service calls
  - Converting video tutorials into text content
  - Creating accessible content for product videos
  - Processing voice notes from sales teams

#### Basic cURL Example:
```bash
curl https://api.openai.com/v1/audio/transcriptions \
  -H "Authorization: Bearer YOUR_API_KEY" \
  -H "Content-Type: multipart/form-data" \
  -F file=@audio.mp3 \
  -F model=whisper-1
```

The Audio Transcription API uses OpenAI's Whisper model to accurately convert spoken audio to text, supporting multiple languages and various output formats for different use cases.

### 11. Text-to-Speech (TTS)
- Generate natural-sounding speech from text
- Multiple voice options (alloy, echo, fable, onyx, nova, shimmer)
- Support for various audio formats (MP3, Opus, AAC, FLAC)
- Adjustable speech speed for different use cases
- Perfect for:
  - Creating audio product descriptions
  - Adding voice prompts to your store interface
  - Building voice notifications for order status
  - Making content accessible to visually impaired customers

#### Basic cURL Example:
```bash
curl https://api.openai.com/v1/audio/speech \
  -H "Authorization: Bearer YOUR_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "model": "tts-1",
    "voice": "alloy",
    "input": "Your Magento order has been confirmed and will be shipped within 24 hours. Thank you for shopping with us!"
  }'
```

The Text-to-Speech API transforms written text into natural-sounding speech, enabling you to create audio content dynamically from your Magento store's data.

### 12. Configuration

#### Google Cloud API Setup
1. Create a Google Cloud project
2. Enable Vision API and Speech-to-Text API
3. Create service account credentials
4. Generate access token using:
```php
// Example code to get Google access token
$accessToken = $googleAuthService->getAccessToken($serviceAccountKeyFile);
```

#### OpenAI Files API Setup
1. Configure OpenAI API key in admin panel
2. Set appropriate file size limits
3. Configure allowed file types:
```
pdf,txt,csv,json,docx,xlsx
```

### 13. Security Considerations
- All media files are processed with strict validation
- No permanent storage of sensitive data
- Temporary file handling with secure cleanup
- Rate limiting for API requests
- Access control based on admin permissions
- Audit logging for all media processing operations

## Sending Magento Data to AI

### Overview

The "Send Magento Data to AI" configuration allows you to control whether Magento data is sent to the AI for processing. This setting is useful for managing data privacy and ensuring that only necessary data is shared with the AI.

### Configuration

- **Location:** The configuration can be found in the Magento admin panel under `Stores > Configuration > Genaker > Magento MCP AI > General Configuration`.
- **Default Value:** Disabled (set to `No`).
- **How to Enable:** To enable sending data to AI, set the "Send Magento Data to AI" option to `Yes`.

### Usage

When enabled, this configuration allows the AI to receive and process Magento data, which can be used to generate SQL queries or provide insights based on the data. The AI can assist with tasks such as:

- Generating SQL queries for data analysis.
- Providing recommendations based on historical data.
- Enhancing decision-making processes with AI-driven insights.

### Data Handling

- **Security:** Ensure that sensitive data is handled securely and that only necessary data is shared with the AI.
- **Data Storage:** Currently, the system does not store SQL data. Future updates may include options for storing or managing this data.

### Important Notes

- Always review the data being sent to ensure compliance with data privacy regulations.
- Consider the implications of sharing data with external services and ensure that appropriate security measures are in place.
