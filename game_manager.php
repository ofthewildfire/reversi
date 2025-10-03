<?php
require_once 'database.php';
require_once 'game_engine.php';
require_once 'ai.php';

class GameManager {
    private $db;

    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
    }

    public function createGame($userId, $gameMode) {
        $engine = new ReversiEngine();
        $boardString = $engine->getBoardString();
        $joinKey = $this->generateJoinKey();

        $stmt = $this->db->prepare("INSERT INTO games (join_key, player1_id, board, game_mode, status)
                                     VALUES (:join_key, :player1_id, :board, :game_mode, :status)");
        $stmt->bindValue(':join_key', $joinKey, SQLITE3_TEXT);
        $stmt->bindValue(':player1_id', $userId, SQLITE3_INTEGER);
        $stmt->bindValue(':board', $boardString, SQLITE3_TEXT);
        $stmt->bindValue(':game_mode', $gameMode, SQLITE3_TEXT);

        if ($gameMode == 'ai') {
            $stmt->bindValue(':status', 'active', SQLITE3_TEXT);
        } else {
            $stmt->bindValue(':status', 'waiting', SQLITE3_TEXT);
        }

        $stmt->execute();
        return ['id' => $this->db->lastInsertRowID(), 'join_key' => $joinKey];
    }

    public function joinGame($joinKey, $userId) {
        $stmt = $this->db->prepare("SELECT id, player1_id, status FROM games WHERE join_key = :join_key");
        $stmt->bindValue(':join_key', $joinKey, SQLITE3_TEXT);
        $result = $stmt->execute();
        $game = $result->fetchArray(SQLITE3_ASSOC);

        if (!$game) {
            return ['success' => false, 'error' => 'Game not found'];
        }

        if ($game['status'] != 'waiting') {
            return ['success' => false, 'error' => 'Game already started or finished'];
        }

        if ($game['player1_id'] == $userId) {
            return ['success' => false, 'error' => 'Cannot join your own game'];
        }

        $stmt = $this->db->prepare("UPDATE games SET player2_id = :player2_id, status = 'active', updated_at = CURRENT_TIMESTAMP
                                     WHERE id = :id");
        $stmt->bindValue(':player2_id', $userId, SQLITE3_INTEGER);
        $stmt->bindValue(':id', $game['id'], SQLITE3_INTEGER);
        $stmt->execute();

        return ['success' => true, 'game_id' => $game['id']];
    }

    public function getGame($gameId) {
        $stmt = $this->db->prepare("SELECT * FROM games WHERE id = :id");
        $stmt->bindValue(':id', $gameId, SQLITE3_INTEGER);
        $result = $stmt->execute();
        return $result->fetchArray(SQLITE3_ASSOC);
    }

    public function makeMove($gameId, $userId, $row, $col) {
        $game = $this->getGame($gameId);

        if (!$game) {
            return ['success' => false, 'error' => 'Game not found'];
        }

        if ($game['status'] != 'active') {
            return ['success' => false, 'error' => 'Game is not active'];
        }

        // Determine which player number the user is
        $playerNumber = ($userId == $game['player1_id']) ? 1 : 2;

        // Check if it's this player's turn
        if ($playerNumber != $game['current_player']) {
            return ['success' => false, 'error' => 'Not your turn'];
        }

        // Load game engine and validate move
        $engine = new ReversiEngine($game['board']);

        if (!$engine->isValidMove($row, $col, $playerNumber)) {
            return ['success' => false, 'error' => 'Invalid move'];
        }

        // Make the move
        $engine->makeMove($row, $col, $playerNumber);

        // Save move to history
        $stmt = $this->db->prepare("INSERT INTO moves (game_id, player, row, col) VALUES (:game_id, :player, :row, :col)");
        $stmt->bindValue(':game_id', $gameId, SQLITE3_INTEGER);
        $stmt->bindValue(':player', $playerNumber, SQLITE3_INTEGER);
        $stmt->bindValue(':row', $row, SQLITE3_INTEGER);
        $stmt->bindValue(':col', $col, SQLITE3_INTEGER);
        $stmt->execute();

        // Determine next player
        $nextPlayer = $playerNumber == 1 ? 2 : 1;

        // Check if next player has valid moves
        if (!$engine->hasValidMoves($nextPlayer)) {
            // If current player also has no moves, game is over
            if (!$engine->hasValidMoves($playerNumber)) {
                return $this->endGame($gameId, $engine);
            }
            // Otherwise, current player goes again
            $nextPlayer = $playerNumber;
        }

        // Update game state
        $stmt = $this->db->prepare("UPDATE games SET board = :board, current_player = :current_player, updated_at = CURRENT_TIMESTAMP
                                     WHERE id = :id");
        $stmt->bindValue(':board', $engine->getBoardString(), SQLITE3_TEXT);
        $stmt->bindValue(':current_player', $nextPlayer, SQLITE3_INTEGER);
        $stmt->bindValue(':id', $gameId, SQLITE3_INTEGER);
        $stmt->execute();

        // If playing against AI and it's AI's turn, make AI move
        if ($game['game_mode'] == 'ai' && $nextPlayer == 2) {
            $this->makeAIMove($gameId);
        }

        return [
            'success' => true,
            'board' => $engine->getBoard(),
            'score' => $engine->getScore(),
            'current_player' => $nextPlayer,
            'status' => 'active'
        ];
    }

    public function makeAIMove($gameId) {
        $game = $this->getGame($gameId);
        $engine = new ReversiEngine($game['board']);
        $ai = new ReversiAI();

        $move = $ai->getBestMove($engine, 2);

        if ($move) {
            // Small delay to make it feel more natural
            usleep(500000); // 0.5 seconds

            // Make the AI move directly (not through makeMove which checks userId)
            $engine->makeMove($move['row'], $move['col'], 2);

            // Save move to history
            $stmt = $this->db->prepare("INSERT INTO moves (game_id, player, row, col) VALUES (:game_id, :player, :row, :col)");
            $stmt->bindValue(':game_id', $gameId, SQLITE3_INTEGER);
            $stmt->bindValue(':player', 2, SQLITE3_INTEGER);
            $stmt->bindValue(':row', $move['row'], SQLITE3_INTEGER);
            $stmt->bindValue(':col', $move['col'], SQLITE3_INTEGER);
            $stmt->execute();

            // Determine next player
            $nextPlayer = 1;

            // Check if next player (human) has valid moves
            if (!$engine->hasValidMoves(1)) {
                // Check if AI has valid moves
                if (!$engine->hasValidMoves(2)) {
                    // Neither player has moves - game is over
                    return $this->endGame($gameId, $engine);
                }
                // Human has no moves but AI does - AI goes again
                $nextPlayer = 2;
                // Recursively make another AI move
                $stmt = $this->db->prepare("UPDATE games SET board = :board, current_player = :current_player, updated_at = CURRENT_TIMESTAMP
                                             WHERE id = :id");
                $stmt->bindValue(':board', $engine->getBoardString(), SQLITE3_TEXT);
                $stmt->bindValue(':current_player', $nextPlayer, SQLITE3_INTEGER);
                $stmt->bindValue(':id', $gameId, SQLITE3_INTEGER);
                $stmt->execute();
                return $this->makeAIMove($gameId);
            }

            // Update game state
            $stmt = $this->db->prepare("UPDATE games SET board = :board, current_player = :current_player, updated_at = CURRENT_TIMESTAMP
                                         WHERE id = :id");
            $stmt->bindValue(':board', $engine->getBoardString(), SQLITE3_TEXT);
            $stmt->bindValue(':current_player', $nextPlayer, SQLITE3_INTEGER);
            $stmt->bindValue(':id', $gameId, SQLITE3_INTEGER);
            $stmt->execute();

            return [
                'success' => true,
                'board' => $engine->getBoard(),
                'score' => $engine->getScore(),
                'current_player' => 1,
                'status' => 'active'
            ];
        } else {
            // AI has no valid moves
            // Check if human has moves
            if (!$engine->hasValidMoves(1)) {
                // Neither player has moves - game is over
                return $this->endGame($gameId, $engine);
            }
            // Pass turn back to human
            $stmt = $this->db->prepare("UPDATE games SET current_player = 1, updated_at = CURRENT_TIMESTAMP WHERE id = :id");
            $stmt->bindValue(':id', $gameId, SQLITE3_INTEGER);
            $stmt->execute();
            return [
                'success' => true,
                'ai_passed' => true,
                'board' => $engine->getBoard(),
                'score' => $engine->getScore(),
                'current_player' => 1,
                'status' => 'active'
            ];
        }
    }

    private function endGame($gameId, $engine) {
        $winner = $engine->getWinner();
        $game = $this->getGame($gameId);

        $winnerId = null;
        if ($winner == 1) {
            $winnerId = $game['player1_id'];
        } else if ($winner == 2) {
            // For AI games, player2_id might be null, so use -1 to indicate AI won
            $winnerId = $game['player2_id'] ? $game['player2_id'] : -1;
        }
        // If winner is 0 (tie), winnerId stays null

        $stmt = $this->db->prepare("UPDATE games SET status = 'finished', winner_id = :winner_id, updated_at = CURRENT_TIMESTAMP
                                     WHERE id = :id");
        $stmt->bindValue(':winner_id', $winnerId, SQLITE3_INTEGER);
        $stmt->bindValue(':id', $gameId, SQLITE3_INTEGER);
        $stmt->execute();

        return [
            'success' => true,
            'game_over' => true,
            'winner' => $winner,
            'score' => $engine->getScore()
        ];
    }

    private function generateJoinKey() {
        return strtoupper(substr(md5(uniqid(rand(), true)), 0, 8));
    }

    public function getUserGames($userId) {
        $stmt = $this->db->prepare("SELECT * FROM games
                                     WHERE player1_id = :user_id OR player2_id = :user_id
                                     ORDER BY updated_at DESC LIMIT 10");
        $stmt->bindValue(':user_id', $userId, SQLITE3_INTEGER);
        $result = $stmt->execute();

        $games = [];
        while ($game = $result->fetchArray(SQLITE3_ASSOC)) {
            $games[] = $game;
        }
        return $games;
    }

    public function deleteGame($gameId, $userId) {
        // Check if user is part of this game
        $game = $this->getGame($gameId);
        if (!$game) {
            return ['success' => false, 'error' => 'Game not found'];
        }

        if ($game['player1_id'] != $userId && $game['player2_id'] != $userId) {
            return ['success' => false, 'error' => 'You are not part of this game'];
        }

        // Delete related moves first
        $stmt = $this->db->prepare("DELETE FROM moves WHERE game_id = :game_id");
        $stmt->bindValue(':game_id', $gameId, SQLITE3_INTEGER);
        $stmt->execute();

        // Delete the game
        $stmt = $this->db->prepare("DELETE FROM games WHERE id = :id");
        $stmt->bindValue(':id', $gameId, SQLITE3_INTEGER);
        $stmt->execute();

        return ['success' => true];
    }
    
    public function getMoveCount($gameId) {
        $stmt = $this->db->prepare("SELECT COUNT(*) as move_count FROM moves WHERE game_id = :game_id");
        $stmt->bindValue(':game_id', $gameId, SQLITE3_INTEGER);
        $result = $stmt->execute();
        $moveCount = $result->fetchArray(SQLITE3_ASSOC);
        
        return $moveCount['move_count'];
    }

    public function forfeitGame($gameId, $userId) {
        $game = $this->getGame($gameId);
        if (!$game) {
            return ['success' => false, 'error' => 'Game not found'];
        }

        if ($game['player1_id'] != $userId && $game['player2_id'] != $userId) {
            return ['success' => false, 'error' => 'You are not part of this game'];
        }

        if ($game['status'] == 'finished') {
            return ['success' => false, 'error' => 'Game already finished'];
        }

        // Determine winner (opponent)
        $winnerId = null;
        if ($game['player1_id'] == $userId) {
            $winnerId = $game['player2_id'];
        } else {
            $winnerId = $game['player1_id'];
        }

        // Update game status
        $stmt = $this->db->prepare("UPDATE games SET status = 'finished', winner_id = :winner_id, updated_at = CURRENT_TIMESTAMP
                                     WHERE id = :id");
        $stmt->bindValue(':winner_id', $winnerId, SQLITE3_INTEGER);
        $stmt->bindValue(':id', $gameId, SQLITE3_INTEGER);
        $stmt->execute();

        return ['success' => true];
    }
}
