<?php

namespace App\Core;

use PDO;

/**
 * Clase Base para Modelos con PDO
 */
abstract class Model
{
    protected PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }
}
