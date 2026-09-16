-- Fixture schema for the PostgreSQL connector integration tests.
-- Loaded automatically by compose.yaml on first container start.

CREATE TABLE customers (
    id BIGSERIAL PRIMARY KEY,
    email VARCHAR(255) NOT NULL UNIQUE,
    name VARCHAR(255),
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE orders (
    id BIGSERIAL PRIMARY KEY,
    customer_id BIGINT NOT NULL REFERENCES customers (id),
    total NUMERIC(10, 2) NOT NULL DEFAULT 0.00,
    placed_at TIMESTAMP,
    metadata JSONB,
    external_id UUID
);

INSERT INTO customers (email, name) VALUES
    ('alice@example.com', 'Alice'),
    ('bob@example.com', 'Bob');

INSERT INTO orders (customer_id, total) VALUES
    (1, 9.99),
    (2, 19.99);
