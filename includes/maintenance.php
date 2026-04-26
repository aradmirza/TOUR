<?php
// Include this at the top of public pages to enforce maintenance mode.
// Must be called AFTER db.php and auth.php are loaded.
// Admins bypass maintenance mode automatically.
if (!isLoggedIn()) {
    $result = $db->query("SELECT setting_value FROM site_settings WHERE setting_key='maintenance_mode' LIMIT 1");
    if ($result) {
        $row = $result->fetch_row();
        if ($row && $row[0] === '1') {
            http_response_code(503);
            die('<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Maintenance</title>
                <style>body{font-family:sans-serif;display:flex;align-items:center;justify-content:center;min-height:100vh;background:#f1f5f9;margin:0}
                .box{text-align:center;padding:40px;background:#fff;border-radius:12px;box-shadow:0 4px 20px rgba(0,0,0,.08);}
                h1{font-size:48px;margin-bottom:8px}h2{color:#1e293b;margin-bottom:8px}p{color:#64748b}</style>
                </head><body><div class="box"><h1>🔧</h1><h2>Under Maintenance</h2>
                <p>We\'ll be back shortly. Thank you for your patience.</p></div></body></html>');
        }
    }
}
