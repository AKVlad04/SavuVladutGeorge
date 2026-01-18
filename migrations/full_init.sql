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


INSERT INTO `users` (`id`, `username`, `email`, `password`, `status`, `role`, `is_verified`, `wallet`) VALUES
(4, 'ioana.dumitru', 'ioana.d@yahoo.com', '$2y$10$L9.fB3.d6.f5.d6.f5.d6.f5.d6.f5.d6.f5.d6.f5.d6.f5.d6.f5', 'active', 'user', 0, NULL),
(5, 'george.alex', 'george.alex89@outlook.com', '$2y$10$M0.gC4.f7.g6.f7.g6.f7.g6.f7.g6.f7.g6.f7.g6.f7.g6.f7.g6', 'active', 'user', 0, '0x93E9878GD9cd00d120fgD973D9623D7h8f01298B'),
(6, 'roxana_ionescu', 'roxana.ionescu@hotmail.com', '$2y$10$N1.hD5.g8.h7.g8.h7.g8.h7.g8.h7.g8.h7.g8.h7.g8.h7.g8.h7', 'inactive', 'user', 0, NULL),
(7, 'daniel_p', 'danielp23@gmail.com', '$2y$10$O2.iE6.h9.i8.h9.i8.h9.i8.h9.i8.h9.i8.h9.i8.h9.i8.h9.i8', 'active', 'user', 0, '0xA4F0989HE0de11e231ghE084E0734E8i9g12309C'),
(8, 'ana_maria95', 'ana_maria95@yahoo.com', '$2y$10$P3.jF7.i0.j9.i0.j9.i0.j9.i0.j9.i0.j9.i0.j9.i0.j9.i0.j9', 'banned', 'user', 0, NULL),
(9, 'dragos_m', 'dragos.mihnea@gmail.com', '$2y$10$Q4.kG8.j1.k0.j1.k0.j1.k0.j1.k0.j1.k0.j1.k0.j1.k0.j1.k0', 'active', 'user', 0, '0xB5G1090IF1ef22f342hiF195F1845F9j0h23410D'),
(10, 'elena_vl', 'elena.vl@yahoo.com', '$2y$10$R5.lH9.k2.l1.k2.l1.k2.l1.k2.l1.k2.l1.k2.l1.k2.l1.k2.l1', 'active', 'user', 0, NULL),
(11, 'marius_costin', 'm.costin@hotmail.com', '$2y$10$S6.mI0.l3.m2.l3.m2.l3.m2.l3.m2.l3.m2.l3.m2.l3.m2.l3.m2', 'active', 'user', 0, '0xC6H2101JG2fg33g453ijG206G2956G0k1i34521E'),
(12, 'adelina_stefan', 'adelina.s@yahoo.com', '$2y$10$T7.nJ1.m4.n3.m4.n3.m4.n3.m4.n3.m4.n3.m4.n3.m4.n3.m4.n3', 'inactive', 'user', 0, '0xD7I3212KH3gh44h564jkH317H3067H1l2j45632F'),
(13, 'alex_tudor', 'tudor.alex@gmail.com', '$2y$10$U8.oK2.n5.o4.n5.o4.n5.o4.n5.o4.n5.o4.n5.o4.n5.o4.n5.o4', 'active', 'user', 0, NULL),
(14, 'bianca_d', 'bianca.d123@gmail.com', '$2y$10$V9.pL3.o6.p5.o6.p5.o6.p5.o6.p5.o6.p5.o6.p5.o6.p5.o6.p5', 'banned', 'user', 0, NULL),
(15, 'raul_93', 'raul93@outlook.com', '$2y$10$W0.qM4.p7.q6.p7.q6.p7.q6.p7.q6.p7.q6.p7.q6.p7.q6.p7.q6', 'active', 'user', 0, '0xE8J4323LI4hi55i675klI428I4178I2m3k56743G'),
(16, 'irina_p', 'irinap@yahoo.com', '$2y$10$X1.rN5.q8.r7.q8.r7.q8.r7.q8.r7.q8.r7.q8.r7.q8.r7.q8.r7', 'active', 'user', 0, '0xF9K5434MJ5ij66j786lmJ539J5289J3n4l67854H'),
(17, 'vlad_ghita', 'vladghita@gmail.com', '$2y$10$Y2.sO6.r9.s8.r9.s8.r9.s8.r9.s8.r9.s8.r9.s8.r9.s8.r9.s8', 'inactive', 'user', 0, NULL),
(18, 'crina_i', 'crinai89@yahoo.com', '$2y$10$Z3.tP7.s0.t9.s0.t9.s0.t9.s0.t9.s0.t9.s0.t9.s0.t9.s0.t9', 'active', 'user', 0, '0x0AL6545NK6jk77k897mnK640K6390K4o5m78965I'),
(19, 'bogdan_velea', 'bogdan.velea@live.com', '$2y$10$A4.uQ8.t1.u0.t1.u0.t1.u0.t1.u0.t1.u0.t1.u0.t1.u0.t1.u0', 'active', 'user', 0, NULL),
(20, 'carla_b', 'carla_b@yahoo.com', '$2y$10$B5.vR9.u2.v1.u2.v1.u2.v1.u2.v1.u2.v1.u2.v1.u2.v1.u2.v1', 'banned', 'user', 0, '0x1BM7656OL7kl88l908noL751L7401L5p6n89076J'),
(21, 'mihai_balan', 'mihai.balan@gmail.com', '$2y$10$C6.wS0.v3.w2.v3.w2.v3.w2.v3.w2.v3.w2.v3.w2.v3.w2.v3.w2', 'active', 'user', 0, NULL),
(22, 'florentina_c', 'florentina.c@yahoo.com', '$2y$10$D7.xT1.w4.x3.w4.x3.w4.x3.w4.x3.w4.x3.w4.x3.w4.x3.w4.x3', 'inactive', 'user', 0, '0x2CN8767PM8lm99m019opM862M8512M6q7o90187K'),
(23, 'stefan_i', 'stefan_i@outlook.com', '$2y$10$E8.yU2.x5.y4.x5.y4.x5.y4.x5.y4.x5.y4.x5.y4.x5.y4.x5.y4', 'active', 'user', 0, NULL),
(24, 'teodora_v', 'teodora.vlad@gmail.com', '$2y$10$F9.zV3.y6.z5.y6.z5.y6.z5.y6.z5.y6.z5.y6.z5.y6.z5.y6.z5', 'active', 'user', 0, '0x3DO9878QN9mn00n120pqN973N9623N7r8p01298L'),
(25, 'paul_cristian', 'paul.cristian@yahoo.com', '$2y$10$G0.aW4.z7.a6.z7.a6.z7.a6.z7.a6.z7.a6.z7.a6.z7.a6.z7.a6', 'banned', 'user', 0, NULL),
(26, 'daria_maria', 'daria_maria@hotmail.com', '$2y$10$H1.bX5.a8.b7.a8.b7.a8.b7.a8.b7.a8.b7.a8.b7.a8.b7.a8.b7', 'active', 'user', 0, '0x4EP0989RO0no11o231qrO084O0734O8s9q12309M'),
(27, 'ionut_m', 'ionut.m@example.com', '$2y$10$I2.cY6.b9.c8.b9.c8.b9.c8.b9.c8.b9.c8.b9.c8.b9.c8.b9.c8', 'active', 'user', 0, NULL),
(28, 'adina_popescu', 'adina.p@gmail.com', '$2y$10$J3.dZ7.c0.d9.c0.d9.c0.d9.c0.d9.c0.d9.c0.d9.c0.d9.c0.d9', 'inactive', 'user', 0, '0x5FQ1090SP1op22p342rsP195P1845P9t0r23410N'),
(29, 'lucian_f', 'lucianf@yahoo.com', '$2y$10$K4.eA8.d1.e0.d1.e0.d1.e0.d1.e0.d1.e0.d1.e0.d1.e0.d1.e0', 'active', 'user', 0, NULL),
(30, 'sorina_d', 'sorina_d@live.com', '$2y$10$L5.fB9.e2.f1.e2.f1.e2.f1.e2.f1.e2.f1.e2.f1.e2.f1.e2.f1', 'banned', 'user', 0, '0x6GR2101TQ2pq33q453stQ206Q2956Q0u1s34521O'),
(31, 'laura_c', 'laura_c@yahoo.com', '$2y$10$M6.gC0.f3.g2.f3.g2.f3.g2.f3.g2.f3.g2.f3.g2.f3.g2.f3.g2', 'active', 'user', 0, NULL),
(32, 'david_r', 'david.robert@gmail.com', '$2y$10$N7.hD1.g4.h3.g4.h3.g4.h3.g4.h3.g4.h3.g4.h3.g4.h3.g4.h3', 'active', 'user', 0, '0x7HS3212UR3qr44r564tuR317R3067R1v2t45632P'),
(33, 'madalina_t', 'madalina.t89@gmail.com', '$2y$10$O8.iE2.h5.i4.h5.i4.h5.i4.h5.i4.h5.i4.h5.i4.h5.i4.h5.i4', 'inactive', 'user', 0, NULL),
(34, 'andrei_c', 'andrei.c@yahoo.com', '$2y$10$P9.jF3.i6.j5.i6.j5.i6.j5.i6.j5.i6.j5.i6.j5.i6.j5.i6.j5', 'active', 'user', 0, '0x8IT4323VS4rs55s675uvS428S4178S2w3u56743Q'),
(35, 'roxana_p', 'roxana.p@outlook.com', '$2y$10$Q0.kG4.j7.k6.j7.k6.j7.k6.j7.k6.j7.k6.j7.k6.j7.k6.j7.k6', 'active', 'user', 0, '0x9JU5434WT5st66t786vwT539T5289T3x4v67854R'),
(36, 'tudor_m', 'tudor.mihai@gmail.com', '$2y$10$R1.lH5.k8.l7.k8.l7.k8.l7.k8.l7.k8.l7.k8.l7.k8.l7.k8.l7', 'banned', 'user', 0, NULL),
(37, 'georgiana_l', 'georgiana.l@yahoo.com', '$2y$10$S2.mI6.l9.m8.l9.m8.l9.m8.l9.m8.l9.m8.l9.m8.l9.m8.l9.m8', 'active', 'user', 0, NULL),
(38, 'sebi_p', 'sebi_p@yahoo.com', '$2y$10$T3.nJ7.m0.n9.m0.n9.m0.n9.m0.n9.m0.n9.m0.n9.m0.n9.m0.n9', 'inactive', 'user', 0, '0x0KV6545XU6tu77u897wxU640U6390U4y5w78965S'),
(39, 'emilia_s', 'emilia.s@outlook.com', '$2y$10$U4.oK8.n1.o0.n1.o0.n1.o0.n1.o0.n1.o0.n1.o0.n1.o0.n1.o0', 'active', 'user', 0, '0x1LW7656YV7uv88v908xyV751V7401V5z6x89076T'),
(40, 'radu_n', 'radu.nistor@gmail.com', '$2y$10$V5.pL9.o2.p1.o2.p1.o2.p1.o2.p1.o2.p1.o2.p1.o2.p1.o2.p1', 'active', 'user', 0, NULL),
(41, 'alina_c', 'alina.c@yahoo.com', '$2y$10$W6.qM0.p3.q2.p3.q2.p3.q2.p3.q2.p3.q2.p3.q2.p3.q2.p3.q2', 'active', 'user', 0, '0x2MX8767ZW8vw99w019yzW862W8512W6a7y90187U'),
(42, 'gabriel_d', 'gabriel.daniel@yahoo.com', '$2y$10$X7.rN1.q4.r3.q4.r3.q4.r3.q4.r3.q4.r3.q4.r3.q4.r3.q4.r3', 'inactive', 'user', 0, NULL),
(43, 'valentina_f', 'valentina.f@gmail.com', '$2y$10$Y8.sO2.r5.s4.r5.s4.r5.s4.r5.s4.r5.s4.r5.s4.r5.s4.r5.s4', 'active', 'user', 0, '0x3NY9878AX9wx00x120zaX973X9623X7b8z01298V'),
(44, 'octavian_m', 'octavian.m@yahoo.com', '$2y$10$Z9.tP3.s6.t5.s6.t5.s6.t5.s6.t5.s6.t5.s6.t5.s6.t5.s6.t5', 'banned', 'user', 0, NULL),
(45, 'elisa_b', 'elisa.balint@gmail.com', '$2y$10$A0.uQ4.t7.u6.t7.u6.t7.u6.t7.u6.t7.u6.t7.u6.t7.u6.t7.u6', 'active', 'user', 0, '0x4OZ0989BY0xy11y231abY084Y0734Y8c9a12309W'),
(46, 'andreea_f', 'andreea.f@yahoo.com', '$2y$10$B1.vR5.u8.v7.u8.v7.u8.v7.u8.v7.u8.v7.u8.v7.u8.v7.u8.v7', 'inactive', 'user', 0, NULL),
(47, 'horia_v', 'horia.vlad@yahoo.com', '$2y$10$C2.wS6.v9.w8.v9.w8.v9.w8.v9.w8.v9.w8.v9.w8.v9.w8.v9.w8', 'active', 'user', 0, '0x5PA1090CZ1yz22z342bcZ195Z1845Z9d0b23410X'),
(48, 'nicoleta_d', 'nicoleta.d@outlook.com', '$2y$10$D3.xT7.w0.x9.w0.x9.w0.x9.w0.x9.w0.x9.w0.x9.w0.x9.w0.x9', 'active', 'user', 0, NULL),
(49, 'cosmin_p', 'cosminp@yahoo.com', '$2y$10$E4.yU8.x1.y0.x1.y0.x1.y0.x1.y0.x1.y0.x1.y0.x1.y0.x1.y0', 'banned', 'user', 0, '0x6QB2101DA2za33a453cdA206A2956A0e1c34521Y'),
(50, 'sabin_g', 'sabin.gheorghe@gmail.com', '$2y$10$F5.zV9.y2.z1.y2.z1.y2.z1.y2.z1.y2.z1.y2.z1.y2.z1.y2.z1', 'active', 'user', 0, NULL),
(51, 'adrian_m', 'adrian.matei@gmail.com', '$2y$10$G6.aW0.z3.a2.z3.a2.z3.a2.z3.a2.z3.a2.z3.a2.z3.a2.z3.a2', 'active', 'user', 0, '0x7RC3212EB3ab44b564deB317B3067B1f2d45632Z'),
(52, 'cristina_g', 'cristina.gheorghe@yahoo.com', '$2y$10$H7.bX1.a4.b3.a4.b3.a4.b3.a4.b3.a4.b3.a4.b3.a4.b3.a4.b3', 'inactive', 'user', 0, '0x8SD4323FC4bc55c675efC428C4178C2g3e56743A'),
(53, 'mihnea_t', 'mihnea.toma@outlook.com', '$2y$10$I8.cY2.b5.c4.b5.c4.b5.c4.b5.c4.b5.c4.b5.c4.b5.c4.b5.c4', 'active', 'user', 0, NULL),
(54, 'elena_i', 'elena.ionescu@gmail.com', '$2y$10$J9.dZ3.c6.d5.c6.d5.c6.d5.c6.d5.c6.d5.c6.d5.c6.d5.c6.d5', 'active', 'user', 0, '0x9TE5434GD5cd66d786fgD539D5289D3h4f67854B'),
(55, 'daniel_c', 'daniel.ciobanu@yahoo.com', '$2y$10$K0.eA4.d7.e6.d7.e6.d7.e6.d7.e6.d7.e6.d7.e6.d7.e6.d7.e6', 'banned', 'user', 0, NULL),
(56, 'claudia_r', 'claudia.radu@hotmail.com', '$2y$10$L1.fB5.e8.f7.e8.f7.e8.f7.e8.f7.e8.f7.e8.f7.e8.f7.e8.f7', 'active', 'user', 0, '0x0UF6545HE6de77e897ghE640E6390E4i5g78965C'),
(57, 'raul_b', 'raul.balan@gmail.com', '$2y$10$M2.gC6.f9.g8.f9.g8.f9.g8.f9.g8.f9.g8.f9.g8.f9.g8.f9.g8', 'inactive', 'user', 0, NULL),
(58, 'bianca_m', 'bianca.muntean@outlook.com', '$2y$10$N3.hD7.g0.h9.g0.h9.g0.h9.g0.h9.g0.h9.g0.h9.g0.h9.g0.h9', 'active', 'user', 0, '0x1VG7656IF7ef88f908hiF751F7401F5j6h89076D'),
(59, 'george_t', 'george.tudor@yahoo.com', '$2y$10$O4.iE8.h1.i0.h1.i0.h1.i0.h1.i0.h1.i0.h1.i0.h1.i0.h1.i0', 'active', 'user', 0, NULL),
(60, 'andreea_n', 'andreea.nistor@gmail.com', '$2y$10$P5.jF9.i2.j1.i2.j1.i2.j1.i2.j1.i2.j1.i2.j1.i2.j1.i2.j1', 'banned', 'user', 0, '0x2WH8767JG8fg99g019ijG862G8512G6k7i90187E'),
(61, 'lucia_s', 'lucia.stefan@hotmail.com', '$2y$10$Q6.kG0.j3.k2.j3.k2.j3.k2.j3.k2.j3.k2.j3.k2.j3.k2.j3.k2', 'active', 'user', 0, '0x3XI9878KH9gh00h120jkH973H9623H7l8j01298F'),
(62, 'marius_v', 'marius.vlad@yahoo.com', '$2y$10$R7.lH1.k4.l3.k4.l3.k4.l3.k4.l3.k4.l3.k4.l3.k4.l3.k4.l3', 'active', 'user', 0, NULL),
(63, 'roxana_d', 'roxana.dima@gmail.com', '$2y$10$S8.mI2.l5.m4.l5.m4.l5.m4.l5.m4.l5.m4.l5.m4.l5.m4.l5.m4', 'inactive', 'user', 0, '0x4YJ0989LI0hi11i231klI084I0734I8m9k12309G'),
(64, 'florin_p', 'florin.popescu@outlook.com', '$2y$10$T9.nJ3.m6.n5.m6.n5.m6.n5.m6.n5.m6.n5.m6.n5.m6.n5.m6.n5', 'active', 'user', 0, NULL),
(65, 'mariana_l', 'mariana.lazar@yahoo.com', '$2y$10$U0.oK4.n7.o6.n7.o6.n7.o6.n7.o6.n7.o6.n7.o6.n7.o6.n7.o6', 'active', 'user', 0, '0x5ZK1090MJ1ij22j342lmJ195J1845J9n0l23410H'),
(66, 'ovidiu_c', 'ovidiu.ciuca@gmail.com', '$2y$10$V1.pL5.o8.p7.o8.p7.o8.p7.o8.p7.o8.p7.o8.p7.o8.p7.o8.p7', 'banned', 'user', 0, NULL),
(67, 'cristina_b', 'cristina.balan@yahoo.com', '$2y$10$W2.qM6.p9.q8.p9.q8.p9.q8.p9.q8.p9.q8.p9.q8.p9.q8.p9.q8', 'active', 'user', 0, '0x6AL2101NK2jk33k453mnK206K2956K0o1m34521I'),
(68, 'alexandru_f', 'alexandru.florin@gmail.com', '$2y$10$X3.rN7.q0.r9.q0.r9.q0.r9.q0.r9.q0.r9.q0.r9.q0.r9.q0.r9', 'active', 'user', 0, '0x7BM3212OL3kl44l564noL317L3067L1p2n45632J'),
(69, 'simona_m', 'simona.mihai@outlook.com', '$2y$10$Y4.sO8.r1.s0.r1.s0.r1.s0.r1.s0.r1.s0.r1.s0.r1.s0.r1.s0', 'inactive', 'user', 0, NULL),
(70, 'daniela_r', 'daniela.rusu@yahoo.com', '$2y$10$Z5.tP9.s2.t1.s2.t1.s2.t1.s2.t1.s2.t1.s2.t1.s2.t1.s2.t1', 'active', 'user', 0, '0x8CN4323PM4lm55m675opM428M4178M2q3o56743K'),
(71, 'valentin_g', 'valentin.gheorghiu@gmail.com', '$2y$10$A6.uQ0.t3.u2.t3.u2.t3.u2.t3.u2.t3.u2.t3.u2.t3.u2.t3.u2', 'active', 'user', 0, NULL),
(72, 'andrei_m', 'andrei.marinescu@yahoo.com', '$2y$10$B7.vR1.u4.v3.u4.v3.u4.v3.u4.v3.u4.v3.u4.v3.u4.v3.u4.v3', 'active', 'user', 0, '0x9DO5434QN5mn66n786pqN539N5289N3r4p67854L'),
(73, 'cristian_s', 'cristian.stan@hotmail.com', '$2y$10$C8.wS2.v5.w4.v5.w4.v5.w4.v5.w4.v5.w4.v5.w4.v5.w4.v5.w4', 'banned', 'user', 0, '0x0EP6545RO6no77o897qrO640O6390O4s5q78965M'),
(74, 'elisa_p', 'elisa.popa@gmail.com', '$2y$10$D9.xT3.w6.x5.w6.x5.w6.x5.w6.x5.w6.x5.w6.x5.w6.x5.w6.x5', 'active', 'user', 0, NULL),
(75, 'florin_i', 'florin.ionescu@yahoo.com', '$2y$10$E0.yU4.x7.y6.x7.y6.x7.y6.x7.y6.x7.y6.x7.y6.x7.y6.x7.y6', 'inactive', 'user', 0, '0x1FQ7656SP7op88p908rsP751P7401P5t6r89076N'),
(76, 'ioana_b', 'ioana.bogdan@outlook.com', '$2y$10$F1.zV5.y8.z7.y8.z7.y8.z7.y8.z7.y8.z7.y8.z7.y8.z7.y8.z7', 'active', 'user', 0, NULL),
(77, 'marius_t', 'marius.tanase@gmail.com', '$2y$10$G2.aW6.z9.a8.z9.a8.z9.a8.z9.a8.z9.a8.z9.a8.z9.a8.z9.a8', 'active', 'user', 0, '0x2GR8767TQ8pq99q019stQ862Q8512Q6u7s90187O'),
(78, 'adela_c', 'adela.constantin@yahoo.com', '$2y$10$H3.bX7.a0.b9.a0.b9.a0.b9.a0.b9.a0.b9.a0.b9.a0.b9.a0.b9', 'inactive', 'user', 0, NULL),
(79, 'bogdan_r', 'bogdan.rusu@gmail.com', '$2y$10$I4.cY8.b1.c0.b1.c0.b1.c0.b1.c0.b1.c0.b1.c0.b1.c0.b1.c0', 'active', 'user', 0, '0x3HS9878UR9qr00r120tuR973R9623R7v8t01298P'),
(80, 'cristina_v', 'cristina.vlad@yahoo.com', '$2y$10$J5.dZ9.c2.d1.c2.d1.c2.d1.c2.d1.c2.d1.c2.d1.c2.d1.c2.d1', 'active', 'user', 0, '0x4IT0989VS0rs11s231uvS084S0734S8w9u12309Q'),
(81, 'ioan_s', 'ioan.stanescu@hotmail.com', '$2y$10$K6.eA0.d3.e2.d3.e2.d3.e2.d3.e2.d3.e2.d3.e2.d3.e2.d3.e2', 'banned', 'user', 0, NULL),
(82, 'alina_d', 'alina.dumitru@gmail.com', '$2y$10$L7.fB1.e4.f3.e4.f3.e4.f3.e4.f3.e4.f3.e4.f3.e4.f3.e4.f3', 'active', 'user', 0, '0x5JU1090WT1st22t342vwT195T1845T9x0v23410R'),
(83, 'daniel_f', 'daniel.florin@yahoo.com', '$2y$10$M8.gC2.f5.g4.f5.g4.f5.g4.f5.g4.f5.g4.f5.g4.f5.g4.f5.g4', 'active', 'user', 0, NULL),
(84, 'elena_c', 'elena.ciobanu@outlook.com', '$2y$10$N9.hD3.g6.h5.g6.h5.g6.h5.g6.h5.g6.h5.g6.h5.g6.h5.g6.h5', 'inactive', 'user', 0, '0x6KV2101XU2tu33u453wxU206U2956U0y1w34521S'),
(85, 'alex_t', 'alex.toma@gmail.com', '$2y$10$O0.iE4.h7.i6.h7.i6.h7.i6.h7.i6.h7.i6.h7.i6.h7.i6.h7.i6', 'active', 'user', 0, NULL),
(86, 'cristian_m', 'cristian.muntean@yahoo.com', '$2y$10$P1.jF5.i8.j7.i8.j7.i8.j7.i8.j7.i8.j7.i8.j7.i8.j7.i8.j7', 'active', 'user', 0, '0x7LW3212YV3uv44v564xyV317V3067V1z2x45632T'),
(87, 'andreea_s', 'andreea.stefan@gmail.com', '$2y$10$Q2.kG6.j9.k8.j9.k8.j9.k8.j9.k8.j9.k8.j9.k8.j9.k8.j9.k8', 'banned', 'user', 0, NULL),
(88, 'mihai_r', 'mihai.radu@hotmail.com', '$2y$10$R3.lH7.k0.l9.k0.l9.k0.l9.k0.l9.k0.l9.k0.l9.k0.l9.k0.l9', 'active', 'user', 0, '0x8MX4323ZW4vw55w675yzW428W4178W2a3y56743U'),
(89, 'georgiana_m', 'georgiana.mihai@yahoo.com', '$2y$10$S4.mI8.l1.m0.l1.m0.l1.m0.l1.m0.l1.m0.l1.m0.l1.m0.l1.m0', 'inactive', 'user', 0, NULL),
(90, 'raul_c', 'raul.costin@gmail.com', '$2y$10$T5.nJ9.m2.n1.m2.n1.m2.n1.m2.n1.m2.n1.m2.n1.m2.n1.m2.n1', 'active', 'user', 0, '0x9NY5434AX5wx66x786zaX539X5289X3b4z67854V'),
(91, 'simona_t', 'simona.tudor@outlook.com', '$2y$10$U6.oK0.n3.o2.n3.o2.n3.o2.n3.o2.n3.o2.n3.o2.n3.o2.n3.o2', 'active', 'user', 0, '0x0OZ6545BY6xy77y897abY640Y6390Y4c5a78965W'),
(92, 'bogdan_p', 'bogdan.popa@yahoo.com', '$2y$10$V7.pL1.o4.p3.o4.p3.o4.p3.o4.p3.o4.p3.o4.p3.o4.p3.o4.p3', 'active', 'user', 0, NULL),
(93, 'maria_l', 'maria.lupu@gmail.com', '$2y$10$W8.qM2.p5.q4.p5.q4.p5.q4.p5.q4.p5.q4.p5.q4.p5.q4.p5.q4', 'inactive', 'user', 0, '0x1PA7656CZ7yz88z908bcZ751Z7401Z5d6b89076X'),
(94, 'alexandru_s', 'alexandru.silva@hotmail.com', '$2y$10$X9.rN3.q6.r5.q6.r5.q6.r5.q6.r5.q6.r5.q6.r5.q6.r5.q6.r5', 'active', 'user', 0, NULL),
(95, 'valentina_r', 'valentina.rusu@yahoo.com', '$2y$10$Y0.sO4.r7.s6.r7.s6.r7.s6.r7.s6.r7.s6.r7.s6.r7.s6.r7.s6', 'active', 'user', 0, '0x2QB8767DA8za99a019cdA862A8512A6e7c90187Y'),
(96, 'florin_m', 'florin.mihail@gmail.com', '$2y$10$Z1.tP5.s8.t7.s8.t7.s8.t7.s8.t7.s8.t7.s8.t7.s8.t7.s8.t7', 'banned', 'user', 0, NULL),
(97, 'cristina_t', 'cristina.tanase@yahoo.com', '$2y$10$A2.uQ6.t9.u8.t9.u8.t9.u8.t9.u8.t9.u8.t9.u8.t9.u8.t9.u8', 'active', 'user', 0, '0x3RC9878EB9ab00b120deB973B9623B7f8d01298Z'),
(98, 'daniel_r', 'daniel.radu@gmail.com', '$2y$10$B3.vR7.u0.v9.u0.v9.u0.v9.u0.v9.u0.v9.u0.v9.u0.v9.u0.v9', 'active', 'user', 0, '0x4SD0989FC0bc11c231efC084C0734C8g9e12309A'),
(99, 'elena_m', 'elena.muntean@hotmail.com', '$2y$10$C4.wS8.v1.w0.v1.w0.v1.w0.v1.w0.v1.w0.v1.w0.v1.w0.v1.w0', 'inactive', 'user', 0, NULL),
(100, 'gabriel_p', 'gabriel.popa@yahoo.com', '$2y$10$D5.xT9.w2.x1.w2.x1.w2.x1.w2.x1.w2.x1.w2.x1.w2.x1.w2.x1', 'active', 'user', 0, '0x5TE1090GD1cd22d342fgD195D1845D9h0f23410B');

ALTER TABLE `users` MODIFY `status` VARCHAR(20) NOT NULL DEFAULT 'active';

UPDATE `users`
SET `status` = 'disabled'
WHERE `status` = 'inactive';

SELECT * FROM `users` WHERE `status` = 'disabled';



INSERT INTO `nfts` (`id`, `name`, `description`, `image_url`, `creator_id`, `price`, `category`, `owner_id`, `created_at`) VALUES
(1, 'Galactic Phoenix', 'A fiery bird rising from cosmic ashes.', 'img/1.jpg', 1, 5.88, 'Digital Art', 45, '2021-03-12 14:20:10'),
(2, 'Neon Samurai', 'Cyberpunk warrior with glowing katana.', 'img/2.jpg', 4, 3.32, 'Collectibles', 12, '2020-11-05 09:15:22'),
(3, 'Echoes of Eternity', 'Ambient soundscape to soothe your mind.', 'img/3.jpg', 18, 0.48, 'Music', 89, '2023-06-21 18:45:00'),
(4, 'Shadow Racer', 'Sleek hovercar ready for futuristic races.', 'img/4.jpg', 1, 9.40, 'Gaming', 33, '2022-01-15 11:30:45'),
(5, 'Mystic Forest', 'Enchanting trees glowing under moonlight.', 'img/5.jpg', 18, 2.24, 'Digital Art', 67, '2019-08-30 22:10:05'),
(6, 'Pixel Gladiator', 'Retro-style pixel hero in battle stance.', 'img/6.jpg', 19, 1.16, 'Collectibles', 5, '2024-02-14 16:55:33'),
(7, 'Lunar Oasis', 'Virtual island bathed in silver moonlight.', 'img/7.jpg', 3, 16.80, 'Virtual Real Estate', 99, '2025-12-01 08:00:00'),
(8, 'Sonic Pulse', 'High-energy electronic beat for your mix.', 'img/8.jpg', 9, 0.36, 'Music', 23, '2020-04-10 13:25:11'),
(9, 'Cybernet Warrior', 'Armored fighter with neon circuitry.', 'img/9.jpg', 3, 4.52, 'Gaming', 56, '2021-09-19 20:40:50'),
(10, 'Solar Winds', 'Abstract art inspired by solar flares.', 'img/10.jpg', 1, 2.70, 'Digital Art', 1, '2023-03-03 10:10:10'),
(11, 'Phantom Falcon', 'Silent predator of the digital skies.', 'img/11.jpg', 43, 2.96, 'Collectibles', 78, '2019-12-25 07:30:25'),
(12, 'Bassline Surge', 'Deep bass track to energize any party.', 'img/12.jpg', 4, 0.60, 'Music', 44, '2022-07-08 23:59:59'),
(13, 'Virtual Skater', 'Agile avatar for the urban metaverse.', 'img/13.jpg', 18, 1.28, 'Gaming', 10, '2024-05-20 15:15:15'),
(14, 'Crystal Caverns', 'Gem-filled caves sparkling with magic.', 'img/14.jpg', 47, 3.94, 'Digital Art', 92, '2020-01-05 12:00:00'),
(15, 'Digital Nomad', 'Explorer avatar roaming virtual worlds.', 'img/15.jpg', 45, 1.10, 'Collectibles', 31, '2021-10-31 19:20:30'),
(16, 'Hyperdrive Rally', 'Intense racing game NFT with speed boost.', 'img/16.jpg', 43, 7.76, 'Gaming', 55, '2023-08-15 14:45:00'),
(17, 'Oceanic Dreamscape', 'Peaceful underwater digital painting.', 'img/17.jpg', 62, 3.26, 'Digital Art', 81, '2022-05-05 09:30:20'),
(18, 'Retro Synthwave', 'Nostalgic 80’s synth music track.', 'img/18.jpg', 62, 0.44, 'Music', 19, '2019-11-11 21:00:00'),
(19, 'Virtual Penthouse', 'Luxury sky-high apartment in VR city.', 'img/19.jpg', 64, 35.56, 'Virtual Real Estate', 100, '2025-01-01 00:01:00'),
(20, 'Galactic Crusader', 'Space hero fighting for cosmic justice.', 'img/20.jpg', 43, 2.88, 'Collectibles', 3, '2020-06-15 17:10:45'),
(21, 'Neon Horizon', 'Futuristic cityscape glowing at dusk.', 'img/21.jpg', 45, 6.60, 'Digital Art', 28, '2021-04-22 06:45:10'),
(22, 'Cyber Hawk', 'Swift drone scout with razor-sharp vision.', 'img/22.jpg', 21, 4.92, 'Collectibles', 72, '2023-12-10 13:50:30'),
(23, 'Pulsewave', 'Energetic EDM track with deep bass drops.', 'img/23.jpg', 19, 0.72, 'Music', 41, '2024-09-29 20:20:20'),
(24, 'Titan Mech', 'Massive robot warrior built for battle.', 'img/24.jpg', 9, 10.80, 'Gaming', 50, '2019-02-14 11:11:11'),
(25, 'Velvet Grove', 'Mystical forest with glowing flora.', 'img/25.jpg', 18, 3.76, 'Digital Art', 85, '2022-03-18 16:30:00'),
(26, 'Pixel Ninja', 'Stealthy pixel avatar ready for action.', 'img/26.jpg', 61, 1.24, 'Collectibles', 17, '2020-08-08 08:08:08'),
(27, 'Crystal Harbor', 'Virtual beachfront property with stunning views.', 'img/27.jpg', 61, 22.48, 'Virtual Real Estate', 63, '2025-06-21 12:00:00'),
(28, 'Sonic Bloom', 'Vibrant electronic melody for chill vibes.', 'img/28.jpg', 61, 0.88, 'Music', 9, '2021-01-20 22:45:15'),
(29, 'Quantum Racer', 'Speedy bike with futuristic tech upgrades.', 'img/29.jpg', 61, 8.72, 'Gaming', 37, '2023-04-12 10:30:50'),
(30, 'Aurora Skies', 'Abstract painting inspired by northern lights.', 'img/30.jpg', 10, 3.06, 'Digital Art', 95, '2019-07-04 15:00:00'),
(31, 'Phantom Rider', 'Ghostly motorcyclist in a digital wasteland.', 'img/31.jpg', 61, 2.36, 'Collectibles', 52, '2024-11-11 19:19:19'),
(32, 'Bassstorm', 'Powerful bass track shaking virtual clubs.', 'img/32.jpg', 4, 0.66, 'Music', 6, '2022-10-25 14:10:05'),
(33, 'Neon Streetwalker', 'Urban avatar blending into the city lights.', 'img/33.jpg', 43, 1.36, 'Gaming', 22, '2020-03-30 09:20:30'),
(34, 'Mystic Lagoon', 'Serene digital artwork with mythical creatures.', 'img/34.jpg', 43, 3.50, 'Digital Art', 88, '2021-07-15 18:55:40'),
(35, 'Digital Ranger', 'Explorer of pixelated wilderness.', 'img/35.jpg', 11, 1.16, 'Collectibles', 49, '2023-09-01 11:11:00'),
(36, 'Hypernova Blitz', 'Fast-paced space shooter game asset.', 'img/36.jpg', 59, 7.56, 'Gaming', 76, '2019-05-05 23:45:10'),
(37, 'Ocean Horizon', 'Calm seascape with vibrant digital colors.', 'img/37.jpg', 16, 2.58, 'Digital Art', 14, '2022-02-20 10:00:25'),
(38, 'Retro Wave', 'Classic synth track with a futuristic twist.', 'img/38.jpg', 79, 0.42, 'Music', 39, '2024-07-07 16:20:15'),
(39, 'Skycastle Suite', 'Luxurious virtual penthouse in the clouds.', 'img/39.jpg', 85, 29.72, 'Virtual Real Estate', 91, '2025-10-31 21:00:00'),
(40, 'Starfighter Ace', 'Elite pilot avatar for space battles.', 'img/40.jpg', 85, 3.64, 'Collectibles', 60, '2020-12-12 13:40:50'),
(41, 'Cyber Siren', 'Mysterious digital femme fatale with glowing eyes.', 'img/41.jpg', 85, 2.12, 'Digital Art', 2, '2021-08-08 07:15:30'),
(42, 'Pixel Outlaw', 'Rebel avatar in pixelated wild west style.', 'img/42.jpg', 79, 1.40, 'Collectibles', 30, '2023-02-14 18:30:00'),
(43, 'Electric Groove', 'Funky electronic tune for dance floors worldwide.', 'img/43.jpg', 85, 0.84, 'Music', 71, '2019-10-10 22:50:10'),
(44, 'Mech Titan', 'Colossal fighting robot with heavy armor.', 'img/44.jpg', 47, 11.80, 'Gaming', 58, '2022-09-29 15:25:35'),
(45, 'Enchanted Meadows', 'Magical fields under a starlit sky.', 'img/45.jpg', 21, 2.44, 'Digital Art', 13, '2024-03-21 11:05:05'),
(46, 'Retro Archer', 'Pixelated bowman aiming for glory.', 'img/46.jpg', 4, 1.12, 'Collectibles', 84, '2020-05-15 14:00:00'),
(47, 'Neon Waterfront', 'Digital art of a glowing riverside city.', 'img/47.jpg', 47, 5.52, 'Digital Art', 40, '2021-11-30 09:45:20'),
(48, 'Synthwave Racer', 'Racing car with vibrant neon trails.', 'img/48.jpg', 57, 9.00, 'Gaming', 65, '2023-01-25 17:30:45'),
(49, 'Sonic Echo', 'Chillwave track with soothing synths.', 'img/49.jpg', 10, 0.68, 'Music', 26, '2019-06-18 20:15:10'),
(50, 'Lunar Fortress', 'Virtual stronghold on the moon\'s surface.', 'img/50.jpg', 7, 18.92, 'Virtual Real Estate', 96, '2022-12-05 12:12:12'),
(51, 'Phantom Stalker', 'Silent hunter in a cybernetic jungle.', 'img/51.jpg', 66, 1.92, 'Collectibles', 7, '2024-08-30 16:50:30'),
(52, 'Bass Drop', 'Heavy bass EDM track shaking virtual clubs.', 'img/52.jpg', 66, 0.56, 'Music', 34, '2020-02-02 23:20:00'),
(53, 'Digital Samurai', 'Futuristic warrior blending tradition with tech.', 'img/53.jpg', 23, 3.68, 'Gaming', 75, '2021-05-20 10:40:15'),
(54, 'Crystal Lagoon', 'Glittering water filled with luminescent crystals.', 'img/54.jpg', 83, 2.76, 'Digital Art', 53, '2023-11-11 08:30:00'),
(55, 'Pixel Spy', 'Stealthy avatar for covert missions.', 'img/55.jpg', 83, 1.20, 'Collectibles', 18, '2019-09-09 19:10:25'),
(56, 'Hyperdrive Sniper', 'Long-range shooter with laser precision.', 'img/56.jpg', 9, 7.20, 'Gaming', 69, '2022-04-15 13:00:40'),
(57, 'Ocean Mirage', 'Digital art of a shimmering desert oasis.', 'img/57.jpg', 64, 2.88, 'Digital Art', 29, '2024-01-10 21:55:50'),
(58, 'Retro Synth Queen', 'Synth-heavy track with vintage vibes.', 'img/58.jpg', 64, 0.52, 'Music', 94, '2020-09-22 15:35:10'),
(59, 'Skyhigh Manor', 'Grand estate floating in the virtual clouds.', 'img/59.jpg', 66, 32.56, 'Virtual Real Estate', 36, '2021-12-31 23:58:00'),
(60, 'Galactic Scout', 'Explorer avatar scanning alien worlds.', 'img/60.jpg', 21, 3.40, 'Collectibles', 62, '2023-05-05 06:20:15'),
(61, 'Neon Blaze', 'Digital painting of blazing neon flames.', 'img/61.jpg', 48, 2.28, 'Digital Art', 11, '2019-03-15 12:45:30'),
(62, 'Pixel Pirate', 'Swashbuckling pixelated buccaneer.', 'img/62.jpg', 48, 1.48, 'Collectibles', 87, '2022-08-18 17:10:20'),
(63, 'Electric Harmony', 'Smooth electronic track for relaxation.', 'img/63.jpg', 29, 0.78, 'Music', 43, '2024-06-25 09:30:00'),
(64, 'Mech Gladiator', 'Armored fighter in futuristic arenas.', 'img/64.jpg', 66, 10.60, 'Gaming', 70, '2020-11-20 14:05:55'),
(65, 'Enchanted Grove', 'Forest scene with glowing mushrooms and fairies.', 'img/65.jpg', 71, 2.56, 'Digital Art', 24, '2021-02-28 20:00:10'),
(66, 'Retro Knight', 'Pixel art hero with shining armor.', 'img/66.jpg', 79, 1.04, 'Collectibles', 59, '2023-07-12 11:25:35'),
(67, 'Neon Skyline', 'City skyline glowing in futuristic hues.', 'img/67.jpg', 71, 5.60, 'Digital Art', 98, '2019-12-01 16:40:45'),
(68, 'Synth Racer', 'Fast-paced synthwave racing game NFT.', 'img/68.jpg', 4, 9.20, 'Gaming', 4, '2022-06-06 08:50:20'),
(69, 'Sonic Wave', 'Uplifting electronic beat with powerful drops.', 'img/69.jpg', 5, 0.74, 'Music', 35, '2024-04-14 22:30:00'),
(70, 'Lunar Estate', 'High-tech home on the moon\'s surface.', 'img/70.jpg', 29, 20.84, 'Virtual Real Estate', 82, '2025-08-30 13:15:10');


