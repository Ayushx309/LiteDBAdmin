<?php
require_once './config.php';
session_start();


if(!isset($_SESSION['dev_authenticated'])){
    header('Location: ../');
    exit();
}


if (isset($_GET['action'])) {
    $action = $_GET['action'];
    
    switch ($action) {
        case 'get_table_structure':
            getTableStructure();
            break;
        
        case 'get_table_data':
            getTableData();
            break;
            
        case 'get_query_suggestions':
            getQuerySuggestions();
            break;
            
        case 'change_database':
            changeDatabase();
            break;
            
        case 'get_database_schema':
            getDatabaseSchema();
            break;
            
        case 'execute_query':
            executeQuery();
            break;
            
        case 'nl_to_sql':
            naturalLanguageToSQL();
            break;
            
        default:
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Invalid action']);
            break;
    }
}

/**
 * Get the structure of a specific table
 */
function getTableStructure() {
    global $conn;
    
    if (!isset($_GET['table'])) {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Table name is required']);
        return;
    }
    
    $table = mysqli_real_escape_string($conn, $_GET['table']);
    $db = isset($_GET['db']) ? mysqli_real_escape_string($conn, $_GET['db']) : '';
    

    if (!empty($db)) {
        mysqli_select_db($conn, $db);
    }
    
    try {
    
        $query = "DESCRIBE `$table`";
        $result = mysqli_query($conn, $query);
        
        if (!$result) {
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Error fetching table structure: ' . mysqli_error($conn)]);
            return;
        }
        
        $columns = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $columns[] = $row;
        }
        
      
        $indexQuery = "SHOW INDEX FROM `$table`";
        $indexResult = mysqli_query($conn, $indexQuery);
        
        $indexes = [];
        if ($indexResult) {
            while ($row = mysqli_fetch_assoc($indexResult)) {
                $indexes[] = $row;
            }
        }
        
        header('Content-Type: application/json');
        echo json_encode([
            'table' => $table,
            'columns' => $columns,
            'indexes' => $indexes
        ]);
    } catch (Exception $e) {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Error: ' . $e->getMessage()]);
    }
}

/**
 * Get data from a specific table with pagination
 */
function getTableData() {
    global $conn;
    
    if (!isset($_GET['table'])) {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Table name is required']);
        return;
    }
    
    $table = mysqli_real_escape_string($conn, $_GET['table']);
    $db = isset($_GET['db']) ? mysqli_real_escape_string($conn, $_GET['db']) : '';
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $perPage = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 50;
    

    if ($page < 1) $page = 1;
    if ($perPage < 10) $perPage = 10;
    if ($perPage > 1000) $perPage = 1000;
    
    $offset = ($page - 1) * $perPage;
    

    if (!empty($db)) {
        mysqli_select_db($conn, $db);
    }
    
    try {

        $countQuery = "SELECT COUNT(*) as total FROM `$table`";
        $countResult = mysqli_query($conn, $countQuery);
        $totalRows = 0;
        
        if ($countResult && $row = mysqli_fetch_assoc($countResult)) {
            $totalRows = $row['total'];
        }
        

        $query = "SELECT * FROM `$table` LIMIT $offset, $perPage";
        $result = mysqli_query($conn, $query);
        
        if (!$result) {
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Error fetching table data: ' . mysqli_error($conn)]);
            return;
        }
        
        $data = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $data[] = $row;
        }
        
        header('Content-Type: application/json');
        echo json_encode([
            'table' => $table,
            'data' => $data,
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total_rows' => $totalRows,
                'total_pages' => ceil($totalRows / $perPage)
            ]
        ]);
    } catch (Exception $e) {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Error: ' . $e->getMessage()]);
    }
}

/**
 * Get SQL query suggestions based on context
 */
function getQuerySuggestions() {
    global $conn;
    
    $partial = isset($_GET['partial']) ? $_GET['partial'] : '';
    $context = isset($_GET['context']) ? $_GET['context'] : '';
    $db = isset($_GET['db']) ? mysqli_real_escape_string($conn, $_GET['db']) : '';
    

    if (!empty($db)) {
        mysqli_select_db($conn, $db);
    }
    
    $suggestions = [];
    

    $keywords = ['SELECT', 'FROM', 'WHERE', 'GROUP BY', 'ORDER BY', 'HAVING', 'LIMIT', 
                 'JOIN', 'INNER JOIN', 'LEFT JOIN', 'RIGHT JOIN', 'OUTER JOIN', 'INSERT INTO', 
                 'VALUES', 'UPDATE', 'SET', 'DELETE FROM', 'CREATE TABLE', 'ALTER TABLE', 
                 'DROP TABLE', 'CREATE INDEX', 'DROP INDEX', 'UNION', 'UNION ALL'];
    

    if (!empty($partial)) {
        $partial = strtoupper($partial);
        $keywords = array_filter($keywords, function($keyword) use ($partial) {
            return strpos($keyword, $partial) === 0;
        });
    }
    

    foreach ($keywords as $keyword) {
        $suggestions[] = [
            'text' => $keyword,
            'type' => 'keyword'
        ];
    }
    
    try {
        $query = "SHOW TABLES";
        $result = mysqli_query($conn, $query);
        
        if ($result) {
            while ($row = mysqli_fetch_array($result)) {
                $tableName = $row[0];
                if (empty($partial) || stripos($tableName, $partial) === 0) {
                    $suggestions[] = [
                        'text' => $tableName,
                        'type' => 'table'
                    ];
                }
            }
        }
    } catch (Exception $e) {
        // Silent catch - just don't add tables to suggestions
    }
    
    header('Content-Type: application/json');
    echo json_encode(['suggestions' => $suggestions]);
}

/**
 * Change the current database
 */
function changeDatabase() {
    global $conn;
    
    if (!isset($_GET['db'])) {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Database name is required']);
        return;
    }
    
    $db = mysqli_real_escape_string($conn, $_GET['db']);
    
    try {

        $result = mysqli_select_db($conn, $db);
        
        if (!$result) {
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Error changing database: ' . mysqli_error($conn)]);
            return;
        }
        

        $tablesQuery = "SHOW TABLES";
        $tablesResult = mysqli_query($conn, $tablesQuery);
        
        $tables = [];
        if ($tablesResult) {
            while ($row = mysqli_fetch_array($tablesResult)) {
                $tables[] = $row[0];
            }
        }
        
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'database' => $db,
            'tables' => $tables
        ]);
    } catch (Exception $e) {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Error: ' . $e->getMessage()]);
    }
}

/**
 * Get database schema including tables and relationships for visualization
 */
function getDatabaseSchema() {
    global $conn;
    
    $database = $_GET['db'] ?? '';
    
    if (empty($database)) {
        echo json_encode(['error' => 'Database not specified']);
        exit;
    }
    
    try {
        $conn->select_db($database);
        
 
        $tables = [];
        $tablesResult = $conn->query("SHOW TABLES");
        
        if ($tablesResult) {
            while ($row = $tablesResult->fetch_row()) {
                $tables[] = ['name' => $row[0]];
            }
            $tablesResult->free();
        }
        
 
        foreach ($tables as &$table) {
            $columns = [];
            $columnsResult = $conn->query("DESCRIBE `{$table['name']}`");
            
            if ($columnsResult) {
                while ($row = $columnsResult->fetch_assoc()) {
                    $columns[] = $row;
                }
                $columnsResult->free();
            }
            
            $table['columns'] = $columns;
        }
        
        // Get relationships using INFORMATION_SCHEMA
        $relationships = [];
        $relationshipsQuery = "
            SELECT 
                TABLE_NAME AS table_name,
                COLUMN_NAME AS column_name,
                REFERENCED_TABLE_NAME AS referenced_table_name,
                REFERENCED_COLUMN_NAME AS referenced_column_name
            FROM
                INFORMATION_SCHEMA.KEY_COLUMN_USAGE
            WHERE
                REFERENCED_TABLE_SCHEMA = '$database'
                AND REFERENCED_TABLE_NAME IS NOT NULL
                AND REFERENCED_COLUMN_NAME IS NOT NULL
        ";
        
        $relationshipsResult = $conn->query($relationshipsQuery);
        
        if ($relationshipsResult) {
            while ($row = $relationshipsResult->fetch_assoc()) {
                $relationships[] = [
                    'table' => $row['table_name'],
                    'column' => $row['column_name'],
                    'referenced_table' => $row['referenced_table_name'],
                    'referenced_column' => $row['referenced_column_name']
                ];
            }
            $relationshipsResult->free();
        }
        
    
        if (empty($relationships)) {
           
            foreach ($tables as $table) {
                if (empty($table['columns'])) continue;
                
                foreach ($table['columns'] as $column) {
                
                    $columnName = $column['Field'];
                    
            
                    if (preg_match('/(.*?)_id$/', $columnName, $matches)) {
                        $potentialTableName = $matches[1];
                        
          
                        $tableExists = false;
                        $referencedTable = '';
                        $referencedColumn = '';
                        

                        foreach ($tables as $t) {
                            if ($t['name'] === $potentialTableName) {
                                $tableExists = true;
                                $referencedTable = $t['name'];
                                break;
                            }
                        }
                        
 
                        if (!$tableExists) {
                            foreach ($tables as $t) {
                                if ($t['name'] === $potentialTableName . 's') {
                                    $tableExists = true;
                                    $referencedTable = $t['name'];
                                    break;
                                }
                            }
                        }
                        

                        if (!$tableExists && substr($potentialTableName, -1) === 's') {
                            $singularName = substr($potentialTableName, 0, -1);
                            foreach ($tables as $t) {
                                if ($t['name'] === $singularName) {
                                    $tableExists = true;
                                    $referencedTable = $t['name'];
                                    break;
                                }
                            }
                        }
                        
                        if ($tableExists) {
                    
                            foreach ($tables as $t) {
                                if ($t['name'] === $referencedTable) {
                                    foreach ($t['columns'] as $col) {
                                        if (isset($col['Key']) && $col['Key'] === 'PRI') {
                                            $referencedColumn = $col['Field'];
                                            break;
                                        }
                                    }
                                    
                                 
                                    if (empty($referencedColumn)) {
                                        $referencedColumn = 'id';
                                    }
                                    
                                    break;
                                }
                            }
                            
                            if (!empty($referencedColumn)) {
                                $relationships[] = [
                                    'table' => $table['name'],
                                    'column' => $columnName,
                                    'referenced_table' => $referencedTable,
                                    'referenced_column' => $referencedColumn,
                                    'inferred' => true
                                ];
                            }
                        }
                    }
                }
            }
        }
        

        $debugInfo = [
            'tableCount' => count($tables),
            'relationshipCount' => count($relationships),
            'relatedTableNames' => array_unique(array_merge(
                array_column($relationships, 'table'),
                array_column($relationships, 'referenced_table')
            ))
        ];
        
        echo json_encode([
            'tables' => $tables,
            'relationships' => $relationships,
            'debug' => $debugInfo
        ]);
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
}

/**
 * Execute a custom SQL query
 */
function executeQuery() {
    global $conn;
    
    header('Content-Type: application/json');
    

    if (!isset($_POST['query']) || empty($_POST['query'])) {
        echo json_encode(['error' => 'No query provided']);
        return;
    }
    
    $query = $_POST['query'];
    $database = isset($_POST['database']) ? $_POST['database'] : '';
    

    if (!empty($database)) {
        if (!mysqli_select_db($conn, $database)) {
            echo json_encode(['error' => 'Error selecting database: ' . mysqli_error($conn)]);
            return;
        }
    }
    
    try {

        $startTime = microtime(true);
        

        $result = mysqli_query($conn, $query);
        
   
        $endTime = microtime(true);
        $executionTime = round(($endTime - $startTime) * 1000, 2); // in milliseconds
        
        if ($result === false) {
            echo json_encode([
                'error' => 'Query error: ' . mysqli_error($conn),
                'query' => $query
            ]);
            return;
        }
        

        if ($result instanceof mysqli_result) {
      
            $data = [];
            $fields = [];
            
   
            $fieldInfo = mysqli_fetch_fields($result);
            foreach ($fieldInfo as $field) {
                $fields[] = $field->name;
            }
            
       
            while ($row = mysqli_fetch_assoc($result)) {
                $data[] = $row;
            }
            
            mysqli_free_result($result);
            
            echo json_encode([
                'success' => true,
                'isSelect' => true,
                'fields' => $fields,
                'data' => $data,
                'rowCount' => count($data),
                'executionTime' => $executionTime
            ]);
        } else {
          
            echo json_encode([
                'success' => true,
                'isSelect' => false,
                'affectedRows' => mysqli_affected_rows($conn),
                'insertId' => mysqli_insert_id($conn),
                'executionTime' => $executionTime
            ]);
        }
    } catch (Exception $e) {
        echo json_encode([
            'error' => 'Error: ' . $e->getMessage(),
            'query' => $query
        ]);
    }
}

/**
 * Convert natural language to SQL query
 * This function takes a natural language description and converts it to a SQL query
 */
function naturalLanguageToSQL() {
    global $conn;
    
    header('Content-Type: application/json');
    

    if (!isset($_POST['nl_query']) || empty($_POST['nl_query'])) {
        echo json_encode(['error' => 'No natural language query provided']);
        return;
    }
    
    $nlQuery = $_POST['nl_query'];
    $database = isset($_POST['database']) ? $_POST['database'] : '';
    

    $useAI = isset($_POST['use_ai']) && $_POST['use_ai'] === 'true';
    $apiKey = isset($_POST['api_key']) ? $_POST['api_key'] : '';
    $apiModel = isset($_POST['api_model']) ? $_POST['api_model'] : 'gpt-3.5-turbo';
    $apiProvider = isset($_POST['api_provider']) ? $_POST['api_provider'] : 'openai';
    

    if ($useAI && empty($apiKey) && $apiProvider !== 'deepseek-free') {
        echo json_encode(['error' => 'API key is required when using AI']);
        return;
    }
    
 
    if (!empty($database)) {
        if (!mysqli_select_db($conn, $database)) {
            echo json_encode(['error' => 'Error selecting database: ' . mysqli_error($conn)]);
            return;
        }
    }
    
    try {

        $tables = [];
        $tableColumns = [];
        
    
        $tablesResult = mysqli_query($conn, "SHOW TABLES");
        if ($tablesResult) {
            while ($tableRow = mysqli_fetch_row($tablesResult)) {
                $tableName = $tableRow[0];
                $tables[] = $tableName;
                
             
                $columnsResult = mysqli_query($conn, "SHOW COLUMNS FROM `$tableName`");
                if ($columnsResult) {
                    $tableColumns[$tableName] = [];
                    while ($columnRow = mysqli_fetch_assoc($columnsResult)) {
                        $tableColumns[$tableName][] = $columnRow;
                    }
                }
            }
        }
        

        $schemaString = generateSchemaString($tables, $tableColumns);
        

        if ($useAI) {
       
            $result = generateSQLWithAI($nlQuery, $schemaString, $tables, $tableColumns, $apiKey, $apiModel, $apiProvider);
            $sqlQuery = $result['sql_query'];
            $explanation = $result['explanation'];
        } else {

            $sqlQuery = generateSQLFromNaturalLanguage($nlQuery, $tables, $tableColumns);
            $explanation = explainQueryGeneration($nlQuery, $sqlQuery);
        }
        
        echo json_encode([
            'success' => true,
            'natural_language_query' => $nlQuery,
            'sql_query' => $sqlQuery,
            'tables' => $tables,
            'explanation' => $explanation,
            'ai_used' => $useAI
        ]);
    } catch (Exception $e) {
        echo json_encode([
            'error' => 'Error: ' . $e->getMessage(),
            'natural_language_query' => $nlQuery
        ]);
    }
}

/**
 * Convert database schema to a string representation for AI context
 */
function generateSchemaString($tables, $tableColumns) {
    $schemaString = "Database Schema:\n\n";
    
    foreach ($tables as $table) {
        $schemaString .= "Table: {$table}\n";
        $schemaString .= "Columns:\n";
        
        if (!empty($tableColumns[$table])) {
            foreach ($tableColumns[$table] as $column) {
                $isPrimary = isset($column['Key']) && $column['Key'] === 'PRI' ? ' (Primary Key)' : '';
                $isNull = $column['Null'] === 'YES' ? ' NULL' : ' NOT NULL';
                $default = isset($column['Default']) && $column['Default'] !== null ? " Default: {$column['Default']}" : '';
                
                $schemaString .= "- {$column['Field']} ({$column['Type']}){$isPrimary}{$isNull}{$default}\n";
            }
        }
        
        $schemaString .= "\n";
    }
    
    return $schemaString;
}

/**
 * Generate SQL using an AI API
 */
function generateSQLWithAI($nlQuery, $schemaString, $tables, $tableColumns, $apiKey, $apiModel, $apiProvider) {

    
    // System message for the AI
    $systemMessage = "You are an elite-level SQL architect and database optimization specialist with expertise in enterprise-scale database systems, query planning, and performance tuning. ";
    $systemMessage .= "Your task is to convert natural language descriptions into sophisticated, highly optimized SQL queries that follow industry best practices. ";
    $systemMessage .= "Below is the database schema you should use to generate the SQL query:\n\n";
    $systemMessage .= $schemaString;
    
    $systemMessage .= "\n\n## ADVANCED QUERY GENERATION DIRECTIVES ##";
    
    $systemMessage .= "\n\n### Schema Analysis & Optimization ###";
    $systemMessage .= "\n- Analyze relationships between tables to determine optimal join strategies";
    $systemMessage .= "\n- Consider available indexes to optimize query execution paths";
    $systemMessage .= "\n- Identify and leverage primary and foreign key relationships";
    $systemMessage .= "\n- Use table aliases to improve query readability and prevent ambiguity";
    
    $systemMessage .= "\n\n### Query Construction Excellence ###";
    $systemMessage .= "\n- Choose between subqueries, CTEs (WITH clause), or JOINs based on performance characteristics";
    $systemMessage .= "\n- Implement appropriate query hints when beneficial for performance";
    $systemMessage .= "\n- Use EXISTS/NOT EXISTS, IN/NOT IN, or joins appropriately based on data distribution patterns";
    $systemMessage .= "\n- Consider UNION vs. UNION ALL based on need for duplicate elimination";
    $systemMessage .= "\n- Optimize aggregation with GROUP BY clauses using the minimal necessary columns";
    $systemMessage .= "\n- Implement window functions (ROW_NUMBER, RANK, etc.) for sophisticated data analysis";
    $systemMessage .= "\n- Apply advanced filtering techniques like dynamic pivoting or hierarchical queries when appropriate";
    
    $systemMessage .= "\n\n### Data Integrity & Security ###";
    $systemMessage .= "\n- Prevent SQL injection by properly parametrizing user inputs";
    $systemMessage .= "\n- Include appropriate transaction isolation levels for multi-statement operations";
    $systemMessage .= "\n- Implement appropriate error handling and graceful fallbacks";
    $systemMessage .= "\n- Consider ACID properties when designing transaction-based queries";
    
    $systemMessage .= "\n\n### Performance Tuning ###";
    $systemMessage .= "\n- Minimize full table scans and optimize for index usage";
    $systemMessage .= "\n- Avoid unnecessary columns in SELECT statements";
    $systemMessage .= "\n- Consider data volume when choosing between aggregation methods";
    $systemMessage .= "\n- Use appropriate LIMIT/OFFSET for pagination with optimized execution";
    $systemMessage .= "\n- Anticipate and mitigate potential bottlenecks in complex joins or aggregations";
    $systemMessage .= "\n- Consider query cache implications with appropriate cache hints if supported";
    
    $systemMessage .= "\n\n### Error Correction & Enhancement ###";
    $systemMessage .= "\n- Identify and fix potential logical errors in the user's request";
    $systemMessage .= "\n- Detect ambiguities in natural language and resolve with the most likely intent";
    $systemMessage .= "\n- Provide alternative query approaches when multiple valid interpretations exist";
    $systemMessage .= "\n- When encountering incomplete requests, make reasonable assumptions and document them";
    $systemMessage .= "\n- Include thorough commenting of complex logic or assumptions made";
    
    $systemMessage .= "\n\n### Response Format ###";
    $systemMessage .= "\nYou MUST format your response as a JSON object with EXACTLY these two properties:";
    $systemMessage .= "\n1. 'sql_query': A string containing ONLY the complete SQL query with detailed inline comments";
    $systemMessage .= "\n2. 'explanation': A string containing a comprehensive explanation";
    
    $systemMessage .= "\n\nIMPORTANT: Your response MUST be valid JSON. Do not include any text outside the JSON object.";
    $systemMessage .= "\nFormat example:";
    $systemMessage .= "\n```json";
    $systemMessage .= "\n{";
    $systemMessage .= "\n  \"sql_query\": \"SELECT * FROM users WHERE status = 'active' LIMIT 10;\",";
    $systemMessage .= "\n  \"explanation\": \"This query retrieves active users with a limit of 10 records.\"";
    $systemMessage .= "\n}";
    $systemMessage .= "\n```";
    
    $systemMessage .= "\n\nPlease note that in the actual response, you should NOT include the ```json and ``` markers - your response should ONLY be the JSON object itself.";
    $systemMessage .= "\n\nEnsure the SQL follows MySQL best practices and optimization techniques. Your goal is to create production-ready, maintainable, and highly efficient queries.";
    
    // Create the user message with additional context extraction
    $userMessage = "Generate an optimal MySQL query for the following request. If my request contains any errors or ambiguities, please correct them: {$nlQuery}";
    

    $apiResult = null;
    try {
        switch ($apiProvider) {
            case 'openai':
                $apiResult = callOpenAI($systemMessage, $userMessage, $apiKey, $apiModel, $tables);
                break;
            
            case 'claude':
                $apiResult = callClaude($systemMessage, $userMessage, $apiKey, $apiModel, $tables);
                break;
                
            case 'gemini':
                $apiResult = callGemini($systemMessage, $userMessage, $apiKey, $apiModel, $tables);
                break;
                
            case 'mistral':
                $apiResult = callMistral($systemMessage, $userMessage, $apiKey, $apiModel, $tables);
                break;
                
            case 'deepseek':
                $apiResult = callDeepSeek($systemMessage, $userMessage, $apiKey, $apiModel, $tables);
                break;
                
            case 'deepseek-free':
                $apiResult = callDeepSeekFree($systemMessage, $userMessage, $tables);
                break;
                
            case 'llama':
                $apiResult = callLlama($systemMessage, $userMessage, $apiKey, $apiModel, $tables);
                break;
                
            default:
          
                $apiResult = callOpenAI($systemMessage, $userMessage, $apiKey, $apiModel, $tables);
                break;
        }
        
      
        if (is_array($apiResult) && !isset($apiResult['provider'])) {
            $apiResult['provider'] = $apiProvider;
        }
        
        return $apiResult;
        
    } catch (Exception $e) {
   
        return [
            'sql_query' => "-- Error from {$apiProvider} API\n-- " . $e->getMessage() . "\nSELECT * FROM " . ($tables[0] ?? 'unknown_table') . " LIMIT 10;",
            'explanation' => "Error while generating SQL with {$apiProvider}: " . $e->getMessage() . ". Using a safe fallback query.",
            'provider' => $apiProvider,
            'error' => true
        ];
    }
}

/**
 * Call OpenAI API to generate SQL
 */
function callOpenAI($systemMessage, $userMessage, $apiKey, $apiModel, $tables) {

    $data = [
        'model' => $apiModel,
        'messages' => [
            ['role' => 'system', 'content' => $systemMessage],
            ['role' => 'user', 'content' => $userMessage]
        ],
        'temperature' => 0.3,
        'max_tokens' => 1500,
        'response_format' => ['type' => 'json_object'] 
    ];
    

    $ch = curl_init('https://api.openai.com/v1/chat/completions');
    

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiKey
    ]);
    
    // SECURITY NOTE: SSL verification is disabled to handle environments with missing or outdated certificates.
    // In production, consider enabling proper SSL verification and installing required certificates.
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    

    $response = curl_exec($ch);
    

    if (curl_errno($ch)) {
        $error = curl_error($ch);
        curl_close($ch);
        throw new Exception('cURL error: ' . $error);
    }
    

    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    

    if ($httpCode >= 400) {
        throw new Exception('HTTP error ' . $httpCode . ': OpenAI API request failed. Response: ' . substr($response, 0, 200));
    }
    

    $responseData = json_decode($response, true);
    
   
    if (isset($responseData['error'])) {
        throw new Exception('OpenAI API error: ' . ($responseData['error']['message'] ?? json_encode($responseData['error'])));
    }

    if (isset($responseData['choices'][0]['message']['content'])) {
        $content = $responseData['choices'][0]['message']['content'];
        return parseAIResponse($content, $tables);
    } else {
        throw new Exception('Invalid response format from OpenAI API: ' . json_encode($responseData));
    }
}

/**
 * Call Claude API to generate SQL
 */
function callClaude($systemMessage, $userMessage, $apiKey, $apiModel, $tables) {

    $data = [
        'model' => $apiModel,
        'system' => $systemMessage,
        'messages' => [
            ['role' => 'user', 'content' => $userMessage]
        ],
        'temperature' => 0.3,
        'max_tokens' => 1500,
        'anthropic_version' => '2023-06-01'
    ];
    
  
    $ch = curl_init('https://api.anthropic.com/v1/messages');
    
    // Set cURL options
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'x-api-key: ' . $apiKey,
        'anthropic-version: 2023-06-01'
    ]);
    
    // SECURITY NOTE: SSL verification is disabled to handle environments with missing or outdated certificates.
    // In production, consider enabling proper SSL verification and installing required certificates.
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    
   
    $response = curl_exec($ch);
    
    
    if (curl_errno($ch)) {
        $error = curl_error($ch);
        curl_close($ch);
        throw new Exception('cURL error: ' . $error);
    }
    
    
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
  
    if ($httpCode >= 400) {
        throw new Exception('HTTP error ' . $httpCode . ': Claude API request failed. Response: ' . substr($response, 0, 200));
    }
    
 
    $responseData = json_decode($response, true);
    
  
    if (isset($responseData['error'])) {
        throw new Exception('Claude API error: ' . ($responseData['error']['message'] ?? json_encode($responseData['error'])));
    }
    
  
    $content = '';
    if (isset($responseData['content']) && is_array($responseData['content']) && !empty($responseData['content'])) {
        foreach ($responseData['content'] as $part) {
            if (isset($part['text'])) {
                $content .= $part['text'];
            }
        }
        
       
        if (preg_match('/\{\s*"sql_query"\s*:.*"explanation"\s*:.*\}/s', $content, $matches)) {
            $content = $matches[0];
        }
        
        return parseAIResponse($content, $tables);
    } else {
        
        throw new Exception('Invalid response format from Claude API: ' . json_encode($responseData));
    }
}

/**
 * Call Google Gemini API to generate SQL
 */
function callGemini($systemMessage, $userMessage, $apiKey, $apiModel, $tables) {
 
    $prompt = $systemMessage . "\n\n" . $userMessage;
    

    $isGemini2 = (strpos($apiModel, 'gemini-2') === 0);
    

    if ($isGemini2) {
        // Gemini 2.0 format with system instruction
        $data = [
            'contents' => [
                [
                    'role' => 'user',
                    'parts' => [
                        ['text' => $userMessage]
                    ]
                ]
            ],
            'systemInstruction' => [
                'parts' => [
                    ['text' => $systemMessage]
                ]
            ],
            'generationConfig' => [
                'temperature' => 0.3,
                'maxOutputTokens' => 1000
            ]
        ];
    } else {
        // Original Gemini format (gemini-pro, gemini-ultra, etc.)
        $data = [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $prompt]
                    ]
                ]
            ],
            'generationConfig' => [
                'temperature' => 0.3,
                'maxOutputTokens' => 1000
            ]
        ];
    }
    
    
    $ch = curl_init("https://generativelanguage.googleapis.com/v1beta/models/{$apiModel}:generateContent?key={$apiKey}");
    
 
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json'
    ]);
    
    // SECURITY NOTE: SSL verification is disabled to handle environments with missing or outdated certificates.
    // In production, consider enabling proper SSL verification and installing required certificates.
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    
    
    $response = curl_exec($ch);
    
 
    if (curl_errno($ch)) {
        $error = curl_error($ch);
        curl_close($ch);
        throw new Exception('cURL error: ' . $error);
    }
    
 
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
 
    if ($httpCode >= 400) {
        throw new Exception('HTTP error ' . $httpCode . ': Gemini API request failed. Response: ' . substr($response, 0, 200));
    }
    
 
    $responseData = json_decode($response, true);
    
  
    if (isset($responseData['error'])) {
        throw new Exception('Gemini API error: ' . ($responseData['error']['message'] ?? json_encode($responseData['error'])));
    }
    
    
    $content = '';
    

    if (isset($responseData['candidates'][0]['content']['parts'][0]['text'])) {
        $content = $responseData['candidates'][0]['content']['parts'][0]['text'];
    } else {

        $content = json_encode($responseData);
    }
    
    return parseAIResponse($content, $tables);
}

/**
 * Call DeepSeek API with API key to generate SQL
 */
function callDeepSeek($systemMessage, $userMessage, $apiKey, $apiModel, $tables) {

    $data = [
        'model' => $apiModel,
        'messages' => [
            ['role' => 'system', 'content' => $systemMessage],
            ['role' => 'user', 'content' => $userMessage]
        ],
        'temperature' => 0.3,
        'max_tokens' => 1000
    ];
    

    $ch = curl_init('https://api.deepseek.com/v1/chat/completions');
    

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiKey
    ]);
    

    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    

    $response = curl_exec($ch);
    

    if (curl_errno($ch)) {
        $error = curl_error($ch);
        curl_close($ch);
        throw new Exception('cURL error: ' . $error);
    }
    
  
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
  
    if ($httpCode >= 400) {
        throw new Exception('HTTP error ' . $httpCode . ': DeepSeek API request failed. Response: ' . substr($response, 0, 200));
    }
    
 
    $responseData = json_decode($response, true);
    
 
    if (isset($responseData['error'])) {
        throw new Exception('DeepSeek API error: ' . ($responseData['error']['message'] ?? json_encode($responseData['error'])));
    }
    
 
    $content = '';
    
    if (isset($responseData['choices'][0]['message']['content'])) {
        $content = $responseData['choices'][0]['message']['content'];
    } else {

        $content = json_encode($responseData);
    }
    
    return parseAIResponse($content, $tables);
}

/**
 * Call Mistral API to generate SQL
 */
function callMistral($systemMessage, $userMessage, $apiKey, $apiModel, $tables) {

    $data = [
        'model' => $apiModel,
        'messages' => [
            ['role' => 'system', 'content' => $systemMessage],
            ['role' => 'user', 'content' => $userMessage]
        ],
        'temperature' => 0.3,
        'max_tokens' => 1000
    ];
    

    $ch = curl_init('https://api.mistral.ai/v1/chat/completions');
    
 
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiKey
    ]);
    
    // SECURITY NOTE: SSL verification is disabled to handle environments with missing or outdated certificates.
    // In production, consider enabling proper SSL verification and installing required certificates.
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    

    $response = curl_exec($ch);
    
   
    if (curl_errno($ch)) {
        $error = curl_error($ch);
        curl_close($ch);
        throw new Exception('cURL error: ' . $error);
    }
    

    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    

    if ($httpCode >= 400) {
        throw new Exception('HTTP error ' . $httpCode . ': Mistral API request failed. Response: ' . substr($response, 0, 200));
    }
    

    $responseData = json_decode($response, true);
    

    if (isset($responseData['error'])) {
        throw new Exception('Mistral API error: ' . ($responseData['error']['message'] ?? json_encode($responseData['error'])));
    }
    

    $content = '';
    
    if (isset($responseData['choices'][0]['message']['content'])) {
        $content = $responseData['choices'][0]['message']['content'];
    } else {

        $content = json_encode($responseData);
    }
    
    return parseAIResponse($content, $tables);
}

/**
 * Call Llama API to generate SQL
 */
function callLlama($systemMessage, $userMessage, $apiKey, $apiModel, $tables) {

    $data = [
        'model' => $apiModel,
        'messages' => [
            ['role' => 'system', 'content' => $systemMessage],
            ['role' => 'user', 'content' => $userMessage]
        ],
        'temperature' => 0.3,
        'max_tokens' => 1000
    ];

    $ch = curl_init('https://api.llama-api.com/v1/chat/completions');
    

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiKey
    ]);
    
    // SECURITY NOTE: SSL verification is disabled to handle environments with missing or outdated certificates.
    // In production, consider enabling proper SSL verification and installing required certificates.
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    
    // Execute cURL request
    $response = curl_exec($ch);
    

    if (curl_errno($ch)) {
        $error = curl_error($ch);
        curl_close($ch);
        throw new Exception('cURL error: ' . $error);
    }
    

    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    

    if ($httpCode >= 400) {
        throw new Exception('HTTP error ' . $httpCode . ': Llama API request failed. Response: ' . substr($response, 0, 200));
    }
    
 
    $responseData = json_decode($response, true);
    

    if (isset($responseData['error'])) {
        throw new Exception('Llama API error: ' . ($responseData['error']['message'] ?? json_encode($responseData['error'])));
    }
    

    $content = '';
    
    if (isset($responseData['choices'][0]['message']['content'])) {
        $content = $responseData['choices'][0]['message']['content'];
    } else {

        $content = json_encode($responseData);
    }
    
    return parseAIResponse($content, $tables);
}

/**
 * Parse AI response to extract SQL query and explanation
 */
function parseAIResponse($content, $tables) {
  
    if (empty($content)) {
        return [
            'sql_query' => "SELECT * FROM " . ($tables[0] ?? 'unknown_table') . " LIMIT 10; -- AI response was empty",
            'explanation' => "The AI didn't provide a valid response. Using a default query instead."
        ];
    }
    
   
    try {
       
        $content = trim($content);
        
        
        $content = preg_replace('/^```(?:json)?|```$/m', '', $content);
        
      
        $parsedResponse = json_decode($content, true);
        
        if (json_last_error() === JSON_ERROR_NONE && isset($parsedResponse['sql_query'])) {
           
            return [
                'sql_query' => $parsedResponse['sql_query'],
                'explanation' => $parsedResponse['explanation'] ?? "The AI generated this SQL query based on your request."
            ];
        }
        
    
        if (preg_match('/\{[\s\S]*?\}/m', $content, $jsonMatches)) {
            $jsonContent = $jsonMatches[0];
            $parsedJson = json_decode($jsonContent, true);
            
            if (json_last_error() === JSON_ERROR_NONE && isset($parsedJson['sql_query'])) {
                return [
                    'sql_query' => $parsedJson['sql_query'],
                    'explanation' => $parsedJson['explanation'] ?? "The AI generated this SQL query based on your request."
                ];
            }
        }
        
       
        if (preg_match('/```(?:sql)?\s*([\s\S]*?)```/m', $content, $sqlMatches)) {
            $sql = trim($sqlMatches[1]);
            
            // Try to find explanation
            if (preg_match('/(?:explanation|here\'s why|reasoning)(?:\s*:|)\s*([\s\S]*?)(?=```|$)/mi', $content, $explMatches)) {
                $explanation = trim($explMatches[1]);
            } else {
                $explanation = "The AI generated a SQL query based on your request.";
            }
            
            return [
                'sql_query' => $sql,
                'explanation' => $explanation
            ];
        }
        
        
        if (preg_match('/(SELECT|INSERT\s+INTO|UPDATE|DELETE\s+FROM|CREATE\s+TABLE|ALTER\s+TABLE|DROP\s+TABLE|CREATE\s+INDEX)\s+[\s\S]*?;/mi', $content, $sqlMatches)) {
            $sql = trim($sqlMatches[0]);
            
           
            $contentParts = explode($sql, $content, 2);
            $explanation = !empty($contentParts[1]) ? trim($contentParts[1]) : "Extracted SQL query from the AI response.";
            
            return [
                'sql_query' => $sql,
                'explanation' => $explanation
            ];
        }
        
       
        if (preg_match('/[A-Za-z_][A-Za-z0-9_\s\.\,\(\)\=\'\"\`\*\+\-\/\<\>\!\%]*;/m', $content, $sqlMatches)) {
            $sql = trim($sqlMatches[0]);
            
        
            if (preg_match('/(select|from|where|order by|group by|having|limit|join|insert|update|delete|create|alter|drop)/i', $sql)) {
                return [
                    'sql_query' => $sql,
                    'explanation' => "Extracted SQL query from the AI response text."
                ];
            }
        }
        
     
        return [
            'sql_query' => "SELECT * FROM " . ($tables[0] ?? 'unknown_table') . " LIMIT 10; -- Could not extract SQL from AI response",
            'explanation' => "The AI response did not contain a valid SQL query. Using a default query instead.\nOriginal response: " . substr($content, 0, 100) . "..."
        ];
    } catch (Exception $e) {
       
        return [
            'sql_query' => "SELECT * FROM " . ($tables[0] ?? 'unknown_table') . " LIMIT 10; -- Error parsing AI response",
            'explanation' => "Error parsing the AI response: " . $e->getMessage() . ". Using a default query instead."
        ];
    }
}

/**
 * Generate SQL from natural language query
 * This is a pattern matching approach for common query patterns
 */
function generateSQLFromNaturalLanguage($nlQuery, $tables, $tableColumns) {
 
    $nlQuery = trim(strtolower($nlQuery));
    

    $queryType = 'SELECT';
    $selectColumns = '*';
    $whereClause = '';
    $orderByClause = '';
    $limitClause = '';
    $joinClause = '';
    $groupByClause = '';
    $havingClause = '';
    
  
    $mainTable = findMainTable($nlQuery, $tables);
    $fromClause = "`$mainTable`";
    

    if (preg_match('/\b(show|list|get|display|select|find|search|query|fetch|retrieve)\b/', $nlQuery)) {
        $queryType = 'SELECT';
        
      
        if (preg_match('/\b(count|how many)\b/', $nlQuery)) {
            $selectColumns = "COUNT(*)";
            
            if (preg_match('/\bdistinct\b/', $nlQuery)) {
         
                foreach ($tableColumns[$mainTable] as $colInfo) {
                    $colName = $colInfo['Field'];
                    if (stripos($nlQuery, $colName) !== false) {
                        $selectColumns = "COUNT(DISTINCT `$colName`)";
                        break;
                    }
                }
            }
        } else {
           
            $columnsToSelect = [];
            if (isset($tableColumns[$mainTable])) {
                foreach ($tableColumns[$mainTable] as $colInfo) {
                    $colName = $colInfo['Field'];
                 
                    if (($colName === 'id' || preg_match('/_id$/', $colName)) && 
                        !preg_match('/\bid\b|\bidentifier\b/', $nlQuery)) {
                        continue;
                    }
                    
                    if (stripos($nlQuery, $colName) !== false) {
                        $columnsToSelect[] = "`$colName`";
                    }
                }
            }
            
            if (!empty($columnsToSelect)) {
                $selectColumns = implode(', ', $columnsToSelect);
            }
        }
        

        $whereConditions = [];
        
       
        $comparisonPatterns = [
            '/\bwhere\s+(\w+)\s+equals?\s+(["\']?[\w\s]+["\']?)/' => '`$1` = \'$2\'',
            '/\bwhere\s+(\w+)\s+is\s+(["\']?[\w\s]+["\']?)/' => '`$1` = \'$2\'',
            '/\bwhere\s+(\w+)\s+greater than\s+(["\']?[\w\s]+["\']?)/' => '`$1` > \'$2\'',
            '/\bwhere\s+(\w+)\s+less than\s+(["\']?[\w\s]+["\']?)/' => '`$1` < \'$2\'',
            '/\bwhere\s+(\w+)\s+contains\s+(["\']?[\w\s]+["\']?)/' => '`$1` LIKE \'%$2%\'',
            '/\bwhere\s+(\w+)\s+starts with\s+(["\']?[\w\s]+["\']?)/' => '`$1` LIKE \'$2%\'',
            '/\bwhere\s+(\w+)\s+ends with\s+(["\']?[\w\s]+["\']?)/' => '`$1` LIKE \'%$2\'',
        ];
        
        foreach ($comparisonPatterns as $pattern => $replacement) {
            if (preg_match($pattern, $nlQuery, $matches)) {
                $condition = preg_replace($pattern, $replacement, $matches[0]);
                $whereConditions[] = $condition;
            }
        }
        

        if (preg_match('/\b(\w+)\s+(greater than|more than|higher than|>)\s+(\d+)/', $nlQuery, $matches)) {
            $whereConditions[] = "`{$matches[1]}` > {$matches[3]}";
        }
        
        if (preg_match('/\b(\w+)\s+(less than|lower than|<)\s+(\d+)/', $nlQuery, $matches)) {
            $whereConditions[] = "`{$matches[1]}` < {$matches[3]}";
        }
        

        if (preg_match('/\b(from|between)\s+(\d{4}-\d{2}-\d{2})\s+(to|and)\s+(\d{4}-\d{2}-\d{2})/', $nlQuery, $matches)) {
         
            $dateColumn = null;
            foreach ($tableColumns[$mainTable] as $colInfo) {
                if (strpos(strtolower($colInfo['Type']), 'date') !== false) {
                    $dateColumn = $colInfo['Field'];
                    break;
                }
            }
            
            if ($dateColumn) {
                $whereConditions[] = "`$dateColumn` BETWEEN '{$matches[2]}' AND '{$matches[4]}'";
            }
        }
        

        if (!empty($whereConditions)) {
            $whereClause = "WHERE " . implode(' AND ', $whereConditions);
        }
        
    
        if (preg_match('/\b(order|sort)\s+by\s+(\w+)\s+(asc|ascending|desc|descending)/', $nlQuery, $matches)) {
            $direction = (strpos($matches[3], 'desc') === 0) ? 'DESC' : 'ASC';
            $orderByClause = "ORDER BY `{$matches[2]}` $direction";
        }

        if (preg_match('/\blimit\s+(\d+)/', $nlQuery, $matches)) {
            $limitClause = "LIMIT {$matches[1]}";
        } else if (preg_match('/\b(top|first)\s+(\d+)/', $nlQuery, $matches)) {
            $limitClause = "LIMIT {$matches[2]}";
        }
        
        if (preg_match('/\bgroup\s+by\s+(\w+)/', $nlQuery, $matches)) {
            $groupByClause = "GROUP BY `{$matches[1]}`";
        }
    }
 
    else if (preg_match('/\b(insert|add|create|new)\b/', $nlQuery)) {
        $queryType = 'INSERT';
       
        return "INSERT INTO `$mainTable` (...) VALUES (...)";
    }
   
    else if (preg_match('/\b(update|modify|change|edit)\b/', $nlQuery)) {
        $queryType = 'UPDATE';
       
        return "UPDATE `$mainTable` SET ... WHERE ...";
    }

    else if (preg_match('/\b(delete|remove|drop)\b/', $nlQuery)) {
        $queryType = 'DELETE';
       
        return "DELETE FROM `$mainTable` WHERE ...";
    }
    

    $sqlQuery = "$queryType $selectColumns FROM $fromClause";
    
    if (!empty($joinClause)) {
        $sqlQuery .= " $joinClause";
    }
    
    if (!empty($whereClause)) {
        $sqlQuery .= " $whereClause";
    }
    
    if (!empty($groupByClause)) {
        $sqlQuery .= " $groupByClause";
    }
    
    if (!empty($havingClause)) {
        $sqlQuery .= " $havingClause";
    }
    
    if (!empty($orderByClause)) {
        $sqlQuery .= " $orderByClause";
    }
    
    if (!empty($limitClause)) {
        $sqlQuery .= " $limitClause";
    }
    
    $sqlQuery .= ";";
    
    return $sqlQuery;
}

/**
 * Determine which table is most likely the main one from the natural language query
 */
function findMainTable($nlQuery, $tables) {
    foreach ($tables as $table) {
       
        $singular = rtrim($table, 's');
        
        if (stripos($nlQuery, $table) !== false || 
            (strlen($singular) > 3 && stripos($nlQuery, $singular) !== false)) {
            return $table;
        }
    }
   
    return $tables[0] ?? 'unknown_table';
}

/**
 * Generate an explanation of how the SQL query was derived from natural language
 */
function explainQueryGeneration($nlQuery, $sqlQuery) {
    return "Converted your question \"$nlQuery\" into a SQL query by identifying the main table, 
            relevant columns, and conditions. The generated query retrieves the data you're looking for.";
}

/**
 * Call DeepSeek free models without API key
 */
function callDeepSeekFree($systemMessage, $userMessage, $tables) {

    $data = [
        'model' => 'deepseek-coder-v2',  
        'messages' => [
            ['role' => 'system', 'content' => $systemMessage],
            ['role' => 'user', 'content' => $userMessage]
        ],
        'temperature' => 0.3,
        'max_tokens' => 1000
    ];
    
  
    $ch = curl_init('https://api.deepseek.com/v1/chat/completions');

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json'
    ]);
    
    // SECURITY NOTE: SSL verification is disabled to handle environments with missing or outdated certificates.
    // In production, consider enabling proper SSL verification and installing required certificates.
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    
  
    $response = curl_exec($ch);
    
 
    if (curl_errno($ch)) {
        $error = curl_error($ch);
        curl_close($ch);
        
    
        $fallbackResponse = [
            'sql_query' => "SELECT * FROM " . ($tables[0] ?? 'unknown_table') . " LIMIT 10; -- Connection error with DeepSeek API",
            'explanation' => "Could not connect to DeepSeek free service: " . $error . "\nUsing a default query instead."
        ];
        
        return $fallbackResponse;
    }
    
 
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
  
    if ($httpCode >= 400) {
        $fallbackResponse = [
            'sql_query' => "SELECT * FROM " . ($tables[0] ?? 'unknown_table') . " LIMIT 10; -- HTTP error with DeepSeek API",
            'explanation' => "HTTP error " . $httpCode . " when calling DeepSeek free service. Using a default query instead."
        ];
        
        return $fallbackResponse;
    }
    

    $responseData = json_decode($response, true);
    

    if (isset($responseData['choices'][0]['message']['content'])) {
        $content = $responseData['choices'][0]['message']['content'];
        return parseAIResponse($content, $tables);
    } else {
        // Return a more helpful fallback response
        $fallbackResponse = [
            'sql_query' => "SELECT * FROM " . ($tables[0] ?? 'unknown_table') . " LIMIT 10; -- Invalid response from DeepSeek API",
            'explanation' => "Received an invalid response format from DeepSeek free service. Using a default query instead."
        ];
        
        return $fallbackResponse;
    }
} 