<?php

require_once 'vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

require_once 'src/config/database.php';

function createAdminUser() {
    try {
        $db = Database::getInstance()->getConnection();
        
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM admins WHERE email = ?");
        $stmt->execute([getenv('ADMIN_EMAIL')]);
        $result = $stmt->fetch();
        
        if ($result['count'] == 0) {
        
            $password = getenv('ADMIN_PASSWORD') ?: 'Admin@123';
            $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
            
            $uuid = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
                mt_rand(0, 0xffff), mt_rand(0, 0xffff),
                mt_rand(0, 0xffff),
                mt_rand(0, 0x0fff) | 0x4000,
                mt_rand(0, 0x3fff) | 0x8000,
                mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
            );
            
            $stmt = $db->prepare("INSERT INTO admins (id, email, password, name, role) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([
                $uuid,
                getenv('ADMIN_EMAIL'),
                $hashedPassword,
                'Super Admin',
                'super_admin'
            ]);
            
            echo "✅ Admin user created successfully!\n";
            echo "📧 Email: " . getenv('ADMIN_EMAIL') . "\n";
            echo "🔑 Password: " . $password . "\n";
            echo "⚠️  IMPORTANT: Change these credentials after first login!\n";
        } else {
            echo "✅ Admin user already exists\n";
        }
        
        echo "✅ Setup completed successfully!\n";
        echo "📊 Server URL: http://localhost:8000\n";
        echo "📊 Health check: http://localhost:8000/health\n";
        
    } catch (Exception $e) {
        echo "❌ Setup failed: " . $e->getMessage() . "\n";
    }
}


createAdminUser();