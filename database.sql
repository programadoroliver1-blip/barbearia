-- Banco de dados para o sistema de agendamento da barbearia
CREATE DATABASE IF NOT EXISTS barbearia CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE barbearia;

CREATE TABLE IF NOT EXISTS services (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    description VARCHAR(255) NOT NULL,
    duration_minutes INT NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS barbers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    experience_years INT NOT NULL DEFAULT 0,
    bio VARCHAR(255) DEFAULT NULL,
    avatar_url VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS appointments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    service_id INT NOT NULL,
    barber_id INT NOT NULL,
    client_name VARCHAR(120) NOT NULL,
    client_email VARCHAR(120) NOT NULL,
    client_phone VARCHAR(30) DEFAULT NULL,
    appointment_at DATETIME NOT NULL,
    notes TEXT,
    status ENUM('scheduled', 'completed', 'cancelled') NOT NULL DEFAULT 'scheduled',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_appointments_service FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE CASCADE,
    CONSTRAINT fk_appointments_barber FOREIGN KEY (barber_id) REFERENCES barbers(id) ON DELETE CASCADE,
    CONSTRAINT uq_appointments_slot UNIQUE (barber_id, appointment_at)
) ENGINE=InnoDB;

INSERT INTO services (name, description, duration_minutes, price) VALUES
    ('Corte Clássico', 'Corte tradicional com acabamento à navalha e finalização personalizada.', 45, 55.00),
    ('Barba Premium', 'Modelagem completa da barba com toalha quente e produtos especiais.', 40, 45.00),
    ('Combo Corte + Barba', 'Experiência completa com corte, barba e tratamento facial.', 75, 95.00)
ON DUPLICATE KEY UPDATE
    description = VALUES(description),
    duration_minutes = VALUES(duration_minutes),
    price = VALUES(price);

INSERT INTO barbers (name, experience_years, bio, avatar_url) VALUES
    ('Lucas Andrade', 8, 'Especialista em cortes clássicos e design de barba.', 'https://avatars.dicebear.com/api/initials/Lucas%20Andrade.svg'),
    ('Pedro Carvalho', 5, 'Apaixonado por fades modernos e tendências urbanas.', 'https://avatars.dicebear.com/api/initials/Pedro%20Carvalho.svg'),
    ('Rafael Silva', 10, 'Experiência premium com técnicas tradicionais e contemporâneas.', 'https://avatars.dicebear.com/api/initials/Rafael%20Silva.svg')
ON DUPLICATE KEY UPDATE
    experience_years = VALUES(experience_years),
    bio = VALUES(bio),
    avatar_url = VALUES(avatar_url);
