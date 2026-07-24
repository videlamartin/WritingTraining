CREATE TABLE IF NOT EXISTS models (
    id INT AUTO_INCREMENT PRIMARY KEY,
    type ENUM('essay','email','article','review','report') NOT NULL,
    number TINYINT NOT NULL,
    title VARCHAR(255) NOT NULL,
    prompt TEXT NOT NULL,
    content TEXT NOT NULL,
    useful_language TEXT NOT NULL,
    word_count SMALLINT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS training_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    model_id INT NOT NULL,
    session_date DATE NOT NULL,
    day_number TINYINT NOT NULL DEFAULT 1,
    copy_time_seconds INT DEFAULT NULL,
    draft_time_seconds INT DEFAULT NULL,
    errors TINYINT DEFAULT NULL,
    feeling TINYINT DEFAULT NULL,
    new_connectors TEXT,
    favorite_phrase VARCHAR(255),
    reflection TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (model_id) REFERENCES models(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS weekly_progress (
    id INT AUTO_INCREMENT PRIMARY KEY,
    week_number TINYINT NOT NULL,
    writing_type VARCHAR(20) NOT NULL,
    start_date DATE DEFAULT NULL,
    is_active TINYINT(1) DEFAULT 0,
    is_completed TINYINT(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS useful_expressions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category VARCHAR(50) NOT NULL,
    expression VARCHAR(500) NOT NULL,
    writing_types VARCHAR(100) NOT NULL,
    model_id INT DEFAULT NULL,
    FOREIGN KEY (model_id) REFERENCES models(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
