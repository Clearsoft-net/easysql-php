-- Fixture schema for the MySQL connector integration tests.
-- Loaded automatically by compose.yaml on first container start.

CREATE TABLE customers (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    email VARCHAR(255) NOT NULL,
    name VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_customers_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE orders (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    customer_id INT UNSIGNED NOT NULL,
    total DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    placed_at DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_orders_customer (customer_id),
    CONSTRAINT fk_orders_customer FOREIGN KEY (customer_id) REFERENCES customers (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO customers (email, name) VALUES
    ('alice@example.com', 'Alice'),
    ('bob@example.com', 'Bob');

INSERT INTO orders (customer_id, total) VALUES
    (1, 9.99),
    (2, 19.99);
