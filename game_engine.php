<?php
class ReversiEngine {
    private $board;
    private $size = 8;

    public function __construct($boardString = null) {
        if ($boardString) {
            $this->board = json_decode($boardString, true);
        } else {
            $this->initBoard();
        }
    }

    private function initBoard() {
        // Initialize empty 8x8 board
        $this->board = array_fill(0, $this->size, array_fill(0, $this->size, 0));

        // Set up initial pieces (0 = empty, 1 = black, 2 = white)
        // Black pieces (1) start at positions [3,3] and [4,4]
        // White pieces (2) start at positions [3,4] and [4,3]
        $this->board[3][3] = 2;
        $this->board[3][4] = 1;
        $this->board[4][3] = 1;
        $this->board[4][4] = 2;
    }

    public function getBoardString() {
        return json_encode($this->board);
    }

    public function getBoard() {
        return $this->board;
    }

    public function isValidMove($row, $col, $player) {
        // Check if position is on board and empty
        if ($row < 0 || $row >= $this->size || $col < 0 || $col >= $this->size) {
            return false;
        }
        if ($this->board[$row][$col] != 0) {
            return false;
        }

        // Check all 8 directions
        $directions = [
            [-1, -1], [-1, 0], [-1, 1],
            [0, -1],           [0, 1],
            [1, -1],  [1, 0],  [1, 1]
        ];

        foreach ($directions as $dir) {
            if ($this->checkDirection($row, $col, $dir[0], $dir[1], $player)) {
                return true;
            }
        }

        return false;
    }

    private function checkDirection($row, $col, $dRow, $dCol, $player) {
        $opponent = $player == 1 ? 2 : 1;
        $r = $row + $dRow;
        $c = $col + $dCol;
        $foundOpponent = false;

        while ($r >= 0 && $r < $this->size && $c >= 0 && $c < $this->size) {
            if ($this->board[$r][$c] == 0) {
                return false;
            }
            if ($this->board[$r][$c] == $opponent) {
                $foundOpponent = true;
            } else if ($this->board[$r][$c] == $player) {
                return $foundOpponent;
            }
            $r += $dRow;
            $c += $dCol;
        }

        return false;
    }

    public function makeMove($row, $col, $player) {
        if (!$this->isValidMove($row, $col, $player)) {
            return false;
        }

        $this->board[$row][$col] = $player;

        // Flip pieces in all valid directions
        $directions = [
            [-1, -1], [-1, 0], [-1, 1],
            [0, -1],           [0, 1],
            [1, -1],  [1, 0],  [1, 1]
        ];

        foreach ($directions as $dir) {
            if ($this->checkDirection($row, $col, $dir[0], $dir[1], $player)) {
                $this->flipPieces($row, $col, $dir[0], $dir[1], $player);
            }
        }

        return true;
    }

    private function flipPieces($row, $col, $dRow, $dCol, $player) {
        $opponent = $player == 1 ? 2 : 1;
        $r = $row + $dRow;
        $c = $col + $dCol;

        while ($r >= 0 && $r < $this->size && $c >= 0 && $c < $this->size) {
            if ($this->board[$r][$c] == $opponent) {
                $this->board[$r][$c] = $player;
            } else {
                break;
            }
            $r += $dRow;
            $c += $dCol;
        }
    }

    public function getValidMoves($player) {
        $moves = [];
        for ($r = 0; $r < $this->size; $r++) {
            for ($c = 0; $c < $this->size; $c++) {
                if ($this->isValidMove($r, $c, $player)) {
                    $moves[] = ['row' => $r, 'col' => $c];
                }
            }
        }
        return $moves;
    }

    public function hasValidMoves($player) {
        return count($this->getValidMoves($player)) > 0;
    }

    public function isGameOver() {
        return !$this->hasValidMoves(1) && !$this->hasValidMoves(2);
    }

    public function getScore() {
        $black = 0;
        $white = 0;
        for ($r = 0; $r < $this->size; $r++) {
            for ($c = 0; $c < $this->size; $c++) {
                if ($this->board[$r][$c] == 1) {
                    $black++;
                } else if ($this->board[$r][$c] == 2) {
                    $white++;
                }
            }
        }
        return ['black' => $black, 'white' => $white];
    }

    public function getWinner() {
        $score = $this->getScore();
        if ($score['black'] > $score['white']) {
            return 1;
        } else if ($score['white'] > $score['black']) {
            return 2;
        } else {
            return 0; // Tie
        }
    }

    public function evaluateBoard($player) {
        $score = $this->getScore();
        if ($player == 1) {
            return $score['black'] - $score['white'];
        } else {
            return $score['white'] - $score['black'];
        }
    }
}
