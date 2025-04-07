# Magento MCP AI Assistant

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
