<?php
class Database {
    private $db;

    public function __construct($dbFile = 'reversi.db') {
        $this->db = new SQLite3($dbFile);
        $this->initDatabase();
    }

    private function initDatabase() {
        // Users table
        $this->db->exec("CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT UNIQUE NOT NULL,
            password TEXT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");

        // Games table
        $this->db->exec("CREATE TABLE IF NOT EXISTS games (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            join_key TEXT UNIQUE,
            player1_id INTEGER NOT NULL,
            player2_id INTEGER,
            current_player INTEGER DEFAULT 1,
            board TEXT NOT NULL,
            game_mode TEXT NOT NULL,
            status TEXT DEFAULT 'waiting',
            winner_id INTEGER,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (player1_id) REFERENCES users(id),
            FOREIGN KEY (player2_id) REFERENCES users(id),
            FOREIGN KEY (winner_id) REFERENCES users(id)
        )");

        // Game moves history
        $this->db->exec("CREATE TABLE IF NOT EXISTS moves (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            game_id INTEGER NOT NULL,
            player INTEGER NOT NULL,
            row INTEGER NOT NULL,
            col INTEGER NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (game_id) REFERENCES games(id)
        )");
    }

    public function getConnection() {
        return $this->db;
    }

    public function close() {
        $this->db->close();
    }
}
