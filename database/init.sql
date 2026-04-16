-- Database schema for TradeBench
CREATE TABLE users (
    id SERIAL PRIMARY KEY,
    email VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role VARCHAR(20) DEFAULT 'standard',
    pro_expires_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE analysis_tasks (
    id UUID PRIMARY KEY,
    user_id INTEGER REFERENCES users(id) ON DELETE CASCADE,
    pair VARCHAR(10) NOT NULL,
    status VARCHAR(20) DEFAULT 'PENDING',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE analysis_results (
    id SERIAL PRIMARY KEY,
    task_id UUID REFERENCES analysis_tasks(id) ON DELETE CASCADE,
    result_data JSONB NOT NULL,
    processed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_tasks_user ON analysis_tasks(user_id);

CREATE TABLE currency_data (
    id SERIAL PRIMARY KEY,
    pair_name VARCHAR(20) NOT NULL,
    timeframe VARCHAR(5) DEFAULT '1h',
    tick_time TIMESTAMP NOT NULL,
    open_price NUMERIC(18, 8),
    high_price NUMERIC(18, 8),
    low_price NUMERIC(18, 8),
    close_price NUMERIC(18, 8),
    volume NUMERIC(24, 8),
    UNIQUE(pair_name, timeframe, tick_time)
);

CREATE INDEX idx_currency_data_pair_time ON currency_data(pair_name, timeframe, tick_time DESC);