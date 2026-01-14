<?php
session_start();


// Function to read users from 'users.txt'
function getUsersFilePath() {
    $dataDir = sys_get_temp_dir() . '/quiz-game';
    if (!is_dir($dataDir)) {
        mkdir($dataDir, 0777, true);
    }
    if (is_dir($dataDir) && is_writable($dataDir)) {
        return $dataDir . '/users.txt';
    }
    return __DIR__ . '/users.txt';
}

function getUsers() {
    $users = [];
    $usersFile = getUsersFilePath();
    if (!is_readable($usersFile)) {
        return $users;
    }
    $lines = file($usersFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $parts = explode(':', $line, 2);
        if (count($parts) === 2) {
            $users[$parts[0]] = $parts[1];
        }
    }
    return $users;
}

// Function to load and shuffle questions from a file
function getQuestions($quiz_file) {
    $questions = [];
    $correct_answers = [];

    $lines = file($quiz_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $parts = explode('|', $line); // Split the line into question, options, and correct answer
        $question_text = array_shift($parts); // The first part is the question
        $correct_answer = array_pop($parts); // The last part is the correct answer

        // Shuffle options and determine the new index of the correct answer
        $original_options = $parts;
        shuffle($parts); // Shuffle options
        $new_correct_index = array_search($correct_answer, $parts) + 1; // 1-based indexing

        // Add the shuffled question and its options
        $questions[] = array_merge([$question_text], $parts);
        $correct_answers[] = $new_correct_index; // Store the index of the correct answer
    }

    return [
        'questions' => $questions,
        'correct_answers' => $correct_answers,
    ];
}


// Quiz selection
$quiz_files = [
    "HTML & CSS Quiz" => "questions_html_css.txt",
    "History Quiz" => "questions_history.txt",
    "Geography Quiz" => "questions_geography.txt",
    "Math Quiz" => "questions_math.txt",
    "Science Quiz" => "questions_science.txt",
    "Sports Quiz" => "questions_sports.txt",
    "Movies Quiz" => "questions_movies.txt",
    "Music Quiz" => "questions_music.txt",
     "Surprise Me!" => "surprise_me.txt"
    
];

$time_limit = 30; // 30 seconds per question


// Login handling
if (isset($_POST['login'])) {
    $users = getUsers();
    $username = $_POST['username'];
    $password = $_POST['password'];

    if (isset($users[$username]) && $users[$username] === $password) {
        $_SESSION['user'] = $username;
    } else {
        $error = "Invalid username or password. Please try again.";
    }
}


// Quiz selection handling
if (isset($_POST['select_quiz'])) {
    $selected_quiz = $_POST['select_quiz'];

    if ($selected_quiz === "Surprise Me!") {
        // Load questions from all quiz files
        $all_questions = [];
        $all_correct_answers = [];
        foreach ($quiz_files as $quiz_name => $file) {
            if ($quiz_name !== "Surprise Me!") { // Exclude "Surprise Me!" itself
                $quiz_data = getQuestions($file);
                $all_questions = array_merge($all_questions, $quiz_data['questions']);
                $all_correct_answers = array_merge($all_correct_answers, $quiz_data['correct_answers']);
            }
        }

        // Shuffle and select 10 random questions while keeping answers aligned
        $combined = [];
        foreach ($all_questions as $index => $question) {
            $combined[] = [
                'question' => $question,
                'correct_answer' => $all_correct_answers[$index],
            ];
        }
        shuffle($combined);
        $combined = array_slice($combined, 0, 10);

        $_SESSION['questions'] = array_column($combined, 'question');
        $_SESSION['correct_option_index'] = array_column($combined, 'correct_answer');
        $_SESSION['quiz_title'] = "Surprise Me!";
    } elseif (isset($quiz_files[$selected_quiz])) {
        // Load questions for the selected quiz
        $quiz_data = getQuestions($quiz_files[$selected_quiz]);
        $_SESSION['questions'] = $quiz_data['questions'];
        $_SESSION['correct_option_index'] = $quiz_data['correct_answers'];
        $_SESSION['quiz_title'] = $selected_quiz;
    }

    // Initialize session variables
    $_SESSION['current_question'] = 0;
    $_SESSION['score'] = 0;
    $_SESSION['timer_start'] = time();
}
// Timer Initialization and Time Check
if (!isset($_SESSION['timer_start'])) {
    $_SESSION['timer_start'] = time(); // Initialize if not already set
}

$current_time = time();
$time_elapsed = $current_time - $_SESSION['timer_start'];

// If time expires, move to the next question
if ($time_elapsed >= $time_limit) {
    $_SESSION['current_question']++;
    $_SESSION['timer_start'] = time(); // Reset timer for the next question
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// Handle "Select Another Quiz" button
if (isset($_POST['select_another_quiz'])) {
    unset($_SESSION['questions']);
    unset($_SESSION['current_question']);
    unset($_SESSION['score']);
    unset($_SESSION['quiz_title']);
}

// Handle answer submission
if (isset($_POST['submit_answer'])) {
    // Fetch the selected answer (button value submitted by the user)
    $selected_index = intval($_POST['answer']); // Ensure it's treated as an integer (1-based index)

    // Fetch the correct index for the current question
    $correct_index = $_SESSION['correct_option_index'][$_SESSION['current_question']]; // 1-based index

    // Debugging (optional for testing purposes)
    // Uncomment the following line during testing to check the indices
    // echo "Selected Index: $selected_index, Correct Index: $correct_index";

    // Compare the selected answer with the correct answer
    if ($selected_index === $correct_index) {
        $_SESSION['score']++; // Increment the score if the user's answer is correct
    }

    // Move to the next question
    $_SESSION['current_question']++;

    // Reset the timer for the next question
    $_SESSION['timer_start'] = time();

    // Redirect to refresh the page and avoid duplicate submissions
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}




// Handle logout
if (isset($_POST['logout'])) {
    session_destroy();
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// Handle quiz reset
if (isset($_POST['reset'])) {
    $_SESSION['current_question'] = 0;
    $_SESSION['score'] = 0;
    $_SESSION['timer_start'] = time();
}

/*if (isset($_POST['select_random_quiz'])) {
    $all_questions = [];
    
    // Combine questions from all quiz files
    foreach ($quiz_files as $file) {
        $questions = getQuestions($file); // Use your existing `getQuestions` function
        $all_questions = array_merge($all_questions, $questions);
    }
    
    // Shuffle the combined questions
    shuffle($all_questions);
    
    // Limit to 10 questions
    $limited_questions = array_slice($all_questions, 0, 10);
    
    // Store the random questions in the session
    $_SESSION['questions'] = $limited_questions;
    $_SESSION['quiz_title'] = "Surprise Me!";
    $_SESSION['current_question'] = 0;
    $_SESSION['score'] = 0;
    $_SESSION['timer_start'] = time();
} */


?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quiz</title>
    <link rel="stylesheet" href="st.css">
</head>
<body>
    <!-- Dark Mode Toggle -->
<div class="theme-toggle">
    <label class="switch">
        <input type="checkbox" id="themeSwitch">
        <span class="slider"></span>
    </label>
</div>


  <!-- Fun and Challenging Header -->
  <?php if (!isset($_SESSION['user'])): ?>
        <!-- Fun and Challenging Header (Only Visible Before Login) -->
        <header class="quiz-header">
            <h1>Are You Ready to Test Your Knowledge?</h1>
            <p>Take the quiz and see how much you know!</p>
        </header>
    <?php endif; ?>

    <?php if (isset($_SESSION['user']) && !isset($_SESSION['questions'])): ?>
    <!-- Fun Header for Quiz Selection -->
    <header class="quiz-header2">
        <h1>Choose Your Challenge!</h1>
        <p>Pick a quiz and put your skills to the test!</p>
    </header>
<?php endif; ?>

<div class="container login-container <?php echo !isset($_SESSION['user']) ? 'login-container--narrow' : ''; ?>">
    <?php if (!isset($_SESSION['user'])): ?>
        <!-- Login Form -->
        <h2>Welcome to the Quiz</h2>
        <?php if (isset($error)): ?>
            <div class="error"><?php echo $error; ?></div>
        <?php endif; ?>
        <form method="POST">
            <div class="form-group">
                <label for="username">Username:</label>
                <input type="text" id="username" name="username" required>
            </div>
            <div class="form-group">
                <label for="password">Password:</label>
                <input type="password" id="password" name="password" required>
            </div>
            <button type="submit" name="login" class="btn">Login</button>
        </form>
        <p class="register-note">
            Don't have an account? <a href="register.php">Register here.</a>
        </p>
    <?php elseif (!isset($_SESSION['questions'])): ?>
        
        <!-- Quiz Selection Page -->
        <h2>Select a Quiz</h2>
        <div class="quiz-cards-container">
    <?php 
    $surpriseCard = null;

    // Separate the "Surprise Me!" card from other quiz cards
    foreach ($quiz_files as $quiz_name => $file): 
        if ($quiz_name === "Surprise Me!") {
            $surpriseCard = $quiz_name; // Store "Surprise Me!" card
            continue; // Skip rendering it in this loop
        }
    ?>
        <form method="POST" class="quiz-card-form">
            <input type="hidden" name="select_quiz" value="<?php echo htmlspecialchars($quiz_name); ?>">
            <button type="submit" class="quiz-card">
                <img src="images/<?php echo strtolower(str_replace(' ', '_', $quiz_name)); ?>.jpg" 
                     alt="<?php echo htmlspecialchars($quiz_name); ?>" 
                     class="quiz-card-image">
                <div class="quiz-card-content">
                    <h3><?php echo htmlspecialchars($quiz_name); ?></h3>
                    <p>Test your knowledge in <?php echo htmlspecialchars($quiz_name); ?>!</p>
                </div>
            </button>
        </form>
    <?php endforeach; ?>

    <!-- Ensure "Surprise Me!" card is shown last -->
    <?php if ($surpriseCard): ?>
        <form method="POST" class="quiz-card-form">
            <input type="hidden" name="select_quiz" value="Surprise Me!">
            <button type="submit" class="quiz-card">
                <img src="images/surprise_me.jpg" alt="Surprise Me!" class="quiz-card-image">
                <div class="quiz-card-content">
                    <h3>Surprise Me!</h3>
                    <p>Test your knowledge across all categories!</p>
                </div>
            </button>
        </form>
    <?php endif; ?>
</div>

        <form method="POST" style="margin-top: 20px;">
            <button type="submit" name="logout" class="btn logout-btn">Logout</button>
        </form>
    <?php else: ?>
        <!-- Quiz Interface -->
        <div class="header">
    <h2><?php echo htmlspecialchars($_SESSION['quiz_title']); ?></h2>
    <form method="POST" style="display: inline;">
        <button type="submit" name="select_another_quiz" class="btn">Select Another Quiz</button>
    </form>
    <form method="POST" style="display: inline;">
        <button type="submit" name="logout" class="btn logout-btn">Logout</button>
    </form>
</div>

<?php if ($_SESSION['current_question'] < count($_SESSION['questions'])): ?>
    <div class="quiz-controls">
        <!-- Timer Display -->
        <div class="timer-container">
            <p>Time Remaining: 
                <span id="timer" class="timer-box"><?php echo $time_limit; ?></span> seconds
            </p>
        </div>
<!-- Hint Button -->
<?php if ($_SESSION['current_question'] < count($_SESSION['questions'])): ?>
<div class="quiz-controls">
    <button id="hint-btn" class="hint-btn">Use a Hint</button>
</div>
<?php endif; ?>



<script>
    // JavaScript Countdown Timer
    let timeRemaining = <?php echo max(0, $time_limit - $time_elapsed); ?>;
    const timerElement = document.getElementById('timer');

    const countdown = setInterval(() => {
        timeRemaining--;

        // Update timer text
        timerElement.textContent = timeRemaining;

        // Change border color dynamically
        if (timeRemaining <= 10) {
            timerElement.style.borderColor = 'red';
            timerElement.style.color = 'red';
        } else if (timeRemaining <= 20) {
            timerElement.style.borderColor = 'orange';
            timerElement.style.color = 'orange';
        } else {
            timerElement.style.borderColor = '#6a11cb'; // Default color (purple)
            timerElement.style.color = '#6a11cb';
        }

        // Auto-submit when time runs out
        if (timeRemaining <= 0) {
            clearInterval(countdown);
            document.forms['auto-submit'].submit();
        }
    }, 1000);
</script>

<!-- Hidden Form to Submit When Time Expires -->
<form id="auto-submit" method="POST">
    <input type="hidden" name="submit_answer" value="1">
</form>
<?php endif; ?>


        <?php if ($_SESSION['current_question'] < count($_SESSION['questions'])): ?>
            <?php $question = $_SESSION['questions'][$_SESSION['current_question']]; ?>
            <div class="question-container">
                <p>Question <?php echo $_SESSION['current_question'] + 1; ?> of <?php echo count($_SESSION['questions']); ?></p>
                <h3><?php echo htmlspecialchars($question[0]); ?></h3>
                <form method="POST">
                    <div class="options">
                    <?php
$correct_option_index = $_SESSION['correct_option_index'][$_SESSION['current_question']]; // 1-based index
for ($i = 1; $i <= 4; $i++): ?>
    <button 
        type="submit" 
        name="answer" 
        value="<?php echo $i; ?>" 
        class="option-btn <?php echo $i === $correct_option_index ? 'correct' : ''; ?>">
        <?php echo htmlspecialchars($question[$i]); ?>
    </button>
<?php endfor; ?>

                        <input type="hidden" name="submit_answer" value="1">
                    </div>
                </form>
            </div>
        <?php else: ?>
            <!-- Quiz Complete -->
            <div class="score-container">
    <h3 class="score-title">Quiz Complete!</h3>
    <div class="score-box">
        <p class="score-text">Your Score:</p>
        <p class="score-value"><?php echo $_SESSION['score']; ?> / <?php echo count($_SESSION['questions']); ?></p>
    </div>
    <div class="percentage-box">
        <p class="percentage-text">Percentage:</p>
        <p class="percentage-value"><?php echo round(($_SESSION['score'] / count($_SESSION['questions'])) * 100, 1); ?>%</p>
    </div>
    <form method="POST">
        <button type="submit" name="reset" class="btn try-again-btn">Try Again</button>
    </form>
</div>

        <?php endif; ?>
    <?php endif; ?>
</div>

<script>
    // Dark Mode Toggle
    const themeSwitch = document.getElementById('themeSwitch');
    const body = document.body;

    // Load stored theme preference
    if (localStorage.getItem('theme') === 'dark') {
        body.classList.add('dark-mode');
        themeSwitch.checked = true;
    }

    // Toggle Theme
    themeSwitch.addEventListener('change', () => {
        body.classList.toggle('dark-mode');

        // Save preference to localStorage
        if (body.classList.contains('dark-mode')) {
            localStorage.setItem('theme', 'dark');
        } else {
            localStorage.setItem('theme', 'light');
        }
    });
</script>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const timerElement = document.getElementById('timer');
    const hintButton = document.getElementById('hint-btn');
    const options = document.querySelectorAll('.option-btn');
    let timeRemaining = parseInt(timerElement.textContent, 10);

    // Countdown timer
    const countdown = setInterval(() => {
        timeRemaining--;
        timerElement.textContent = timeRemaining;

        // Stop the timer when it reaches 0
        if (timeRemaining <= 0) {
            clearInterval(countdown);
            document.forms['auto-submit'].submit(); // Auto-submit the question
        }
    }, 1000);

    // Handle the hint button functionality
    if (hintButton) {
        hintButton.addEventListener('click', (e) => {
            e.preventDefault(); // Prevent form submission
            let removed = 0;

            // Ensure the correct answer is never removed
            const correctOption = Array.from(options).find((option) => option.classList.contains('correct'));

            // Hide two incorrect answers
            options.forEach((option) => {
                if (removed < 2 && option !== correctOption && option.style.display !== 'none') {
                    option.style.display = 'none'; // Hide incorrect option
                    removed++;
                }
            });

            // Deduct 10 seconds from the remaining time
            timeRemaining = Math.max(0, timeRemaining - 10);
            timerElement.textContent = timeRemaining;

            // Disable the hint button for the current question
            hintButton.disabled = true;
            hintButton.textContent = 'Hint Used';
        });
    }

    // Reset for the next question (if applicable)
    document.addEventListener('questionChange', () => {
        // Reset hint button state
        if (hintButton) {
            hintButton.disabled = false;
            hintButton.textContent = 'Use Hint';
        }

        // Reset visibility of all options
        options.forEach((option) => {
            option.style.display = 'block';
        });

        // Update timer value dynamically
        timeRemaining = parseInt(timerElement.textContent, 10);
    });
});

</script>

</body>
</html>
