-- Para una instalación nueva. No borra los registros ni modifica tablas existentes.
CREATE DATABASE IF NOT EXISTS veterinaria
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE veterinaria;

CREATE TABLE IF NOT EXISTS mascota (
  id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  nombres VARCHAR(50) NOT NULL,
  genero VARCHAR(10) NOT NULL,
  raza VARCHAR(50) NOT NULL,
  fecha_nacimiento DATE NOT NULL,
  especie VARCHAR(30) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
