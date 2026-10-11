# String Matching Analysis: Naive vs Rabin-Karp

A Design and Analysis of Algorithms (DAA) project that compares the performance of the Naive (Brute-Force) and Rabin-Karp (Rolling Hash) string matching algorithms using empirical tests and an interactive step-by-step decision tree.

---

## Tech Stack

<p align="left">
  <img src="https://img.shields.io/badge/PHP_8-777BB4?style=flat-square&logo=php&logoColor=white" alt="PHP" />
  <img src="https://img.shields.io/badge/PostgreSQL-4169E1?style=flat-square&logo=postgresql&logoColor=white" alt="PostgreSQL" />
  <img src="https://img.shields.io/badge/HTML5-E34F26?style=flat-square&logo=html5&logoColor=white" alt="HTML5" />
  <img src="https://img.shields.io/badge/CSS3-1572B6?style=flat-square&logo=css3&logoColor=white" alt="CSS3" />
  <img src="https://img.shields.io/badge/JavaScript-F7DF1E?style=flat-square&logo=javascript&logoColor=black" alt="JavaScript" />
</p>

---

## About the Project

This project focuses on the substring search problem, where the goal is to locate a given pattern string within a larger block of text.

Instead of just checking whether a pattern exists, this application:
1. Runs either the Naive algorithm or the Rabin-Karp algorithm based on user selection.
2. Measures execution time in milliseconds using high-precision timers.
3. Counts the exact number of character comparisons made during execution.
4. Stores the search history in a PostgreSQL database safely using prepared statements.
5. Renders a clear visual decision tree with circular nodes showing step-by-step progress:
   - Green nodes represent character matches.
   - Red nodes represent character mismatches where that search path ends.
   - Blue nodes represent hash matches in Rabin-Karp.
   - Yellow nodes represent hash mismatches where the window is skipped.
   - Highlighted green badges mark the position where a full match was found.

---

## What Problem Does It Solve?

Searching for text inside documents is a core part of software like search engines, code editors, and biological DNA analyzers.

- The Naive approach tests characters one by one. On repetitive inputs or large texts, this can be slow because it checks almost every window manually.
- The Rabin-Karp approach converts windows of text into numeric hash values. Because numbers can be compared in constant time, it skips windows where the hash does not match without having to inspect every individual character.

This project gives students and teachers a direct, practical way to test both algorithms side by side and see how hash-based filtering reduces character comparisons.

---

## Algorithms Explained

### 1. Naive String Matching (Brute Force)
- Slides the pattern over the text one position at a time.
- Compares characters from left to right until it finds a mismatch or confirms a match.
- Best Case Time: O(n)
- Worst Case Time: O((n - m + 1) * m)
- Space Complexity: O(1)

### 2. Rabin-Karp Algorithm (Rolling Hash)
- Calculates a hash value for the pattern and for the first window of text.
- If the current window hash does not match the pattern hash, it skips directly to the next window.
- If the hashes match, it checks the characters one by one to verify that it is an actual match and not a hash collision.
- Shifts the window using a rolling hash formula in O(1) time.
- Average Case Time: O(n + m)
- Worst Case Time: O(n * m) (only happens if hash collisions occur constantly)
- Space Complexity: O(1)

---

## Performance Comparison

| Criteria | Naive Algorithm | Rabin-Karp Algorithm |
|---|---|---|
| Average Time Complexity | O(n * m) | O(n + m) |
| Preprocessing Time | None | O(m) to hash the pattern |
| Total Comparisons | High (compares characters in most windows) | Low (compares characters only on hash matches) |
| Best Application | Short text inputs with diverse characters | Larger texts or repeated character patterns |

### Why Rabin-Karp is Faster
Rabin-Karp turns string comparisons into integer comparisons. Since checking two numbers is very fast, it can discard most non-matching positions immediately. This keeps the total number of character comparisons much lower.

---

## Project Structure

- schema.sql: Defines the PostgreSQL table structure and indexes.
- db.php: Central database connection using PDO.
- index.php: Main user interface, input form, and decision tree visualization.
- process.php: Core algorithm implementations, performance measurement, and database insertion.
- display.php: Queries and renders the table of recent search runs.
- README.md: Project documentation.

---

## How to Set Up and Run

### 1. Requirements
- PHP 8 or newer (with pdo_pgsql extension enabled)
- PostgreSQL installed and running

### 2. Database Setup
Create the database and run the schema file:
```sql
CREATE DATABASE daa_project;
\c daa_project
\i schema.sql
```

### 3. Database Configuration
Confirm that the database credentials in `db.php` match your local PostgreSQL setup:
```php
define('DB_HOST', '127.0.0.1');
define('DB_PORT', '5432');
define('DB_NAME', 'daa_project');
define('DB_USER', 'postgres');
define('DB_PASS', 'admin');
```

### 4. Start the Application
Run the PHP development server from the project folder:
```bash
php -S localhost:8000
```

Open `http://localhost:8000` in your browser.
