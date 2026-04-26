<?php
// ============================================================
// Admin Auth Middleware & URL Helpers
// ============================================================

function requireAdmin() {
    if (!isset($_SESSION['admin_id'])) {
        header('Location: ' . adminBaseUrl() . 'login.php');
        exit;
    }
}

function isAdminLoggedIn() {
    return isset($_SESSION['admin_id']);
}

function currentAdmin() {
    return [
        'id'    => $_SESSION['admin_id']    ?? null,
        'name'  => $_SESSION['admin_name']  ?? '',
        'email' => $_SESSION['admin_email'] ?? '',
    ];
}

function setAdminSession($admin) {
    $_SESSION['admin_id']    = $admin['id'];
    $_SESSION['admin_name']  = $admin['name'];
    $_SESSION['admin_email'] = $admin['email'];
}

// URL to /admin/ directory
function adminBaseUrl() {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'];
    $script = $_SERVER['SCRIPT_NAME'];
    if (preg_match('#^(.*?/admin)/#i', $script, $m)) {
        return $protocol . $host . $m[1] . '/';
    }
    return $protocol . $host . rtrim(dirname($script), '/') . '/';
}

// URL to site root (above /admin/)
function rootBaseUrl() {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'];
    $script = $_SERVER['SCRIPT_NAME'];
    if (preg_match('#^(.*?)/admin/#i', $script, $m)) {
        return $protocol . $host . ($m[1] ?: '') . '/';
    }
    return $protocol . $host . '/';
}

// URL for uploaded files from admin context
function uploadUrl($folder, $filename) {
    if (!$filename) return '';
    return rootBaseUrl() . 'uploads/' . $folder . '/' . htmlspecialchars($filename, ENT_QUOTES, 'UTF-8');
}

// Site settings helper
function getSetting($db, $key, $default = '') {
    $stmt = $db->prepare("SELECT setting_value FROM site_settings WHERE setting_key = ? LIMIT 1");
    $stmt->bind_param("s", $key);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_row();
    return $row ? (string)$row[0] : $default;
}

function setSetting($db, $key, $value) {
    $stmt = $db->prepare(
        "INSERT INTO site_settings (setting_key, setting_value) VALUES (?,?)
         ON DUPLICATE KEY UPDATE setting_value = ?"
    );
    $stmt->bind_param("sss", $key, $value, $value);
    return $stmt->execute();
}

// Admin-scoped flash messages
function adminFlash($message, $type = 'success') {
    $_SESSION['admin_flash'] = ['message' => $message, 'type' => $type];
}

function showAdminFlash() {
    if (!isset($_SESSION['admin_flash'])) return '';
    $f = $_SESSION['admin_flash'];
    unset($_SESSION['admin_flash']);
    $icons = ['success' => '✅', 'danger' => '❌', 'warning' => '⚠️', 'info' => 'ℹ️'];
    $icon = $icons[$f['type']] ?? 'ℹ️';
    $type = htmlspecialchars($f['type'], ENT_QUOTES, 'UTF-8');
    $msg  = htmlspecialchars($f['message'], ENT_QUOTES, 'UTF-8');
    return "<div class=\"admin-alert admin-alert-{$type}\">{$icon} {$msg}</div>";
}

// CSRF helpers for admin
function adminCsrfToken() {
    if (empty($_SESSION['admin_csrf'])) {
        $_SESSION['admin_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['admin_csrf'];
}

function adminCsrfField() {
    return '<input type="hidden" name="admin_csrf" value="' . adminCsrfToken() . '">';
}

function verifyAdminCsrf() {
    $token = $_POST['admin_csrf'] ?? '';
    return !empty($token) && hash_equals($_SESSION['admin_csrf'] ?? '', $token);
}
