<?php

namespace App;

class CreateTables
{
    private $pdo;

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
    }

    public function createTableUrls()
    {
        $sql = 'CREATE TABLE IF NOT EXISTS urls (
                    id bigint PRIMARY KEY GENERATED ALWAYS AS IDENTITY,
                    name varchar(255),
                    created_at timestamp
        );';

        $this->pdo->exec($sql);

        return $this;
    }
}
