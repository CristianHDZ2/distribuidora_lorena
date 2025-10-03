CREATE DATABASE IF NOT EXISTS distribuidora_lorena CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE distribuidora_lorena;

-- Tabla de usuarios
CREATE TABLE usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    nombre VARCHAR(100) NOT NULL,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabla de rutas
CREATE TABLE rutas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    descripcion TEXT,
    activo TINYINT(1) DEFAULT 1,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabla de productos (MODIFICADA)
CREATE TABLE productos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(200) NOT NULL,
    precio_caja DECIMAL(10,2) NOT NULL DEFAULT 1.00,
    precio_unitario DECIMAL(10,2) NULL DEFAULT NULL,
    tipo ENUM('Big Cola', 'Varios', 'Ambos') NOT NULL,
    activo TINYINT(1) DEFAULT 1,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabla de salidas (MODIFICADA)
CREATE TABLE salidas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ruta_id INT NOT NULL,
    producto_id INT NOT NULL,
    cantidad DECIMAL(10,1) NOT NULL,
    usa_precio_unitario TINYINT(1) DEFAULT 0,
    fecha DATE NOT NULL,
    usuario_id INT NOT NULL,
    fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ruta_id) REFERENCES rutas(id),
    FOREIGN KEY (producto_id) REFERENCES productos(id),
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
);

-- Tabla de recargas (MODIFICADA)
CREATE TABLE recargas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ruta_id INT NOT NULL,
    producto_id INT NOT NULL,
    cantidad DECIMAL(10,1) NOT NULL,
    usa_precio_unitario TINYINT(1) DEFAULT 0,
    fecha DATE NOT NULL,
    usuario_id INT NOT NULL,
    fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ruta_id) REFERENCES rutas(id),
    FOREIGN KEY (producto_id) REFERENCES productos(id),
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
);

-- Tabla de retornos (MODIFICADA)
CREATE TABLE retornos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ruta_id INT NOT NULL,
    producto_id INT NOT NULL,
    cantidad DECIMAL(10,1) NOT NULL,
    usa_precio_unitario TINYINT(1) DEFAULT 0,
    fecha DATE NOT NULL,
    usuario_id INT NOT NULL,
    fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ruta_id) REFERENCES rutas(id),
    FOREIGN KEY (producto_id) REFERENCES productos(id),
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
);

-- Tabla para ajustes de precios en ventas específicas
CREATE TABLE ajustes_precios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ruta_id INT NOT NULL,
    producto_id INT NOT NULL,
    fecha DATE NOT NULL,
    cantidad DECIMAL(10,1) NOT NULL,
    precio_ajustado DECIMAL(10,2) NOT NULL,
    usuario_id INT NOT NULL,
    fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ruta_id) REFERENCES rutas(id),
    FOREIGN KEY (producto_id) REFERENCES productos(id),
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
);

-- Insertar usuario admin
INSERT INTO usuarios (usuario, password, nombre) VALUES ('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Administrador');
-- Contraseña: admin

-- Insertar las 5 rutas
INSERT INTO rutas (nombre, descripcion) VALUES 
('RUTA #1: COSTA DEL SOL Y LA HERRADURA', 'Ruta 1 - Productos Varios'),
('RUTA #2: LAS ISLETAS – SANTA ISABEL Y SAN MARCELINO', 'Ruta 2 - Productos Varios'),
('RUTA #3: CIDECO - PORFIADO Y SAN MARCELINO', 'Ruta 3 - Productos Varios'),
('RUTA #4: SAN LUIS TALPA Y ACHIOTAL', 'Ruta 4 - Productos Varios'),
('RUTA #5: BIG COLA', 'Ruta 5 - Productos Big Cola');

-- Insertar productos de "Varios" (Rutas 1-4)
INSERT INTO productos (nombre, precio_caja, precio_unitario, tipo) VALUES 
('Agua "Caída del Cielo" (Fardo)', 0.90, NULL, 'Varios'),
('Agua "De Los Ángeles" Garrafa 18.5L', 2.00, NULL, 'Varios'),
('Agua "Aqua" 750ml (24 Pack)', 8.00, NULL, 'Varios'),
('Aloe 24', 24.00, 1.00, 'Varios'),
('AMP Energy 600ml (12 Pack)', 10.00, NULL, 'Varios'),
('Baygon (Unidad)', 2.25, 2.25, 'Varios'),
('Café Instantáneo Aroma $1.00', 1.00, NULL, 'Varios'),
('Café Instantáneo Aroma Caja $3.00', 3.00, NULL, 'Varios'),
('Café Instantáneo Coscafe Caja $2.85', 2.85, NULL, 'Varios'),
('Café Instantáneo Coscafe Caja 3.95', 3.95, NULL, 'Varios'),
('Coca-Cola 2.5L (6 Pack)', 11.55, NULL, 'Varios'),
('Coca-Cola 3L (4 Pack)', 8.50, NULL, 'Varios'),
('Coca-Cola lata 354ml (24 Pack)', 14.45, NULL, 'Varios'),
('Coca-Cola pet 1.25L (12 Pack)', 13.50, NULL, 'Varios'),
('Coca-Cola Vidrio 354ml (24 Pack)', 10.25, NULL, 'Varios'),
('Del Valle Mandarina 1.5L (6 Pack)', 5.85, NULL, 'Varios'),
('Del Valle Mandarina 2.5L (6 Pack)', 8.55, NULL, 'Varios'),
('Del Valle Mandarina 500ml (12 Pack)', 5.75, NULL, 'Varios'),
('Frosky (24 Pack)', 5.00, NULL, 'Varios'),
('Frutsis (24 Pack)', 3.00, NULL, 'Varios'),
('Gatorade 600ml (24 Pack)', 22.10, NULL, 'Varios'),
('Pachas Granadita 260ml (12 Pack)', 5.00, NULL, 'Varios'),
('Pachitas Quanty 237ml (24 Pack)', 5.00, NULL, 'Varios'),
('Papel Higiénico Nevax Fardo (12 Pack)', 9.60, NULL, 'Varios'),
('Paquete de Bolsa Gabacha', 3.00, NULL, 'Varios'),
('Paquetes de Bolsa #1', 3.50, NULL, 'Varios'),
('Petit Lata 330ml (24 Pack)', 13.00, NULL, 'Varios'),
('Petit Tetra CAJA', 8.15, NULL, 'Varios'),
('Powerade Avalancha 500ml (12 Pack)', 7.25, NULL, 'Varios'),
('Powerade Avalancha 750ml (12 Pack)', 9.75, NULL, 'Varios'),
('Raptor energizante', 10.00, NULL, 'Varios'),
('Suero Suerox 630ml (12 Pack)', 24.00, 2.00, 'Varios'),
('Surf Bote 300ml (12 Pack)', 5.00, NULL, 'Varios'),
('Surf Junior Bolsa 400ml (12 Pack)', 2.50, NULL, 'Varios'),
('TropiJuguito Pichinguitas (24 Unidades)', 5.00, NULL, 'Varios');

-- Insertar productos de "Big Cola" (Ruta 5)
INSERT INTO productos (nombre, precio_caja, precio_unitario, tipo) VALUES 
('Agua "Cielo" 1L Caja', 4.65, NULL, 'Big Cola'),
('Agua "Cielo" 375ml Caja (24 Pack)', 4.00, NULL, 'Big Cola'),
('Agua "Cielo" 625ml Caja (20 Pack)', 5.00, NULL, 'Big Cola'),
('Big 360ml Caja (24 Pack)', 6.00, NULL, 'Big Cola'),
('Big Cola 625ml Caja', 9.10, NULL, 'Big Cola'),
('Big Sabores 360ml Caja (24 Pack)', 6.00, NULL, 'Big Cola'),
('Bio Aloe "Aloe y Uva" 360ml (6 Pack)', 3.75, NULL, 'Big Cola'),
('Bio Aloe Vera Natural 500ml (6 Pack)', 5.00, NULL, 'Big Cola'),
('Cereal DGussto Bolsa', 4.65, NULL, 'Big Cola'),
('Cereal DGussto Tiras', 2.35, NULL, 'Big Cola'),
('Cifrut 360ml Caja (24 Pack)', 6.00, NULL, 'Big Cola'),
('Cifrut 625ml Caja', 11.40, NULL, 'Big Cola'),
('Jugos y Gaseosas 1.3L cajas (16 pack)', 10.00, NULL, 'Big Cola'),
('Jugos y Gaseosas 1.8L cajas (12 pack)', 10.00, NULL, 'Big Cola'),
('Jugos y Gaseosas 1L Caja', 9.10, NULL, 'Big Cola'),
('Jugos y Gaseosas 2.6L', 6.50, NULL, 'Big Cola'),
('Jugos y Gaseosas 250ml Caja (24 Pack)', 5.00, NULL, 'Big Cola'),
('Cifrut Jugos 3.03L', 8.75, NULL, 'Big Cola'),
('Big Cola Gaseosas 3.03L', 8.75, NULL, 'Big Cola'),
('Powerade Avalancha 750ml', 9.75, NULL, 'Big Cola'),
('Pulp 145ml (12 Pack)', 3.40, NULL, 'Big Cola'),
('Pulp 250ml (12 Pack)', 2.50, NULL, 'Big Cola'),
('Pulp 360ml', 1.00, NULL, 'Big Cola'),
('Sporade 360ml (12 Pack)', 4.00, NULL, 'Big Cola'),
('Sporade 625ml (12 Pack)', 5.75, NULL, 'Big Cola'),
('Volt Go 360ml Caja (24 Pack)', 7.10, NULL, 'Big Cola'),
('Volt Yellow 300ml Caja (24 Pack)', 9.00, NULL, 'Big Cola');

-- Insertar productos de "Ambos" (Aparecen en todas las rutas)
INSERT INTO productos (nombre, precio_caja, precio_unitario, tipo) VALUES 
('Agua "Agua Fresca" 1Litro (24 Unidades)', 5.00, NULL, 'Ambos');