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
    balance_eth DECIMAL(16,8) NOT NULL DEFAULT 0,
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

-- 9. TRADES TABLE (open and future accepted trades)
CREATE TABLE IF NOT EXISTS trades (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nft_id INT UNSIGNED NOT NULL,
  seller_id INT NOT NULL,
  buyer_id INT DEFAULT NULL,
  price DECIMAL(16,8) NOT NULL,
  status ENUM('open','accepted','cancelled') DEFAULT 'open',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  accepted_at DATETIME DEFAULT NULL,
  PRIMARY KEY (id),
  INDEX (nft_id),
  INDEX (seller_id),
  INDEX (status),
  CONSTRAINT fk_trades_nft FOREIGN KEY (nft_id) REFERENCES nfts(id) ON DELETE CASCADE,
  CONSTRAINT fk_trades_seller FOREIGN KEY (seller_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_trades_buyer FOREIGN KEY (buyer_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 10. BALANCE TRANSACTIONS TABLE (deposit / withdraw / trade history)
CREATE TABLE IF NOT EXISTS balance_transactions (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT NOT NULL,
  type ENUM('deposit','withdraw','buy','sell') NOT NULL,
  nft_id INT UNSIGNED DEFAULT NULL,
  nft_name VARCHAR(191) DEFAULT NULL,
  amount DECIMAL(16,8) NOT NULL,
  balance_after DECIMAL(16,8) NOT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  INDEX (user_id),
  INDEX (created_at),
  CONSTRAINT fk_balance_tx_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 11. OFFERS TABLE (price offers on NFTs)
CREATE TABLE IF NOT EXISTS offers (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nft_id INT UNSIGNED NOT NULL,
  seller_id INT NOT NULL,
  buyer_id INT NOT NULL,
  price DECIMAL(16,8) NOT NULL,
  parent_offer_id INT UNSIGNED DEFAULT NULL,
  funds_reserved TINYINT(1) NOT NULL DEFAULT 1,
  status ENUM('open','accepted','rejected','cancelled') NOT NULL DEFAULT 'open',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  INDEX (nft_id),
  INDEX (seller_id),
  INDEX (buyer_id),
   INDEX (parent_offer_id),
  INDEX (status),
  CONSTRAINT fk_offers_nft FOREIGN KEY (nft_id) REFERENCES nfts(id) ON DELETE CASCADE,
  CONSTRAINT fk_offers_seller FOREIGN KEY (seller_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_offers_buyer FOREIGN KEY (buyer_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. POPULATE VERIFICATION REQUESTS (optional)
INSERT INTO verification_requests (user_id, description, image_url, status) VALUES
(2, 'Please verify my account', 'img/verify1.jpg', 'pending');

-- 7. UPDATE/ALTERS (dacă ai nevoie de modificări ulterioare)
-- ALTER TABLE users MODIFY status VARCHAR(20) NOT NULL DEFAULT 'active';
-- UPDATE users SET role = 'owner' WHERE id = 1;
-- UPDATE users SET status = 'disabled' WHERE status = 'inactive';

-- Adaugă/editează INSERT-urile după nevoie pentru date reale/test.
