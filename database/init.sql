# Database schema for TradeBench

CREATE TABLE users (
    id SERIAL PRIMARY KEY,
    email VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE analysis_tasks (
    id UUID PRIMARY KEY,
    user_id INTEGER REFERENCES users(id),
    pair VARCHAR(10) NOT NULL,
    status VARCHAR(20) DEFAULT 'PENDING', # PENDING, PROCESSING, COMPLETED, FAILED
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE analysis_results (
    id SERIAL PRIMARY KEY,
    task_id UUID REFERENCES analysis_tasks(id),
    # Complex JSON storage for technical indicators and signals
    result_data JSONB NOT NULL,
    processed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

# Indexing for faster task lookup by user
CREATE INDEX idx_tasks_user ON analysis_tasks(user_id);