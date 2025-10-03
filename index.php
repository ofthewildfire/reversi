<?php
require_once 'auth.php';
$auth = new Auth();

// Handle login/register/logout
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] == 'register') {
            $username = $_POST['username'] ?? '';
            $password = $_POST['password'] ?? '';
            if ($auth->register($username, $password)) {
                $auth->login($username, $password);
                header('Location: index.php');
                exit;
            } else {
                $error = 'Username already exists';
            }
        } else if ($_POST['action'] == 'login') {
            $username = $_POST['username'] ?? '';
            $password = $_POST['password'] ?? '';
            if ($auth->login($username, $password)) {
                header('Location: index.php');
                exit;
            } else {
                $error = 'Invalid username or password';
            }
        } else if ($_POST['action'] == 'logout') {
            $auth->logout();
            header('Location: index.php');
            exit;
        }
    }
}

if (!$auth->isLoggedIn()) {
    include 'login.php';
    exit;
}

require_once 'game_manager.php';
$gameManager = new GameManager();

// Handle game actions
if (isset($_GET['action'])) {
    if ($_GET['action'] == 'new_game') {
        $mode = $_GET['mode'] ?? 'ai';
        $game = $gameManager->createGame($auth->getUserId(), $mode);
        header('Location: game.php?id=' . $game['id']);
        exit;
    } else if ($_GET['action'] == 'join_game' && isset($_POST['join_key'])) {
        $result = $gameManager->joinGame($_POST['join_key'], $auth->getUserId());
        if ($result['success']) {
            header('Location: game.php?id=' . $result['game_id']);
            exit;
        } else {
            $error = $result['error'];
        }
    }
}

$userGames = $gameManager->getUserGames($auth->getUserId());
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reversi Game</title>
    <link rel="stylesheet" href="style_v3.css">
</head>
<body>
    <div class="container">
        <header>
            <h1>Reversi</h1>
            <div class="user-info">
                <span>Welcome, <?php echo htmlspecialchars($auth->getUsername()); ?>!</span>
                <form method="POST" style="display: inline;">
                    <input type="hidden" name="action" value="logout">
                    <button type="submit" class="btn btn-secondary">Logout</button>
                </form>
            </div>
        </header>

        <?php if (isset($error)): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <div class="main-menu">
            <h2>Start a New Game</h2>
            <div class="game-options">
                <a href="?action=new_game&mode=ai" class="btn btn-primary">Play vs Computer</a>
                <a href="?action=new_game&mode=multiplayer" class="btn btn-primary">Create Multiplayer Game</a>
            </div>

            <div class="join-game">
                <h3>Join a Game</h3>
                <form method="POST" action="?action=join_game">
                    <input type="text" name="join_key" placeholder="Enter Game Key" required>
                    <button type="submit" class="btn btn-success">Join</button>
                </form>
            </div>

            <?php if (!empty($userGames)): ?>
                <div class="recent-games">
                    <h3>Recent Games</h3>
                    <table>
                        <thead>
                            <tr>
                                <th>Game ID</th>
                                <th>Mode</th>
                                <th>Status</th>
                                <th>Join Key</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($userGames as $game): ?>
                                <tr>
                                    <td>#<?php echo $game['id']; ?></td>
                                    <td><?php echo htmlspecialchars($game['game_mode']); ?></td>
                                    <td><?php echo htmlspecialchars($game['status']); ?></td>
                                    <td><?php echo $game['game_mode'] == 'multiplayer' && $game['status'] == 'waiting' ? htmlspecialchars($game['join_key']) : '-'; ?></td>
                                    <td style="display: flex; gap: 8px;">
                                        <?php if ($game['status'] == 'active' || $game['status'] == 'waiting'): ?>
                                            <a href="game.php?id=<?php echo $game['id']; ?>" class="btn btn-small">Play</a>
                                        <?php else: ?>
                                            <a href="game.php?id=<?php echo $game['id']; ?>" class="btn btn-small">View</a>
                                        <?php endif; ?>
                                        <button onclick="deleteGameFromList(<?php echo $game['id']; ?>)" class="btn btn-small btn-danger">Delete</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <footer>
            made with 🖤 by kay
        </footer>
    </div>

    <script>
        function deleteGameFromList(gameId) {
            if (!confirm('Are you sure you want to delete this game?')) {
                return;
            }

            fetch('api.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=delete_game&game_id=${gameId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert(data.error || 'Error deleting game');
                }
            })
            .catch(error => {
                alert('Error deleting game');
            });
        }
    </script>
</body>
</html>
