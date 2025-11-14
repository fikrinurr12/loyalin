-- ==========================================
-- MIGRATION SCRIPT: Single to Multi-Tenant
-- Web Royalty Restaurant Loyalty System
-- ==========================================

-- Step 1: Create businesses table
CREATE TABLE IF NOT EXISTS businesses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    business_name VARCHAR(255) NOT NULL,
    business_slug VARCHAR(255) UNIQUE NOT NULL,
    owner_name VARCHAR(255),
    phone VARCHAR(15),
    email VARCHAR(255),
    address TEXT,
    logo_path VARCHAR(255),
    
    -- Points Settings per Business
    points_ratio INT DEFAULT 10000,
    
    -- Branding Colors
    primary_color VARCHAR(7) DEFAULT '#e63946',
    secondary_color VARCHAR(7) DEFAULT '#f1faee',
    accent_color VARCHAR(7) DEFAULT '#f8a963',
    
    -- Subscription Management
    subscription_status ENUM('active', 'suspended', 'cancelled') DEFAULT 'active',
    subscription_start_date DATE,
    subscription_end_date DATE,
    monthly_fee DECIMAL(10,2) DEFAULT 50000,
    
    -- Business Status
    status ENUM('active', 'inactive') DEFAULT 'active',
    
    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_slug (business_slug),
    INDEX idx_status (status),
    INDEX idx_subscription (subscription_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Step 2: Create subscription_payments table
CREATE TABLE IF NOT EXISTS subscription_payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    business_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    payment_date DATE NOT NULL,
    payment_method VARCHAR(50),
    period_start DATE NOT NULL,
    period_end DATE NOT NULL,
    status ENUM('paid', 'pending', 'failed') DEFAULT 'pending',
    notes TEXT,
    proof_image VARCHAR(255),
    verified_by INT,
    verified_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE,
    FOREIGN KEY (verified_by) REFERENCES users(id) ON DELETE SET NULL,
    
    INDEX idx_business (business_id),
    INDEX idx_status (status),
    INDEX idx_payment_date (payment_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Step 3: Add business_id to existing tables

-- Modify users table
ALTER TABLE users ADD COLUMN business_id INT AFTER id;
ALTER TABLE users ADD COLUMN is_active TINYINT(1) DEFAULT 1 AFTER role;

-- Drop old unique constraint on phone
ALTER TABLE users DROP INDEX phone;

-- Add new unique constraint (phone + business_id)
ALTER TABLE users ADD UNIQUE KEY unique_phone_business (phone, business_id);

-- Add foreign key
ALTER TABLE users ADD CONSTRAINT fk_users_business 
    FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE;

-- Modify rewards table
ALTER TABLE rewards ADD COLUMN business_id INT AFTER id;
ALTER TABLE rewards ADD CONSTRAINT fk_rewards_business 
    FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE;

-- Modify transactions table
ALTER TABLE transactions ADD COLUMN business_id INT AFTER id;
ALTER TABLE transactions ADD CONSTRAINT fk_transactions_business 
    FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE;

-- Modify redemptions table
ALTER TABLE redemptions ADD COLUMN business_id INT AFTER id;
ALTER TABLE redemptions ADD CONSTRAINT fk_redemptions_business 
    FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE;

-- Modify settings table
ALTER TABLE settings ADD COLUMN business_id INT AFTER id;
-- NULL business_id means global setting for superadmin
ALTER TABLE settings DROP INDEX setting_key;
ALTER TABLE settings ADD UNIQUE KEY unique_key_business (setting_key, business_id);

-- Step 4: Create default business for existing data
INSERT INTO businesses (
    business_name, 
    business_slug, 
    owner_name, 
    phone, 
    email, 
    address,
    points_ratio,
    subscription_status,
    subscription_start_date,
    subscription_end_date,
    status
) VALUES (
    'Rumah Makan Sate (Default)', 
    'sate-default',
    'Admin',
    '628123456789',
    'info@suricrypt-sate.com',
    'Jl. Sate Lezat No. 123, Jakarta',
    10000,
    'active',
    CURDATE(),
    DATE_ADD(CURDATE(), INTERVAL 1 YEAR),
    'active'
);

-- Get the ID of the default business
SET @default_business_id = LAST_INSERT_ID();

-- Step 5: Update existing data with default business_id
UPDATE users SET business_id = @default_business_id WHERE business_id IS NULL;
UPDATE rewards SET business_id = @default_business_id WHERE business_id IS NULL;
UPDATE transactions SET business_id = @default_business_id WHERE business_id IS NULL;
UPDATE redemptions SET business_id = @default_business_id WHERE business_id IS NULL;
UPDATE settings SET business_id = @default_business_id WHERE business_id IS NULL;

-- Step 6: Make business_id NOT NULL after migration
ALTER TABLE users MODIFY business_id INT NOT NULL;
ALTER TABLE rewards MODIFY business_id INT NOT NULL;
ALTER TABLE transactions MODIFY business_id INT NOT NULL;
ALTER TABLE redemptions MODIFY business_id INT NOT NULL;

-- Step 7: Add indexes for better performance
ALTER TABLE users ADD INDEX idx_business_role (business_id, role);
ALTER TABLE rewards ADD INDEX idx_business_status (business_id, status);
ALTER TABLE transactions ADD INDEX idx_business_user (business_id, user_id);
ALTER TABLE redemptions ADD INDEX idx_business_status (business_id, status);

-- Step 8: Create table for user business access (if one user can access multiple businesses)
CREATE TABLE IF NOT EXISTS user_business_access (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_phone VARCHAR(15) NOT NULL,
    business_id INT NOT NULL,
    user_id INT NOT NULL,
    last_accessed TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    UNIQUE KEY unique_phone_business_access (user_phone, business_id),
    FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    
    INDEX idx_phone (user_phone),
    INDEX idx_business (business_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Populate user_business_access with existing users
INSERT INTO user_business_access (user_phone, business_id, user_id)
SELECT phone, business_id, id FROM users WHERE role = 'customer';

-- Step 9: Add activity log table
CREATE TABLE IF NOT EXISTS activity_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    business_id INT,
    user_id INT,
    action VARCHAR(255) NOT NULL,
    description TEXT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    
    INDEX idx_business (business_id),
    INDEX idx_user (user_id),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Step 10: Insert sample businesses for testing
INSERT INTO businesses (
    business_name, 
    business_slug, 
    owner_name, 
    phone, 
    email, 
    address,
    points_ratio,
    primary_color,
    subscription_status,
    subscription_start_date,
    subscription_end_date
) VALUES 
(
    'Warung Bubur Ayam Mantap', 
    'bubur-mantap',
    'Budi Santoso',
    '628111222333',
    'info@buburmantap.com',
    'Jl. Bubur No. 45, Bandung',
    5000,
    '#4CAF50',
    'active',
    CURDATE(),
    DATE_ADD(CURDATE(), INTERVAL 1 MONTH)
),
(
    'Kedai Kopi Nusantara', 
    'kopi-nusantara',
    'Siti Rahma',
    '628222333444',
    'hello@kopinusantara.com',
    'Jl. Kopi No. 78, Surabaya',
    8000,
    '#795548',
    'active',
    CURDATE(),
    DATE_ADD(CURDATE(), INTERVAL 1 MONTH)
);

-- Migration Complete!
-- ==========================================
-- NOTES:
-- 1. Existing data has been migrated to default business
-- 2. Two sample businesses have been added for testing
-- 3. All relationships and constraints are properly set
-- 4. Indexes added for performance optimization
-- ==========================================
