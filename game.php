<?php
require_once 'auth.php';
require_once 'game_manager.php';

$auth = new Auth();

if (!$auth->isLoggedIn()) {
    header('Location: index.php');
    exit;
}

$gameId = $_GET['id'] ?? 0;
$gameManager = new GameManager();
$game = $gameManager->getGame($gameId);

if (!$game) {
    header('Location: index.php');
    exit;
}

// Check if user is part of this game
$userId = $auth->getUserId();
if ($game['player1_id'] != $userId && $game['player2_id'] != $userId) {
    header('Location: index.php');
    exit;
}

$isPlayer1 = ($game['player1_id'] == $userId);
$playerNumber = $isPlayer1 ? 1 : 2;

require_once 'game_engine.php';
$engine = new ReversiEngine($game['board']);
$board = $engine->getBoard();
$score = $engine->getScore();
// Only get valid moves if it's this player's turn
$validMoves = ($game['current_player'] == $playerNumber) ? $engine->getValidMoves($playerNumber) : [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reversi Game #<?php echo $gameId; ?></title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <header>
            <h1>Reversi Game #<?php echo $gameId; ?></h1>
            <div style="display: flex; gap: 12px;">
                <?php if ($game['status'] != 'finished'): ?>
                    <button onclick="forfeitGame()" class="btn btn-danger">Forfeit Game</button>
                <?php endif; ?>
                <button onclick="deleteGame()" class="btn btn-danger">Delete Game</button>
                <a href="index.php" class="btn btn-secondary">Back to Menu</a>
            </div>
        </header>

        <div class="game-container">
            <div class="game-info">
                <div class="players">
                    <div class="player <?php echo $game['current_player'] == 1 ? 'active' : ''; ?>" id="player1">
                        <div class="player-piece black"></div>
                        <div class="player-details">
                            <strong><?php echo htmlspecialchars($auth->getUsernameById($game['player1_id'])); ?></strong>
                            <span class="score" id="player1-score"><?php echo $score['black']; ?> pieces</span>
                        </div>
                    </div>
                    <div class="player <?php echo $game['current_player'] == 2 ? 'active' : ''; ?>" id="player2">
                        <div class="player-piece white"></div>
                        <div class="player-details">
                            <strong>
                                <?php
                                if ($game['game_mode'] == 'ai') {
                                    echo 'Computer';
                                } else {
                                    echo $game['player2_id'] ? htmlspecialchars($auth->getUsernameById($game['player2_id'])) : 'Waiting...';
                                }
                                ?>
                            </strong>
                            <span class="score" id="player2-score"><?php echo $score['white']; ?> pieces</span>
                        </div>
                    </div>
                </div>

                <?php if ($game['status'] == 'waiting'): ?>
                    <div class="game-status waiting">
                        <p>Waiting for opponent to join...</p>
                        <p>Share this key: <strong><?php echo htmlspecialchars($game['join_key']); ?></strong></p>
                    </div>
                <?php elseif ($game['status'] == 'active'): ?>
                    <div class="game-status" id="turn-indicator">
                        <?php
                        if ($game['current_player'] == $playerNumber) {
                            if (empty($validMoves)) {
                                echo '<p class="no-moves">No Valid Moves Available</p>';
                                echo '<button onclick="passTurn()" class="btn btn-primary" style="margin-top: 10px;">Pass Turn</button>';
                            } else {
                                echo '<p class="your-turn">Your Turn!</p>';
                                echo '<p style="font-size: 0.9em; color: #6b7280; margin-top: 8px;">Click a highlighted square to play</p>';
                            }
                        } else {
                            echo '<p>Opponent\'s Turn</p>';
                        }
                        ?>
                    </div>
                <?php elseif ($game['status'] == 'finished'): ?>
                    <div class="game-status finished">
                        <h2>Game Over!</h2>
                        <?php
                        if ($game['winner_id'] == $userId) {
                            echo '<p class="winner">You Won!</p>';
                        } elseif ($game['winner_id'] == null) {
                            echo '<p class="tie">It\'s a Tie!</p>';
                        } elseif ($game['winner_id'] == -1) {
                            echo '<p class="loser">Computer Won!</p>';
                        } else {
                            echo '<p class="loser">You Lost</p>';
                        }
                        ?>
                        <p>Final Score: Black <?php echo $score['black']; ?> - White <?php echo $score['white']; ?></p>
                    </div>
                <?php endif; ?>

                <div id="message" class="message"></div>
                <div id="ai-thinking" class="message" style="display: none;">
                    <p>Computer is thinking...</p>
                </div>
            </div>

            <div class="board-wrapper">
                <table class="board" id="game-board">
                    <?php for ($row = 0; $row < 8; $row++): ?>
                        <tr>
                            <?php for ($col = 0; $col < 8; $col++): ?>
                                <?php
                                $cellValue = $board[$row][$col];
                                $isValid = false;
                                foreach ($validMoves as $move) {
                                    if ($move['row'] == $row && $move['col'] == $col) {
                                        $isValid = true;
                                        break;
                                    }
                                }
                                ?>
                                <td class="cell <?php echo $isValid ? 'valid-move' : ''; ?>"
                                    data-row="<?php echo $row; ?>"
                                    data-col="<?php echo $col; ?>">
                                    <?php if ($cellValue == 1): ?>
                                        <div class="piece black"></div>
                                    <?php elseif ($cellValue == 2): ?>
                                        <div class="piece white"></div>
                                    <?php endif; ?>
                                </td>
                            <?php endfor; ?>
                        </tr>
                    <?php endfor; ?>
                </table>
            </div>
        </div>
    </div>

    <script>
        const gameId = <?php echo $gameId; ?>;
        const playerNumber = <?php echo $playerNumber; ?>;
        const gameStatus = '<?php echo $game['status']; ?>';
        const gameMode = '<?php echo $game['game_mode']; ?>';

        // Handle cell clicks
        document.querySelectorAll('.cell').forEach(cell => {
            cell.addEventListener('click', function() {
                if (!this.classList.contains('valid-move') || gameStatus !== 'active') {
                    return;
                }

                const row = this.dataset.row;
                const col = this.dataset.col;
                makeMove(row, col);
            });
        });

        function makeMove(row, col) {
            fetch('api.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=make_move&game_id=${gameId}&row=${row}&col=${col}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Wait a moment then refresh the board
                    setTimeout(() => refreshBoard(), 100);
                } else {
                    showMessage(data.error || 'Invalid move', 'error');
                }
            })
            .catch(error => {
                showMessage('Error making move', 'error');
            });
        }

        function refreshBoard() {
            fetch(`api.php?action=get_board&game_id=${gameId}&player_number=${playerNumber}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    updateBoard(data.board);
                    updateScore(data.score);
                    updateValidMoves(data.valid_moves);
                    updateTurnIndicator(data.current_player, data.status, data.valid_moves);

                    if (data.status === 'finished') {
                        setTimeout(() => location.reload(), 500);
                    }
                }
            })
            .catch(error => console.error('Error refreshing board:', error));
        }

        function updateBoard(board) {
            const cells = document.querySelectorAll('.cell');
            cells.forEach(cell => {
                const row = parseInt(cell.dataset.row);
                const col = parseInt(cell.dataset.col);
                const cellValue = board[row][col];
                
                // Get existing piece
                let piece = cell.querySelector('.piece');
                
                // If there's no piece but there should be one, create it
                if (!piece && cellValue !== 0) {
                    piece = document.createElement('div');
                    piece.className = 'piece';
                    cell.appendChild(piece);
                    
                    // Add animation for new pieces
                    piece.classList.add('new-piece');
                    if (cellValue == 1) {
                        piece.classList.add('black');
                    } else if (cellValue == 2) {
                        piece.classList.add('white');
                    }
                }
                // If there's a piece but it shouldn't be there, remove it
                else if (piece && cellValue === 0) {
                    piece.remove();
                }
                // If there's a piece and its color changed, flip it
                else if (piece && cellValue !== 0) {
                    const isBlack = piece.classList.contains('black');
                    const isWhite = piece.classList.contains('white');
                    const shouldBeBlack = cellValue == 1;
                    const shouldBeWhite = cellValue == 2;
                    
                    // If color changed, apply flipping animation
                    if ((isBlack && shouldBeWhite) || (isWhite && shouldBeBlack)) {
                        // Remove any existing animation classes
                        piece.classList.remove('flipping', 'black', 'white');
                        
                        // Add flipping animation
                        piece.classList.add('flipping');
                        
                        // After animation completes, update the piece color
                        setTimeout(() => {
                            piece.classList.remove('flipping');
                            if (shouldBeBlack) {
                                piece.classList.add('black');
                            } else {
                                piece.classList.add('white');
                            }
                        }, 500);
                    }
                    // If color is the same but piece doesn't have color class, add it
                    else if (shouldBeBlack && !isBlack) {
                        piece.classList.add('black');
                    } else if (shouldBeWhite && !isWhite) {
                        piece.classList.add('white');
                    }
                }
            });
        }

        function updateScore(score) {
            const blackScores = document.querySelectorAll('.score');
            if (blackScores.length >= 2) {
                blackScores[0].textContent = score.black + ' pieces';
                blackScores[1].textContent = score.white + ' pieces';
            }
        }

        function updateValidMoves(validMoves) {
            // Remove all valid-move classes
            document.querySelectorAll('.cell').forEach(cell => {
                cell.classList.remove('valid-move');
            });

            // Add valid-move class to valid cells
            validMoves.forEach(move => {
                const cell = document.querySelector(`[data-row="${move.row}"][data-col="${move.col}"]`);
                if (cell) {
                    cell.classList.add('valid-move');
                }
            });
        }

        function updateTurnIndicator(currentPlayer, status, validMoves) {
            const players = document.querySelectorAll('.player');
            if (players.length >= 2) {
                if (currentPlayer == 1) {
                    players[0].classList.add('active');
                    players[1].classList.remove('active');
                } else {
                    players[0].classList.remove('active');
                    players[1].classList.add('active');
                }
            }

            const turnIndicator = document.getElementById('turn-indicator');
            if (turnIndicator && status === 'active') {
                if (currentPlayer == playerNumber) {
                    if (validMoves.length === 0) {
                        turnIndicator.innerHTML = '<p class="no-moves">No Valid Moves Available</p><button onclick="refreshBoard()" class="btn btn-primary" style="margin-top: 10px;">Pass Turn</button>';
                    } else {
                        turnIndicator.innerHTML = '<p class="your-turn">Your Turn!</p><p style="font-size: 0.9em; color: #6b7280; margin-top: 8px;">Click a highlighted square to play</p>';
                    }
                } else {
                    turnIndicator.innerHTML = '<p>Opponent\'s Turn</p>';
                }
            }
        }

        function showMessage(message, type) {
            const msgEl = document.getElementById('message');
            msgEl.textContent = message;
            msgEl.className = 'message ' + type;
            setTimeout(() => {
                msgEl.textContent = '';
                msgEl.className = 'message';
            }, 3000);
        }

        function deleteGame() {
            if (!confirm('Are you sure you want to delete this game? This cannot be undone.')) {
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
                    window.location.href = 'index.php';
                } else {
                    showMessage(data.error || 'Error deleting game', 'error');
                }
            })
            .catch(error => {
                showMessage('Error deleting game', 'error');
            });
        }

        function forfeitGame() {
            if (!confirm('Are you sure you want to forfeit this game?')) {
                return;
            }

            fetch('api.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=forfeit_game&game_id=${gameId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    showMessage(data.error || 'Error forfeiting game', 'error');
                }
            })
            .catch(error => {
                showMessage('Error forfeiting game', 'error');
            });
        }

        function passTurn() {
            // For now, just reload the page to pass turn
            location.reload();
        }

        // Auto-refresh board for active games
        let lastUpdatedAt = '<?php echo $game['updated_at']; ?>';

        if (gameStatus === 'active') {
            setInterval(() => {
                fetch(`api.php?action=get_game&game_id=${gameId}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.updated_at !== lastUpdatedAt) {
                            lastUpdatedAt = data.updated_at;
                            refreshBoard();
                        }
                    })
                    .catch(error => console.error('Error checking for updates:', error));
            }, 1000); // Check every second
        }

        // Auto-refresh for waiting games
        if (gameStatus === 'waiting') {
            setInterval(() => {
                fetch(`api.php?action=get_game&game_id=${gameId}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.status === 'active') {
                            location.reload();
                        }
                    });
            }, 2000);
        }
    </script>
</body>
</html>
