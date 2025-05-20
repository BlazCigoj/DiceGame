<?php
session_start();

$MAX_USERS = 3;
$MAX_DICE = 3;
$MAX_ROUNDS = 5;

// Začetek igre - nastavitev
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['action'])) {
    $numUsers = min((int)$_POST['num_users'], $MAX_USERS);
    $numDice = min((int)$_POST['num_dice'], $MAX_DICE);
    $numRounds = min((int)$_POST['num_rounds'], $MAX_ROUNDS);

    // Preveri unikatnost imen
    $names = [];
    $duplicate = false;
    for ($i = 1; $i <= $numUsers; $i++) {
        $name = trim($_POST["ime$i"]);
        if (in_array($name, $names, true)) {
            $duplicate = true;
            break;
        }
        $names[] = $name;
    }

    if ($duplicate) {
        $_SESSION['form_error'] = "Uporabniška imena morajo biti unikatna!";
        $_SESSION['form_post'] = $_POST;
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }

    $_SESSION['settings'] = [
        'num_users' => $numUsers,
        'num_dice' => $numDice,
        'num_rounds' => $numRounds,
    ];

    $_SESSION['users'] = [];
    for ($i = 1; $i <= $numUsers; $i++) {
        $_SESSION['users'][] = ['ime' => $_POST["ime$i"]];
    }

    // Pripravi prazne rezultate
    $_SESSION['dice_results'] = [];
    foreach ($_SESSION['users'] as $userIndex => $user) {
        $_SESSION['dice_results'][$userIndex] = [
            'rounds' => array_fill(0, $numRounds, []),
            'total' => 0
        ];
    }
    $_SESSION['current_round'] = 0;
    $_SESSION['current_player'] = 0;

    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// Metanje kock po rundah - PRVA FAZA: animacija
if (isset($_POST['action']) && $_POST['action'] === 'roll') {
    $settings = $_SESSION['settings'];
    $userIndex = $_SESSION['current_player'];
    $roundIndex = $_SESSION['current_round'];
    $numDice = $settings['num_dice'];

    // Generiraj rezultate, shrani jih v session, a označi, da so pending (za animacijo)
    $rolls = [];
    $total = 0;
    for ($d = 0; $d < $numDice; $d++) {
        $roll = rand(1, 6);
        $rolls[] = $roll;
        $total += $roll;
    }
    // Shrani rezultate v session, a še ne dodaj v dice_results (to bo v drugi fazi)
    $_SESSION['pending_roll'] = [
        'userIndex' => $userIndex,
        'roundIndex' => $roundIndex,
        'rolls' => $rolls,
        'total' => $total
    ];

    header('Location: ' . $_SERVER['PHP_SELF'] . '?show_roll=1');
    exit;
}

// Druga faza metanja kock: po animaciji shrani rezultate in napreduj
if (isset($_POST['action']) && $_POST['action'] === 'confirm_roll' && isset($_SESSION['pending_roll'])) {
    $pending = $_SESSION['pending_roll'];
    $userIndex = $pending['userIndex'];
    $roundIndex = $pending['roundIndex'];
    $rolls = $pending['rolls'];
    $total = $pending['total'];

    $_SESSION['dice_results'][$userIndex]['rounds'][$roundIndex] = $rolls;
    $_SESSION['dice_results'][$userIndex]['total'] += $total;

    // Premakni na naslednjega igralca ali naslednjo rundo
    if ($_SESSION['current_player'] + 1 < $_SESSION['settings']['num_users']) {
        $_SESSION['current_player']++;
    } else {
        $_SESSION['current_player'] = 0;
        $_SESSION['current_round']++;
    }

    unset($_SESSION['pending_roll']);

    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// Prikaz rezultatov po koncu igre
if (
    isset($_SESSION['users'], $_SESSION['dice_results'], $_SESSION['settings'], $_SESSION['current_round'])
    && $_SESSION['current_round'] >= $_SESSION['settings']['num_rounds']
) {
    $settings = $_SESSION['settings'];
    $totals = array_column($_SESSION['dice_results'], 'total');
    arsort($totals);
    $rankings = array_keys($totals);

    echo '<!DOCTYPE html>
    <html lang="sl">
    <head>
        <meta charset="UTF-8">
        <title>Rezultati igre</title>
        <style>
            body {
                font-family: Arial, sans-serif;
                background: url("img/background.jpg") no-repeat center center fixed;
                background-size: cover;
                color: #fff;
                text-align: center;
                padding: 30px;
                margin: 0;
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
                min-height: 100vh;
            }
            .dice {
                width: 50px;
                height: 50px;
            }
            h1, h2 {
                color: #fca311;
            }
            .winner {
                font-weight: bold;
                font-size: 2em;
                color: #ffd700;
                animation: glow 1s infinite alternate;
            }
            @keyframes glow {
                from {
                    text-shadow: 0 0 10px #ffd700, 0 0 20px #ffd700, 0 0 30px #ffd700;
                }
                to {
                    text-shadow: 0 0 20px #ffd700, 0 0 30px #ffd700, 0 0 40px #ffd700;
                }
            }
            .player-row {
                display: flex;
                justify-content: center;
                align-items: flex-start;
                gap: 30px;
                margin-top: 30px;
                flex-wrap: wrap;
            }
            .player {
                border: 2px solid #fca311;
                padding: 20px;
                border-radius: 10px;
                width: 300px;
                background-color: #222;
            }
            .round {
                margin: 10px 0;
            }
            .dice-container {
                display: inline-block;
                margin: 2px;
            }
            .button {
                margin-top: 20px;
                padding: 10px 20px;
                background-color: #fca311;
                border: none;
                border-radius: 5px;
                color: #1a1a2e;
                font-size: 16px;
                cursor: pointer;
            }
            .button:hover {
                background-color: #ffb347;
            }
            .timer {
                font-size: 2em;
                margin-top: 20px;
                color: #fca311;
            }
            .leaderboard-container {
                margin-top: 40px;
                text-align: center;
                color: gold;
                animation: fadeIn 1.2s ease-in;
            }
            .leaderboard h2 {
                font-size: 32px;
                margin-bottom: 20px;
                color: #FFD700;
            }
            .leaderboard {
                display: flex;
                flex-direction: column;
                align-items: center;
                gap: 12px;
                max-width: 300px;
                margin: auto;
            }
            .leaderboard-entry {
                display: flex;
                justify-content: space-between;
                background-color: rgba(0,0,0,0.8);
                border: 2px solid #FFD700;
                padding: 12px 20px;
                width: 100%;
                border-radius: 12px;
                color: white;
                font-weight: bold;
                box-shadow: 0 0 10px #000;
                transition: transform 0.3s;
            }
            .leaderboard-entry:hover {
                transform: scale(1.03);
            }
            .first-place {
                background-color: #ffcc00;
                color: black;
                box-shadow: 0 0 15px #ffcc00;
            }
            .rank {
                margin-right: 10px;
            }
            @keyframes fadeIn {
                from {opacity: 0; transform: translateY(20px);}
                to {opacity: 1; transform: translateY(0);}
            }
        </style>
        <script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.5.1/dist/confetti.browser.min.js"></script>
    </head>
    <body>
        <h1>Rezultati igre</h1>
        <div class="player-row">';

    foreach ($rankings as $rank => $index) {
        $user = $_SESSION['users'][$index];
        $class = $rank === 0 ? 'winner' : '';
        $fontSize = $rank === 0 ? 'font-size: 2em;' : '';
        echo '<div class="player">';
        echo '<h2 class="' . $class . '" style="' . $fontSize . '">' . htmlspecialchars($user['ime']) . '</h2>';
        foreach ($_SESSION['dice_results'][$index]['rounds'] as $round) {
            echo '<div class="round">';
            foreach ($round as $roll) {
                echo '<span class="dice-container">';
                echo '<img class="dice" src="slike/dice-anim.gif" data-final="slike/dice' . $roll . '.gif" alt="Kocka">';
                echo '</span>';
            }
            echo '</div>';
        }
        echo '<p><strong>Skupno: ' . $_SESSION['dice_results'][$index]['total'] . '</strong></p>';
        echo '<p><strong>Mesto: ' . ($rank + 1) . '</strong></p>';
        echo '</div>';
    }

    echo '</div>
        <div class="timer" id="timer">Preusmeritev čez 10 sekund...</div>
        <button class="button" onclick="window.location.href=\'' . $_SERVER['PHP_SELF'] . '\'">Začni znova</button>
        <script>
            document.addEventListener("DOMContentLoaded", function () {
                document.querySelectorAll(".dice").forEach(function (el) {
                    setTimeout(() => {
                        el.src = el.getAttribute("data-final");
                    }, 2000);
                });

                var duration = 15 * 1000;
                var animationEnd = Date.now() + duration;
                var defaults = { startVelocity: 30, spread: 360, ticks: 60, zIndex: 0 };

                function randomInRange(min, max) {
                    return Math.random() * (max - min) + min;
                }

                var interval = setInterval(function() {
                    var timeLeft = animationEnd - Date.now();

                    if (timeLeft <= 0) {
                        return clearInterval(interval);
                    }

                    var particleCount = 50 * (timeLeft / duration);
                    confetti({ ...defaults, particleCount, origin: { x: randomInRange(0.1, 0.3), y: Math.random() - 0.2 } });
                    confetti({ ...defaults, particleCount, origin: { x: randomInRange(0.7, 0.9), y: Math.random() - 0.2 } });
                }, 250);

                var countdown = 10;
                var timerElement = document.getElementById("timer");
                var countdownInterval = setInterval(function () {
                    countdown--;
                    timerElement.textContent = "Preusmeritev čez " + countdown + " sekund...";
                    if (countdown <= 0) {
                        clearInterval(countdownInterval);
                        window.location.href = "' . $_SERVER['PHP_SELF'] . '";
                    }
                }, 1000);
            });
        </script>
    
<!-- Leaderboard Section -->
<div class="leaderboard-container">
    <h2>🏆 Leaderboard</h2>
    <div class="leaderboard">';
    // Prepare leaderboard data if available
    $players_sorted = [];
    if (isset($_SESSION["users"], $_SESSION["dice_results"])) {
        foreach ($_SESSION["users"] as $idx => $user) {
            $players_sorted[] = [
                "name" => $user["ime"],
                "sum" => $_SESSION["dice_results"][$idx]["total"]
            ];
        }
        // Sort descending by sum
        usort($players_sorted, function($a, $b) {
            return $b["sum"] <=> $a["sum"];
        });
    }
    if (!empty($players_sorted)) {
        foreach ($players_sorted as $index => $player) {
            $rank = $index + 1;
            $highlight = $rank == 1 ? "first-place" : "";
            echo '
                <div class="leaderboard-entry ' . $highlight . '">
                    <span class="rank">#' . $rank . '</span>
                    <span class="name">' . htmlspecialchars($player["name"]) . ($rank == 1 ? " 👑" : "") . '</span>
                    <span class="score">' . $player["sum"] . '</span>
                </div>';
        }
    } else {
        echo '<div style="color: #fff;">Leaderboard bo prikazan po koncu igre.</div>';
    }
    echo '
    </div>
</div>

</body></html>';

    session_destroy();
    exit;
}

// ANIMACIJA METANJA KOCK (po kliku na "Vrzi kocke")
if (isset($_GET['show_roll']) && isset($_SESSION['pending_roll'])) {
    $pending = $_SESSION['pending_roll'];
    $settings = $_SESSION['settings'];
    $currentPlayer = $pending['userIndex'];
    $currentRound = $pending['roundIndex'];
    $user = $_SESSION['users'][$currentPlayer];
    $numDice = $settings['num_dice'];
    $rolls = $pending['rolls'];

    echo '<!DOCTYPE html>
    <html lang="sl">
    <head>
        <meta charset="UTF-8">
        <title>Metanje kock - Animacija</title>
        <style>
            body {
                font-family: Arial, sans-serif;
                background: url("img/background.jpg") no-repeat center center fixed;
                background-size: cover;
                color: #fff;
                text-align: center;
                padding: 30px;
                margin: 0;
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
                min-height: 100vh;
            }
            .dice {
                width: 70px;
                height: 70px;
            }
            h1, h2 {
                color: #fca311;
            }
            .button {
                margin-top: 30px;
                padding: 15px 30px;
                background-color: #fca311;
                border: none;
                border-radius: 5px;
                color: #1a1a2e;
                font-size: 20px;
                cursor: pointer;
                display: none;
            }
            .button:hover {
                background-color: #ffb347;
            }
            .dice-row {
                margin-top: 40px;
            }
        </style>
    </head>
    <body>
        <h1>Runda ' . ($currentRound + 1) . ' / ' . $settings['num_rounds'] . '</h1>
        <h2>Na vrsti je: <span style="color:#FFD700;">' . htmlspecialchars($user['ime']) . '</span></h2>
        <div class="dice-row">';
    // Prikaži animirane kocke, ki se bodo po 2s zamenjale v prave rezultate
    for ($i = 0; $i < $numDice; $i++) {
        echo '<img class="dice" src="slike/dice-anim.gif" data-final="slike/dice' . $rolls[$i] . '.gif" alt="Kocka">';
    }
    echo '</div>
        <form method="POST" id="confirmForm" style="margin-top:30px;">
            <input type="hidden" name="action" value="confirm_roll">
            <button class="button" id="confirmBtn" type="submit">Potrdi rezultat</button>
        </form>
        <script>
            document.addEventListener("DOMContentLoaded", function () {
                // Po 2s zamenjaj slike kock z rezultati in prikaži gumb
                setTimeout(function () {
                    document.querySelectorAll(".dice").forEach(function (el) {
                        el.src = el.getAttribute("data-final");
                    });
                    document.getElementById("confirmBtn").style.display = "inline-block";
                }, 2000);
            });
        </script>
    </body>
    </html>';
    exit;
}

// Če je igra v teku, prikaži trenutno stanje in omogoči metanje kock
if (
    isset($_SESSION['users'], $_SESSION['dice_results'], $_SESSION['settings'], $_SESSION['current_round'])
    && $_SESSION['current_round'] < $_SESSION['settings']['num_rounds']
) {
    $settings = $_SESSION['settings'];
    $currentPlayer = $_SESSION['current_player'];
    $currentRound = $_SESSION['current_round'];
    $user = $_SESSION['users'][$currentPlayer];
    $numDice = $settings['num_dice'];

    echo '<!DOCTYPE html>
    <html lang="sl">
    <head>
        <meta charset="UTF-8">
        <title>Metanje kock - Runda ' . ($currentRound + 1) . '</title>
        <style>
            body {
                font-family: Arial, sans-serif;
                background: url("img/background.jpg") no-repeat center center fixed;
                background-size: cover;
                color: #fff;
                text-align: center;
                padding: 30px;
                margin: 0;
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
                min-height: 100vh;
            }
            .dice {
                width: 50px;
                height: 50px;
            }
            h1, h2 {
                color: #fca311;
            }
            .player-row {
                display: flex;
                justify-content: center;
                align-items: flex-start;
                gap: 30px;
                margin-top: 30px;
                flex-wrap: wrap;
            }
            .player {
                border: 2px solid #fca311;
                padding: 20px;
                border-radius: 10px;
                width: 300px;
                background-color: #222;
            }
            .round {
                margin: 10px 0;
            }
            .dice-container {
                display: inline-block;
                margin: 2px;
            }
            .button {
                margin-top: 20px;
                padding: 10px 20px;
                background-color: #fca311;
                border: none;
                border-radius: 5px;
                color: #1a1a2e;
                font-size: 16px;
                cursor: pointer;
            }
            .button:hover {
                background-color: #ffb347;
            }
        </style>
    </head>
    <body>
        <h1>Runda ' . ($currentRound + 1) . ' / ' . $settings['num_rounds'] . '</h1>
        <h2>Na vrsti je: <span style="color:#FFD700;">' . htmlspecialchars($user['ime']) . '</span></h2>
        <form method="POST">
            <input type="hidden" name="action" value="roll">
            <button class="button" type="submit">Vrzi ' . $numDice . ' kock' . ($numDice > 1 ? 'e' : 'o') . '</button>
        </form>
        <div style="margin-top:40px;">
            <h3>Trenutni rezultati:</h3>
            <div class="player-row">';
    foreach ($_SESSION['users'] as $idx => $u) {
        echo '<div class="player">';
        echo '<h2>' . htmlspecialchars($u['ime']) . '</h2>';
        foreach ($_SESSION['dice_results'][$idx]['rounds'] as $r => $round) {
            echo '<div class="round">';
            echo 'Runda ' . ($r + 1) . ': ';
            if (!empty($round)) {
                foreach ($round as $roll) {
                    echo '<span class="dice-container">';
                    echo '<img class="dice" src="slike/dice' . $roll . '.gif" alt="Kocka">';
                    echo '</span>';
                }
            } else {
                echo '<span style="color:#888;">(še ni vrženo)</span>';
            }
            echo '</div>';
        }
        echo '<p><strong>Skupno: ' . $_SESSION['dice_results'][$idx]['total'] . '</strong></p>';
        echo '</div>';
    }
    echo '</div>
    </body>
    </html>';
    exit;
}
?>

<!-- FORM STRAN -->
<!DOCTYPE html>
<html lang="sl">
<head>
    <meta charset="UTF-8">
    <title>Gambling Room - Začetek</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: url("img/background.jpg") no-repeat center center fixed;
            background-size: cover;
            color: #fff;
            text-align: center;
            padding: 30px;
            margin: 0;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
        }
        h1 {
            font-size: 3em;
            color: #fca311;
        }
        .form-container {
            border: 2px solid #fca311;
            border-radius: 8px;
            padding: 30px;
            background-color: #222;
            width: 400px;
        }
        .button {
            padding: 15px 25px;
            background-color: #fca311;
            border: none;
            border-radius: 5px;
            color: #1a1a2e;
            font-size: 18px;
            cursor: pointer;
            margin-top: 15px;
        }
        .button:hover {
            background-color: #ffb347;
        }
        label {
            display: block;
            margin: 12px 0;
            font-size: 1.2em;
        }
        fieldset {
            border: 2px solid #fca311;
            border-radius: 8px;
            margin: 15px auto;
            padding: 15px;
            background-color: #333;
        }
        .error-msg {
            color: #ff5555;
            font-weight: bold;
            margin-bottom: 10px;
            font-size: 1.2em;
        }
        .leaderboard-container {
            margin-top: 40px;
            text-align: center;
            color: gold;
            animation: fadeIn 1.2s ease-in;
        }
        .leaderboard h2 {
            font-size: 32px;
            margin-bottom: 20px;
            color: #FFD700;
        }
        .leaderboard {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 12px;
            max-width: 300px;
            margin: auto;
        }
        .leaderboard-entry {
            display: flex;
            justify-content: center;
            background-color: rgba(0,0,0,0.8);
            border: 2px solid #FFD700;
            padding: 12px 20px;
            width: 100%;
            border-radius: 12px;
            color: white;
            font-weight: bold;
            box-shadow: 0 0 10px #000;
            transition: transform 0.3s;
        }
        .leaderboard-entry:hover {
            transform: scale(1.03);
        }
        .first-place {
            background-color: #ffcc00;
            color: black;
            box-shadow: 0 0 15px #ffcc00;
        }
        .rank {
            margin-right: 10px;
        }
        @keyframes fadeIn {
            from {opacity: 0; transform: translateY(20px);}
            to {opacity: 1; transform: translateY(0);}
        }
    </style>
</head>
<body>
    <h1>Gambling Room</h1>
    <div class="form-container">
        <?php
        if (isset($_SESSION['form_error'])) {
            echo '<div class="error-msg">' . htmlspecialchars($_SESSION['form_error']) . '</div>';
            unset($_SESSION['form_error']);
        }
        $form_post = $_SESSION['form_post'] ?? [];
        unset($_SESSION['form_post']);
        ?>
        <form method="POST" autocomplete="off">
            <label>Število uporabnikov (1–3):
                <input type="number" name="num_users" min="1" max="3" value="<?php echo isset($form_post['num_users']) ? htmlspecialchars($form_post['num_users']) : 3; ?>" required>
            </label>
            <label>Število kock (1–3):
                <input type="number" name="num_dice" min="1" max="3" value="<?php echo isset($form_post['num_dice']) ? htmlspecialchars($form_post['num_dice']) : 3; ?>" required>
            </label>
            <label>Število rund (1–5):
                <input type="number" name="num_rounds" min="1" max="5" value="<?php echo isset($form_post['num_rounds']) ? htmlspecialchars($form_post['num_rounds']) : 1; ?>" required>
            </label>
            <div id="userFields"></div>
            <button class="button" type="submit">Začni igro</button>
        </form>
    </div>

    <script>
        const userFields = document.getElementById('userFields');
        const numUsersInput = document.querySelector('input[name="num_users"]');
        const formPost = <?php echo json_encode($form_post); ?>;

        function generateUserFields(count) {
            userFields.innerHTML = '';
            for (let i = 1; i <= Math.min(count, 3); i++) {
                let value = formPost['ime'+i] ? formPost['ime'+i] : '';
                userFields.innerHTML += `
                    <fieldset>
                        <legend>Uporabnik ${i}</legend>
                        <label>Ime:
                            <input type="text" name="ime${i}" required value="${value.replace(/"/g, '&quot;')}">
                        </label>
                    </fieldset>`;
            }
        }

        numUsersInput.addEventListener('input', () => {
            let val = parseInt(numUsersInput.value);
            if (val >= 1 && val <= 3) {
                generateUserFields(val);
            }
        });

        // Začetni prikaz uporabnikov
        let initialUsers = formPost['num_users'] ? parseInt(formPost['num_users']) : 3;
        generateUserFields(initialUsers);
    </script>

<!-- Leaderboard Section -->
<div class="leaderboard-container">
    <h2>🏆 Leaderboard</h2>
    <div class="leaderboard">
        <?php
        // Show only player names before the game
        $numUsers = isset($form_post['num_users']) ? (int)$form_post['num_users'] : 3;
        if ($numUsers > 0) {
            for ($i = 1; $i <= min($numUsers, 3); $i++) {
                $name = htmlspecialchars($form_post["ime$i"] ?? "Uporabnik $i");
                echo "<div class='leaderboard-entry'><span class='name'>$name</span></div>";
            }
        } else {
            // Default: show empty leaderboard
            echo "<div style='color: #fff;'>Leaderboard bo prikazan po koncu igre.</div>";
        }
        ?>
    </div>
</div>

</body>
</html>
