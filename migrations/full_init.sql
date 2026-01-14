-- FULL DATABASE INIT SCRIPT (users, nfts, verification_requests)

-- 1. USERS TABLE
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    role VARCHAR(32) NOT NULL DEFAULT 'user',
    wallet VARCHAR(255) DEFAULT NULL,
    is_verified TINYINT(1) DEFAULT 0
);

-- 2. NFTS TABLE
CREATE TABLE IF NOT EXISTS nfts (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  creator_id INT UNSIGNED NOT NULL,
  owner_id INT UNSIGNED DEFAULT NULL,
  name VARCHAR(191) NOT NULL,
  description TEXT,
  price DECIMAL(16,8) DEFAULT 0,
  category VARCHAR(100) DEFAULT NULL,
  image_url VARCHAR(255) DEFAULT NULL,
  is_featured TINYINT(1) DEFAULT 0,
  is_deleted TINYINT(1) DEFAULT 0,
  is_approved TINYINT(1) DEFAULT 0,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  INDEX (creator_id),
  INDEX (owner_id),
  INDEX (category)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. VERIFICATION REQUESTS TABLE
CREATE TABLE IF NOT EXISTS verification_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    description TEXT NOT NULL,
    image_url VARCHAR(255) NOT NULL,
    status ENUM('pending','approved','rejected') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- 4. POPULATE USERS
-- (exemplu, poți adăuga/edita după nevoie)
INSERT INTO users (id, username, email, password, status, role, is_verified, wallet) VALUES
(1, 'admin', 'admin@example.com', '$2y$10$hashadmin', 'active', 'owner', 1, '0xADMINWALLET'),
(2, 'user1', 'user1@example.com', '$2y$10$hashuser1', 'active', 'user', 0, NULL);

-- 5. POPULATE NFTS
INSERT INTO nfts (id, name, description, image_url, creator_id, price, category, owner_id, created_at) VALUES
(1, 'Galactic Phoenix', 'A fiery bird rising from cosmic ashes.', 'img/1.jpg', 1, 5.88, 'Digital Art', 2, '2021-03-12 14:20:10');

-- 8. WISHLIST TABLE (user <-> nft many-to-many)
CREATE TABLE IF NOT EXISTS wishlist (
  user_id INT NOT NULL,
  nft_id INT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id, nft_id),
  CONSTRAINT fk_wishlist_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_wishlist_nft FOREIGN KEY (nft_id) REFERENCES nfts(id) ON DELETE CASCADE
);

-- 6. POPULATE VERIFICATION REQUESTS (optional)
INSERT INTO verification_requests (user_id, description, image_url, status) VALUES
(2, 'Please verify my account', 'img/verify1.jpg', 'pending');

-- 7. UPDATE/ALTERS (dacă ai nevoie de modificări ulterioare)
-- ALTER TABLE users MODIFY status VARCHAR(20) NOT NULL DEFAULT 'active';
-- UPDATE users SET role = 'owner' WHERE id = 1;
-- UPDATE users SET status = 'disabled' WHERE status = 'inactive';

-- Adaugă/editează INSERT-urile după nevoie pentru date reale/test.
