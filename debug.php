<!DOCTYPE html>
<html>
<head>
    <title>Debug Reversi Logic</title>
    <style>
        table { border-collapse: collapse; margin: 20px; }
        td {
            width: 40px;
            height: 40px;
            border: 1px solid #000;
            text-align: center;
            vertical-align: middle;
        }
        .black { background: #000; color: #fff; }
        .white { background: #fff; }
        .empty { background: #ccc; }
        .valid { background: #0f0; font-weight: bold; }
    </style>
</head>
<body>
    <h1>Reversi Logic Debug</h1>

    <?php
    require_once 'game_engine.php';

    $engine = new ReversiEngine();
    $board = $engine->getBoard();
    $validMoves1 = $engine->getValidMoves(1);
    $validMoves2 = $engine->getValidMoves(2);

    echo "<h2>Initial Board</h2>";
    echo "<p>0=empty, 1=black, 2=white</p>";
    echo "<table>";
    echo "<tr><th></th>";
    for ($c = 0; $c < 8; $c++) echo "<th>$c</th>";
    echo "</tr>";

    for ($r = 0; $r < 8; $r++) {
        echo "<tr><th>$r</th>";
        for ($c = 0; $c < 8; $c++) {
            $val = $board[$r][$c];
            $class = $val == 1 ? 'black' : ($val == 2 ? 'white' : 'empty');

            // Check if this is a valid move for either player
            $validFor1 = false;
            $validFor2 = false;
            foreach ($validMoves1 as $m) {
                if ($m['row'] == $r && $m['col'] == $c) $validFor1 = true;
            }
            foreach ($validMoves2 as $m) {
                if ($m['row'] == $r && $m['col'] == $c) $validFor2 = true;
            }

            if ($validFor1) $class = 'valid';

            echo "<td class='$class'>";
            if ($val == 1) echo "B";
            else if ($val == 2) echo "W";
            else if ($validFor1) echo "1";
            else if ($validFor2) echo "2";
            else echo "-";
            echo "</td>";
        }
        echo "</tr>";
    }
    echo "</table>";

    echo "<p>Green cells with '1' = valid moves for Black (player 1)</p>";
    echo "<p>Cells with '2' = valid moves for White (player 2)</p>";

    echo "<h3>Valid Moves for Black (Player 1):</h3>";
    echo "<ul>";
    foreach ($validMoves1 as $move) {
        echo "<li>Row {$move['row']}, Col {$move['col']}</li>";
    }
    echo "</ul>";

    echo "<h3>Valid Moves for White (Player 2):</h3>";
    echo "<ul>";
    foreach ($validMoves2 as $move) {
        echo "<li>Row {$move['row']}, Col {$move['col']}</li>";
    }
    echo "</ul>";

    // Standard opening moves should be:
    echo "<h3>Expected Valid Moves for Black:</h3>";
    echo "<p>Standard Othello opening positions (Black moves first):</p>";
    echo "<ul>";
    echo "<li>Row 2, Col 3 (flips white at 3,3)</li>";
    echo "<li>Row 3, Col 2 (flips white at 3,3)</li>";
    echo "<li>Row 4, Col 5 (flips white at 4,4)</li>";
    echo "<li>Row 5, Col 4 (flips white at 4,4)</li>";
    echo "</ul>";
    ?>
</body>
</html>
