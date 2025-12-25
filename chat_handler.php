<?php
// chat_handler.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// File database
$databaseFile = 'database-chat.json';
$usersFile = 'database-users.json';

// Fungsi untuk membaca file JSON
function readJSON($file) {
    if (!file_exists($file)) {
        return [];
    }
    
    $content = file_get_contents($file);
    return json_decode($content, true) ?? [];
}

// Fungsi untuk menulis ke file JSON
function writeJSON($file, $data) {
    $json = json_encode($data, JSON_PRETTY_PRINT);
    return file_put_contents($file, $json);
}

// Inisialisasi file jika belum ada
if (!file_exists($databaseFile)) {
    writeJSON($databaseFile, []);
}

if (!file_exists($usersFile)) {
    writeJSON($usersFile, []);
}

// Ambil aksi dari request
$action = $_POST['action'] ?? '';

// Proses berdasarkan aksi
switch ($action) {
    case 'login':
        handleLogin();
        break;
        
    case 'send_message':
        handleSendMessage();
        break;
        
    case 'get_messages':
        handleGetMessages();
        break;
        
    case 'get_users':
        handleGetUsers();
        break;
        
    case 'update_user_online':
        handleUpdateUserOnline();
        break;
        
    default:
        echo json_encode(['success' => false, 'error' => 'Aksi tidak valid']);
        break;
}

// Fungsi untuk handle login
function handleLogin() {
    global $usersFile;
    
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    
    // Validasi
    if (empty($username) || empty($password)) {
        echo json_encode(['success' => false, 'error' => 'Username dan password harus diisi']);
        return;
    }
    
    if (strlen($username) < 3) {
        echo json_encode(['success' => false, 'error' => 'Username minimal 3 karakter']);
        return;
    }
    
    $users = readJSON($usersFile);
    
    // Cek apakah user sudah ada
    $userExists = false;
    foreach ($users as $user) {
        if ($user['username'] === $username) {
            $userExists = true;
            // Dalam implementasi nyata, password harus di-hash
            if ($user['password'] === $password) {
                // Update last login
                $user['last_login'] = time();
                echo json_encode(['success' => true, 'message' => 'Login berhasil']);
                return;
            } else {
                echo json_encode(['success' => false, 'error' => 'Password salah']);
                return;
            }
        }
    }
    
    // Jika user belum ada, daftarkan
    if (!$userExists) {
        $newUser = [
            'username' => $username,
            'password' => $password, // Catatan: Dalam implementasi nyata harus di-hash
            'created_at' => time(),
            'last_login' => time()
        ];
        
        $users[] = $newUser;
        writeJSON($usersFile, $users);
        
        echo json_encode(['success' => true, 'message' => 'Pendaftaran berhasil']);
    }
}

// Fungsi untuk mengirim pesan
function handleSendMessage() {
    global $databaseFile;
    
    $username = trim($_POST['username'] ?? '');
    $message = trim($_POST['message'] ?? '');
    
    if (empty($username) || empty($message)) {
        echo json_encode(['success' => false, 'error' => 'Username dan pesan harus diisi']);
        return;
    }
    
    // Filter kata-kata kasar (contoh sederhana)
    $bannedWords = ['kasar', 'jorok', 'spam'];
    $filteredMessage = $message;
    foreach ($bannedWords as $word) {
        $filteredMessage = str_ireplace($word, '***', $filteredMessage);
    }
    
    $messages = readJSON($databaseFile);
    
    // Batasi jumlah pesan yang disimpan (max 100 pesan)
    if (count($messages) >= 100) {
        array_shift($messages); // Hapus pesan tertua
    }
    
    // Tambah pesan baru
    $newMessage = [
        'id' => uniqid(),
        'username' => $username,
        'message' => $filteredMessage,
        'timestamp' => time()
    ];
    
    $messages[] = $newMessage;
    
    // Simpan ke file
    if (writeJSON($databaseFile, $messages)) {
        echo json_encode(['success' => true, 'message' => 'Pesan terkirim']);
    } else {
        echo json_encode(['success' => false, 'error' => 'Gagal menyimpan pesan']);
    }
}

// Fungsi untuk mengambil pesan
function handleGetMessages() {
    global $databaseFile;
    
    $messages = readJSON($databaseFile);
    
    // Format timestamp ke format yang lebih mudah dibaca
    foreach ($messages as &$message) {
        $message['time_display'] = date('H:i', $message['timestamp']);
    }
    
    echo json_encode(['success' => true, 'messages' => $messages]);
}

// Fungsi untuk mengambil daftar pengguna online
function handleGetUsers() {
    global $usersFile;
    
    $users = readJSON($usersFile);
    $onlineUsers = [];
    
    // Anggap user online jika login dalam 5 menit terakhir
    $onlineThreshold = time() - 300; // 5 menit
    
    foreach ($users as $user) {
        if ($user['last_login'] >= $onlineThreshold) {
            $onlineUsers[] = [
                'username' => $user['username'],
                'last_active' => $user['last_login']
            ];
        }
    }
    
    echo json_encode(['success' => true, 'users' => $onlineUsers]);
}

// Fungsi untuk update status online user
function handleUpdateUserOnline() {
    global $usersFile;
    
    $username = trim($_POST['username'] ?? '');
    
    if (empty($username)) {
        echo json_encode(['success' => false, 'error' => 'Username harus diisi']);
        return;
    }
    
    $users = readJSON($usersFile);
    
    // Update last login untuk user
    foreach ($users as &$user) {
        if ($user['username'] === $username) {
            $user['last_login'] = time();
            break;
        }
    }
    
    writeJSON($usersFile, $users);
    
    echo json_encode(['success' => true, 'message' => 'Status online diperbarui']);
}
?>