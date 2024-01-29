<?php

namespace App;

class SqlQuery
{
    private $pdo;

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
    }

    public function query($sql, $params = [])
    {
        $stmt = $this->pdo->prepare($sql);

        if (!empty($params)) {
            foreach ($params as $key => $value) {
                $stmt->bindValue(":$key", $value);
            }
        }

        $stmt->execute();
        return $stmt->fetchAll($this->pdo::FETCH_ASSOC);
    }
}
