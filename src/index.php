<?php
session_start();

// Include configuration file
require_once 'LiteDBAdmin/config.php';

if (isset($_SESSION['dev_authenticated']) && $_SESSION['dev_authenticated'] === true) {
    header("Location: ./");
    exit();
}

$error = "";
$attempts_remaining = $max_login_attempts;
$is_locked = false;
$wait_time = 0;

if (!isset($_SESSION['login_attempts'])) {
    $_SESSION['login_attempts'] = 0;
    $_SESSION['last_attempt_time'] = 0;
}

if ($_SESSION['login_attempts'] >= $max_login_attempts) {
    $time_elapsed = time() - $_SESSION['last_attempt_time'];
    if ($time_elapsed < $lockout_time) {
        $is_locked = true;
        $wait_time = $lockout_time - $time_elapsed;
        $error = "Too many failed attempts. Please try again in " . ceil($wait_time / 60) . " minutes.";
    } else {
        $_SESSION['login_attempts'] = 0;
    }
}

$attempts_remaining = $max_login_attempts - $_SESSION['login_attempts'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$is_locked) {
    if (isset($_POST['password'])) {
        $password = $_POST['password'];
        
        $_SESSION['last_attempt_time'] = time();
        
        if ($password === $dev_password) {
            $_SESSION['login_attempts'] = 0;
            
            $_SESSION['dev_authenticated'] = true;
            
            header("Location: LiteDBAdmin/");
            exit();
        } else {
            $_SESSION['login_attempts']++;
            
            if ($_SESSION['login_attempts'] >= $max_login_attempts) {
                $error = "Too many failed attempts. Please try again in " . ceil($lockout_time / 60) . " minutes.";
                $is_locked = true;
            } else {
                $attempts_remaining = $max_login_attempts - $_SESSION['login_attempts'];
                $error = "Invalid password. Attempts remaining: $attempts_remaining";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LiteDBAdmin Login</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {}
            }
        }
    </script>
    <style>
        .dark body {
            background-color: #1a202c;
            color: #e2e8f0;
        }
        .dark .bg-white {
            background-color: #2d3748;
        }
        .dark .text-gray-800 {
            color: #f7fafc;
        }
        .dark .text-gray-600, .dark .text-gray-700 {
            color: #cbd5e0;
        }
        .dark .border {
            border-color: #4a5568;
        }
        .dark input {
            background-color: #1a202c;
            color: #e2e8f0;
            border-color: #4a5568;
        }
        .dark .shadow-md {
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.5);
        }
    </style>
</head>
<body class="bg-gray-900 min-h-screen flex items-center justify-center">
    <div class="bg-gray-800 p-8 rounded-lg shadow-md w-full max-w-md border border-gray-700">
        <div class="text-center mb-6">
            <h1 class="text-2xl font-bold text-white">Developer Access</h1>
            <p class="text-gray-400">Enter developer password to continue</p>
            <div class="mt-2 text-yellow-400 text-sm italic">
                <i class="fas fa-exclamation-triangle mr-1"></i> Warning: You're entering the secret developer zone! 
                <br>With great power comes great responsibility.
            </div>
        </div>
        
        <?php if (!empty($error)): ?>
        <div class="bg-red-900 border border-red-700 text-red-300 px-4 py-3 rounded mb-4">
            <?php echo htmlspecialchars($error); ?>
        </div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div class="mb-4">
                <label for="password" class="block text-gray-300 text-sm font-bold mb-2">Password</label>
                <input type="password" id="password" name="password" 
                       class="shadow appearance-none border border-gray-700 rounded w-full py-2 px-3 text-gray-300 bg-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:border-blue-500"
                       <?php echo $is_locked ? 'disabled' : ''; ?>>
            </div>
            
            <div class="flex items-center justify-between">
                <button type="submit" 
                        class="bg-blue-700 hover:bg-blue-800 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline w-full <?php echo $is_locked ? 'opacity-50 cursor-not-allowed' : ''; ?>"
                        <?php echo $is_locked ? 'disabled' : ''; ?>>
                    <i class="fas fa-lock-open mr-2"></i> Login
                </button>
            </div>
            
            <div class="mt-4 text-center">
                <a href="../" class="text-sm text-blue-400 hover:text-blue-300">
                    <i class="fas fa-arrow-left mr-1"></i> Back
                </a>
                <p class="text-gray-500 text-xs mt-2">Note: Only authorized developers should access this area. 
                <br>Regular users should turn back now!</p>
            </div>
        </form>
    </div>
</body>
</html>
