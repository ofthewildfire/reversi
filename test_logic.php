<?php
require_once 'game_engine.php';

echo "=== Testing Reversi Game Logic ===\n\n";

// Create a new game
$engine = new ReversiEngine();
$board = $engine->getBoard();

echo "Initial Board (0=empty, 1=black, 2=white):\n";
for ($r = 0; $r < 8; $r++) {
    for ($c = 0; $c < 8; $c++) {
        echo $board[$r][$c] . ' ';
    }
    echo "\n";
}

echo "\nInitial setup should be:\n";
echo "Row 3: 0 0 0 2 1 0 0 0 (white at 3,3 and black at 3,4)\n";
echo "Row 4: 0 0 0 1 2 0 0 0 (black at 4,3 and white at 4,4)\n\n";

// Get valid moves for player 1 (black)
echo "Valid moves for Player 1 (Black):\n";
$validMoves = $engine->getValidMoves(1);
foreach ($validMoves as $move) {
    echo "  Row {$move['row']}, Col {$move['col']}\n";

    // Verify each move manually
    $testEngine = new ReversiEngine($engine->getBoardString());
    if (!$testEngine->isValidMove($move['row'], $move['col'], 1)) {
        echo "    ERROR: This move should be valid but isValidMove() returns false!\n";
    }

    // Try to make the move and see what gets flipped
    $testEngine->makeMove($move['row'], $move['col'], 1);
    $newBoard = $testEngine->getBoard();
    echo "    After move, would flip pieces to get black at: ";
    for ($r = 0; $r < 8; $r++) {
        for ($c = 0; $c < 8; $c++) {
            if ($board[$r][$c] != $newBoard[$r][$c] && $newBoard[$r][$c] == 1) {
                echo "($r,$c) ";
            }
        }
    }
    echo "\n";
}

echo "\nExpected valid moves for Black (standard Reversi opening):\n";
echo "  Row 2, Col 3 (would flip 3,3)\n";
echo "  Row 3, Col 2 (would flip 3,3)\n";
echo "  Row 4, Col 5 (would flip 4,4)\n";
echo "  Row 5, Col 4 (would flip 4,4)\n";

// Test a specific move manually
echo "\n=== Testing specific move: Row 2, Col 3 ===\n";
$testMove = $engine->isValidMove(2, 3, 1);
echo "Is (2,3) valid for Black? " . ($testMove ? "YES" : "NO") . "\n";

// Check each direction
echo "Checking directions from (2,3):\n";
$directions = [
    'Up' => [-1, 0],
    'Down' => [1, 0],
    'Left' => [0, -1],
    'Right' => [0, 1],
    'Up-Left' => [-1, -1],
    'Up-Right' => [-1, 1],
    'Down-Left' => [1, -1],
    'Down-Right' => [1, 1]
];

foreach ($directions as $name => $dir) {
    $r = 2 + $dir[0];
    $c = 3 + $dir[1];
    echo "  $name: ";
    if ($r >= 0 && $r < 8 && $c >= 0 && $c < 8) {
        echo "({$r},{$c}) = " . $board[$r][$c];
        if ($board[$r][$c] == 2) echo " (WHITE - opponent)";
        if ($board[$r][$c] == 1) echo " (BLACK - same)";
        if ($board[$r][$c] == 0) echo " (EMPTY)";
    } else {
        echo "out of bounds";
    }
    echo "\n";
}

echo "\n=== Testing move execution ===\n";
$testEngine2 = new ReversiEngine();
echo "Making move at (2,3) for Black...\n";
$result = $testEngine2->makeMove(2, 3, 1);
echo "Move result: " . ($result ? "SUCCESS" : "FAILED") . "\n";

if ($result) {
    $newBoard = $testEngine2->getBoard();
    echo "Board after move:\n";
    for ($r = 0; $r < 8; $r++) {
        for ($c = 0; $c < 8; $c++) {
            echo $newBoard[$r][$c] . ' ';
        }
        echo "\n";
    }

    $score = $testEngine2->getScore();
    echo "Score - Black: {$score['black']}, White: {$score['white']}\n";
}
