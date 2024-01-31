DROP TABLE IF EXISTS urls;
CREATE TABLE IF NOT EXISTS urls (
        id bigint PRIMARY KEY GENERATED ALWAYS AS IDENTITY,
        name varchar(255),
        created_at timestamp);
DROP TABLE IF EXISTS url_checks;
CREATE TABLE IF NOT EXISTS url_checks (
        id bigint PRIMARY KEY GENERATED ALWAYS AS IDENTITY,
        url_id bigint REFERENCES urls (id),
        status_code int,
        h1 varchar(255),
        title varchar(255),
        desctiprion text,
        created_at timestamp);