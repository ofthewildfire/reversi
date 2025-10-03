<?php
require_once 'game_engine.php';

class ReversiAI {
    private $maxDepth = 4; // AI difficulty level

    public function getBestMove($engine, $player) {
        $validMoves = $engine->getValidMoves($player);

        if (empty($validMoves)) {
            return null;
        }

        // Early game: use faster heuristic
        $score = $engine->getScore();
        $totalPieces = $score['black'] + $score['white'];

        if ($totalPieces < 20) {
            return $this->getHeuristicMove($engine, $player, $validMoves);
        }

        // Mid to late game: use minimax
        $bestMove = null;
        $bestScore = PHP_INT_MIN;

        foreach ($validMoves as $move) {
            // Create a copy of the engine to test the move
            $testEngine = new ReversiEngine($engine->getBoardString());
            $testEngine->makeMove($move['row'], $move['col'], $player);

            // Evaluate this move using minimax
            $score = $this->minimax($testEngine, $this->maxDepth - 1, false, $player, PHP_INT_MIN, PHP_INT_MAX);

            if ($score > $bestScore) {
                $bestScore = $score;
                $bestMove = $move;
            }
        }

        return $bestMove;
    }

    private function minimax($engine, $depth, $maximizing, $player, $alpha, $beta) {
        $opponent = $player == 1 ? 2 : 1;

        // Terminal conditions
        if ($depth == 0 || $engine->isGameOver()) {
            return $this->evaluatePosition($engine, $player);
        }

        $currentPlayer = $maximizing ? $player : $opponent;
        $validMoves = $engine->getValidMoves($currentPlayer);

        // If no valid moves, pass to opponent
        if (empty($validMoves)) {
            return $this->minimax($engine, $depth - 1, !$maximizing, $player, $alpha, $beta);
        }

        if ($maximizing) {
            $maxEval = PHP_INT_MIN;
            foreach ($validMoves as $move) {
                $testEngine = new ReversiEngine($engine->getBoardString());
                $testEngine->makeMove($move['row'], $move['col'], $currentPlayer);
                $eval = $this->minimax($testEngine, $depth - 1, false, $player, $alpha, $beta);
                $maxEval = max($maxEval, $eval);
                $alpha = max($alpha, $eval);
                if ($beta <= $alpha) {
                    break; // Beta cutoff
                }
            }
            return $maxEval;
        } else {
            $minEval = PHP_INT_MAX;
            foreach ($validMoves as $move) {
                $testEngine = new ReversiEngine($engine->getBoardString());
                $testEngine->makeMove($move['row'], $move['col'], $currentPlayer);
                $eval = $this->minimax($testEngine, $depth - 1, true, $player, $alpha, $beta);
                $minEval = min($minEval, $eval);
                $beta = min($beta, $eval);
                if ($beta <= $alpha) {
                    break; // Alpha cutoff
                }
            }
            return $minEval;
        }
    }

    private function evaluatePosition($engine, $player) {
        $board = $engine->getBoard();
        $opponent = $player == 1 ? 2 : 1;

        // Positional weights (corners and edges are valuable)
        $weights = [
            [100, -20, 10,  5,  5, 10, -20, 100],
            [-20, -50, -2, -2, -2, -2, -50, -20],
            [ 10,  -2, -1, -1, -1, -1,  -2,  10],
            [  5,  -2, -1, -1, -1, -1,  -2,   5],
            [  5,  -2, -1, -1, -1, -1,  -2,   5],
            [ 10,  -2, -1, -1, -1, -1,  -2,  10],
            [-20, -50, -2, -2, -2, -2, -50, -20],
            [100, -20, 10,  5,  5, 10, -20, 100]
        ];

        $score = 0;
        for ($r = 0; $r < 8; $r++) {
            for ($c = 0; $c < 8; $c++) {
                if ($board[$r][$c] == $player) {
                    $score += $weights[$r][$c];
                } else if ($board[$r][$c] == $opponent) {
                    $score -= $weights[$r][$c];
                }
            }
        }

        // Mobility: number of valid moves
        $playerMoves = count($engine->getValidMoves($player));
        $opponentMoves = count($engine->getValidMoves($opponent));
        $score += ($playerMoves - $opponentMoves) * 5;

        return $score;
    }

    private function getHeuristicMove($engine, $player, $validMoves) {
        // Early game strategy: prioritize corners and edges
        $cornerMoves = [];
        $edgeMoves = [];
        $otherMoves = [];

        foreach ($validMoves as $move) {
            $r = $move['row'];
            $c = $move['col'];

            // Check for corners
            if (($r == 0 || $r == 7) && ($c == 0 || $c == 7)) {
                $cornerMoves[] = $move;
            }
            // Check for edges (but not next to corners)
            else if (($r == 0 || $r == 7 || $c == 0 || $c == 7) &&
                     !(($r == 0 || $r == 7) && ($c == 1 || $c == 6)) &&
                     !(($c == 0 || $c == 7) && ($r == 1 || $r == 6))) {
                $edgeMoves[] = $move;
            } else {
                $otherMoves[] = $move;
            }
        }

        // Prefer corners, then edges, then others
        if (!empty($cornerMoves)) {
            return $cornerMoves[array_rand($cornerMoves)];
        }
        if (!empty($edgeMoves)) {
            return $edgeMoves[array_rand($edgeMoves)];
        }
        return $otherMoves[array_rand($otherMoves)];
    }
}
