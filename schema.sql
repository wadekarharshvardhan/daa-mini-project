CREATE TABLE IF NOT EXISTS search_results (
    id          SERIAL PRIMARY KEY,
    input_text  TEXT        NOT NULL,
    pattern     VARCHAR(255) NOT NULL,
    algorithm   VARCHAR(20)  NOT NULL CHECK (algorithm IN ('Naive', 'Rabin-Karp')),
    time_ms     NUMERIC(12,4) NOT NULL,
    comparisons BIGINT       NOT NULL,
    created_at  TIMESTAMP    NOT NULL DEFAULT NOW()
);

CREATE INDEX idx_search_results_created ON search_results (created_at DESC);
