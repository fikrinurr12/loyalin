-- Create the database
CREATE DATABASE IF NOT EXISTS suricrypt_loyalty;
USE suricrypt_loyalty;

-- Users table
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    phone VARCHAR(15) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'customer') NOT NULL DEFAULT 'customer',
    total_points INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Rewards table
CREATE TABLE IF NOT EXISTS rewards (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    points_required INT NOT NULL,
    description TEXT,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Transactions table
CREATE TABLE IF NOT EXISTS transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    points_earned INT NOT NULL,
    transaction_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Redemptions table
CREATE TABLE IF NOT EXISTS redemptions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    reward_id INT NOT NULL,
    points_used INT NOT NULL,
    redemption_code VARCHAR(20) NOT NULL UNIQUE,
    status ENUM('pending', 'redeemed') DEFAULT 'pending',
    redemption_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (reward_id) REFERENCES rewards(id) ON DELETE CASCADE
);
-- Buat tabel settings untuk penyimpanan konfigurasi sistem
CREATE TABLE IF NOT EXISTS settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(50) NOT NULL UNIQUE,
    setting_value TEXT NOT NULL,
    setting_description TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Tambahkan pengaturan default
INSERT INTO settings (setting_key, setting_value, setting_description) VALUES
('points_ratio', '10000', 'Jumlah transaksi (Rp) untuk mendapatkan 1 poin'),
('site_name', 'SuriCrypt Loyalty - Rumah Makan Sate', 'Nama situs'),
('resto_address', 'Jl. Sate Lezat No. 123, Jakarta', 'Alamat rumah makan'),
('resto_phone', '021-1234567', 'Nomor telepon rumah makan'),
('resto_email', 'info@suricrypt-sate.com', 'Email rumah makan');

-- Ubah tabel users untuk menambahkan role superadmin
ALTER TABLE users MODIFY COLUMN role ENUM('superadmin', 'admin', 'customer') NOT NULL DEFAULT 'customer';

-- Tambahkan user superadmin (password: Super#123)
INSERT INTO users (name, phone, password, role) VALUES 
('Superadmin', '6282123456789', '$2y$10$DdBWe0B1dB1dGz5k4POXhe39x3C.pYM1z.8Qkp.XT5PBR5BkYO7Ei', 'superadmin');

-- Insert admin user
INSERT INTO users (name, phone, password, role) VALUES 
('Admin', '628123456789', '$2y$10$uCE/MnJQUKF1LZmVYZsT7u0sG1/DOnJJVzdA6d6YHqHSJwgu1fNLa', 'admin');

-- Insert sample rewards
INSERT INTO rewards (name, points_required, description) VALUES 
('1 Tusuk Sate', 25, 'Gratis 1 tusuk sate pilihan'),
('Es Teh Manis', 50, 'Gratis 1 gelas es teh manis'),
('Sate Paket Kecil', 100, 'Gratis 5 tusuk sate dengan nasi dan sambal');