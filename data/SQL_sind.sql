-- SINDERELLA TABLE
DROP TABLE IF EXISTS sinderellas;
CREATE TABLE IF NOT EXISTS sinderellas (
	sind_id INT(11) PRIMARY KEY AUTO_INCREMENT,
	sind_name VARCHAR(255) NOT NULL,
	sind_phno VARCHAR(11) NOT NULL,
	sind_pwd VARCHAR(255) NOT NULL,
	sind_address TEXT NOT NULL,
	sind_postcode VARCHAR(5) NOT NULL,
	sind_area VARCHAR(100) NOT NULL,
	sind_state VARCHAR(100) NOT NULL,
	sind_icno VARCHAR(20) NOT NULL,
    sind_dob DATE NOT NULL,
    sind_gender ENUM('male', 'female') NOT NULL,
    sind_emer_name VARCHAR(255) NOT NULL,
    sind_emer_phno VARCHAR(11) NOT NULL,
    sind_race VARCHAR(100) NOT NULL,
    sind_marital_status VARCHAR(100) NOT NULL,
    sind_no_kids INT(11), 
    sind_spouse_name VARCHAR(255),
    sind_spouse_phno VARCHAR(11),
    sind_spouse_ic_no VARCHAR(20),
    sind_spouse_occupation VARCHAR(100),
	sind_icphoto_path VARCHAR(255),
	sind_profile_path VARCHAR(255),
	sind_upline_id VARCHAR(11),
	sind_status VARCHAR(20),  -- Stores 'pending', 'active', 'inactive'
    acc_approved ENUM('pending', 'approve', 'reject') NOT NULL DEFAULT 'pending',
	sind_bank_name VARCHAR(100) NOT NULL,
    sind_bank_acc_no VARCHAR(20) NOT NULL,
    last_login_date DATETIME, 
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

DROP TABLE IF EXISTS sind_child;
CREATE TABLE IF NOT EXISTS sind_child (
    sind_child_id INT(11) PRIMARY KEY AUTO_INCREMENT,
    sind_id INT(11) NOT NULL,
    child_name VARCHAR(255) NOT NULL,
    child_born_year INT(4) NOT NULL,
    child_occupation VARCHAR(100) NOT NULL,
    FOREIGN KEY (sind_id) REFERENCES sinderellas(sind_id)
);

-- ALTER TABLE sinderellas
-- ADD COLUMN acc_approved TINYINT(1) NOT NULL DEFAULT 0 AFTER sind_status;

-- INSERT INTO sinderellas
-- (sind_name, sind_phno, sind_pwd, sind_address, sind_postcode, sind_area, sind_state, sind_icno, 
-- sind_icphoto_path, sind_profile_path, sind_upline_id, sind_status) VALUES
-- ('Sinderella One', '0123456789', 'pwd123', '12, Jalan ABC, Taman XYZ', '43000', 'Kajang', 'Selangor', '123456121234', 
-- '../img/ic_photo/0001.jpeg','../img/profile_photo/0001.jpg', '', 'pending');

-- SINDERELLA'S SERVICE AREA TABLE
DROP TABLE IF EXISTS sind_service_area;
CREATE TABLE IF NOT EXISTS sind_service_area (
    service_area_id INT(11) PRIMARY KEY AUTO_INCREMENT,
    sind_id INT(11) NOT NULL,
    area VARCHAR(100) NOT NULL,
    state VARCHAR(100) NOT NULL,
    FOREIGN KEY (sind_id) REFERENCES sinderellas(sind_id)
);

-- QUALIFIER TEST
DROP TABLE IF EXISTS qualifier_test;
CREATE TABLE IF NOT EXISTS qualifier_test (
    question_id INT(11) PRIMARY KEY AUTO_INCREMENT,
    question_text TEXT NOT NULL,
    f_option0 TEXT NOT NULL, -- stores the correct option
    f_option1 TEXT NOT NULL, -- stores the false option
    f_option2 TEXT NOT NULL, -- stores the false option
    f_option3 TEXT NOT NULL  -- stores the false option
);

INSERT INTO qualifier_test (question_text, f_option0, f_option1, f_option2, f_option3) VALUES
('question 1', 'true 1', 'false 1.1', 'false 1.2', 'false 1.3'),
('question 2', 'true 2', 'false 2.1', 'false 2.2', 'false 2.3'),
('question 3', 'true 3', 'false 3.1', 'false 3.2', 'false 3.3'),
('question 4', 'true 4', 'false 4.1', 'false 4.2', 'false 4.3'),
('question 5', 'true 5', 'false 5.1', 'false 5.2', 'false 5.3'),
('question 6', 'true 6', 'false 6.1', 'false 6.2', 'false 6.3'),
('question 7', 'true 7', 'false 7.1', 'false 7.2', 'false 7.3'),
('question 8', 'true 8', 'false 8.1', 'false 8.2', 'false 8.3'),
('question 9', 'true 9', 'false 9.1', 'false 9.2', 'false 9.3'),
('question 10', 'true 10', 'false 10.1', 'false 10.2', 'false 10.3'),
('question 11', 'true 11', 'false 11.1', 'false 11.2', 'false 11.3'),
('question 12', 'true 12', 'false 12.1', 'false 12.2', 'false 12.3'),
('question 13', 'true 13', 'false 13.1', 'false 13.2', 'false 13.3'),
('question 14', 'true 14', 'false 14.1', 'false 14.2', 'false 14.3'),
('question 15', 'true 15', 'false 15.1', 'false 15.2', 'false 15.3'),
('question 16', 'true 16', 'false 16.1', 'false 16.2', 'false 16.3'),
('question 17', 'true 17', 'false 17.1', 'false 17.2', 'false 17.3'),
('question 18', 'true 18', 'false 18.1', 'false 18.2', 'false 18.3'),
('question 19', 'true 19', 'false 19.1', 'false 19.2', 'false 19.3'),
('question 20', 'true 20', 'false 20.1', 'false 20.2', 'false 20.3'),
('question 21', 'true 21', 'false 21.1', 'false 21.2', 'false 21.3'),
('question 22', 'true 22', 'false 22.1', 'false 22.2', 'false 22.3'),
('question 23', 'true 23', 'false 23.1', 'false 23.2', 'false 23.3'),
('question 24', 'true 24', 'false 24.1', 'false 24.2', 'false 24.3'),
('question 25', 'true 25', 'false 25.1', 'false 25.2', 'false 25.3');

-- QUALIFIER TEST ATTEMPT HISTORY
DROP TABLE IF EXISTS qt_attempt_hist;
CREATE TABLE IF NOT EXISTS qt_attempt_hist (
    attempt_id INT(10) PRIMARY KEY AUTO_INCREMENT,
    sind_id INT(11) NOT NULL,
    attempt_date DATETIME NOT NULL,
    attempt_score INT(10) NOT NULL,
    FOREIGN KEY (sind_id) REFERENCES sinderellas(sind_id)
);

-- SINDERELLA SERVICE AREA
DROP TABLE IF EXISTS sind_service_area;
CREATE TABLE IF NOT EXISTS sind_service_area (
    service_area_id INT(11) PRIMARY KEY AUTO_INCREMENT,
    sind_id INT(11) NOT NULL,
    area VARCHAR(100) NOT NULL,
    state VARCHAR(100) NOT NULL,
    FOREIGN KEY (sind_id) REFERENCES sinderellas(sind_id)
);

-- SINDERELLA LABEL TABLE
DROP TABLE IF EXISTS sind_label;
CREATE TABLE IF NOT EXISTS sind_label (
    slbl_id INT(11) PRIMARY KEY AUTO_INCREMENT,
    slbl_name VARCHAR(255) NOT NULL,
    slbl_color_code VARCHAR(100) NOT NULL,
    slbl_status VARCHAR(100) NOT NULL DEFAULT 'active' -- Stores 'active', 'inactive'
);

-- SINDERELLA ID+LABEL TABLE
DROP TABLE IF EXISTS sind_id_label;
CREATE TABLE IF NOT EXISTS sind_id_label (
    sind_id_label_id INT(11) PRIMARY KEY AUTO_INCREMENT,
    sind_id INT(11) NOT NULL,
    slbl_id INT(11) NOT NULL,
    FOREIGN KEY (sind_id) REFERENCES sinderellas(sind_id),
    FOREIGN KEY (slbl_id) REFERENCES sind_label(slbl_id)
);

-- SINDERELLA'S DOWNLINE TABLE [[[[NEW]]]]
DROP TABLE IF EXISTS sind_downline;
CREATE TABLE sind_downline (
    sind_id INT(11) NOT NULL, 
    dwln_phno VARCHAR(11) NOT NULL, 
    dwln_id INT(11) DEFAULT NULL, 
    created_at DATETIME NULL,
    PRIMARY KEY (sind_id, dwln_phno), 
    FOREIGN KEY (sind_id) REFERENCES sinderellas(sind_id), 
    FOREIGN KEY (dwln_id) REFERENCES sinderellas(sind_id) 
);

-- SINDERELLA AVAILABLE TIME - DATE [[[[ENHANCEMENT]]]]
DROP TABLE IF EXISTS sind_available_time;
CREATE TABLE IF NOT EXISTS sind_available_time (
    schedule_id INT(11) PRIMARY KEY AUTO_INCREMENT,
    sind_id INT(11) NOT NULL,
    available_date DATE NOT NULL,
    available_from1 TIME NULL,
    available_from2 TIME NULL,
    FOREIGN KEY (sind_id) REFERENCES sinderellas(sind_id)
);

-- SINDERELLA AVAILABLE TIME - DAY [[[[ENHANCEMENT]]]]
DROP TABLE IF EXISTS sind_available_day;
CREATE TABLE IF NOT EXISTS sind_available_day (
    day_id INT(11) PRIMARY KEY AUTO_INCREMENT,
    sind_id INT(11) NOT NULL,
    day_of_week VARCHAR(10) NOT NULL,
    available_from1 TIME NULL,
    available_from2 TIME NULL,
    FOREIGN KEY (sind_id) REFERENCES sinderellas(sind_id)
);

-- SINDERELLA REJECTED HISTORY TABLE
DROP TABLE IF EXISTS sind_rejected_hist;
CREATE TABLE sind_rejected_hist (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sind_id INT(11) NOT NULL,
    booking_id INT(11) NOT NULL,
    reason VARCHAR(255) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sind_id) REFERENCES sinderellas(sind_id),
    FOREIGN KEY (booking_id) REFERENCES bookings(booking_id)
);