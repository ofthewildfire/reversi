<?php
require_once 'auth.php';
require_once 'game_manager.php';

header('Content-Type: application/json');

$auth = new Auth();

if (!$auth->isLoggedIn()) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

$gameManager = new GameManager();

switch ($action) {
    case 'make_move':
        $gameId = $_POST['game_id'] ?? 0;
        $row = $_POST['row'] ?? -1;
        $col = $_POST['col'] ?? -1;
        $userId = $auth->getUserId();

        $result = $gameManager->makeMove($gameId, $userId, $row, $col);
        
        // Add board state and scores to the response
        if ($result['success']) {
            $game = $gameManager->getGame($gameId);
            if ($game) {
                $engine = new ReversiEngine($game['board']);
                $result['board'] = $engine->getBoard();
                $result['score'] = $engine->getScore();
                $result['current_player'] = $game['current_player'];
                $result['status'] = $game['status'];
            }
        }
        
        echo json_encode($result);
        break;

    case 'get_game':
        $gameId = $_GET['game_id'] ?? 0;
        $game = $gameManager->getGame($gameId);

        if ($game) {
            echo json_encode($game);
        } else {
            echo json_encode(['success' => false, 'error' => 'Game not found']);
        }
        break;

    case 'get_board':
        $gameId = $_GET['game_id'] ?? 0;
        $playerNumber = $_GET['player_number'] ?? 1;
        $game = $gameManager->getGame($gameId);

        if ($game) {
            require_once 'game_engine.php';
            $engine = new ReversiEngine($game['board']);

            // Get valid moves only if it's this player's turn
            $validMoves = [];
            if ($game['current_player'] == $playerNumber) {
                $validMoves = $engine->getValidMoves($playerNumber);
            }

            echo json_encode([
                'success' => true,
                'board' => $engine->getBoard(),
                'score' => $engine->getScore(),
                'current_player' => $game['current_player'],
                'status' => $game['status'],
                'valid_moves' => $validMoves,
                'winner_id' => $game['winner_id'],
                'updated_at' => $game['updated_at']
            ]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Game not found']);
        }
        break;

    case 'get_move_count':
        $gameId = $_GET['game_id'] ?? 0;
        $stmt = $gameManager->db->prepare("SELECT COUNT(*) as move_count FROM moves WHERE game_id = :game_id");
        $stmt->bindValue(':game_id', $gameId, SQLITE3_INTEGER);
        $result = $stmt->execute();
        $moveCount = $result->fetchArray(SQLITE3_ASSOC);
        
        echo json_encode([
            'success' => true,
            'move_count' => $moveCount['move_count']
        ]);
        break;

    case 'delete_game':
        $gameId = $_POST['game_id'] ?? 0;
        $userId = $auth->getUserId();
        $result = $gameManager->deleteGame($gameId, $userId);
        echo json_encode($result);
        break;

    case 'forfeit_game':
        $gameId = $_POST['game_id'] ?? 0;
        $userId = $auth->getUserId();
        $result = $gameManager->forfeitGame($gameId, $userId);
        echo json_encode($result);
        break;

    default:
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
        break;
}
