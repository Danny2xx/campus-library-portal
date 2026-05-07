CREATE DATABASE IF NOT EXISTS library_portal CHARACTER SET utf8 COLLATE utf8_general_ci;
USE library_portal;

DROP TABLE IF EXISTS reservations;
DROP TABLE IF EXISTS loans;
DROP TABLE IF EXISTS copies;
DROP TABLE IF EXISTS book_authors;
DROP TABLE IF EXISTS books;
DROP TABLE IF EXISTS authors;
DROP TABLE IF EXISTS publishers;
DROP TABLE IF EXISTS categories;
DROP TABLE IF EXISTS members;
DROP TABLE IF EXISTS users;

CREATE TABLE users (
    id INT NOT NULL AUTO_INCREMENT,
    email VARCHAR(150) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin', 'member') NOT NULL DEFAULT 'member',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE members (
    id INT NOT NULL AUTO_INCREMENT,
    user_id INT NOT NULL,
    membership_number VARCHAR(30) NOT NULL,
    full_name VARCHAR(120) NOT NULL,
    phone VARCHAR(30) DEFAULT NULL,
    course_name VARCHAR(120) DEFAULT NULL,
    joined_on DATE NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    UNIQUE KEY uq_members_user (user_id),
    UNIQUE KEY uq_membership_number (membership_number),
    CONSTRAINT fk_members_user FOREIGN KEY (user_id) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE categories (
    id INT NOT NULL AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    PRIMARY KEY (id),
    UNIQUE KEY uq_categories_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE publishers (
    id INT NOT NULL AUTO_INCREMENT,
    name VARCHAR(150) NOT NULL,
    website_url VARCHAR(255) DEFAULT NULL,
    description TEXT,
    PRIMARY KEY (id),
    UNIQUE KEY uq_publishers_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE authors (
    id INT NOT NULL AUTO_INCREMENT,
    name VARCHAR(150) NOT NULL,
    biography TEXT,
    PRIMARY KEY (id),
    UNIQUE KEY uq_authors_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE books (
    id INT NOT NULL AUTO_INCREMENT,
    title VARCHAR(180) NOT NULL,
    isbn VARCHAR(30) NOT NULL,
    category_id INT NOT NULL,
    publisher_id INT NOT NULL,
    published_year INT DEFAULT NULL,
    summary TEXT,
    featured TINYINT(1) NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_books_isbn (isbn),
    CONSTRAINT fk_books_category FOREIGN KEY (category_id) REFERENCES categories (id),
    CONSTRAINT fk_books_publisher FOREIGN KEY (publisher_id) REFERENCES publishers (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE book_authors (
    book_id INT NOT NULL,
    author_id INT NOT NULL,
    PRIMARY KEY (book_id, author_id),
    CONSTRAINT fk_book_authors_book FOREIGN KEY (book_id) REFERENCES books (id),
    CONSTRAINT fk_book_authors_author FOREIGN KEY (author_id) REFERENCES authors (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE copies (
    id INT NOT NULL AUTO_INCREMENT,
    book_id INT NOT NULL,
    accession_code VARCHAR(30) NOT NULL,
    shelf_location VARCHAR(30) NOT NULL,
    status ENUM('available', 'loaned', 'maintenance') NOT NULL DEFAULT 'available',
    condition_notes TEXT,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_copies_accession_code (accession_code),
    CONSTRAINT fk_copies_book FOREIGN KEY (book_id) REFERENCES books (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE loans (
    id INT NOT NULL AUTO_INCREMENT,
    copy_id INT NOT NULL,
    member_id INT NOT NULL,
    loaned_at DATETIME NOT NULL,
    due_at DATETIME NOT NULL,
    returned_at DATETIME DEFAULT NULL,
    notes TEXT,
    PRIMARY KEY (id),
    CONSTRAINT fk_loans_copy FOREIGN KEY (copy_id) REFERENCES copies (id),
    CONSTRAINT fk_loans_member FOREIGN KEY (member_id) REFERENCES members (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE reservations (
    id INT NOT NULL AUTO_INCREMENT,
    member_id INT NOT NULL,
    book_id INT NOT NULL,
    status ENUM('pending', 'ready_for_collection', 'fulfilled', 'cancelled') NOT NULL DEFAULT 'pending',
    requested_at DATETIME NOT NULL,
    expires_at DATETIME DEFAULT NULL,
    notes TEXT,
    PRIMARY KEY (id),
    CONSTRAINT fk_reservations_member FOREIGN KEY (member_id) REFERENCES members (id),
    CONSTRAINT fk_reservations_book FOREIGN KEY (book_id) REFERENCES books (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO users (id, email, password_hash, role, is_active, created_at) VALUES
    (1, 'admin@campuslibrary.com', '$2y$05$a1Z6hc2urHG4MXlMFDlaCeRtisnUZBDq1iuWBdlDGSYIDM/TPHAbi', 'admin', 1, '2026-03-01 09:00:00'),
    (2, 'maya.chen@campuslibrary.com', '$2y$05$a1Z6hc2urHG4MXlMFDlaCeRtisnUZBDq1iuWBdlDGSYIDM/TPHAbi', 'member', 1, '2026-03-01 09:05:00'),
    (3, 'ahmed.khan@campuslibrary.com', '$2y$05$a1Z6hc2urHG4MXlMFDlaCeRtisnUZBDq1iuWBdlDGSYIDM/TPHAbi', 'member', 1, '2026-03-01 09:10:00'),
    (4, 'sophie.turner@campuslibrary.com', '$2y$05$a1Z6hc2urHG4MXlMFDlaCeRtisnUZBDq1iuWBdlDGSYIDM/TPHAbi', 'member', 1, '2026-03-01 09:15:00');

INSERT INTO members (id, user_id, membership_number, full_name, phone, course_name, joined_on, is_active) VALUES
    (1, 2, 'LIB-2026-0001', 'Maya Chen', '07111 222333', 'Software Engineering', '2026-03-01', 1),
    (2, 3, 'LIB-2026-0002', 'Ahmed Khan', '07222 333444', 'Network Infrastructure', '2026-03-01', 1),
    (3, 4, 'LIB-2026-0003', 'Sophie Turner', '07333 444555', 'Digital Media', '2026-03-01', 1);

INSERT INTO categories (id, name, description) VALUES
    (1, 'Software Engineering', 'Books covering software craft, architecture and maintainable code.'),
    (2, 'Data & AI', 'Machine learning, data engineering and practical Python resources.'),
    (3, 'Design & UX', 'Human-centred design, usability and interface thinking.'),
    (4, 'Networking', 'Computer networks, infrastructure and connected systems.'),
    (5, 'Web Development', 'Front-end, responsive design and full-stack web skills.');

INSERT INTO publishers (id, name, website_url, description) VALUES
    (1, 'Pearson', 'https://www.pearson.com', 'Academic and professional technology publishing.'),
    (2, 'O''Reilly Media', 'https://www.oreilly.com', 'Developer-focused books and learning resources.'),
    (3, 'MIT Press', 'https://mitpress.mit.edu', 'Scholarly publishing across computing and design.'),
    (4, 'Apress', 'https://www.apress.com', 'Professional development and software titles.'),
    (5, 'Packt', 'https://www.packtpub.com', 'Hands-on technology and programming resources.'),
    (6, 'No Starch Press', 'https://nostarch.com', 'Accessible programming titles for practical learning.'),
    (7, 'John Wiley & Sons', 'https://www.wiley.com', 'Educational and technical publishing.'),
    (8, 'A Book Apart', 'https://abookapart.com', 'Concise books for web professionals.');

INSERT INTO authors (id, name, biography) VALUES
    (1, 'Robert C Martin', 'Software engineer and author focused on clean code and software craftsmanship.'),
    (2, 'Eric Matthes', 'Author and educator known for beginner-friendly Python resources.'),
    (3, 'Don Norman', 'Design thinker and usability expert.'),
    (4, 'Andrew S Tanenbaum', 'Computer scientist and textbook author in networking and systems.'),
    (5, 'Al Sweigart', 'Writer of practical automation and programming books.'),
    (6, 'Jen Simmons', 'Designer and web educator focused on modern CSS and layout.'),
    (7, 'Martin Kleppmann', 'Researcher and author on distributed data systems.'),
    (8, 'Jon Duckett', 'Author of visually driven web design books.'),
    (9, 'Ethan Marcotte', 'Designer who popularised responsive web design.'),
    (10, 'Ian Goodfellow', 'Research scientist and co-author of a leading deep learning text.'),
    (11, 'Yoshua Bengio', 'AI researcher and co-author of deep learning resources.'),
    (12, 'Aaron Courville', 'Machine learning researcher and co-author of deep learning texts.');

INSERT INTO books (id, title, isbn, category_id, publisher_id, published_year, summary, featured, is_active, created_at) VALUES
    (1, 'Clean Code', '9780132350884', 1, 1, 2008, 'A practical guide to writing readable, maintainable and professional software.', 1, 1, '2026-03-01 10:00:00'),
    (2, 'Designing Data-Intensive Applications', '9781449373320', 2, 2, 2017, 'A modern exploration of distributed systems, databases and reliable data architecture.', 1, 1, '2026-03-01 10:05:00'),
    (3, 'The Design of Everyday Things', '9780262525671', 3, 3, 2013, 'A foundational text on human-centred design and usability principles.', 1, 1, '2026-03-01 10:10:00'),
    (4, 'Computer Networks', '9780132126953', 4, 1, 2011, 'A broad introduction to networking concepts, protocols and system communication.', 0, 1, '2026-03-01 10:15:00'),
    (5, 'Automate the Boring Stuff with Python', '9781593279929', 2, 6, 2019, 'Practical Python automation for everyday workflows and repetitive tasks.', 1, 1, '2026-03-01 10:20:00'),
    (6, 'HTML and CSS: Design and Build Websites', '9781118008188', 5, 7, 2011, 'A visual introduction to HTML and CSS for front-end development.', 1, 1, '2026-03-01 10:25:00'),
    (7, 'Responsive Web Design', '9781937557027', 5, 8, 2014, 'A concise guide to flexible layouts and responsive web thinking.', 0, 1, '2026-03-01 10:30:00'),
    (8, 'Python Crash Course', '9781718502707', 2, 6, 2023, 'A project-based introduction to Python programming for learners.', 1, 1, '2026-03-01 10:35:00'),
    (9, 'Deep Learning', '9780262035613', 2, 3, 2016, 'A comprehensive academic introduction to deep learning theory and practice.', 0, 1, '2026-03-01 10:40:00');

INSERT INTO book_authors (book_id, author_id) VALUES
    (1, 1),
    (2, 7),
    (3, 3),
    (4, 4),
    (5, 5),
    (6, 8),
    (7, 9),
    (8, 2),
    (9, 10),
    (9, 11),
    (9, 12);

INSERT INTO copies (id, book_id, accession_code, shelf_location, status, condition_notes, created_at) VALUES
    (1, 1, 'CPY-1001', 'A1-01', 'available', 'Good condition.', '2026-03-01 11:00:00'),
    (2, 1, 'CPY-1002', 'A1-02', 'loaned', 'Minor wear on the cover.', '2026-03-01 11:01:00'),
    (3, 2, 'CPY-1003', 'B2-01', 'available', 'New copy.', '2026-03-01 11:02:00'),
    (4, 2, 'CPY-1004', 'B2-02', 'available', 'Good condition.', '2026-03-01 11:03:00'),
    (5, 3, 'CPY-1005', 'C3-01', 'available', 'Highlighted example pages.', '2026-03-01 11:04:00'),
    (6, 3, 'CPY-1006', 'C3-02', 'maintenance', 'Binding repair required.', '2026-03-01 11:05:00'),
    (7, 4, 'CPY-1007', 'D4-01', 'loaned', 'Good condition.', '2026-03-01 11:06:00'),
    (8, 4, 'CPY-1008', 'D4-02', 'available', 'Good condition.', '2026-03-01 11:07:00'),
    (9, 5, 'CPY-1009', 'B2-03', 'available', 'Slight shelf wear.', '2026-03-01 11:08:00'),
    (10, 5, 'CPY-1010', 'B2-04', 'loaned', 'Good condition.', '2026-03-01 11:09:00'),
    (11, 6, 'CPY-1011', 'E5-01', 'available', 'Popular front-end reference.', '2026-03-01 11:10:00'),
    (12, 6, 'CPY-1012', 'E5-02', 'available', 'Good condition.', '2026-03-01 11:11:00'),
    (13, 7, 'CPY-1013', 'E5-03', 'available', 'Compact handbook edition.', '2026-03-01 11:12:00'),
    (14, 8, 'CPY-1014', 'B2-05', 'loaned', 'Good condition.', '2026-03-01 11:13:00'),
    (15, 9, 'CPY-1015', 'B3-01', 'available', 'Reference copy.', '2026-03-01 11:14:00'),
    (16, 9, 'CPY-1016', 'B3-02', 'available', 'New copy.', '2026-03-01 11:15:00');

INSERT INTO loans (id, copy_id, member_id, loaned_at, due_at, returned_at, notes) VALUES
    (1, 2, 1, '2026-03-10 10:15:00', '2026-03-24 17:00:00', NULL, 'Issued from the circulation desk.'),
    (2, 7, 2, '2026-03-01 15:30:00', '2026-03-15 17:00:00', NULL, 'Issued from the circulation desk.'),
    (3, 10, 3, '2026-03-14 09:20:00', '2026-03-28 17:00:00', NULL, 'Issued from the circulation desk.'),
    (4, 14, 1, '2026-02-01 11:00:00', '2026-02-15 17:00:00', '2026-02-13 12:30:00', 'Returned early.'),
    (5, 11, 2, '2026-01-05 14:00:00', '2026-01-19 17:00:00', '2026-01-18 16:20:00', 'Returned in good condition.');

INSERT INTO reservations (id, member_id, book_id, status, requested_at, expires_at, notes) VALUES
    (1, 1, 4, 'pending', '2026-03-16 09:00:00', '2026-03-23 17:00:00', 'Waiting for the current borrower to return a copy.'),
    (2, 2, 1, 'ready_for_collection', '2026-03-18 13:20:00', '2026-03-25 17:00:00', 'A copy is being held at the main desk.'),
    (3, 3, 9, 'fulfilled', '2026-03-05 08:45:00', '2026-03-12 17:00:00', 'Reservation was fulfilled and collected.'),
    (4, 3, 3, 'cancelled', '2026-02-20 10:10:00', '2026-02-27 17:00:00', 'Cancelled by the member.');
