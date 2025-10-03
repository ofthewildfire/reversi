# Reversi / Othello Game

A complete web-based implementation of Reversi (Othello) built with PHP and SQLite.

## Features

- **User Authentication**: Register and login system with secure password hashing
- **Two Game Modes**:
  - **vs Computer**: Play against an AI opponent with minimax algorithm
  - **vs Player**: Multiplayer mode with join keys to play with friends
- **Real-time Gameplay**: AJAX-based updates for smooth multiplayer experience
- **Full Game Logic**: Complete Reversi rules implementation including move validation and piece flipping
- **Responsive Design**: Works on desktop and mobile devices
- **Game History**: View your recent games

## Installation

1. **Requirements**:
   - PHP 7.4 or higher with SQLite3 extension enabled
   - Web server (Apache, Nginx, or PHP built-in server)

2. **Setup**:
   ```bash
   # Clone or download the files to your web directory
   cd reversi

   # Make sure the directory is writable for the database file
   chmod 755 .

   # Start PHP built-in server (for development)
   php -S localhost:8000
   ```

3. **Access the game**:
   - Open your browser and navigate to `http://localhost:8000`
   - Register a new account or login
   - Start playing!

## How to Play

### Game Rules
- Reversi is played on an 8x8 board with black and white pieces
- Black plays first
- Players take turns placing pieces on empty squares
- A valid move must flip at least one opponent's piece
- Pieces are flipped when sandwiched between your pieces in any direction
- If you have no valid moves, your turn is passed
- Game ends when the board is full or neither player can move
- Player with the most pieces wins

### Playing vs Computer
1. Click "Play vs Computer" on the main menu
2. You play as Black (goes first)
3. Click on highlighted green squares to make your move
4. The AI will automatically respond

### Playing vs Another Player
1. Click "Create Multiplayer Game"
2. Share the join key with your friend
3. Your friend enters the key on the main menu
4. Take turns making moves

## File Structure

- `index.php` - Main menu and game lobby
- `game.php` - Game board and gameplay interface
- `login.php` - Authentication interface
- `auth.php` - Authentication logic
- `database.php` - Database initialization and connection
- `game_engine.php` - Core Reversi game logic
- `game_manager.php` - Game state management
- `ai.php` - AI opponent with minimax algorithm
- `api.php` - AJAX endpoints for game actions
- `style.css` - All styling
- `reversi.db` - SQLite database (auto-created)

## Database Schema

### Users Table
- `id`: Primary key
- `username`: Unique username
- `password`: Hashed password
- `created_at`: Registration timestamp

### Games Table
- `id`: Primary key
- `join_key`: Unique 8-character join code
- `player1_id`: First player (creates game)
- `player2_id`: Second player (joins game)
- `current_player`: 1 or 2 (whose turn)
- `board`: JSON-encoded board state
- `game_mode`: 'ai' or 'multiplayer'
- `status`: 'waiting', 'active', or 'finished'
- `winner_id`: User ID of winner (null for tie)

### Moves Table
- `id`: Primary key
- `game_id`: Reference to game
- `player`: 1 or 2
- `row`, `col`: Move coordinates
- `created_at`: Move timestamp

## AI Implementation

The AI uses a minimax algorithm with alpha-beta pruning:
- **Early game**: Uses positional heuristics (corners, edges)
- **Mid-late game**: Uses minimax with depth 4
- **Evaluation**: Combines positional weights and mobility

## Security

- Passwords are hashed using PHP's `password_hash()` with bcrypt
- SQL injection protection using prepared statements
- Session-based authentication
- CSRF protection recommended for production use

## Credits

Game developed as a complete PHP implementation of Reversi/Othello.

## License

Free to use and modify.
