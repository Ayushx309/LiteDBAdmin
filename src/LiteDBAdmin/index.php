<?php
require_once './config.php';

session_start();


if(!isset($_SESSION['dev_authenticated'])){
    header('Location: ../');
    exit();
}


$query = isset($_POST['query']) ? $_POST['query'] : '';
$result = null;
$error = null;
$tables = [];
$databases = [];
$currentDb = isset($_GET['db']) ? $_GET['db'] : $database;

if ($currentDb !== $database) {
    try {
        if (!mysqli_select_db($conn, $currentDb)) {
            $error = "Error selecting database: " . mysqli_error($conn);
            $currentDb = $database;
        }
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
        $currentDb = $database;
    }
}

try {
    $dbQuery = mysqli_query($conn, "SHOW DATABASES");
    while ($row = mysqli_fetch_array($dbQuery)) {
        $databases[] = $row[0];
    }
} catch (Exception $e) {
    $error = "Error fetching databases: " . $e->getMessage();
}

try {
    $tableQuery = mysqli_query($conn, "SHOW TABLES FROM `$currentDb`");
    while ($row = mysqli_fetch_array($tableQuery)) {
        $tables[] = $row[0];
    }
} catch (Exception $e) {
    $error = "Error fetching tables: " . $e->getMessage();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($query)) {
    try {
        $start_time = microtime(true);
        $result = mysqli_query($conn, $query);
        $execution_time = microtime(true) - $start_time;
        
        if (!$result) {
            $error = "Error executing query: " . mysqli_error($conn);
        }
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en" class="<?php echo (isset($_COOKIE['sqlmanager_dark_mode']) && $_COOKIE['sqlmanager_dark_mode'] === 'true') ? 'dark' : ''; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LiteDBAdmin</title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {}
            }
        }
    </script>
    
    <link rel="stylesheet" data-name="vs/editor/editor.main" href="https://cdnjs.cloudflare.com/ajax/libs/monaco-editor/0.44.0/min/vs/editor/editor.main.min.css">
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        #editor-container {
            height: 200px;
            border: 1px solid #e2e8f0;
            border-radius: 0.375rem;
        }
        .dark #editor-container {
            border-color: #4b5563;
        }
        .monaco-editor {
            border-radius: 0.375rem;
            overflow: hidden;
        }
        .table-container {
            overflow-x: auto;
            max-width: 100%;
        }
        .dark-scrollbar::-webkit-scrollbar {
            height: 8px;
            width: 8px;
        }
        .dark-scrollbar::-webkit-scrollbar-track {
            background: #f1f1f1;
        }
        .dark-scrollbar::-webkit-scrollbar-thumb {
            background: #888;
            border-radius: 4px;
        }
        .dark-scrollbar::-webkit-scrollbar-thumb:hover {
            background: #555;
        }
        .dark .dark-scrollbar::-webkit-scrollbar-track {
            background: #374151;
        }
        .dark .dark-scrollbar::-webkit-scrollbar-thumb {
            background: #6b7280;
        }
        .dark .dark-scrollbar::-webkit-scrollbar-thumb:hover {
            background: #9ca3af;
        }
        
        .dark body { 
            background-color: #111827; 
            color: #f3f4f6; 
        }
        .dark .bg-white { 
            background-color: #1f2937; 
        }
        .dark .bg-gray-50 { 
            background-color: #111827; 
        }
        .dark .bg-gray-100 { 
            background-color: #1f2937; 
        }
        .dark .text-gray-700 { 
            color: #f3f4f6; 
        }
        .dark .text-gray-900 { 
            color: #f9fafb; 
        }
        .dark .text-gray-500 { 
            color: #9ca3af; 
        }
        .dark .text-gray-600 { 
            color: #d1d5db; 
        }
        .dark .border-gray-200 { 
            border-color: #374151; 
        }
        .dark .border-gray-300 { 
            border-color: #4b5563; 
        }
        .dark .divide-gray-200 > * + * { 
            border-color: #374151; 
        }
        .dark .hover\:bg-gray-100:hover { 
            background-color: #374151; 
        }
        .dark .hover\:bg-gray-50:hover { 
            background-color: #2d3748; 
        }
        .dark th[scope="col"] { 
            color: #9ca3af; 
            background-color: #1f2937; 
        }
        .dark .bg-red-100 { 
            background-color: rgba(248, 113, 113, 0.2); 
            border-color: rgba(248, 113, 113, 0.4); 
            color: #f87171; 
        }
        .dark .bg-green-100 { 
            background-color: rgba(34, 197, 94, 0.2); 
            border-color: rgba(34, 197, 94, 0.4); 
            color: #4ade80; 
        }
        .dark .bg-yellow-100 {
            background-color: rgba(234, 179, 8, 0.2);
            border-color: rgba(234, 179, 8, 0.4);
            color: #facc15;
        }
        .dark .text-yellow-700 {
            color: #facc15;
        }
        .dark tbody.bg-white {
            background-color: #1f2937;
        }
        .dark tr.hover\:bg-gray-50:hover {
            background-color: #374151;
        }
        .dark .text-sm.text-gray-500 {
            color: #9ca3af;
        }
    </style>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/cytoscape/3.2.22/cytoscape.min.js"></script>
    
</head>
<body class="bg-gray-100 min-h-screen dark:bg-gray-900">
    <nav class="bg-gradient-to-r from-blue-800 to-blue-600 text-white shadow-lg">
        <div class="container mx-auto px-4 py-3 flex justify-between items-center">
            <div class="flex items-center space-x-2">
                <i class="fas fa-database text-xl"></i>
                <h1 class="text-2xl font-bold">LiteDBAdmin</h1>
            </div>
            <div class="flex items-center space-x-4">
                <span class="hidden md:inline-block">Connected to: <?php echo htmlspecialchars($currentDb); ?></span>
                <a href="../logout.php" class="px-3 py-1 bg-blue-700 hover:bg-blue-900 rounded-md transition-colors duration-200">
                    <i class="fas fa-arrow-left mr-1"></i> Exit
                </a>
            </div>
        </div>
    </nav>
    
    <div class="container mx-auto px-4 py-6">
        <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
            <div class="lg:col-span-1 bg-white dark:bg-gray-800 rounded-lg shadow-md p-4">
                <div class="mb-4">
                    <h2 class="text-lg font-semibold text-gray-700 dark:text-gray-200 mb-2">Databases</h2>
                    <select id="database-selector" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <?php foreach ($databases as $db): ?>
                        <option value="<?php echo htmlspecialchars($db); ?>" <?php echo ($db === $currentDb) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($db); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div>
                    <h2 class="text-lg font-semibold text-gray-700 dark:text-gray-200 mb-2">Tables</h2>
                    <div class="max-h-64 overflow-y-auto dark-scrollbar pr-2">
                        <ul class="space-y-1">
                            <?php foreach ($tables as $table): ?>
                            <li>
                                <button class="table-btn w-full text-left px-3 py-2 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-md flex items-center" 
                                        data-table="<?php echo htmlspecialchars($table); ?>">
                                    <i class="fas fa-table text-blue-600 mr-2"></i>
                                    <?php echo htmlspecialchars($table); ?>
                                </button>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
                
                <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-600">
                    <h2 class="text-lg font-semibold text-gray-700 dark:text-gray-200 mb-2">Common Queries</h2>
                    <ul class="space-y-1">
                        <li><button class="common-query w-full text-left px-3 py-2 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-md text-blue-600 dark:text-blue-400" data-query="SHOW TABLES">Show Tables</button></li>
                        <li><button class="common-query w-full text-left px-3 py-2 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-md text-blue-600 dark:text-blue-400" data-query="SHOW DATABASES">Show Databases</button></li>
                        <li><button class="common-query w-full text-left px-3 py-2 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-md text-blue-600 dark:text-blue-400" data-query="SELECT * FROM information_schema.columns WHERE table_schema = 'DATABASE_NAME'">Show All Columns</button></li>
                    </ul>
                </div>
                
                <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-600">
                    <h2 class="text-lg font-semibold text-gray-700 dark:text-gray-200 mb-2">Advanced Features</h2>
                    <ul class="space-y-1">
                        <li><button id="show-query-history" class="w-full text-left px-3 py-2 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-md text-blue-600 dark:text-blue-400 flex items-center">
                            <i class="fas fa-history mr-2"></i> Query History
                        </button></li>
                        <li><button id="show-saved-queries" class="w-full text-left px-3 py-2 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-md text-blue-600 dark:text-blue-400 flex items-center">
                            <i class="fas fa-bookmark mr-2"></i> Saved Queries
                        </button></li>
                        <li><button id="schema-visualize" class="w-full text-left px-3 py-2 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-md text-blue-600 dark:text-blue-400 flex items-center">
                            <i class="fas fa-project-diagram mr-2"></i> Visualize Schema
                            <span class="ml-2 px-2 py-0.5 text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300 rounded-full">BETA</span>
                        </button></li>
                        <li><button id="natural-language-sql" class="w-full text-left px-3 py-2 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-md text-blue-600 dark:text-blue-400 flex items-center">
                            <i class="fas fa-magic mr-2"></i> Natural Language to SQL
                            <span class="ml-2 px-2 py-0.5 text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300 rounded-full">BETA</span>
                        </button></li>
                        <li><button id="toggle-theme" class="w-full text-left px-3 py-2 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-md text-blue-600 dark:text-blue-400 flex items-center">
                            <i class="fas fa-moon mr-2"></i> <span id="theme-text">Dark Mode</span>
                        </button></li>
                    </ul>
                </div>
            </div>
            
            <div class="lg:col-span-3 space-y-6">
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md p-4">
                    <div class="flex justify-between items-center mb-4">
                        <h1 class="text-2xl font-semibold text-gray-800 dark:text-white">SQL Query Editor</h1>
                        <div class="text-sm text-gray-600 dark:text-gray-300">
                            <span class="font-medium">Current Database:</span> 
                            <span id="current-db"><?php echo htmlspecialchars($currentDb); ?></span>
                        </div>
                    </div>
                    
                    <form method="POST" id="query-form">
                        <div id="editor-container" class="mb-4"></div>
                        <textarea name="query" id="query-input" class="hidden"><?php echo htmlspecialchars($query); ?></textarea>
                        
                        <div class="flex flex-wrap items-center gap-2 mt-4">
                            <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors duration-200 flex items-center">
                                <i class="fas fa-play mr-2"></i> Execute
                            </button>
                            <button type="button" id="clear-btn" class="px-4 py-2 bg-gray-200 dark:bg-gray-600 text-gray-700 dark:text-gray-200 rounded-md hover:bg-gray-300 dark:hover:bg-gray-500 transition-colors duration-200 flex items-center">
                                <i class="fas fa-eraser mr-2"></i> Clear
                            </button>
                            <button type="button" id="format-btn" class="px-4 py-2 bg-gray-200 dark:bg-gray-600 text-gray-700 dark:text-gray-200 rounded-md hover:bg-gray-300 dark:hover:bg-gray-500 transition-colors duration-200 flex items-center">
                                <i class="fas fa-code mr-2"></i> Format SQL
                            </button>
                            
                            <?php if (isset($execution_time)): ?>
                            <span class="ml-auto text-gray-500 dark:text-gray-400">
                                <i class="fas fa-clock mr-1"></i> <?php echo number_format($execution_time * 1000, 2); ?> ms
                            </span>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
                
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md p-4">
                    <div class="flex flex-wrap items-center justify-between mb-3">
                        <h2 class="text-lg font-semibold text-gray-700 dark:text-gray-200">Results</h2>
                        
                        <?php if ($result && mysqli_num_rows($result) > 0): ?>
                        <div class="flex flex-wrap items-center gap-2 mt-2 sm:mt-0">
                            <div class="relative flex-grow max-w-md">
                                <input type="text" id="results-search" placeholder="Search in results..." 
                                       class="w-full px-3 py-2 pr-10 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <div class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none">
                                    <i class="fas fa-search text-gray-400"></i>
                                </div>
                            </div>
                            <div class="relative">
                                <button id="filter-dropdown-btn" class="px-3 py-2 bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-200 rounded-md hover:bg-gray-300 dark:hover:bg-gray-600 flex items-center gap-1">
                                    <i class="fas fa-filter"></i>
                                    <span>Filters</span>
                                    <i class="fas fa-chevron-down text-xs"></i>
                                </button>
                                <div id="filter-dropdown" class="hidden absolute right-0 mt-2 w-64 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-md shadow-lg z-10">
                                    <div class="p-3 border-b border-gray-200 dark:border-gray-700">
                                        <h3 class="font-medium text-gray-700 dark:text-gray-300">Filter by Column</h3>
                                    </div>
                                    <div class="p-3 max-h-72 overflow-y-auto" id="column-filters">
                                    </div>
                                    <div class="p-3 border-t border-gray-200 dark:border-gray-700 flex justify-between">
                                        <button id="clear-filters" class="text-sm text-blue-600 dark:text-blue-400 hover:underline">Clear all</button>
                                        <button id="apply-filters" class="text-sm bg-blue-600 text-white px-3 py-1 rounded-md hover:bg-blue-700">Apply</button>
                                    </div>
                                </div>
                            </div>
                            <div>
                                <select id="rows-per-page" class="px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    <option value="all">All rows</option>
                                    <option value="10">10 rows</option>
                                    <option value="25">25 rows</option>
                                    <option value="50">50 rows</option>
                                    <option value="100">100 rows</option>
                                </select>
                            </div>
                            <button id="advanced-search-btn" class="px-3 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 flex items-center gap-1">
                                <i class="fas fa-search-plus"></i>
                                <span>Advanced</span>
                            </button>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <?php if ($error): ?>
                    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4 dark:bg-red-900 dark:border-red-700 dark:text-red-300">
                        <i class="fas fa-exclamation-circle mr-2"></i> <?php echo htmlspecialchars($error); ?>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($result && mysqli_num_rows($result) > 0): ?>
                    <div id="results-count" class="text-sm text-gray-500 dark:text-gray-400 my-2">
                        <?php echo mysqli_num_rows($result); ?> rows found
                    </div>
                    <div class="table-container dark-scrollbar">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700" id="results-table">
                            <thead class="bg-gray-50 dark:bg-gray-700">
                                <tr>
                                    <?php 
                                    $fields = mysqli_fetch_fields($result);
                                    $table_name = '';
                                    if (!empty($fields) && isset($fields[0]->table)) {
                                        $table_name = $fields[0]->table;
                                    }
                                    foreach ($fields as $field): 
                                    ?>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider"
                                        data-table="<?php echo htmlspecialchars($field->table); ?>" 
                                        data-field="<?php echo htmlspecialchars($field->name); ?>"
                                        data-type="<?php echo htmlspecialchars($field->type); ?>">
                                        <?php echo htmlspecialchars($field->name); ?>
                                        <?php if ($field->flags & MYSQLI_PRI_KEY_FLAG): ?>
                                        <i class="fas fa-key text-yellow-500 text-xs ml-1" title="Primary Key"></i>
                                        <?php endif; ?>
                                    </th>
                                    <?php endforeach; ?>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                        Actions
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                <?php 
                                mysqli_data_seek($result, 0);
                                $row_index = 0;
                                while ($row = mysqli_fetch_assoc($result)): 
                                ?>
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700 result-row" data-row-index="<?php echo $row_index; ?>">
                                    <?php foreach ($row as $key => $value): ?>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400 editable-cell cursor-pointer hover:bg-blue-50 dark:hover:bg-blue-900" 
                                        data-column="<?php echo htmlspecialchars($key); ?>" 
                                        data-value="<?php echo htmlspecialchars($value !== null ? $value : ''); ?>"
                                        data-is-null="<?php echo $value === null ? '1' : '0'; ?>">
                                        <?php echo htmlspecialchars($value !== null ? $value : 'NULL'); ?>
                                    </td>
                                    <?php endforeach; ?>
                                    <td class="px-2 py-4 whitespace-nowrap text-sm">
                                        <button type="button" class="edit-row-btn text-blue-500 hover:text-blue-700 dark:text-blue-400 dark:hover:text-blue-300 px-2">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button type="button" class="duplicate-row-btn text-green-500 hover:text-green-700 dark:text-green-400 dark:hover:text-green-300 px-2">
                                            <i class="fas fa-copy"></i>
                                        </button>
                                        <button type="button" class="delete-row-btn text-red-500 hover:text-red-700 dark:text-red-400 dark:hover:text-red-300 px-2">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                                <?php 
                                $row_index++;
                                endwhile; 
                                ?>
                            </tbody>
                        </table>
                        
                        <div class="mt-4 text-gray-500 dark:text-gray-400 text-sm flex justify-between items-center">
                            <div>
                                <i class="fas fa-list mr-1"></i> <?php echo mysqli_num_rows($result); ?> rows returned
                                <div class="mt-1 text-xs text-blue-500 dark:text-blue-400">
                                    <i class="fas fa-info-circle mr-1"></i> Click cells to edit values. Double-click action buttons for direct execution.
                                </div>
                            </div>
                            <?php if ($table_name): ?>
                            <button id="add-new-row" class="px-3 py-1 bg-green-600 text-white rounded-md hover:bg-green-700 transition-colors duration-200 text-xs">
                                <i class="fas fa-plus mr-1"></i> Add New Row
                            </button>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php elseif ($result): ?>
                    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                        <i class="fas fa-check-circle mr-2"></i> Query executed successfully. <?php echo mysqli_affected_rows($conn); ?> rows affected.
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div id="advanced-modal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 overflow-y-auto">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl max-w-4xl w-full mx-auto">
                <div class="flex justify-between items-center border-b border-gray-200 dark:border-gray-700 px-6 py-4">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white" id="modal-title">Table Structure</h3>
                    <button type="button" id="close-modal" class="text-gray-400 hover:text-gray-500 dark:text-gray-300 dark:hover:text-gray-200">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="px-6 py-4 max-h-[70vh] overflow-y-auto" id="modal-content">
                    <div class="flex justify-center">
                        <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600"></div>
                    </div>
                </div>
                <div class="px-6 py-4 bg-gray-50 dark:bg-gray-900 border-t border-gray-200 dark:border-gray-700 flex justify-between">
                    <div>
                        <button type="button" id="export-sql" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors duration-200 mr-2">
                            <i class="fas fa-file-export mr-2"></i> Export SQL
                        </button>
                        <button type="button" id="export-csv" class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 transition-colors duration-200">
                            <i class="fas fa-file-csv mr-2"></i> Export CSV
                        </button>
                    </div>
                    <button type="button" id="close-modal-btn" class="px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-200 rounded-md hover:bg-gray-300 dark:hover:bg-gray-600 transition-colors duration-200">
                        Close
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Query History Modal -->
    <div id="history-modal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 overflow-y-auto">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl max-w-4xl w-full mx-auto">
                <div class="flex justify-between items-center border-b border-gray-200 dark:border-gray-700 px-6 py-4">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Query History</h3>
                    <button type="button" class="close-modal text-gray-400 hover:text-gray-500 dark:text-gray-300 dark:hover:text-gray-200">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="px-6 py-4 max-h-[70vh] overflow-y-auto" id="history-content">
                    <div class="flex justify-between mb-4">
                        <div>
                            <button id="clear-history" class="text-red-500 hover:text-red-700 dark:text-red-400 dark:hover:text-red-300 text-sm">
                                <i class="fas fa-trash mr-1"></i> Clear All
                            </button>
                        </div>
                        <div class="text-xs text-gray-500 dark:text-gray-400">Queries are stored in browser storage</div>
                    </div>
                    <div id="query-history-list" class="space-y-3">
                        <div class="text-gray-500 dark:text-gray-400 text-center py-4">No queries in history</div>
                    </div>
                </div>
                <div class="px-6 py-4 bg-gray-50 dark:bg-gray-900 border-t border-gray-200 dark:border-gray-700 flex justify-end">
                    <button type="button" class="close-modal px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-200 rounded-md hover:bg-gray-300 dark:hover:bg-gray-600 transition-colors duration-200">
                        Close
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div id="saved-queries-modal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 overflow-y-auto">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl max-w-4xl w-full mx-auto">
                <div class="flex justify-between items-center border-b border-gray-200 dark:border-gray-700 px-6 py-4">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Saved Queries</h3>
                    <button type="button" class="close-modal text-gray-400 hover:text-gray-500 dark:text-gray-300 dark:hover:text-gray-200">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="px-6 py-4 max-h-[70vh] overflow-y-auto" id="saved-queries-content">
                    <div class="flex justify-between mb-4">
                        <button id="save-current-query" class="text-blue-500 hover:text-blue-700 dark:text-blue-400 dark:hover:text-blue-300 text-sm">
                            <i class="fas fa-save mr-1"></i> Save Current Query
                        </button>
                    </div>
                    <div id="saved-queries-list" class="space-y-3">
                        <div class="text-gray-500 dark:text-gray-400 text-center py-4">No saved queries</div>
                    </div>
                </div>
                <div class="px-6 py-4 bg-gray-50 dark:bg-gray-900 border-t border-gray-200 dark:border-gray-700 flex justify-end">
                    <button type="button" class="close-modal px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-200 rounded-md hover:bg-gray-300 dark:hover:bg-gray-600 transition-colors duration-200">
                        Close
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div id="schema-modal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 overflow-y-auto">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl max-w-6xl w-full mx-auto">
                <div class="flex justify-between items-center border-b border-gray-200 dark:border-gray-700 px-6 py-4">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Database Schema Visualization
                        <span class="ml-2 px-2 py-0.5 text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300 rounded-full">BETA</span>
                    </h3>
                    <button type="button" class="close-modal text-gray-400 hover:text-gray-500 dark:text-gray-300 dark:hover:text-gray-200">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="px-6 py-4 h-[70vh]">
                    <div class="mb-4 flex justify-between items-center">
                        <div>
                            <span class="text-sm text-gray-600 dark:text-gray-300 mr-2">Layout:</span>
                            <select id="schema-layout" class="text-sm border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded px-2 py-1">
                                <option value="dagre">Hierarchical Layout</option>
                                <option value="grid">Grid Layout</option>
                                <option value="circle">Circular Layout</option>
                                <option value="cose">Force-directed</option>
                            </select>
                        </div>
                        <div class="text-sm text-gray-500 dark:text-gray-400">
                            <i class="fas fa-info-circle mr-1"></i> Drag to pan, scroll to zoom, click nodes for details
                        </div>
                    </div>
                    <div class="flex justify-center items-center py-12" id="schema-loading">
                        <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600"></div>
                    </div>
                    <div id="schema-diagram" class="w-full h-[calc(100%-60px)] border border-gray-200 dark:border-gray-700 rounded-lg p-4 relative" style="display: none; position: relative;"></div>
                </div>
                <div class="px-6 py-4 bg-gray-50 dark:bg-gray-900 border-t border-gray-200 dark:border-gray-700 flex justify-end">
                    <button type="button" class="close-modal px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-200 rounded-md hover:bg-gray-300 dark:hover:bg-gray-600 transition-colors duration-200">
                        Close
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div id="save-query-modal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 overflow-y-auto">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl max-w-lg w-full mx-auto">
                <div class="flex justify-between items-center border-b border-gray-200 dark:border-gray-700 px-6 py-4">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Save Query</h3>
                    <button type="button" class="close-modal text-gray-400 hover:text-gray-500 dark:text-gray-300 dark:hover:text-gray-200">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="px-6 py-4">
                    <div class="mb-4">
                        <label for="query-name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Query Name</label>
                        <input type="text" id="query-name" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="Enter a name for this query">
                    </div>
                    <div class="mb-4">
                        <label for="query-description" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Description (Optional)</label>
                        <textarea id="query-description" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" rows="3" placeholder="Describe what this query does"></textarea>
                    </div>
                    <div class="bg-gray-50 dark:bg-gray-900 p-3 rounded-md text-sm">
                        <div class="font-medium mb-1 dark:text-gray-300">Query to save:</div>
                        <div id="query-preview" class="text-gray-600 dark:text-gray-400 break-all"></div>
                    </div>
                </div>
                <div class="px-6 py-4 bg-gray-50 dark:bg-gray-900 border-t border-gray-200 dark:border-gray-700 flex justify-end space-x-3">
                    <button type="button" class="close-modal px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-200 rounded-md hover:bg-gray-300 dark:hover:bg-gray-600 transition-colors duration-200">
                        Cancel
                    </button>
                    <button type="button" id="confirm-save-query" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors duration-200">
                        Save Query
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div id="cell-edit-modal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 overflow-y-auto">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl max-w-lg w-full mx-auto">
                <div class="flex justify-between items-center border-b border-gray-200 dark:border-gray-700 px-6 py-4">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white" id="cell-edit-title">Edit Cell Value</h3>
                    <button type="button" class="close-modal text-gray-400 hover:text-gray-500 dark:text-gray-300 dark:hover:text-gray-200">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="px-6 py-4">
                    <input type="hidden" id="edit-row-index">
                    <input type="hidden" id="edit-column-name">
                    <input type="hidden" id="edit-table-name">
                    
                    <div class="mb-4">
                        <label for="cell-value" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Value</label>
                        <textarea id="cell-value" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" rows="3"></textarea>
                    </div>
                    
                    <div class="flex items-center mb-4">
                        <input type="checkbox" id="cell-is-null" class="mr-2">
                        <label for="cell-is-null" class="text-sm text-gray-700 dark:text-gray-300">Set to NULL</label>
                    </div>
                </div>
                <div class="px-6 py-4 bg-gray-50 dark:bg-gray-900 border-t border-gray-200 dark:border-gray-700 flex justify-end space-x-3">
                    <button type="button" class="close-modal px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-200 rounded-md hover:bg-gray-300 dark:hover:bg-gray-600 transition-colors duration-200">
                        Cancel
                    </button>
                    <button type="button" id="execute-cell-edit" class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 transition-colors duration-200">
                        Execute
                    </button>
                    <button type="button" id="save-cell-edit" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors duration-200">
                        To Editor
                    </button>
                </div>
            </div>
        </div>
    </div>

    <template id="history-item-template">
    </template>


    <script>var require = { paths: { 'vs': 'https://cdnjs.cloudflare.com/ajax/libs/monaco-editor/0.44.0/min/vs' } };</script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/monaco-editor/0.44.0/min/vs/loader.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/monaco-editor/0.44.0/min/vs/editor/editor.main.nls.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/monaco-editor/0.44.0/min/vs/editor/editor.main.min.js"></script>

    
    <script>
       
        let editor;
        
        require(['vs/editor/editor.main'], function() {
            monaco.languages.registerCompletionItemProvider('sql', {
                provideCompletionItems: function(model, position) {
                    const suggestions = [
                        {
                            label: 'SELECT',
                            kind: monaco.languages.CompletionItemKind.Keyword,
                            insertText: 'SELECT',
                            detail: 'SQL SELECT statement'
                        },
                        {
                            label: 'FROM',
                            kind: monaco.languages.CompletionItemKind.Keyword,
                            insertText: 'FROM',
                            detail: 'SQL FROM clause'
                        },
                        {
                            label: 'WHERE',
                            kind: monaco.languages.CompletionItemKind.Keyword,
                            insertText: 'WHERE',
                            detail: 'SQL WHERE clause'
                        },
                        {
                            label: 'GROUP BY',
                            kind: monaco.languages.CompletionItemKind.Keyword,
                            insertText: 'GROUP BY',
                            detail: 'SQL GROUP BY clause'
                        },
                        {
                            label: 'ORDER BY',
                            kind: monaco.languages.CompletionItemKind.Keyword,
                            insertText: 'ORDER BY',
                            detail: 'SQL ORDER BY clause'
                        },
                        {
                            label: 'INSERT INTO',
                            kind: monaco.languages.CompletionItemKind.Keyword,
                            insertText: 'INSERT INTO',
                            detail: 'SQL INSERT statement'
                        },
                        {
                            label: 'UPDATE',
                            kind: monaco.languages.CompletionItemKind.Keyword,
                            insertText: 'UPDATE',
                            detail: 'SQL UPDATE statement'
                        },
                        {
                            label: 'DELETE FROM',
                            kind: monaco.languages.CompletionItemKind.Keyword,
                            insertText: 'DELETE FROM',
                            detail: 'SQL DELETE statement'
                        },
                        {
                            label: 'JOIN',
                            kind: monaco.languages.CompletionItemKind.Keyword,
                            insertText: 'JOIN',
                            detail: 'SQL JOIN clause'
                        },
                        {
                            label: 'INNER JOIN',
                            kind: monaco.languages.CompletionItemKind.Keyword,
                            insertText: 'INNER JOIN',
                            detail: 'SQL INNER JOIN clause'
                        },
                        {
                            label: 'LEFT JOIN',
                            kind: monaco.languages.CompletionItemKind.Keyword,
                            insertText: 'LEFT JOIN',
                            detail: 'SQL LEFT JOIN clause'
                        },
                        {
                            label: 'RIGHT JOIN',
                            kind: monaco.languages.CompletionItemKind.Keyword,
                            insertText: 'RIGHT JOIN',
                            detail: 'SQL RIGHT JOIN clause'
                        }
                    ];
                    
                    <?php foreach ($tables as $table): ?>
                    suggestions.push({
                        label: '<?php echo addslashes($table); ?>',
                        kind: monaco.languages.CompletionItemKind.Class,
                        insertText: '`<?php echo addslashes($table); ?>`',
                        detail: 'Table'
                    });
                    <?php endforeach; ?>
                    
                    return { suggestions: suggestions };
                }
            });
            
            editor = monaco.editor.create(document.getElementById('editor-container'), {
                value: <?php echo json_encode($query); ?>,
                language: 'sql',
                theme: 'vs',
                minimap: {
                    enabled: false
                },
                scrollBeyondLastLine: false,
                automaticLayout: true,
                fontSize: 14,
                tabSize: 2
            });
            
            document.getElementById('query-form').addEventListener('submit', function() {
                document.getElementById('query-input').value = editor.getValue();
            });
            
            document.getElementById('clear-btn').addEventListener('click', function() {
                editor.setValue('');
                editor.focus();
            });
            
            document.getElementById('format-btn').addEventListener('click', function() {
                const sql = editor.getValue();
                
                let formatted = sql
                    .replace(/\s+/g, ' ')
                    .replace(/\s*,\s*/g, ', ')
                    .replace(/\s*;\s*/g, ';\n')
                    .replace(/\s*SELECT\s+/gi, 'SELECT\n  ')
                    .replace(/\s*FROM\s+/gi, '\nFROM\n  ')
                    .replace(/\s*WHERE\s+/gi, '\nWHERE\n  ')
                    .replace(/\s*GROUP\s+BY\s+/gi, '\nGROUP BY\n  ')
                    .replace(/\s*HAVING\s+/gi, '\nHAVING\n  ')
                    .replace(/\s*ORDER\s+BY\s+/gi, '\nORDER BY\n  ')
                    .replace(/\s*INNER\s+JOIN\s+/gi, '\nINNER JOIN\n  ')
                    .replace(/\s*LEFT\s+JOIN\s+/gi, '\nLEFT JOIN\n  ')
                    .replace(/\s*RIGHT\s+JOIN\s+/gi, '\nRIGHT JOIN\n  ')
                    .replace(/\s*JOIN\s+/gi, '\nJOIN\n  ')
                    .replace(/\s*ON\s+/gi, '\n  ON\n    ')
                    .replace(/\s*AND\s+/gi, '\n    AND ')
                    .replace(/\s*OR\s+/gi, '\n    OR ');
                
                editor.setValue(formatted);
                editor.focus();
            });
            
            document.querySelectorAll('.table-btn').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    const tableName = this.dataset.table;
                    editor.setValue(`SELECT * FROM \`${tableName}\` LIMIT 100;`);
                    editor.focus();
                });
            });
            
            document.querySelectorAll('.common-query').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    let query = this.dataset.query;
                    query = query.replace('DATABASE_NAME', '<?php echo addslashes($currentDb); ?>');
                    editor.setValue(query);
                    editor.focus();
                });
            });
            
            document.getElementById('database-selector').addEventListener('change', function() {
                window.location.href = '?db=' + this.value;
            });
        });
        
        document.addEventListener('keydown', function(e) {
            if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
                e.preventDefault();
                document.getElementById('query-input').value = editor.getValue();
                document.getElementById('query-form').submit();
            }
        });
        
        let currentTableData = null;
        let currentTableName = null;
        
        function openModal(title) {
            document.getElementById('modal-title').textContent = title;
            document.getElementById('advanced-modal').classList.remove('hidden');
            document.body.classList.add('overflow-hidden');
        }
        
        function closeModal() {
            document.getElementById('advanced-modal').classList.add('hidden');
            document.body.classList.remove('overflow-hidden');
        }
        
        document.getElementById('close-modal').addEventListener('click', closeModal);
        document.getElementById('close-modal-btn').addEventListener('click', closeModal);
        
        document.getElementById('advanced-modal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });
        
        function viewTableStructure(tableName) {
            currentTableName = tableName;
            openModal(`Table Structure: ${tableName}`);
            
            fetch(`ajax_handler.php?action=get_table_structure&table=${encodeURIComponent(tableName)}&db=${encodeURIComponent('<?php echo addslashes($currentDb); ?>')}`)
                .then(response => response.json())
                .then(data => {
                    if (data.error) {
                        document.getElementById('modal-content').innerHTML = `
                            <div class="bg-red-100 dark:bg-red-900 border border-red-400 dark:border-red-700 text-red-700 dark:text-red-300 px-4 py-3 rounded mb-4">
                                <i class="fas fa-exclamation-circle mr-2"></i> ${data.error}
                            </div>
                        `;
                        return;
                    }
                    
                    let columnsHtml = `
                        <h4 class="text-lg font-semibold mb-2 text-gray-900 dark:text-white">Columns</h4>
                        <div class="overflow-x-auto dark-scrollbar">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 mb-6">
                                <thead class="bg-gray-50 dark:bg-gray-700">
                                    <tr>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Field</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Type</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Null</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Key</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Default</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Extra</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                    `;
                    
                    data.columns.forEach(column => {
                        columnsHtml += `
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white">${column.Field}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">${column.Type}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">${column.Null}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">${column.Key}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">${column.Default !== null ? column.Default : 'NULL'}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">${column.Extra}</td>
                            </tr>
                        `;
                    });
                    
                    columnsHtml += `
                                </tbody>
                            </table>
                        </div>
                    `;
                    
                    let indexesHtml = '';
                    if (data.indexes && data.indexes.length > 0) {
                        indexesHtml = `
                            <h4 class="text-lg font-semibold mb-2 mt-6 text-gray-900 dark:text-white">Indexes</h4>
                            <div class="overflow-x-auto dark-scrollbar">
                                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                    <thead class="bg-gray-50 dark:bg-gray-700">
                                        <tr>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Key Name</th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Column</th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Non Unique</th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Type</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                        `;
                        
                        data.indexes.forEach(index => {
                            indexesHtml += `
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white">${index.Key_name}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">${index.Column_name}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">${index.Non_unique}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">${index.Index_type}</td>
                                </tr>
                            `;
                        });
                        
                        indexesHtml += `
                                    </tbody>
                                </table>
                            </div>
                        `;
                    }
                    
                    let createSqlHtml = `
                        <h4 class="text-lg font-semibold mb-2 mt-6 text-gray-900 dark:text-white">Create Table SQL</h4>
                        <div class="bg-gray-100 dark:bg-gray-900 p-4 rounded-md overflow-x-auto dark-scrollbar">
                            <pre class="text-sm text-gray-800 dark:text-gray-300">CREATE TABLE \`${tableName}\` (
${data.columns.map(col => `  \`${col.Field}\` ${col.Type}${col.Null === 'YES' ? '' : ' NOT NULL'}${col.Default !== null ? ` DEFAULT '${col.Default}'` : ''}${col.Extra ? ` ${col.Extra}` : ''}`).join(',\n')}
${data.indexes.filter(idx => idx.Key_name === 'PRIMARY').length > 0 ? ',\n  PRIMARY KEY (' + data.indexes.filter(idx => idx.Key_name === 'PRIMARY').map(idx => `\`${idx.Column_name}\``).join(', ') + ')' : ''}
${data.indexes.filter(idx => idx.Key_name !== 'PRIMARY' && !idx.Key_name.startsWith('UNIQUE')).length > 0 ? ',\n  ' + data.indexes.filter(idx => idx.Key_name !== 'PRIMARY' && !idx.Key_name.startsWith('UNIQUE')).map(idx => `KEY \`${idx.Key_name}\` (\`${idx.Column_name}\`)`).join(',\n  ') : ''}
${data.indexes.filter(idx => idx.Key_name.startsWith('UNIQUE')).length > 0 ? ',\n  ' + data.indexes.filter(idx => idx.Key_name.startsWith('UNIQUE')).map(idx => `UNIQUE KEY \`${idx.Key_name}\` (\`${idx.Column_name}\`)`).join(',\n  ') : ''}
);</pre>
                        </div>
                    `;
                    
                    let addRecordFormHtml = `
                        <h4 class="text-lg font-semibold mb-2 mt-6 text-gray-900 dark:text-white">Quick Insert Form</h4>
                        <form id="quick-insert-form" class="bg-white dark:bg-gray-800 p-4 rounded-md border border-gray-200 dark:border-gray-700">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                ${data.columns.map(col => `
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">${col.Field}</label>
                                        <input type="text" name="${col.Field}" placeholder="${col.Type}" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    </div>
                                `).join('')}
                            </div>
                            <div class="mt-4 flex justify-end">
                                <button type="button" id="quick-insert-btn" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors duration-200">
                                    <i class="fas fa-plus mr-2"></i> Insert Record
                                </button>
                            </div>
                        </form>
                    `;
                    
                    document.getElementById('modal-content').innerHTML = columnsHtml + indexesHtml + createSqlHtml + addRecordFormHtml;
                    
                    document.getElementById('quick-insert-btn').addEventListener('click', function() {
                        const form = document.getElementById('quick-insert-form');
                        const formData = new FormData(form);
                        
                        let fields = [];
                        let values = [];
                        
                        for (const [key, value] of formData.entries()) {
                            if (value.trim() !== '') {
                                fields.push(`\`${key}\``);
                                values.push(`'${value.replace(/'/g, "''")}'`);
                            }
                        }
                        
                        if (fields.length === 0) {
                            alert('Please fill at least one field');
                            return;
                        }
                        
                        const insertQuery = `INSERT INTO \`${tableName}\` (${fields.join(', ')}) VALUES (${values.join(', ')})`;
                        
                        editor.setValue(insertQuery);
                        closeModal();
                    });
                })
                .catch(error => {
                    document.getElementById('modal-content').innerHTML = `
                        <div class="bg-red-100 dark:bg-red-900 border border-red-400 dark:border-red-700 text-red-700 dark:text-red-300 px-4 py-3 rounded mb-4">
                            <i class="fas fa-exclamation-circle mr-2"></i> Error fetching table structure: ${error.message}
                        </div>
                    `;
                });
        }
        
        document.querySelectorAll('.table-btn').forEach(function(btn) {
            const structureBtn = document.createElement('button');
            structureBtn.innerHTML = '<i class="fas fa-info-circle"></i>';
            structureBtn.className = 'ml-auto text-blue-500 hover:text-blue-700';
            structureBtn.title = 'View Table Structure';
            structureBtn.addEventListener('click', function(e) {
                e.stopPropagation();
                viewTableStructure(btn.dataset.table);
            });
            
            btn.appendChild(structureBtn);
        });
        
        document.getElementById('export-sql').addEventListener('click', function() {
            if (!currentTableName) return;
            
            const sql = `SELECT * FROM \`${currentTableName}\`;`;
            editor.setValue(sql);
            closeModal();
        });
        
        document.getElementById('export-csv').addEventListener('click', function() {
            if (!currentTableName) return;
            
            const sql = `SELECT * FROM \`${currentTableName}\` INTO OUTFILE '/tmp/${currentTableName}.csv' FIELDS TERMINATED BY ',' ENCLOSED BY '"' LINES TERMINATED BY '\\n';`;
            editor.setValue(sql);
            closeModal();
        });

        const htmlElement = document.documentElement;
        const isDarkMode = localStorage.getItem('sqlmanager_dark_mode') === 'true' || document.cookie.includes('sqlmanager_dark_mode=true');
        const themeText = document.getElementById('theme-text');
        
        function applyTheme() {
            const isDark = localStorage.getItem('sqlmanager_dark_mode') === 'true' || document.cookie.includes('sqlmanager_dark_mode=true');
            
            if (isDark) {
                htmlElement.classList.add('dark');
                themeText.textContent = 'Light Mode';
                themeText.previousElementSibling.classList.remove('fa-moon');
                themeText.previousElementSibling.classList.add('fa-sun');
                
                if (editor && monaco) {
                    monaco.editor.setTheme('vs-dark');
                }
            } else {
                htmlElement.classList.remove('dark');
                themeText.textContent = 'Dark Mode';
                themeText.previousElementSibling.classList.remove('fa-sun');
                themeText.previousElementSibling.classList.add('fa-moon');
                
                if (editor && monaco) {
                    monaco.editor.setTheme('vs');
                }
            }
        }
        
        document.getElementById('toggle-theme').addEventListener('click', function() {
            const currentMode = localStorage.getItem('sqlmanager_dark_mode') === 'true' || document.cookie.includes('sqlmanager_dark_mode=true');
            const newMode = !currentMode;
            
            localStorage.setItem('sqlmanager_dark_mode', newMode);
            
            const expiryDate = new Date();
            expiryDate.setFullYear(expiryDate.getFullYear() + 1);
            document.cookie = `sqlmanager_dark_mode=${newMode}; expires=${expiryDate.toUTCString()}; path=/; SameSite=Lax`;
            
            applyTheme();
        });
        
        if (isDarkMode) {
            applyTheme();
        }

        const MAX_HISTORY_ITEMS = 50;
        let queryHistory = JSON.parse(localStorage.getItem('sqlmanager_query_history') || '[]');
        
        function addToHistory(query) {
            if (!query.trim()) return;
            
            queryHistory = queryHistory.filter(item => item.query !== query);
            
            queryHistory.unshift({
                query: query,
                timestamp: new Date().toISOString(),
                database: '<?php echo addslashes($currentDb); ?>'
            });
            
            if (queryHistory.length > MAX_HISTORY_ITEMS) {
                queryHistory = queryHistory.slice(0, MAX_HISTORY_ITEMS);
            }
            
            localStorage.setItem('sqlmanager_query_history', JSON.stringify(queryHistory));
        }
        
        function renderQueryHistory() {
            const historyList = document.getElementById('query-history-list');
            
            if (queryHistory.length === 0) {
                historyList.innerHTML = '<div class="text-gray-500 text-center py-4">No queries in history</div>';
                return;
            }
            
            historyList.innerHTML = '';
            
            queryHistory.forEach((item, index) => {
                const date = new Date(item.timestamp);
                const formattedDate = date.toLocaleString();
                
                const historyItem = document.createElement('div');
                historyItem.className = 'bg-gray-50 dark:bg-gray-800 p-3 rounded-md';
                historyItem.innerHTML = `
                    <div class="flex justify-between mb-2">
                        <div class="text-xs text-gray-500">${formattedDate}</div>
                        <div class="text-xs text-gray-500">${item.database}</div>
                    </div>
                    <div class="text-sm mb-2 overflow-hidden text-ellipsis whitespace-nowrap">${item.query}</div>
                    <div class="flex space-x-2">
                        <button class="use-history-query text-blue-500 hover:text-blue-700 text-xs" data-index="${index}">
                            <i class="fas fa-play mr-1"></i> Use
                        </button>
                        <button class="remove-history-query text-red-500 hover:text-red-700 text-xs" data-index="${index}">
                            <i class="fas fa-trash mr-1"></i> Remove
                        </button>
                    </div>
                `;
                
                historyList.appendChild(historyItem);
            });
            
            document.querySelectorAll('.use-history-query').forEach(btn => {
                btn.addEventListener('click', function() {
                    const index = parseInt(this.dataset.index);
                    editor.setValue(queryHistory[index].query);
                    closeHistoryModal();
                });
            });
            
            document.querySelectorAll('.remove-history-query').forEach(btn => {
                btn.addEventListener('click', function() {
                    const index = parseInt(this.dataset.index);
                    queryHistory.splice(index, 1);
                    localStorage.setItem('sqlmanager_query_history', JSON.stringify(queryHistory));
                    renderQueryHistory();
                });
            });
        }
        
        document.getElementById('clear-history').addEventListener('click', function() {
            if (confirm('Are you sure you want to clear all query history?')) {
                queryHistory = [];
                localStorage.setItem('sqlmanager_query_history', JSON.stringify(queryHistory));
                renderQueryHistory();
            }
        });
        
        document.getElementById('show-query-history').addEventListener('click', function() {
            renderQueryHistory();
            document.getElementById('history-modal').classList.remove('hidden');
            document.body.classList.add('overflow-hidden');
        });
        
        function closeHistoryModal() {
            document.getElementById('history-modal').classList.add('hidden');
            document.body.classList.remove('overflow-hidden');
        }
        
        let savedQueries = JSON.parse(localStorage.getItem('sqlmanager_saved_queries') || '[]');
        
        function renderSavedQueries() {
            const savedQueriesList = document.getElementById('saved-queries-list');
            
            if (savedQueries.length === 0) {
                savedQueriesList.innerHTML = '<div class="text-gray-500 dark:text-gray-400 text-center py-4">No saved queries</div>';
                return;
            }
            
            savedQueriesList.innerHTML = '';
            
            savedQueries.forEach((item, index) => {
                const savedQueryItem = document.createElement('div');
                savedQueryItem.className = 'bg-gray-50 dark:bg-gray-700 p-3 rounded-md';
                savedQueryItem.innerHTML = `
                    <div class="font-medium mb-1 text-gray-900 dark:text-gray-100">${item.name}</div>
                    ${item.description ? `<div class="text-sm text-gray-500 dark:text-gray-400 mb-2">${item.description}</div>` : ''}
                    <div class="text-xs bg-gray-100 dark:bg-gray-800 p-2 rounded mb-2 overflow-hidden overflow-ellipsis whitespace-nowrap text-gray-700 dark:text-gray-300">${item.query}</div>
                    <div class="flex space-x-2">
                        <button class="use-saved-query text-blue-500 hover:text-blue-700 dark:text-blue-400 dark:hover:text-blue-300 text-xs" data-index="${index}">
                            <i class="fas fa-play mr-1"></i> Use
                        </button>
                        <button class="remove-saved-query text-red-500 hover:text-red-700 dark:text-red-400 dark:hover:text-red-300 text-xs" data-index="${index}">
                            <i class="fas fa-trash mr-1"></i> Remove
                        </button>
                    </div>
                `;
                
                savedQueriesList.appendChild(savedQueryItem);
            });
            
            document.querySelectorAll('.use-saved-query').forEach(btn => {
                btn.addEventListener('click', function() {
                    const index = parseInt(this.dataset.index);
                    editor.setValue(savedQueries[index].query);
                    closeSavedQueriesModal();
                });
            });
            
            document.querySelectorAll('.remove-saved-query').forEach(btn => {
                btn.addEventListener('click', function() {
                    const index = parseInt(this.dataset.index);
                    if (confirm(`Are you sure you want to delete the saved query "${savedQueries[index].name}"?`)) {
                        savedQueries.splice(index, 1);
                        localStorage.setItem('sqlmanager_saved_queries', JSON.stringify(savedQueries));
                        renderSavedQueries();
                    }
                });
            });
        }
        
        document.getElementById('show-saved-queries').addEventListener('click', function() {
            renderSavedQueries();
            document.getElementById('saved-queries-modal').classList.remove('hidden');
            document.body.classList.add('overflow-hidden');
        });
        
        function closeSavedQueriesModal() {
            document.getElementById('saved-queries-modal').classList.add('hidden');
            document.body.classList.remove('overflow-hidden');
        }
        
        document.getElementById('save-current-query').addEventListener('click', function() {
            const currentQuery = editor.getValue().trim();
            
            if (!currentQuery) {
                alert('Please enter a query to save');
                return;
            }
            
            document.getElementById('query-preview').textContent = currentQuery;
            document.getElementById('save-query-modal').classList.remove('hidden');
            document.body.classList.add('overflow-hidden');
        });
        
        document.getElementById('confirm-save-query').addEventListener('click', function() {
            const name = document.getElementById('query-name').value.trim();
            const description = document.getElementById('query-description').value.trim();
            const query = editor.getValue().trim();
            
            if (!name) {
                alert('Please enter a name for this query');
                return;
            }
            
            savedQueries.push({
                name: name,
                description: description,
                query: query,
                database: '<?php echo addslashes($currentDb); ?>',
                createdAt: new Date().toISOString()
            });
            
            localStorage.setItem('sqlmanager_saved_queries', JSON.stringify(savedQueries));
            
            document.getElementById('query-name').value = '';
            document.getElementById('query-description').value = '';
            document.getElementById('save-query-modal').classList.add('hidden');
            document.body.classList.remove('overflow-hidden');
            
            alert('Query saved successfully');
        });
        
        document.getElementById('schema-visualize').addEventListener('click', function() {
            document.getElementById('schema-modal').classList.remove('hidden');
            document.body.classList.add('overflow-hidden');
            
            document.getElementById('schema-loading').style.display = 'flex';
            document.getElementById('schema-diagram').style.display = 'none';
            
            fetchDatabaseSchema();
        });
        
        function fetchDatabaseSchema() {
            if (typeof cytoscape === 'undefined') {
                loadCytoscapeScripts();
            }
            
            document.getElementById('schema-loading').style.display = 'flex';
            document.getElementById('schema-diagram').style.display = 'none';
            document.getElementById('schema-diagram').innerHTML = '';
            
            fetch(`ajax_handler.php?action=get_database_schema&db=${encodeURIComponent('<?php echo addslashes($currentDb); ?>')}`)
                .then(response => response.json())
                .then(data => {
                    if (data.error) {
                        document.getElementById('schema-diagram').innerHTML = `
                            <div class="bg-red-100 dark:bg-red-900 border border-red-400 dark:border-red-700 text-red-700 dark:text-red-300 px-4 py-3 rounded mb-4">
                                <i class="fas fa-exclamation-circle mr-2"></i> ${data.error}
                            </div>
                        `;
                        return;
                    }
                    
                    console.log("Schema data:", data);
                    
                    if (data.tables && data.tables.length > 0) {
                        if (!data.relationships || data.relationships.length === 0) {
                            document.getElementById('schema-diagram').innerHTML = `
                                <div class="bg-yellow-100 dark:bg-yellow-900 border border-yellow-400 dark:border-yellow-700 text-yellow-700 dark:text-yellow-300 px-4 py-3 rounded mb-4 text-center">
                                    <i class="fas fa-exclamation-triangle mr-2"></i> No relationships detected between tables. 
                                    <div class="mt-2">Found ${data.debug.tableCount} tables, but couldn't identify foreign key relationships.</div>
                                    <div class="mt-1 text-xs">Try adding foreign keys to your database schema or use naming convention like tablename_id.</div>
                                </div>
                                <div class="mt-4">
                                    <h3 class="text-xl font-semibold text-gray-900 dark:text-white mb-2">Tables in database</h3>
                                    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-2 mb-4">
                                        ${data.tables.map(table => `
                                            <div class="p-3 bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-200 rounded">
                                                <div class="font-semibold">${table.name}</div>
                                                <div class="text-xs mt-1">${table.columns ? table.columns.length : 0} columns</div>
                                            </div>
                                        `).join('')}
                                    </div>
                                    <div class="flex justify-center">
                                        <button id="force-visualization" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors">
                                            Show Tables Visualization Anyway
                                        </button>
                                    </div>
                                </div>
                            `;
                            
                            document.getElementById('force-visualization').addEventListener('click', function() {
                                document.getElementById('schema-diagram').style.display = 'block';
                                initCytoscape(data.tables, [], true);
                            });
                            
                            document.getElementById('schema-loading').style.display = 'none';
                            return;
                        }
                        
                        document.getElementById('schema-loading').style.display = 'none';
                        document.getElementById('schema-diagram').style.display = 'block';
                        
                        initCytoscape(data.tables, data.relationships || []);
                    } else {
                        document.getElementById('schema-diagram').innerHTML = `
                            <div class="bg-yellow-100 dark:bg-yellow-900 border border-yellow-400 dark:border-yellow-700 text-yellow-700 dark:text-yellow-300 px-4 py-3 rounded mb-4 text-center">
                                <i class="fas fa-exclamation-triangle mr-2"></i> No tables found in this database.
                            </div>
                        `;
                    }
                })
                .catch(error => {
                    document.getElementById('schema-diagram').innerHTML = `
                        <div class="bg-red-100 dark:bg-red-900 border border-red-400 dark:border-red-700 text-red-700 dark:text-red-300 px-4 py-3 rounded mb-4">
                            <i class="fas fa-exclamation-circle mr-2"></i> Error fetching schema: ${error.message}
                        </div>
                    `;
                });
        }
        
        function loadCytoscapeScripts() {
            return new Promise((resolve, reject) => {
                const oldRequire = window.require;
                window.require = undefined;
                
                const cytoscapeScript = document.createElement('script');
                cytoscapeScript.src = 'https://cdnjs.cloudflare.com/ajax/libs/cytoscape/3.2.22/cytoscape.min.js';
                document.head.appendChild(cytoscapeScript);
                
                cytoscapeScript.onload = function() {
                    const dagreScript = document.createElement('script');
                    dagreScript.src = 'https://cdnjs.cloudflare.com/ajax/libs/cytoscape-dagre/2.5.0/cytoscape-dagre.min.js';
                    document.head.appendChild(dagreScript);
                    
                    dagreScript.onload = function() {
                        window.require = oldRequire;
                        
                        if (typeof cytoscape !== 'undefined') {
                            try {
                                window.dagre = dagre;
                                cytoscape.use(window.dagre);
                                console.log("Cytoscape and dagre extension loaded successfully");
                                resolve();
                            } catch (e) {
                                console.warn("Could not register dagre layout:", e);
                                reject(e);
                            }
                        }
                    };
                };
            });
        }
        
        let cy = null;
        
        function initCytoscape(tables, relationships) {
            console.log('initCytoscape called with', tables.length, 'tables and', relationships.length, 'relationships');
            
            document.getElementById('schema-diagram').innerHTML = '';
            
            document.getElementById('schema-loading').style.display = 'none';
            document.getElementById('schema-diagram').style.display = 'block';
            
            if (tables.length === 0) {
                console.error('No tables to display');
                document.getElementById('schema-diagram').innerHTML = '<div class="flex justify-center items-center h-full"><p class="text-lg text-gray-700 dark:text-gray-300">No tables found in the database.</p></div>';
                return;
            }
            
            if (relationships.length === 0) {
                console.warn('No relationships found between tables');
                document.getElementById('schema-diagram').innerHTML = 
                    '<div class="flex flex-col justify-center items-center h-full">' +
                    '<p class="text-lg text-gray-700 dark:text-gray-300 mb-4">No relationships found between tables.</p>' +
                    '<button id="showTablesAnyway" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">Show Tables Visualization Anyway</button>' +
                    '</div>';
                    
                document.getElementById('showTablesAnyway').addEventListener('click', function() {
                    document.getElementById('schema-diagram').innerHTML = '';
                    createCytoscapeInstance(tables, relationships);
                });
                return;
            }
            
            createCytoscapeInstance(tables, relationships);
        }
        
        function createCytoscapeInstance(tables, relationships) {
            console.log('Creating Cytoscape instance with', tables.length, 'tables and', relationships.length, 'relationships');
            
            var elements = [];
            
            tables.forEach(function(table) {
                elements.push({
                    data: { 
                        id: table.name,
                        label: table.name
                    }
                });
            });
            
            relationships.forEach(function(rel) {
                elements.push({
                    data: {
                        id: rel.from_table + '-' + rel.to_table,
                        source: rel.from_table,
                        target: rel.to_table,
                        label: rel.name || ''
                    }
                });
            });
            
            console.log('Cytoscape elements created:', elements.length);
            
            try {
                var cy = cytoscape({
                    container: document.getElementById('schema-diagram'),
                    elements: elements,
                    style: [
                        {
                            selector: 'node',
                            style: {
                                'background-color': '#4299e1',
                                'label': 'data(label)',
                                'color': '#000000',
                                'text-valign': 'center',
                                'text-halign': 'center',
                                'width': '150px',
                                'height': '40px',
                                'shape': 'rectangle',
                                'text-wrap': 'wrap'
                            }
                        },
                        {
                            selector: 'edge',
                            style: {
                                'width': 2,
                                'line-color': '#666',
                                'target-arrow-color': '#666',
                                'target-arrow-shape': 'triangle',
                                'curve-style': 'bezier'
                            }
                        }
                    ],
                    layout: {
                        name: 'cose',
                        padding: 50,
                        animate: false,
                        componentSpacing: 150,
                        nodeOverlap: 20,
                        nodeRepulsion: 10000,
                        idealEdgeLength: 100
                    }
                });
                
                console.log('Cytoscape instance created successfully');
            } catch (error) {
                console.error('Error creating Cytoscape instance:', error);
                document.getElementById('schema-diagram').innerHTML = 
                    '<div class="flex justify-center items-center h-full">' +
                    '<p class="text-lg text-red-600 dark:text-red-400">Error rendering database diagram: ' + error.message + '</p>' +
                    '</div>';
            }
        }
        
        document.querySelectorAll('.close-modal').forEach(btn => {
            btn.addEventListener('click', function() {
                document.querySelectorAll('div[id$="-modal"]').forEach(modal => {
                    modal.classList.add('hidden');
                });
                document.body.classList.remove('overflow-hidden');
            });
        });
        
        document.getElementById('query-form').addEventListener('submit', function() {
            const query = editor.getValue().trim();
            if (query) {
                addToHistory(query);
            }
        });
    </script>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const cellEditModal = document.getElementById('cell-edit-modal');
            const cellValueInput = document.getElementById('cell-value');
            const cellIsNullCheckbox = document.getElementById('cell-is-null');
            const editRowIndexInput = document.getElementById('edit-row-index');
            const editColumnNameInput = document.getElementById('edit-column-name');
            const editTableNameInput = document.getElementById('edit-table-name');
            
            function closeCellEditModal() {
                cellEditModal.classList.add('hidden');
                document.body.classList.remove('overflow-hidden');
            }
            
            document.querySelectorAll('#cell-edit-modal .close-modal').forEach(btn => {
                btn.addEventListener('click', closeCellEditModal);
            });
            
            document.querySelectorAll('.editable-cell').forEach(cell => {
                cell.addEventListener('click', function() {
                    const columnName = this.dataset.column;
                    const rowIndex = this.parentElement.dataset.rowIndex;
                    const valueText = this.dataset.value;
                    const isNull = this.dataset.isNull === '1';
                    
                    let tableName = '';
                    const headerCell = document.querySelector(`#results-table th[data-field="${columnName}"]`);
                    if (headerCell) {
                        tableName = headerCell.dataset.table;
                    }
                    
                    document.getElementById('cell-edit-title').textContent = `Edit ${columnName}`;
                    
                    editRowIndexInput.value = rowIndex;
                    editColumnNameInput.value = columnName;
                    editTableNameInput.value = tableName;
                    
                    if (isNull) {
                        cellValueInput.value = '';
                        cellIsNullCheckbox.checked = true;
                        cellValueInput.disabled = true;
                    } else {
                        cellValueInput.value = valueText;
                        cellIsNullCheckbox.checked = false;
                        cellValueInput.disabled = false;
                    }
                    
                    cellEditModal.classList.remove('hidden');
                    document.body.classList.add('overflow-hidden');
                });
            });
            
            cellIsNullCheckbox.addEventListener('change', function() {
                cellValueInput.disabled = this.checked;
            });
            
            document.getElementById('save-cell-edit').addEventListener('click', function() {
                const rowIndex = editRowIndexInput.value;
                const columnName = editColumnNameInput.value;
                const tableName = editTableNameInput.value;
                const isNull = cellIsNullCheckbox.checked;
                const value = isNull ? null : cellValueInput.value;
                
                const primaryKeyCols = [];
                const whereConditions = [];
                const headers = document.querySelectorAll('#results-table th');
                
                const row = document.querySelector(`.result-row[data-row-index="${rowIndex}"]`);
                if (row) {
                    headers.forEach(header => {
                        if (header.querySelector('i.fa-key')) {
                            const colName = header.dataset.field;
                            const cell = row.querySelector(`td[data-column="${colName}"]`);
                            if (cell) {
                                primaryKeyCols.push(colName);
                                whereConditions.push({
                                    column: colName,
                                    value: cell.dataset.value,
                                    isNull: cell.dataset.isNull === '1'
                                });
                            }
                        }
                    });
                }
                
                if (whereConditions.length === 0) {
                    const cells = row.querySelectorAll('td.editable-cell');
                    cells.forEach(cell => {
                        if (cell.dataset.column !== columnName) {
                            whereConditions.push({
                                column: cell.dataset.column,
                                value: cell.dataset.value,
                                isNull: cell.dataset.isNull === '1'
                            });
                        }
                    });
                }
                
                let updateQuery = `UPDATE \`${tableName}\` SET \`${columnName}\` = `;
                
                if (isNull) {
                    updateQuery += 'NULL';
                } else {
                    updateQuery += `'${value.replace(/'/g, "''")}'`;
                }
                
                if (whereConditions.length > 0) {
                    updateQuery += ' WHERE ';
                    const whereClauses = whereConditions.map(condition => {
                        if (condition.isNull) {
                            return `\`${condition.column}\` IS NULL`;
                        } else {
                            return `\`${condition.column}\` = '${condition.value.replace(/'/g, "''")}'`;
                        }
                    });
                    updateQuery += whereClauses.join(' AND ');
                }
                
                updateQuery += ' LIMIT 1;';
                
                const cell = row.querySelector(`td[data-column="${columnName}"]`);
                if (cell) {
                    cell.dataset.value = isNull ? '' : value;
                    cell.dataset.isNull = isNull ? '1' : '0';
                    cell.textContent = isNull ? 'NULL' : value;
                }
                
                if (typeof editor !== 'undefined') {
                    editor.setValue(updateQuery);
                }
                
                closeCellEditModal();
                
                showNotification('Cell updated. Click Execute to apply changes.', 'info');
            });
            
            document.getElementById('execute-cell-edit').addEventListener('click', function() {
                const rowIndex = editRowIndexInput.value;
                const columnName = editColumnNameInput.value;
                const tableName = editTableNameInput.value;
                const isNull = cellIsNullCheckbox.checked;
                const value = isNull ? null : cellValueInput.value;
                
                const primaryKeyCols = [];
                const whereConditions = [];
                const headers = document.querySelectorAll('#results-table th');
                
                const row = document.querySelector(`.result-row[data-row-index="${rowIndex}"]`);
                if (row) {
                    headers.forEach(header => {
                        if (header.querySelector('i.fa-key')) {
                            const colName = header.dataset.field;
                            const cell = row.querySelector(`td[data-column="${colName}"]`);
                            if (cell) {
                                primaryKeyCols.push(colName);
                                whereConditions.push({
                                    column: colName,
                                    value: cell.dataset.value,
                                    isNull: cell.dataset.isNull === '1'
                                });
                            }
                        }
                    });
                }
                
                if (whereConditions.length === 0) {
                    const cells = row.querySelectorAll('td.editable-cell');
                    cells.forEach(cell => {
                        if (cell.dataset.column !== columnName) {
                            whereConditions.push({
                                column: cell.dataset.column,
                                value: cell.dataset.value,
                                isNull: cell.dataset.isNull === '1'
                            });
                        }
                    });
                }
                
                let updateQuery = `UPDATE \`${tableName}\` SET \`${columnName}\` = `;
                
                if (isNull) {
                    updateQuery += 'NULL';
                } else {
                    updateQuery += `'${value.replace(/'/g, "''")}'`;
                }
                
                if (whereConditions.length > 0) {
                    updateQuery += ' WHERE ';
                    const whereClauses = whereConditions.map(condition => {
                        if (condition.isNull) {
                            return `\`${condition.column}\` IS NULL`;
                        } else {
                            return `\`${condition.column}\` = '${condition.value.replace(/'/g, "''")}'`;
                        }
                    });
                    updateQuery += whereClauses.join(' AND ');
                }
                
                updateQuery += ' LIMIT 1;';
                
                executeQuery(updateQuery, function(response) {
                    if (response.error) {
                        showNotification('Error: ' + response.error, 'error');
                        return;
                    }
                    
                    const cell = row.querySelector(`td[data-column="${columnName}"]`);
                    if (cell) {
                        cell.dataset.value = isNull ? '' : value;
                        cell.dataset.isNull = isNull ? '1' : '0';
                        cell.textContent = isNull ? 'NULL' : value;
                        
                        cell.classList.add('bg-green-100', 'dark:bg-green-900');
                        setTimeout(() => {
                            cell.classList.remove('bg-green-100', 'dark:bg-green-900');
                        }, 2000);
                    }
                    
                    closeCellEditModal();
                    
                    showNotification(`Cell updated successfully. ${response.affectedRows} row(s) affected.`, 'success');
                });
            });
            
            function addDirectExecutionButtons() {
                document.querySelectorAll('.edit-row-btn').forEach(btn => {
                    if (btn.dataset.eventAdded) return;
                    btn.dataset.eventAdded = 'true';
                    
                    btn.addEventListener('dblclick', function(e) {
                        e.stopPropagation();
                        const row = this.closest('tr');
                        const tableName = document.querySelector('#results-table th[data-table]')?.dataset.table || '';
                        
                        if (!tableName) {
                            showNotification('Cannot determine table name from result', 'error');
                            return;
                        }
                        
                        const primaryKeyCols = [];
                        const whereConditions = [];
                        const headers = document.querySelectorAll('#results-table th');
                        
                        headers.forEach(header => {
                            if (header.querySelector('i.fa-key')) {
                                const colName = header.dataset.field;
                                const cell = row.querySelector(`td[data-column="${colName}"]`);
                                if (cell) {
                                    primaryKeyCols.push(colName);
                                    whereConditions.push({
                                        column: colName,
                                        value: cell.dataset.value,
                                        isNull: cell.dataset.isNull === '1'
                                    });
                                }
                            }
                        });
                        
                        if (whereConditions.length === 0) {
                            const cells = row.querySelectorAll('td.editable-cell');
                            cells.forEach(cell => {
                                whereConditions.push({
                                    column: cell.dataset.column,
                                    value: cell.dataset.value,
                                    isNull: cell.dataset.isNull === '1'
                                });
                            });
                        }
                        
                        let updateQuery = `UPDATE \`${tableName}\` SET\n`;
                        const setClauses = [];
                        
                        const cells = row.querySelectorAll('td.editable-cell');
                        cells.forEach(cell => {
                            const columnName = cell.dataset.column;
                            const isNull = cell.dataset.isNull === '1';
                            const value = cell.dataset.value;
                            
                            if (isNull) {
                                setClauses.push(`  \`${columnName}\` = NULL`);
                            } else {
                                setClauses.push(`  \`${columnName}\` = '${value.replace(/'/g, "''")}'`);
                            }
                        });
                        
                        updateQuery += setClauses.join(',\n');
                        
                        if (whereConditions.length > 0) {
                            updateQuery += '\nWHERE ';
                            const whereClauses = whereConditions.map(condition => {
                                if (condition.isNull) {
                                    return `\`${condition.column}\` IS NULL`;
                                } else {
                                    return `\`${condition.column}\` = '${condition.value.replace(/'/g, "''")}'`;
                                }
                            });
                            updateQuery += whereClauses.join(' AND ');
                        }
                        
                        updateQuery += ' LIMIT 1;';
                        
                        if (confirm('Are you sure you want to execute this update?')) {
                            executeQuery(updateQuery, function(response) {
                                if (response.error) {
                                    showNotification('Error: ' + response.error, 'error');
                                    return;
                                }
                                
                                row.classList.add('bg-green-100', 'dark:bg-green-900');
                                setTimeout(() => {
                                    row.classList.remove('bg-green-100', 'dark:bg-green-900');
                                }, 2000);
                                
                                showNotification(`Row updated successfully. ${response.affectedRows} row(s) affected.`, 'success');
                            });
                        }
                    });
                });
                
                document.querySelectorAll('.delete-row-btn').forEach(btn => {
                    if (btn.dataset.eventAdded) return;
                    btn.dataset.eventAdded = 'true';
                    
                    btn.addEventListener('dblclick', function(e) {
                        e.stopPropagation();
                        const row = this.closest('tr');
                        const tableName = document.querySelector('#results-table th[data-table]')?.dataset.table || '';
                        
                        if (!tableName) {
                            showNotification('Cannot determine table name from result', 'error');
                            return;
                        }
                        
                        if (!confirm('Are you sure you want to DELETE this row? This action cannot be undone!')) {
                            return;
                        }
                        
                        const primaryKeyCols = [];
                        const whereConditions = [];
                        const headers = document.querySelectorAll('#results-table th');
                        
                        headers.forEach(header => {
                            if (header.querySelector('i.fa-key')) {
                                const colName = header.dataset.field;
                                const cell = row.querySelector(`td[data-column="${colName}"]`);
                                if (cell) {
                                    primaryKeyCols.push(colName);
                                    whereConditions.push({
                                        column: colName,
                                        value: cell.dataset.value,
                                        isNull: cell.dataset.isNull === '1'
                                    });
                                }
                            }
                        });
                        
                        if (whereConditions.length === 0) {
                            const cells = row.querySelectorAll('td.editable-cell');
                            cells.forEach(cell => {
                                whereConditions.push({
                                    column: cell.dataset.column,
                                    value: cell.dataset.value,
                                    isNull: cell.dataset.isNull === '1'
                                });
                            });
                        }
                        
                        let deleteQuery = `DELETE FROM \`${tableName}\``;
                        
                        if (whereConditions.length > 0) {
                            deleteQuery += ' WHERE ';
                            const whereClauses = whereConditions.map(condition => {
                                if (condition.isNull) {
                                    return `\`${condition.column}\` IS NULL`;
                                } else {
                                    return `\`${condition.column}\` = '${condition.value.replace(/'/g, "''")}'`;
                                }
                            });
                            deleteQuery += whereClauses.join(' AND ');
                        }
                        
                        deleteQuery += ' LIMIT 1;';
                        
                        executeQuery(deleteQuery, function(response) {
                            if (response.error) {
                                showNotification('Error: ' + response.error, 'error');
                                return;
                            }
                            
                            row.classList.add('bg-red-100', 'dark:bg-red-900');
                            setTimeout(() => {
                                row.remove();
                            }, 500);
                            
                            showNotification(`Row deleted successfully. ${response.affectedRows} row(s) affected.`, 'success');
                        });
                    });
                });
            }
            
            addDirectExecutionButtons();
            
            document.querySelectorAll('.edit-row-btn').forEach(btn => {
                btn.addEventListener('click', function(e) {
                    e.stopPropagation();
                    const row = this.closest('tr');
                    const rowIndex = row.dataset.rowIndex;
                    const tableName = document.querySelector('#results-table th[data-table]')?.dataset.table || '';
                    
                    if (!tableName) {
                        showNotification('Cannot determine table name from result', 'error');
                        return;
                    }
                    
                    const primaryKeyCols = [];
                    const whereConditions = [];
                    const headers = document.querySelectorAll('#results-table th');
                    
                    headers.forEach(header => {
                        if (header.querySelector('i.fa-key')) {
                            const colName = header.dataset.field;
                            const cell = row.querySelector(`td[data-column="${colName}"]`);
                            if (cell) {
                                primaryKeyCols.push(colName);
                                whereConditions.push({
                                    column: colName,
                                    value: cell.dataset.value,
                                    isNull: cell.dataset.isNull === '1'
                                });
                            }
                        }
                    });
                    
                    if (whereConditions.length === 0) {
                        const cells = row.querySelectorAll('td.editable-cell');
                        cells.forEach(cell => {
                            whereConditions.push({
                                column: cell.dataset.column,
                                value: cell.dataset.value,
                                isNull: cell.dataset.isNull === '1'
                            });
                        });
                    }
                    
                    let updateQuery = `UPDATE \`${tableName}\` SET\n`;
                    const setClauses = [];
                    
                    const cells = row.querySelectorAll('td.editable-cell');
                    cells.forEach(cell => {
                        const columnName = cell.dataset.column;
                        const isNull = cell.dataset.isNull === '1';
                        const value = cell.dataset.value;
                        
                        if (isNull) {
                            setClauses.push(`  \`${columnName}\` = NULL`);
                        } else {
                            setClauses.push(`  \`${columnName}\` = '${value.replace(/'/g, "''")}'`);
                        }
                    });
                    
                    updateQuery += setClauses.join(',\n');
                    
                    if (whereConditions.length > 0) {
                        updateQuery += '\nWHERE ';
                        const whereClauses = whereConditions.map(condition => {
                            if (condition.isNull) {
                                return `\`${condition.column}\` IS NULL`;
                            } else {
                                return `\`${condition.column}\` = '${condition.value.replace(/'/g, "''")}'`;
                            }
                        });
                        updateQuery += whereClauses.join(' AND ');
                    }
                    
                    updateQuery += ' LIMIT 1;';
                    
                    if (typeof editor !== 'undefined') {
                        editor.setValue(updateQuery);
                    }
                    
                    showNotification('Edit query created. Click Execute to apply changes.', 'info');
                });
            });
            
            document.querySelectorAll('.delete-row-btn').forEach(btn => {
                btn.addEventListener('click', function(e) {
                    e.stopPropagation();
                    const row = this.closest('tr');
                    const tableName = document.querySelector('#results-table th[data-table]')?.dataset.table || '';
                    
                    if (!tableName) {
                        showNotification('Cannot determine table name from result', 'error');
                        return;
                    }
                    
                    if (!confirm('Are you sure you want to delete this row?')) {
                        return;
                    }
                    
                    const primaryKeyCols = [];
                    const whereConditions = [];
                    const headers = document.querySelectorAll('#results-table th');
                    
                    headers.forEach(header => {
                        if (header.querySelector('i.fa-key')) {
                            const colName = header.dataset.field;
                            const cell = row.querySelector(`td[data-column="${colName}"]`);
                            if (cell) {
                                primaryKeyCols.push(colName);
                                whereConditions.push({
                                    column: colName,
                                    value: cell.dataset.value,
                                    isNull: cell.dataset.isNull === '1'
                                });
                            }
                        }
                    });
                    
                    if (whereConditions.length === 0) {
                        const cells = row.querySelectorAll('td.editable-cell');
                        cells.forEach(cell => {
                            whereConditions.push({
                                column: cell.dataset.column,
                                value: cell.dataset.value,
                                isNull: cell.dataset.isNull === '1'
                            });
                        });
                    }
                    
                    let deleteQuery = `DELETE FROM \`${tableName}\``;
                    
                    if (whereConditions.length > 0) {
                        deleteQuery += ' WHERE ';
                        const whereClauses = whereConditions.map(condition => {
                            if (condition.isNull) {
                                return `\`${condition.column}\` IS NULL`;
                            } else {
                                return `\`${condition.column}\` = '${condition.value.replace(/'/g, "''")}'`;
                            }
                        });
                        deleteQuery += whereClauses.join(' AND ');
                    }
                    
                    deleteQuery += ' LIMIT 1;';
                    
                    if (typeof editor !== 'undefined') {
                        editor.setValue(deleteQuery);
                    }
                    
                    showNotification('Delete query created. Click Execute to apply changes.', 'warning');
                });
            });
            
            document.querySelectorAll('.duplicate-row-btn').forEach(btn => {
                btn.addEventListener('click', function(e) {
                    e.stopPropagation();
                    const row = this.closest('tr');
                    const tableName = document.querySelector('#results-table th[data-table]')?.dataset.table || '';
                    
                    if (!tableName) {
                        showNotification('Cannot determine table name from result', 'error');
                        return;
                    }
                    
                    let insertQuery = `INSERT INTO \`${tableName}\` (`;
                    const columns = [];
                    const values = [];
                    
                    const cells = row.querySelectorAll('td.editable-cell');
                    cells.forEach(cell => {
                        const columnName = cell.dataset.column;
                        const isNull = cell.dataset.isNull === '1';
                        const value = cell.dataset.value;
                        
                        const header = document.querySelector(`#results-table th[data-field="${columnName}"]`);
                        const isPrimaryKey = header && header.querySelector('i.fa-key');
                        
                        if (!isPrimaryKey) {
                            columns.push(`\`${columnName}\``);
                            if (isNull) {
                                values.push('NULL');
                            } else {
                                values.push(`'${value.replace(/'/g, "''")}'`);
                            }
                        }
                    });
                    
                    insertQuery += columns.join(', ');
                    insertQuery += ') VALUES (';
                    insertQuery += values.join(', ');
                    insertQuery += ');';
                    
                    if (typeof editor !== 'undefined') {
                        editor.setValue(insertQuery);
                    }
                    
                    showNotification('Duplicate query created. Click Execute to apply changes.', 'info');
                });
            });
            
            const addNewRowBtn = document.getElementById('add-new-row');
            if (addNewRowBtn) {
                addNewRowBtn.addEventListener('click', function() {
                    const tableName = document.querySelector('#results-table th[data-table]')?.dataset.table || '';
                    
                    if (!tableName) {
                        showNotification('Cannot determine table name from result', 'error');
                        return;
                    }
                    
                    let insertQuery = `INSERT INTO \`${tableName}\` (`;
                    const columns = [];
                    const placeholders = [];
                    
                    const headers = document.querySelectorAll('#results-table th[data-field]');
                    headers.forEach(header => {
                        const colName = header.dataset.field;
                        const isPrimaryKey = header.querySelector('i.fa-key');
                        
                        if (!isPrimaryKey) {
                            columns.push(`\`${colName}\``);
                            placeholders.push('?');
                        }
                    });
                    
                    insertQuery += columns.join(', ');
                    insertQuery += ') VALUES (';
                    insertQuery += placeholders.join(', ');
                    insertQuery += ');';
                    
                    if (typeof editor !== 'undefined') {
                        editor.setValue(insertQuery);
                    }
                    
                    showNotification('Insert query created. Replace ? with values and click Execute.', 'info');
                });
            }
            
            function executeQuery(query, callback) {
                const currentDatabase = document.getElementById('current-db').innerText;
                
                const formData = new FormData();
                formData.append('query', query);
                formData.append('database', currentDatabase);
                
                fetch('ajax_handler.php?action=execute_query', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    callback(data);
                })
                .catch(error => {
                    showNotification('Error: ' + error.message, 'error');
                });
            }
            
            function showNotification(message, type = 'info') {
                const notification = document.createElement('div');
                notification.className = `fixed bottom-4 right-4 px-4 py-2 rounded-md shadow-lg z-50 ${
                    type === 'error' ? 'bg-red-500 text-white' : 
                    type === 'warning' ? 'bg-yellow-500 text-white' : 
                    'bg-blue-500 text-white'
                }`;
                notification.innerHTML = message;
                
                document.body.appendChild(notification);
                
                setTimeout(() => {
                    notification.classList.add('opacity-0', 'transition-opacity', 'duration-500');
                    setTimeout(() => {
                        notification.remove();
                    }, 500);
                }, 3000);
            }
        });
    </script>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            if (document.getElementById('results-table')) {
                initResultsSearch();
                initAdvancedSearch();
            }
        });

        function initResultsSearch() {
            const resultsTable = document.getElementById('results-table');
            const searchInput = document.getElementById('results-search');
            const filterDropdownBtn = document.getElementById('filter-dropdown-btn');
            const filterDropdown = document.getElementById('filter-dropdown');
            const columnFiltersContainer = document.getElementById('column-filters');
            const clearFiltersBtn = document.getElementById('clear-filters');
            const applyFiltersBtn = document.getElementById('apply-filters');
            const rowsPerPageSelect = document.getElementById('rows-per-page');
            const resultsCount = document.getElementById('results-count');
            
            const allRows = Array.from(resultsTable.querySelectorAll('tbody tr'));
            let activeFilters = new Set();
            let currentVisibleRows = allRows;
            
            initColumnFilters();
            
            if (searchInput) {
                searchInput.addEventListener('input', applyFilters);
            }
            
            if (filterDropdownBtn) {
                filterDropdownBtn.addEventListener('click', function() {
                    filterDropdown.classList.toggle('hidden');
                });
                
                document.addEventListener('click', function(event) {
                    if (!filterDropdownBtn.contains(event.target) && !filterDropdown.contains(event.target)) {
                        filterDropdown.classList.add('hidden');
                    }
                });
            }
            
            if (clearFiltersBtn) {
                clearFiltersBtn.addEventListener('click', clearFilters);
            }
            
            if (applyFiltersBtn) {
                applyFiltersBtn.addEventListener('click', function() {
                    filterDropdown.classList.add('hidden');
                    applyFilters();
                });
            }
            
            if (rowsPerPageSelect) {
                rowsPerPageSelect.addEventListener('change', applyPagination);
            }
            
            function initColumnFilters() {
                if (!columnFiltersContainer) return;
                
                const headers = Array.from(resultsTable.querySelectorAll('thead th'));
                const columnFiltersHTML = headers.map((header, index) => {
                    if (index === headers.length - 1) return '';
                    
                    const columnName = header.textContent.trim();
                    return `
                        <div class="flex items-center mb-2">
                            <input type="checkbox" id="filter-${index}" class="column-filter-checkbox mr-2"
                                   data-column="${index}" checked>
                            <label for="filter-${index}" class="text-sm text-gray-700 dark:text-gray-300">
                                ${columnName}
                            </label>
                        </div>
                    `;
                }).join('');
                
                columnFiltersContainer.innerHTML = columnFiltersHTML;
                
                columnFiltersContainer.querySelectorAll('.column-filter-checkbox').forEach(checkbox => {
                    activeFilters.add(parseInt(checkbox.dataset.column));
                    
                    checkbox.addEventListener('change', function() {
                        const columnIndex = parseInt(this.dataset.column);
                        
                        if (this.checked) {
                            activeFilters.add(columnIndex);
                        } else {
                            activeFilters.delete(columnIndex);
                        }
                    });
                });
            }
            
            function applyFilters() {
                const searchTerm = searchInput ? searchInput.value.toLowerCase() : '';
                
                currentVisibleRows = allRows.filter(row => {
                    if (searchTerm === '') return true;
                    
                    const cells = Array.from(row.querySelectorAll('td'));
                    for (let i = 0; i < cells.length - 1; i++) {
                        if (activeFilters.has(i)) {
                            const cellText = cells[i].textContent.toLowerCase();
                            if (cellText.includes(searchTerm)) {
                                return true;
                            }
                        }
                    }
                    return false;
                });
                
                updateVisibleRows();
                
                if (resultsCount) {
                    resultsCount.textContent = `${currentVisibleRows.length} rows found (filtered from ${allRows.length})`;
                }
            }
            
            function clearFilters() {
                if (searchInput) {
                    searchInput.value = '';
                }
                
                columnFiltersContainer.querySelectorAll('.column-filter-checkbox').forEach(checkbox => {
                    checkbox.checked = true;
                    activeFilters.add(parseInt(checkbox.dataset.column));
                });
                
                currentVisibleRows = allRows;
                updateVisibleRows();
                
                if (resultsCount) {
                    resultsCount.textContent = `${allRows.length} rows found`;
                }
            }
            
            function applyPagination() {
                const rowsPerPage = rowsPerPageSelect.value === 'all' ? 
                    currentVisibleRows.length : parseInt(rowsPerPageSelect.value);
                
                if (rowsPerPage < currentVisibleRows.length) {
                    const paginated = currentVisibleRows.slice(0, rowsPerPage);
                    
                    allRows.forEach(row => {
                        row.classList.add('hidden');
                    });
                    
                    paginated.forEach(row => {
                        row.classList.remove('hidden');
                    });
                    
                    if (resultsCount) {
                        resultsCount.textContent = `Showing ${paginated.length} of ${currentVisibleRows.length} filtered rows`;
                    }
                } else {
                    updateVisibleRows();
                }
            }
            
            function updateVisibleRows() {
                allRows.forEach(row => {
                    row.classList.add('hidden');
                });
                
                currentVisibleRows.forEach(row => {
                    row.classList.remove('hidden');
                });
                
                if (rowsPerPageSelect && rowsPerPageSelect.value !== 'all') {
                    applyPagination();
                }
            }
        }

        function initAdvancedSearch() {
            const advancedSearchBtn = document.getElementById('advanced-search-btn');
            const advancedSearchModal = document.getElementById('advanced-search-modal');
            const applyAdvancedSearchBtn = document.getElementById('apply-advanced-search');
            const addFilterBtn = document.getElementById('add-filter');
            const matchAllCheckbox = document.getElementById('match-all');
            const advancedFiltersContainer = document.getElementById('advanced-filters');
            const resultsTable = document.getElementById('results-table');
            const resultsCount = document.getElementById('results-count');
            
            const allRows = Array.from(resultsTable.querySelectorAll('tbody tr'));
            
            const headers = Array.from(resultsTable.querySelectorAll('thead th'));
            const columnOptions = headers.map((header, index) => {
                if (index === headers.length - 1) return '';
                const columnName = header.textContent.trim();
                return `<option value="${index}">${columnName}</option>`;
            }).join('');
            
            const firstFilterColumn = advancedFiltersContainer.querySelector('.filter-column');
            if (firstFilterColumn) {
                firstFilterColumn.innerHTML = columnOptions;
            }
            
            if (advancedSearchBtn) {
                advancedSearchBtn.addEventListener('click', function() {
                    advancedSearchModal.classList.remove('hidden');
                    populateInitialFilter();
                });
            }
            
            advancedSearchModal.querySelectorAll('.close-modal').forEach(btn => {
                btn.addEventListener('click', function() {
                    advancedSearchModal.classList.add('hidden');
                });
            });
            
            advancedSearchModal.addEventListener('click', function(e) {
                if (e.target === advancedSearchModal) {
                    advancedSearchModal.classList.add('hidden');
                }
            });
            
            const modalContent = advancedSearchModal.querySelector('.bg-white');
            if (modalContent) {
                modalContent.addEventListener('click', function(e) {
                    e.stopPropagation();
                });
            }
            
            if (addFilterBtn) {
                addFilterBtn.addEventListener('click', addNewFilter);
            }
            
            if (applyAdvancedSearchBtn) {
                applyAdvancedSearchBtn.addEventListener('click', function() {
                    applyAdvancedFilters();
                    advancedSearchModal.classList.add('hidden');
                });
            }
            
            const resetAdvancedSearchBtn = document.getElementById('reset-advanced-search');
            if (resetAdvancedSearchBtn) {
                resetAdvancedSearchBtn.addEventListener('click', function() {
                    populateInitialFilter();
                    
                    if (typeof showNotification === 'function') {
                        showNotification('Advanced search filters have been reset', 'info');
                    }
                });
            }
            
            advancedFiltersContainer.addEventListener('change', function(e) {
                if (e.target.classList.contains('filter-operator')) {
                    const filterCondition = e.target.closest('.filter-condition');
                    const valueInput2 = filterCondition.querySelector('.filter-value-2');
                    const valueInput1 = filterCondition.querySelector('.filter-value');
                    
                    if (e.target.value === 'between') {
                        valueInput2.classList.remove('hidden');
                    } else {
                        valueInput2.classList.add('hidden');
                    }
                    
                    if (e.target.value === 'null' || e.target.value === 'not_null') {
                        valueInput1.classList.add('hidden');
                    } else {
                        valueInput1.classList.remove('hidden');
                    }
                }
            });
            
            advancedFiltersContainer.addEventListener('click', function(e) {
                if (e.target.classList.contains('remove-filter') || e.target.closest('.remove-filter')) {
                    const button = e.target.classList.contains('remove-filter') ? 
                                   e.target : e.target.closest('.remove-filter');
                    const filterCondition = button.closest('.filter-condition');
                    
                    if (advancedFiltersContainer.querySelectorAll('.filter-condition').length > 1) {
                        filterCondition.remove();
                    }
                }
            });
            
            function populateInitialFilter() {
                const filterColumns = advancedFiltersContainer.querySelectorAll('.filter-column');
                
                filterColumns.forEach(select => {
                    select.innerHTML = '';
                    
                    select.innerHTML = columnOptions;
                });
                
                const conditions = advancedFiltersContainer.querySelectorAll('.filter-condition');
                if (conditions.length > 1) {
                    for (let i = 1; i < conditions.length; i++) {
                        conditions[i].remove();
                    }
                }
                
                const firstCondition = advancedFiltersContainer.querySelector('.filter-condition');
                if (firstCondition) {
                    const valueInput = firstCondition.querySelector('.filter-value');
                    const valueInput2 = firstCondition.querySelector('.filter-value-2');
                    const operatorSelect = firstCondition.querySelector('.filter-operator');
                    
                    if (valueInput) valueInput.value = '';
                    if (valueInput2) valueInput2.value = '';
                    if (operatorSelect) operatorSelect.selectedIndex = 0;
                    
                    if (valueInput2) valueInput2.classList.add('hidden');
                }
                
                if (matchAllCheckbox) matchAllCheckbox.checked = true;
            }
            
            function addNewFilter() {
                const newFilter = document.createElement('div');
                newFilter.className = 'filter-condition flex flex-wrap items-center gap-2 mt-2';
                newFilter.innerHTML = `
                    <select class="filter-column px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md">
                        ${columnOptions}
                    </select>
                    <select class="filter-operator px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md">
                        <option value="contains">Contains</option>
                        <option value="equals">Equals</option>
                        <option value="starts">Starts with</option>
                        <option value="ends">Ends with</option>
                        <option value="greater">Greater than</option>
                        <option value="less">Less than</option>
                        <option value="between">Between</option>
                        <option value="null">Is NULL</option>
                        <option value="not_null">Is not NULL</option>
                    </select>
                    <input type="text" class="filter-value px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md flex-grow">
                    <input type="text" class="filter-value-2 px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md hidden" placeholder="End value">
                    <button type="button" class="remove-filter px-3 py-2 bg-red-500 text-white rounded-md hover:bg-red-600">
                        <i class="fas fa-trash"></i>
                    </button>
                `;
                advancedFiltersContainer.appendChild(newFilter);
            }
            
            function applyAdvancedFilters() {
                const filterConditions = advancedFiltersContainer.querySelectorAll('.filter-condition');
                const matchAll = matchAllCheckbox.checked;
                
                let hasActiveCondition = false;
                filterConditions.forEach(condition => {
                    const operator = condition.querySelector('.filter-operator').value;
                    if (operator === 'null' || operator === 'not_null') {
                        hasActiveCondition = true;
                    } else {
                        const value1 = condition.querySelector('.filter-value').value.trim();
                        if (value1) hasActiveCondition = true;
                    }
                });
                
                if (!hasActiveCondition) {
                    allRows.forEach(row => row.classList.remove('hidden'));
                    if (resultsCount) {
                        resultsCount.textContent = `${allRows.length} rows found`;
                    }
                    return;
                }
                
                const filteredRows = allRows.filter(row => {
                    const cells = Array.from(row.querySelectorAll('td'));
                    
                    if (filterConditions.length === 0) return true;
                    
                    let match = matchAll;
                    
                    filterConditions.forEach(condition => {
                        const columnSelectElement = condition.querySelector('.filter-column');
                        if (!columnSelectElement) return;
                        
                        const columnIndex = parseInt(columnSelectElement.value);
                        const operatorElement = condition.querySelector('.filter-operator');
                        if (!operatorElement) return;
                        
                        const operator = operatorElement.value;
                        
                        if (columnIndex >= cells.length || columnIndex < 0) return;
                        
                        const cell = cells[columnIndex];
                        if (!cell) return;
                        
                        const cellContent = cell.textContent.toLowerCase().trim();
                        const cellValue = cell.dataset.value?.toLowerCase().trim() || cellContent;
                        const isNull = cell.dataset.isNull === '1';
                        
                        let value1 = '', value2 = '';
                        if (operator !== 'null' && operator !== 'not_null') {
                            const valueInput1 = condition.querySelector('.filter-value');
                            if (!valueInput1) return;
                            value1 = valueInput1.value.toLowerCase().trim();
                            
                            if (operator === 'between') {
                                const valueInput2 = condition.querySelector('.filter-value-2');
                                if (valueInput2) {
                                    value2 = valueInput2.value.toLowerCase().trim();
                                }
                            }
                        }
                        
                        if (value1 === '' && operator !== 'null' && operator !== 'not_null') {
                            return;
                        }
                        
                        let conditionMatch = false;
                        
                        switch(operator) {
                            case 'contains':
                                conditionMatch = cellContent.includes(value1);
                                break;
                            case 'equals':
                                conditionMatch = cellContent === value1;
                                break;
                            case 'starts':
                                conditionMatch = cellContent.startsWith(value1);
                                break;
                            case 'ends':
                                conditionMatch = cellContent.endsWith(value1);
                                break;
                            case 'greater':
                                const numCell = parseFloat(cellValue);
                                const numValue = parseFloat(value1);
                                if (!isNaN(numCell) && !isNaN(numValue)) {
                                    conditionMatch = numCell > numValue;
                                } else {
                                    conditionMatch = cellContent > value1;
                                }
                                break;
                            case 'less':
                                const numCellLess = parseFloat(cellValue);
                                const numValueLess = parseFloat(value1);
                                if (!isNaN(numCellLess) && !isNaN(numValueLess)) {
                                    conditionMatch = numCellLess < numValueLess;
                                } else {
                                    conditionMatch = cellContent < value1;
                                }
                                break;
                            case 'between':
                                const numCellBetween = parseFloat(cellValue);
                                const numValueMin = parseFloat(value1);
                                const numValueMax = parseFloat(value2);
                                if (!isNaN(numCellBetween) && !isNaN(numValueMin) && !isNaN(numValueMax)) {
                                    conditionMatch = numCellBetween >= numValueMin && numCellBetween <= numValueMax;
                                } else {
                                    conditionMatch = cellContent >= value1 && (value2 === '' || cellContent <= value2);
                                }
                                break;
                            case 'null':
                                conditionMatch = isNull || cellContent === 'null' || cellContent === '';
                                break;
                            case 'not_null':
                                conditionMatch = !isNull && cellContent !== 'null' && cellContent !== '';
                                break;
                        }
                        
                        if (matchAll) {
                            match = match && conditionMatch;
                        } else {
                            match = match || conditionMatch;
                        }
                    });
                    
                    return match;
                });
                
                allRows.forEach(row => {
                    row.classList.add('hidden');
                });
                
                filteredRows.forEach(row => {
                    row.classList.remove('hidden');
                });
                
                if (resultsCount) {
                    resultsCount.textContent = `${filteredRows.length} rows found (filtered from ${allRows.length})`;
                }
                
                if (typeof showNotification === 'function') {
                    showNotification(`Applied ${filterConditions.length} filter(s) - ${filteredRows.length} rows matched`, 'info');
                }
            }
        }
    </script>

  
    <div id="advanced-search-modal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 overflow-y-auto">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl max-w-4xl w-full mx-auto">
                <div class="flex justify-between items-center border-b border-gray-200 dark:border-gray-700 px-6 py-4">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Advanced Search</h3>
                    <button type="button" class="close-modal text-gray-400 hover:text-gray-500 dark:text-gray-300 dark:hover:text-gray-200">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="px-6 py-4">
                    <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">Create complex filters to find exactly what you're looking for.</p>
                    
                    <div class="bg-blue-50 dark:bg-blue-900 border border-blue-200 dark:border-blue-800 rounded-md p-3 mb-4">
                        <div class="flex items-start">
                            <div class="text-blue-500 dark:text-blue-300 mr-3">
                                <i class="fas fa-info-circle text-lg"></i>
                            </div>
                            <div class="text-sm text-blue-700 dark:text-blue-300">
                                <p class="font-medium mb-1">How to use advanced search:</p>
                                <ul class="list-disc list-inside space-y-1">
                                    <li>Add multiple conditions using the <strong>Add Filter</strong> button</li>
                                    <li>Choose between matching <strong>ALL</strong> conditions (AND) or <strong>ANY</strong> condition (OR)</li>
                                    <li>Double-click cells to edit them after finding your results</li>
                                    <li>Use <strong>Is NULL</strong> to find empty fields, <strong>Between</strong> for ranges</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    
                    <div id="advanced-filters" class="space-y-3 mb-4">
    
                        <div class="filter-condition flex flex-wrap items-center gap-2">
                            <select class="filter-column px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md">
                       
                            </select>
                            <select class="filter-operator px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md">
                                <option value="contains">Contains</option>
                                <option value="equals">Equals</option>
                                <option value="starts">Starts with</option>
                                <option value="ends">Ends with</option>
                                <option value="greater">Greater than</option>
                                <option value="less">Less than</option>
                                <option value="between">Between</option>
                                <option value="null">Is NULL</option>
                                <option value="not_null">Is not NULL</option>
                            </select>
                            <input type="text" class="filter-value px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md flex-grow">
                            <input type="text" class="filter-value-2 px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md hidden" placeholder="End value">
                            <button type="button" class="remove-filter px-3 py-2 bg-red-500 text-white rounded-md hover:bg-red-600">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div class="flex justify-between">
                        <button type="button" id="add-filter" class="px-3 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 flex items-center gap-1">
                            <i class="fas fa-plus"></i>
                            <span>Add Filter</span>
                        </button>
                        <div class="flex items-center gap-2">
                            <label class="text-sm text-gray-700 dark:text-gray-300">
                                <input type="checkbox" id="match-all" class="mr-1" checked>
                                Match all conditions (AND)
                            </label>
                        </div>
                    </div>
                </div>
                <div class="px-6 py-4 bg-gray-50 dark:bg-gray-900 border-t border-gray-200 dark:border-gray-700 flex justify-end">
                    <button type="button" id="reset-advanced-search" class="px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-200 rounded-md hover:bg-gray-300 dark:hover:bg-gray-600 transition-colors duration-200 mr-auto">
                        <i class="fas fa-undo mr-1"></i> Reset
                    </button>
                    <button type="button" class="close-modal px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-200 rounded-md hover:bg-gray-300 dark:hover:bg-gray-600 transition-colors duration-200 mr-2">
                        Cancel
                    </button>
                    <button type="button" id="apply-advanced-search" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors duration-200">
                        Apply Filters
                    </button>
                </div>
            </div>
        </div>
    </div>

  
    <div id="nl-to-sql-modal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 overflow-y-auto">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl max-w-6xl w-full mx-auto">
                <div class="flex justify-between items-center border-b border-gray-200 dark:border-gray-700 px-6 py-4">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                        Natural Language to SQL
                        <span class="ml-2 px-2 py-1 text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300 rounded-full">BETA</span>
                    </h3>
                    <button type="button" class="close-modal text-gray-400 hover:text-gray-500 dark:text-gray-300 dark:hover:text-gray-200">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="px-6 py-4">
                    <div class="bg-blue-50 dark:bg-blue-900 border border-blue-200 dark:border-blue-800 rounded-md p-4 mb-6">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <i class="fas fa-lightbulb text-yellow-500 text-xl mr-4"></i>
                            </div>
                            <div>
                                <h4 class="text-blue-800 dark:text-blue-300 font-medium mb-2">How to use Natural Language Queries</h4>
                                <p class="text-blue-700 dark:text-blue-400 text-sm">
                                    Type your question in plain English and our system will convert it to SQL. For example:
                                </p>
                                <ul class="list-disc list-inside text-blue-700 dark:text-blue-400 text-sm mt-2 space-y-1">
                                    <li>"Show me all customers from New York"</li>
                                    <li>"Find products with price greater than 100"</li>
                                    <li>"Count how many orders were placed last month"</li>
                                    <li>"List the top 5 most expensive products"</li>
                                </ul>
                                <div class="mt-2 pt-2 border-t border-blue-200 dark:border-blue-800">
                                    <p class="text-blue-700 dark:text-blue-400 text-sm">
                                        <i class="fas fa-robot text-blue-500 mr-1"></i> <strong>AI Mode:</strong> For more complex queries, enable AI mode with your API key. 
                                        Supports multiple AI providers: OpenAI, Claude, Gemini, and DeepSeek (no API key required for DeepSeek).
                                        AI understands advanced concepts like joins, subqueries, and complex filtering.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-4 border-b border-gray-200 dark:border-gray-700 pb-4">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center">
                                <label class="inline-flex items-center cursor-pointer mr-4">
                                    <input type="checkbox" id="use-ai-api" class="sr-only peer">
                                    <div class="relative w-11 h-6 bg-gray-200 rounded-full peer dark:bg-gray-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-gray-600 peer-checked:bg-blue-600"></div>
                                    <span class="ml-3 text-sm font-medium text-gray-900 dark:text-gray-300">Use AI for Better Results</span>
                                </label>
                                <div class="text-xs text-gray-500 dark:text-gray-400 hidden md:block">
                                    <i class="fas fa-info-circle mr-1"></i> Uses AI for more accurate query generation
                                </div>
                            </div>
                            <div class="flex items-center space-x-2">
                                <button type="button" id="show-comparison" class="text-blue-600 dark:text-blue-400 text-sm flex items-center">
                                    <i class="fas fa-chart-bar mr-1"></i> Compare
                                </button>
                                <button type="button" id="api-settings-toggle" class="text-blue-600 dark:text-blue-400 text-sm flex items-center">
                                    <i class="fas fa-cog mr-1"></i> API Settings
                                </button>
                            </div>
                        </div>
                        
                        <div id="api-settings-panel" class="hidden mt-4 p-4 bg-gray-50 dark:bg-gray-800 rounded-md border border-gray-200 dark:border-gray-700">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label for="api-key" class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">
                                        API Key
                                    </label>
                                    <input type="password" id="api-key" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="sk-...">
                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">API key required for OpenAI, Claude, and Gemini. Not required for DeepSeek Free.</p>
                                </div>
                                <div>
                                    <label for="api-provider" class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">
                                        API Provider
                                    </label>
                                    <select id="api-provider" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                        <!-- <option value="openai">OpenAI</option>
                                        <option value="claude">Anthropic Claude</option> -->
                                        <option value="gemini">Google Gemini</option>
                                        <!-- <option value="mistral">Mistral AI</option>
                                        <option value="llama">Meta Llama</option>
                                        <option value="deepseek">DeepSeek API</option>
                                        <option value="deepseek-free">DeepSeek Free (No API Key)</option> -->
                                    </select>
                                </div>
                            </div>
                            <div class="mt-3">
                                <label for="api-model" class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">
                                    AI Model
                                </label>
                                <select id="api-model" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    <!-- OpenAI Models -->
                                    <!-- <optgroup label="OpenAI Models" class="model-option openai">
                                        <option value="gpt-3.5-turbo">GPT-3.5 Turbo (Faster)</option>
                                        <option value="gpt-3.5-turbo-16k">GPT-3.5 Turbo 16K (Larger Context)</option>
                                        <option value="gpt-4">GPT-4 (More Accurate)</option>
                                        <option value="gpt-4-turbo">GPT-4 Turbo (Recommended)</option>
                                        <option value="gpt-4-32k">GPT-4 32K (Largest Context)</option>
                                        <option value="gpt-4o">GPT-4o (Latest)</option>
                                    </optgroup> -->
                                    <!-- Claude Models -->
                                    <!-- <optgroup label="Claude Models" class="model-option claude" style="display: none;">
                                        <option value="claude-3-haiku-20240307">Claude 3 Haiku (Faster)</option>
                                        <option value="claude-3-sonnet-20240229">Claude 3 Sonnet (Balanced)</option>
                                        <option value="claude-3-opus-20240229">Claude 3 Opus (Most Powerful)</option>
                                        <option value="claude-3.5-sonnet">Claude 3.5 Sonnet (Latest)</option>
                                        <option value="claude-2.1">Claude 2.1 (Legacy)</option>
                                        <option value="claude-instant-1.2">Claude Instant (Legacy)</option>
                                    </optgroup> -->
                                    <!-- Gemini Models -->
                                    <optgroup label="Gemini Models" class="model-option gemini" style="display: none;">
                                        <option value="gemini-pro">Gemini Pro</option>
                                        <option value="gemini-2.0-pro">Gemini 2.0 Pro</option>
                                        <option value="gemini-2.0-flash">Gemini 2.0 Flash (Faster)</option>
                                        <option value="gemini-1.5-pro-latest">Gemini 1.5 Pro</option>
                                        <option value="gemini-1.5-flash-latest">Gemini 1.5 Flash</option>
                                        <option value="gemini-ultra">Gemini Ultra (Most Powerful)</option>
                                    </optgroup>
                                    <!-- Mistral Models -->
                                    <!-- <optgroup label="Mistral Models" class="model-option mistral" style="display: none;">
                                        <option value="mistral-tiny">Mistral Tiny (Fastest)</option>
                                        <option value="mistral-small">Mistral Small (Balanced)</option>
                                        <option value="mistral-medium">Mistral Medium (Recommended)</option>
                                        <option value="mistral-large-latest">Mistral Large (Most Powerful)</option>
                                    </optgroup> -->
                                    <!-- Llama Models -->
                                    <!-- <optgroup label="Llama Models" class="model-option llama" style="display: none;">
                                        <option value="llama-3-8b">Llama 3 8B (Fastest)</option>
                                        <option value="llama-3-70b">Llama 3 70B (Most Powerful)</option>
                                        <option value="llama-2-7b">Llama 2 7B (Legacy)</option>
                                        <option value="llama-2-13b">Llama 2 13B (Legacy)</option>
                                        <option value="llama-2-70b">Llama 2 70B (Legacy)</option>
                                    </optgroup> -->
                                    <!-- DeepSeek Models -->
                                    <!-- <optgroup label="DeepSeek Models" class="model-option deepseek" style="display: none;">
                                        <option value="deepseek-coder-v2">DeepSeek Coder V2</option>
                                        <option value="deepseek-chat-v2">DeepSeek Chat V2</option>
                                        <option value="deepseek-llm-67b">DeepSeek LLM 67B</option>
                                        <option value="deepseek-math">DeepSeek Math</option>
                                    </optgroup> -->
                                    <!-- DeepSeek Free Models -->
                                    <!-- <optgroup label="DeepSeek Free Models" class="model-option deepseek-free" style="display: none;">
                                        <option value="deepseek-coder">DeepSeek Coder</option>
                                        <option value="deepseek-chat">DeepSeek Chat</option>
                                        <option value="deepseek-v2">DeepSeek V2</option>
                                    </optgroup> -->
                                </select>
                            </div>
                            <div class="mt-3 flex justify-end">
                                <button type="button" id="save-api-settings" class="px-3 py-1 bg-blue-600 text-white rounded-md hover:bg-blue-700 text-sm">
                                    Save Settings
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <div id="comparison-section" class="hidden mt-4 p-4 bg-gray-50 dark:bg-gray-800 rounded-md border border-gray-200 dark:border-gray-700">
                        <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Standard vs. AI Query Generation</h4>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-xs">
                            <div class="p-3 bg-white dark:bg-gray-900 rounded border border-gray-200 dark:border-gray-700">
                                <div class="font-medium text-gray-800 dark:text-gray-200 mb-1">Standard: "find users who signed up this month"</div>
                                <div class="text-gray-600 dark:text-gray-400 font-mono">SELECT * FROM users WHERE MONTH(signup_date) = MONTH(CURRENT_DATE)</div>
                            </div>
                            <div class="p-3 bg-purple-50 dark:bg-purple-900/30 rounded border border-purple-200 dark:border-purple-800">
                                <div class="font-medium text-purple-800 dark:text-purple-300 mb-1">AI: "find users who signed up this month"</div>
                                <div class="text-purple-700 dark:text-purple-400 font-mono">
                                    SELECT * FROM users<br>
                                    WHERE signup_date BETWEEN<br>
                                    DATE_FORMAT(NOW(), '%Y-%m-01') AND<br>
                                    LAST_DAY(NOW())
                                </div>
                            </div>
                            
                            <div class="p-3 bg-white dark:bg-gray-900 rounded border border-gray-200 dark:border-gray-700">
                                <div class="font-medium text-gray-800 dark:text-gray-200 mb-1">Standard: "show sales by product category"</div>
                                <div class="text-gray-600 dark:text-gray-400 font-mono">SELECT * FROM sales</div>
                            </div>
                            <div class="p-3 bg-purple-50 dark:bg-purple-900/30 rounded border border-purple-200 dark:border-purple-800">
                                <div class="font-medium text-purple-800 dark:text-purple-300 mb-1">AI: "show sales by product category"</div>
                                <div class="text-purple-700 dark:text-purple-400 font-mono">
                                    SELECT c.category_name, SUM(s.amount) as total_sales<br>
                                    FROM sales s<br>
                                    JOIN products p ON s.product_id = p.id<br>
                                    JOIN categories c ON p.category_id = c.id<br>
                                    GROUP BY c.category_name<br>
                                    ORDER BY total_sales DESC
                                </div>
                            </div>
                        </div>
                        
                        <div class="mt-3 text-xs text-center text-gray-500 dark:text-gray-400">
                            AI mode requires an API key from OpenAI, Claude, or Gemini, but provides more sophisticated SQL generation capabilities. Try Gemini 2.0 Flash for faster performance or DeepSeek Free for quick testing without an API key.
                        </div>
                    </div>
                    
                    <div class="space-y-4">
                        <div>
                            <label for="nl-query" class="block mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">
                                Ask in plain English
                            </label>
                            <div class="relative">
                                <textarea id="nl-query" class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 min-h-[80px]" placeholder="e.g., Show all users who registered in the last month"></textarea>
                                <div class="absolute right-3 bottom-3 flex space-x-2">
                                    <button id="speech-to-text" class="px-3 py-1 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors duration-200" title="Speech to text">
                                        <i class="fas fa-microphone"></i>
                                    </button>
                                    <button id="submit-nl-query" class="px-3 py-1 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors duration-200">
                                        <i class="fas fa-magic mr-1"></i> Generate SQL
                                    </button>
                                </div>
                            </div>
                        </div>
                        
                        <div id="nl-processing" class="hidden py-8 flex justify-center">
                            <div class="animate-pulse flex space-x-2 items-center">
                                <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
                                <div class="text-blue-600 dark:text-blue-400">Processing your query...</div>
                            </div>
                        </div>
                        
                        <div id="nl-result" class="hidden space-y-4">
                            <div class="border-t border-gray-200 dark:border-gray-700 my-4 pt-4">
                                <h4 class="text-lg font-medium text-gray-800 dark:text-gray-200 mb-2">Generated SQL</h4>
                                <div class="relative">
                                    <div id="generated-sql" class="bg-gray-100 dark:bg-gray-900 p-4 rounded-md overflow-x-auto text-sm font-mono text-gray-900 dark:text-gray-300 border border-gray-300 dark:border-gray-700"></div>
                                    <button id="copy-sql" class="absolute top-2 right-2 p-1 bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded hover:bg-gray-300 dark:hover:bg-gray-600" title="Copy to clipboard">
                                        <i class="fas fa-copy"></i>
                                    </button>
                                </div>
                            </div>
                            
                            <div class="bg-gray-50 dark:bg-gray-800 p-4 rounded-md border border-gray-200 dark:border-gray-700">
                                <h4 class="text-gray-700 dark:text-gray-300 font-medium mb-2">Explanation</h4>
                                <p id="sql-explanation" class="text-sm text-gray-600 dark:text-gray-400"></p>
                            </div>
                            
                            <div class="flex justify-end space-x-2">
                                <button id="use-generated-sql" class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 transition-colors duration-200">
                                    <i class="fas fa-check mr-1"></i> Use This Query
                                </button>
                                <button id="refine-nl-query" class="px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-200 rounded-md hover:bg-gray-300 dark:hover:bg-gray-600 transition-colors duration-200">
                                    <i class="fas fa-edit mr-1"></i> Refine
                                </button>
                            </div>
                            
                            <div id="tables-info" class="text-xs text-gray-500 dark:text-gray-400 mt-2"></div>
                        </div>
                    </div>
                </div>
                <div class="px-6 py-4 bg-gray-50 dark:bg-gray-900 border-t border-gray-200 dark:border-gray-700 flex justify-between">
                    <div class="text-gray-500 dark:text-gray-400 text-sm">
                        <i class="fas fa-info-circle mr-1"></i> Results may vary based on database schema complexity
                    </div>
                    <button type="button" class="close-modal px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-200 rounded-md hover:bg-gray-300 dark:hover:bg-gray-600 transition-colors duration-200">
                        Close
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const naturalLanguageSqlBtn = document.getElementById('natural-language-sql');
            const nlToSqlModal = document.getElementById('nl-to-sql-modal');
            const nlQuery = document.getElementById('nl-query');
            const submitNlQuery = document.getElementById('submit-nl-query');
            const nlProcessing = document.getElementById('nl-processing');
            const nlResult = document.getElementById('nl-result');
            const generatedSql = document.getElementById('generated-sql');
            const sqlExplanation = document.getElementById('sql-explanation');
            const tablesInfo = document.getElementById('tables-info');
            const copySqlBtn = document.getElementById('copy-sql');
            const useGeneratedSqlBtn = document.getElementById('use-generated-sql');
            const refineNlQueryBtn = document.getElementById('refine-nl-query');
            
            const useAiApiToggle = document.getElementById('use-ai-api');
            const apiSettingsToggle = document.getElementById('api-settings-toggle');
            const apiSettingsPanel = document.getElementById('api-settings-panel');
            const apiKeyInput = document.getElementById('api-key');
            const apiModelSelect = document.getElementById('api-model');
            const apiProviderSelect = document.getElementById('api-provider');
            const saveApiSettingsBtn = document.getElementById('save-api-settings');
            
            const showComparisonBtn = document.getElementById('show-comparison');
            const comparisonSection = document.getElementById('comparison-section');
            

              function showNotification(message, type = 'info') {
                const notification = document.createElement('div');
                notification.className = `fixed bottom-4 right-4 px-4 py-2 rounded-md shadow-lg z-50 ${
                    type === 'error' ? 'bg-red-500 text-white' : 
                    type === 'warning' ? 'bg-yellow-500 text-white' : 
                    'bg-blue-500 text-white'
                }`;
                notification.innerHTML = message;
                
                document.body.appendChild(notification);
                
                setTimeout(() => {
                    notification.classList.add('opacity-0', 'transition-opacity', 'duration-500');
                    setTimeout(() => {
                        notification.remove();
                    }, 500);
                }, 3000);
            }
            loadApiSettings();
            
            if (naturalLanguageSqlBtn) {
                naturalLanguageSqlBtn.addEventListener('click', function() {
                    if (nlQuery) nlQuery.value = '';
                    if (nlResult) nlResult.classList.add('hidden');
                    
                    nlToSqlModal.classList.remove('hidden');
                });
            }
            
            if (useAiApiToggle) {
                useAiApiToggle.addEventListener('change', function() {
                    localStorage.setItem('sqlmanager_use_ai', this.checked);
                    
                    if (this.checked && apiSettingsPanel.classList.contains('hidden') && !getApiKey() && getApiProvider() !== 'deepseek-free') {
                        apiSettingsPanel.classList.remove('hidden');
                    }
                    
                    showNotification(this.checked ? 'AI mode enabled' : 'AI mode disabled', 'info');
                });
            }
            
            nlToSqlModal.querySelectorAll('.close-modal').forEach(btn => {
                btn.addEventListener('click', function() {
                    nlToSqlModal.classList.add('hidden');
                });
            });
            
            nlToSqlModal.addEventListener('click', function(e) {
                if (e.target === nlToSqlModal) {
                    nlToSqlModal.classList.add('hidden');
                }
            });
            
            if (apiSettingsToggle) {
                apiSettingsToggle.addEventListener('click', function() {
                    apiSettingsPanel.classList.toggle('hidden');
                    if (!apiSettingsPanel.classList.contains('hidden')) {
                        comparisonSection.classList.add('hidden');
                    }
                });
            }
            
            if (showComparisonBtn) {
                showComparisonBtn.addEventListener('click', function() {
                    comparisonSection.classList.toggle('hidden');
                    if (!comparisonSection.classList.contains('hidden')) {
                        apiSettingsPanel.classList.add('hidden');
                    }
                });
            }
            
            if (saveApiSettingsBtn) {
                saveApiSettingsBtn.addEventListener('click', function() {
                    saveApiSettings();
                    
                    apiSettingsPanel.classList.add('hidden');
                    
                    const provider = getApiProvider();
                    const needsKey = provider !== 'deepseek-free';
                    
                    if (needsKey && !getApiKey()) {
                        showNotification('Warning: API key is missing for ' + provider, 'warning');
                    } else {
                        showNotification('API settings saved', 'success');
                    }
                });
            }
            
            function saveApiSettings() {
                localStorage.setItem('sqlmanager_use_ai', useAiApiToggle.checked);
                localStorage.setItem('sqlmanager_api_key', apiKeyInput.value);
                localStorage.setItem('sqlmanager_api_model', apiModelSelect.value);
                localStorage.setItem('sqlmanager_api_provider', apiProviderSelect.value);
                
                if (apiProviderSelect.value === 'deepseek-free') {
                    apiKeyInput.parentElement.style.display = 'none';
                } else {
                    apiKeyInput.parentElement.style.display = '';
                }
            }
            
            if (submitNlQuery) {
                submitNlQuery.addEventListener('click', processNaturalLanguageQuery);
            }
            
            if (nlQuery) {
                nlQuery.addEventListener('keydown', function(e) {
                    if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) {
                        e.preventDefault();
                        processNaturalLanguageQuery();
                    }
                });
            }
            
            if (apiProviderSelect && apiModelSelect) {
                updateModelOptionsForProvider(apiProviderSelect.value || 'openai');
                
                apiProviderSelect.addEventListener('change', function() {
                    const provider = this.value;
                    updateModelOptionsForProvider(provider);
                    
                    localStorage.setItem('sqlmanager_api_provider', provider);
                    localStorage.setItem('sqlmanager_api_model', apiModelSelect.value);
                    
                    showNotification(`Provider changed to ${provider}`, 'info');
                });
                
                apiModelSelect.addEventListener('change', function() {
                    localStorage.setItem('sqlmanager_api_model', this.value);
                });
                
                apiKeyInput.addEventListener('input', function() {
                    localStorage.setItem('sqlmanager_api_key', this.value);
                });
            }
            
            function processNaturalLanguageQuery() {
                const query = nlQuery.value.trim();
                
                if (!query) {
                    showNotification('Please enter a query in natural language', 'error');
                    return;
                }
                
                const useAi = useAiApiToggle.checked;
                const apiProvider = getApiProvider();
                const apiKey = getApiKey();
                
                if (useAi && !apiKey && apiProvider !== 'deepseek-free') {
                    showNotification(`Please provide an API key for ${apiProvider}`, 'error');
                    apiSettingsPanel.classList.remove('hidden');
                    return;
                }
                
                nlProcessing.classList.remove('hidden');
                nlResult.classList.add('hidden');
                
                const currentDb = document.getElementById('current-db').textContent;
                
                const formData = new FormData();
                formData.append('nl_query', query);
                formData.append('database', currentDb);
                
                if (useAi) {
                    formData.append('use_ai', 'true');
                    formData.append('api_key', apiKey);
                    formData.append('api_model', getApiModel());
                    formData.append('api_provider', apiProvider);
                    
                    console.log(`Using ${apiProvider} model: ${getApiModel()}`);
                }
                
                fetch('ajax_handler.php?action=nl_to_sql', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    nlProcessing.classList.add('hidden');
                    
                    if (data.error) {
                        showNotification('Error: ' + data.error, 'error');
                        return;
                    }
                    
                    generatedSql.textContent = data.sql_query;
                    sqlExplanation.textContent = data.explanation;
                    
                    if (data.tables && data.tables.length > 0) {
                        tablesInfo.textContent = 'Tables used: ' + data.tables.join(', ');
                    } else {
                        tablesInfo.textContent = '';
                    }
                    
                    if (data.ai_used) {
                        const aiBadge = document.createElement('span');
                        aiBadge.className = 'ml-2 px-2 py-1 bg-purple-100 dark:bg-purple-900 text-purple-800 dark:text-purple-200 text-xs rounded';
                        aiBadge.textContent = 'AI Generated';
                        
                        const container = document.createElement('div');
                        container.className = 'flex items-start';
                        
                        const sqlPre = document.createElement('pre');
                        sqlPre.className = 'flex-grow';
                        sqlPre.textContent = data.sql_query;
                        
                        container.appendChild(sqlPre);
                        container.appendChild(aiBadge);
                        
                        generatedSql.innerHTML = '';
                        generatedSql.appendChild(container);
                    }
                    
                    nlResult.classList.remove('hidden');
                })
                .catch(error => {
                    nlProcessing.classList.add('hidden');
                    showNotification('Error: ' + error.message, 'error');
                });
            }
            
            function loadApiSettings() {
                console.log('Loading API settings');
                
                if (!localStorage.getItem('sqlmanager_api_provider')) {
                    localStorage.setItem('sqlmanager_api_provider', 'openai');
                }
                
                if (localStorage.getItem('sqlmanager_use_ai') !== null) {
                    useAiApiToggle.checked = localStorage.getItem('sqlmanager_use_ai') === 'true';
                }
                
                if (localStorage.getItem('sqlmanager_api_key')) {
                    apiKeyInput.value = localStorage.getItem('sqlmanager_api_key');
                }
                
                let selectedProvider = 'openai';
                if (localStorage.getItem('sqlmanager_api_provider')) {
                    selectedProvider = localStorage.getItem('sqlmanager_api_provider');
                    
                    let providerExists = false;
                    for (let i = 0; i < apiProviderSelect.options.length; i++) {
                        if (apiProviderSelect.options[i].value === selectedProvider) {
                            providerExists = true;
                            break;
                        }
                    }
                    
                    if (providerExists) {
                        apiProviderSelect.value = selectedProvider;
                    } else {
                        console.log('Saved provider not found, using default');
                        selectedProvider = 'openai';
                        apiProviderSelect.value = selectedProvider;
                    }
                }
                
                updateModelOptionsForProvider(selectedProvider);
                
                if (localStorage.getItem('sqlmanager_api_model')) {
                    const savedModel = localStorage.getItem('sqlmanager_api_model');
                    console.log('Saved model from localStorage:', savedModel);
                    
                    const availableOptions = [];
                    apiModelSelect.querySelectorAll(`optgroup.${selectedProvider} option`).forEach(option => {
                        availableOptions.push(option.value);
                    });
                    
                    console.log('Available options for provider:', availableOptions);
                    
                    if (availableOptions.includes(savedModel)) {
                        console.log('Setting model to saved value:', savedModel);
                        apiModelSelect.value = savedModel;
                    } else if (availableOptions.length > 0) {
                        console.log('Saved model not available, using first option:', availableOptions[0]);
                        apiModelSelect.value = availableOptions[0];
                        localStorage.setItem('sqlmanager_api_model', apiModelSelect.value);
                    }
                } else if (apiModelSelect.options.length > 0) {
                    const firstOption = apiModelSelect.querySelector(`optgroup.${selectedProvider} option`);
                    if (firstOption) {
                        apiModelSelect.value = firstOption.value;
                        localStorage.setItem('sqlmanager_api_model', firstOption.value);
                    }
                }
            }
            
            function getApiKey() {
                return apiKeyInput.value.trim();
            }
            
            function getApiModel() {
                return apiModelSelect.value;
            }
            
            function getApiProvider() {
                return apiProviderSelect.value;
            }
            
            if (apiProviderSelect) {
                apiProviderSelect.addEventListener('change', function() {
                    updateModelOptionsForProvider(this.value);
                });
            }
            
            function updateModelOptionsForProvider(provider) {
                console.log('Updating model options for provider:', provider);
                
                document.querySelectorAll('.model-option').forEach(group => {
                    group.style.display = 'none';
                });
                
                const modelOptions = document.querySelectorAll(`.model-option.${provider}`);
                modelOptions.forEach(group => {
                    group.style.display = '';
                });
                
                const currentModel = apiModelSelect.value;
                let isCurrentModelValid = false;
                let availableOptions = [];
                
                apiModelSelect.querySelectorAll('option').forEach(option => {
                    if (option.parentElement.classList.contains(provider) && 
                        option.parentElement.style.display !== 'none') {
                        availableOptions.push(option.value);
                        if (option.value === currentModel) {
                            isCurrentModelValid = true;
                        }
                    }
                });
                
                console.log('Current model:', currentModel, 'Is valid:', isCurrentModelValid);
                console.log('Available options:', availableOptions);
                
                if (!isCurrentModelValid && availableOptions.length > 0) {
                    apiModelSelect.value = availableOptions[0];
                    console.log('Setting model to first available option:', availableOptions[0]);
                }
                
                if (provider === 'deepseek-free') {
                    apiKeyInput.parentElement.style.display = 'none';
                } else {
                    apiKeyInput.parentElement.style.display = '';
                }
            }
            
            if (copySqlBtn) {
                copySqlBtn.addEventListener('click', function() {
                    const preElement = generatedSql.querySelector('pre');
                    const sql = preElement ? preElement.textContent.trim() : generatedSql.textContent.trim();
                    
                    navigator.clipboard.writeText(sql).then(() => {
                        showNotification('SQL copied to clipboard', 'success');
                    }).catch(err => {
                        showNotification('Could not copy SQL: ' + err.message, 'error');
                    });
                });
            }
            
            if (useGeneratedSqlBtn) {
                useGeneratedSqlBtn.addEventListener('click', function() {
                    const preElement = generatedSql.querySelector('pre');
                    const sql = preElement ? preElement.textContent.trim() : generatedSql.textContent.trim();
                    
                    if (typeof editor !== 'undefined') {
                        editor.setValue(sql);
                    }
                    
                    nlToSqlModal.classList.add('hidden');
                    
                    showNotification('Natural language query converted to SQL', 'success');
                });
            }
            
            if (refineNlQueryBtn) {
                refineNlQueryBtn.addEventListener('click', function() {
                    nlResult.classList.add('hidden');
                    
                    nlQuery.focus();
                });
            }
            
            const speechToTextBtn = document.getElementById('speech-to-text');
            if (speechToTextBtn && 'webkitSpeechRecognition' in window) {
                const recognition = new webkitSpeechRecognition();
                recognition.continuous = false;
                recognition.interimResults = false;
                recognition.lang = 'en-US';
                
                let isListening = false;
                
                speechToTextBtn.addEventListener('click', function() {
                    if (!isListening) {
                        recognition.start();
                        isListening = true;
                        speechToTextBtn.innerHTML = '<i class="fas fa-microphone-slash text-red-500"></i>';
                        speechToTextBtn.classList.remove('bg-blue-600', 'hover:bg-blue-700');
                        speechToTextBtn.classList.add('bg-red-600', 'hover:bg-red-700');
                        showNotification('Listening... Speak now', 'info');
                    } else {
                        recognition.stop();
                        isListening = false;
                        speechToTextBtn.innerHTML = '<i class="fas fa-microphone"></i>';
                        speechToTextBtn.classList.remove('bg-red-600', 'hover:bg-red-700');
                        speechToTextBtn.classList.add('bg-blue-600', 'hover:bg-blue-700');
                    }
                });
                
                recognition.onresult = function(event) {
                    const transcript = event.results[0][0].transcript;
                    nlQuery.value = nlQuery.value ? nlQuery.value + ' ' + transcript : transcript;
                    isListening = false;
                    speechToTextBtn.innerHTML = '<i class="fas fa-microphone"></i>';
                    speechToTextBtn.classList.remove('bg-red-600', 'hover:bg-red-700');
                    speechToTextBtn.classList.add('bg-blue-600', 'hover:bg-blue-700');
                    showNotification('Speech captured!', 'info');
                };
                
                recognition.onerror = function(event) {
                    console.error('Speech recognition error', event.error);
                    isListening = false;
                    speechToTextBtn.innerHTML = '<i class="fas fa-microphone"></i>';
                    speechToTextBtn.classList.remove('bg-red-600', 'hover:bg-red-700');
                    speechToTextBtn.classList.add('bg-blue-600', 'hover:bg-blue-700');
                    showNotification('Speech recognition error: ' + event.error, 'error');
                };
                
                recognition.onend = function() {
                    isListening = false;
                    speechToTextBtn.innerHTML = '<i class="fas fa-microphone"></i>';
                    speechToTextBtn.classList.remove('bg-red-600', 'hover:bg-red-700');
                    speechToTextBtn.classList.add('bg-blue-600', 'hover:bg-blue-700');
                };
            } else if (speechToTextBtn) {
                speechToTextBtn.style.display = 'none';
                console.warn('Speech recognition not supported by this browser');
            }
        });
    </script>
</body>
</html> 
</html> 