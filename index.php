
<?php
session_start();

/* =========================================
   SKYPASS AIRPORT SYSTEM
   DATABASE CONNECTION
========================================= */

$host = "127.0.0.1";
$port = "3306";
$dbname = "skypass_db";
$username = "root";
$password = "";

$error = "";

try {
    $pdo = new PDO(
        "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4",
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );
} catch (PDOException $e) {
    die("Database connection failed: " . htmlspecialchars($e->getMessage()));
}

/* =========================================
   CREATE TABLES IF THEY DO NOT EXIST
========================================= */

$pdo->exec("
    CREATE TABLE IF NOT EXISTS passengers (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(150) NOT NULL,
        email VARCHAR(150) NOT NULL,
        phone VARCHAR(30) NOT NULL,
        passport VARCHAR(50) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )
");

$pdo->exec("
    CREATE TABLE IF NOT EXISTS flights (
        id INT AUTO_INCREMENT PRIMARY KEY,
        code VARCHAR(30) NOT NULL,
        origin VARCHAR(100) NOT NULL,
        destination VARCHAR(100) NOT NULL,
        departure DATETIME NULL,
        status VARCHAR(50) NOT NULL DEFAULT 'Scheduled',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )
");

$pdo->exec("
    CREATE TABLE IF NOT EXISTS tickets (
        id INT AUTO_INCREMENT PRIMARY KEY,
        reference VARCHAR(50) NOT NULL,
        passenger VARCHAR(150) NOT NULL,
        flight VARCHAR(30) NOT NULL,
        route VARCHAR(200) NOT NULL,
        created DATETIME NOT NULL
    )
");

/* =========================================
   INSERT SAMPLE DATA IF EMPTY
========================================= */

$passengerCheck = $pdo->query(
    "SELECT COUNT(*) FROM passengers"
)->fetchColumn();

if ($passengerCheck == 0) {
    $stmt = $pdo->prepare("
        INSERT INTO passengers (name, email, phone, passport)
        VALUES (?, ?, ?, ?)
    ");

    $stmt->execute([
        "Juan Dela Cruz",
        "juan@example.com",
        "09171234567",
        "P1234567"
    ]);
}

$flightCheck = $pdo->query(
    "SELECT COUNT(*) FROM flights"
)->fetchColumn();

if ($flightCheck == 0) {
    $stmt = $pdo->prepare("
        INSERT INTO flights (code, origin, destination, departure, status)
        VALUES (?, ?, ?, ?, ?)
    ");

    $stmt->execute([
        "SP101",
        "Manila",
        "Cebu",
        "2026-12-01 08:00:00",
        "Scheduled"
    ]);
}

/* =========================================
   HELPER
========================================= */

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}

/* =========================================
   LOGIN AND LOGOUT ONLY
========================================= */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $action = $_POST["action"] ?? "";

    if ($action === "login") {

        $inputUser = $_POST["username"] ?? "";
        $inputPass = $_POST["password"] ?? "";

        if ($inputUser === "admin" && $inputPass === "admin123") {

            session_regenerate_id(true);
            $_SESSION["logged_in"] = true;

            header("Location: ?page=dashboard");
            exit;

        } else {
            $error = "Incorrect username or password.";
        }
    }

    if ($action === "logout") {

        $_SESSION = [];
        session_destroy();

        header("Location: ?page=login");
        exit;
    }
}

/* =========================================
   DASHBOARD ACCESS ONLY
========================================= */

$loggedIn = !empty($_SESSION["logged_in"]);

$page = $loggedIn ? "dashboard" : "login";

/* =========================================
   DASHBOARD DATABASE INFORMATION
========================================= */

$passengerCount = 0;
$flightCount = 0;
$ticketCount = 0;
$scheduledCount = 0;
$recentFlights = [];

if ($loggedIn) {

    $passengerCount = (int)$pdo
        ->query("SELECT COUNT(*) FROM passengers")
        ->fetchColumn();

    $flightCount = (int)$pdo
        ->query("SELECT COUNT(*) FROM flights")
        ->fetchColumn();

    $ticketCount = (int)$pdo
        ->query("SELECT COUNT(*) FROM tickets")
        ->fetchColumn();

    $scheduledCount = (int)$pdo
        ->query("SELECT COUNT(*) FROM flights WHERE status = 'Scheduled'")
        ->fetchColumn();

    $recentFlights = $pdo
        ->query("SELECT * FROM flights ORDER BY id DESC LIMIT 5")
        ->fetchAll();
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>SkyPass Airport Control Center</title>

<style>

:root {
    --navy:#071629;
    --navy2:#102B4E;
    --royal:#2457A7;
    --royal2:#3978C9;
    --gold:#D4AF37;
    --emerald:#087F5B;
    --background:#F4EFE5;
    --text:#172333;
    --muted:#718096;
    --border:#DED3C0;
}

* {
    box-sizing:border-box;
}

body {
    margin:0;
    font-family:Arial,sans-serif;
    background:var(--background);
    color:var(--text);
}

button,input {
    font:inherit;
}

button {
    border:1px solid rgba(255,255,255,.15);
    padding:12px 17px;
    border-radius:9px;
    background:linear-gradient(135deg,var(--royal2),var(--royal),var(--navy2));
    color:white;
    font-weight:bold;
    cursor:pointer;
    box-shadow:0 5px 15px rgba(7,22,41,.16);
    transition:.25s;
}

button:hover {
    transform:translateY(-2px);
    filter:brightness(1.13);
}

input {
    width:100%;
    padding:12px;
    border:1px solid var(--border);
    border-radius:9px;
    margin:7px 0 17px;
    background:#fff;
    color:var(--navy);
    outline:none;
}

input:focus {
    border-color:var(--gold);
    box-shadow:0 0 0 3px rgba(212,175,55,.16);
}

label {
    font-size:13px;
    font-weight:bold;
    color:var(--navy2);
}

.brand {
    display:flex;
    align-items:center;
    gap:12px;
    color:white;
    font-size:20px;
    font-weight:bold;
    letter-spacing:2px;
}

.logo {
    background:linear-gradient(145deg,#FFF3B0,#D4AF37,#9C741B);
    color:var(--navy);
    width:43px;
    height:43px;
    display:grid;
    place-items:center;
    border-radius:12px;
    font-size:25px;
}

.subtitle {
    display:block;
    font-size:9px;
    letter-spacing:2px;
    color:#c5d1df;
    margin-top:4px;
}

/* LOGIN PAGE */

.login-wrap {
    min-height:100vh;
    display:flex;
    justify-content:center;
    align-items:center;
    padding:25px;
    background:
        linear-gradient(110deg,rgba(5,11,20,.76),rgba(7,22,41,.38),rgba(5,11,20,.68)),
        url('https://images.unsplash.com/photo-1436491865332-7a61a109cc05?auto=format&fit=crop&w=2200&q=90')
        center/cover no-repeat;
}

.login-card {
    width:100%;
    max-width:430px;
    padding:38px;
    border-radius:22px;
    background:rgba(255,255,255,.96);
    border:1px solid #E9CC70;
    box-shadow:0 25px 80px rgba(0,0,0,.5);
}

.login-card .brand {
    color:var(--navy);
    justify-content:center;
    margin-bottom:32px;
}

.login-card .subtitle {
    color:var(--emerald);
}

.login-card h1 {
    color:var(--navy);
    text-align:center;
    margin-bottom:8px;
}

.description {
    text-align:center;
    color:var(--muted);
    font-size:14px;
    margin-bottom:28px;
}

.login-card button {
    width:100%;
    padding:14px;
    color:var(--navy);
    background:linear-gradient(120deg,#FFF1A8,#D4AF37,#B58A25);
}

.error {
    background:#fff0ef;
    color:#a83232;
    padding:12px;
    border-radius:8px;
    margin-bottom:15px;
    font-size:13px;
}

/* MAIN LAYOUT */

.layout {
    display:flex;
    min-height:100vh;
}

.sidebar {
    width:245px;
    background:linear-gradient(180deg,#030912 0%,var(--navy) 48%,var(--navy2) 100%);
    color:white;
    padding:25px 17px;
    display:flex;
    flex-direction:column;
    flex-shrink:0;
    border-right:2px solid rgba(212,175,55,.55);
}

.sidebar .brand {
    margin:0 5px 35px;
}

.sidebar nav {
    display:grid;
    gap:8px;
}

/* ONLY DASHBOARD IS CLICKABLE */

.nav-link {
    display:flex;
    align-items:center;
    gap:13px;
    padding:13px;
    border-radius:9px;
    text-decoration:none;
    color:#DCE8F5;
    font-size:14px;
    transition:.2s;
}

.nav-link.active {
    background:linear-gradient(110deg,#FFF0A8,#D4AF37,#B68B2B);
    color:var(--navy);
    font-weight:bold;
}

/* PLAIN NON-CLICKABLE SIDEBAR LABELS */

.nav-label {
    display:flex;
    align-items:center;
    gap:13px;
    padding:13px;
    border-radius:9px;
    color:#DCE8F5;
    font-size:14px;
    cursor:default;
    user-select:none;
}

.nav-label span {
    width:18px;
    text-align:center;
}

.sidebar-footer {
    margin-top:auto;
    color:#aabbd0;
    font-size:11px;
    line-height:1.6;
    border-top:1px solid rgba(212,175,55,.3);
    padding-top:16px;
}

.main {
    flex:1;
    min-width:0;
}

.topbar {
    background:linear-gradient(100deg,#fff,#F5EBD9);
    padding:18px 30px;
    display:flex;
    justify-content:space-between;
    align-items:center;
    border-bottom:1px solid var(--gold);
}

.topbar strong {
    color:var(--navy);
}

.content {
    padding:30px;
    max-width:1450px;
    margin:auto;
}

/* DASHBOARD */

.hero {
    padding:34px;
    border-radius:17px;
    color:white;
    background:
        linear-gradient(100deg,rgba(5,11,20,.96),rgba(16,43,78,.78)),
        url('https://images.unsplash.com/photo-1569154941061-e231b4725ef1?auto=format&fit=crop&w=1500&q=85')
        center/cover;
    margin-bottom:22px;
    border:1px solid rgba(212,175,55,.65);
}

.hero h2 {
    font-size:26px;
    margin:10px 0;
}

.hero p {
    color:#dbe7f5;
    font-size:14px;
    line-height:1.6;
}

.eyebrow {
    color:var(--emerald);
    font-size:11px;
    font-weight:bold;
    letter-spacing:2px;
    text-transform:uppercase;
}

.hero .eyebrow {
    color:#FFE89A;
}

.cards {
    display:grid;
    grid-template-columns:repeat(4,1fr);
    gap:16px;
    margin-bottom:22px;
}

.stat-card,.panel {
    background:linear-gradient(145deg,#fff,#FBF8F1);
    border:1px solid var(--border);
    border-radius:14px;
    padding:20px;
    box-shadow:0 5px 18px rgba(16,44,84,.06);
}

.stat-card {
    border-top:3px solid var(--gold);
}

.stat-label {
    font-size:13px;
    color:var(--muted);
}

.stat-number {
    color:var(--navy);
    font-size:32px;
    font-weight:bold;
    margin-top:12px;
}

.stat-icon {
    font-size:24px;
    color:#9C741B;
}

.panel {
    margin-bottom:20px;
    overflow-x:auto;
}

.panel h2 {
    font-size:17px;
    color:var(--navy);
    margin-top:0;
}

table {
    width:100%;
    border-collapse:collapse;
    min-width:620px;
    font-size:13px;
}

th,td {
    text-align:left;
    padding:13px 10px;
    border-bottom:1px solid var(--border);
}

th {
    color:var(--navy);
    background:#C5E4F7;
    font-size:11px;
    text-transform:uppercase;
}

tr:hover td {
    background:#FBF6EA;
}

.status {
    background:#DDF5EA;
    color:var(--emerald);
    padding:6px 10px;
    border-radius:20px;
    font-size:11px;
    font-weight:bold;
}

.empty {
    color:var(--muted);
    font-size:13px;
    padding:15px 0;
}

.danger {
    background:linear-gradient(120deg,#FFF0EF,#F9D9D5);
    color:#A83232;
    border:1px solid #FFD6D6;
    box-shadow:none;
}

@media(max-width:900px) {
    .cards {
        grid-template-columns:repeat(2,1fr);
    }

    .sidebar {
        width:210px;
    }
}

@media(max-width:650px) {
    .layout {
        display:block;
    }

    .sidebar {
        width:100%;
        padding:15px;
    }

    .sidebar .brand {
        margin-bottom:15px;
    }

    .sidebar nav {
        display:flex;
        flex-wrap:wrap;
    }

    .nav-link,.nav-label {
        font-size:12px;
        padding:9px;
    }

    .sidebar-footer {
        display:none;
    }

    .topbar {
        padding:15px;
    }

    .content {
        padding:18px;
    }

    .login-card {
        padding:25px;
    }
}

@media(max-width:400px) {
    .cards {
        grid-template-columns:1fr;
    }
}

</style>

</head>

<body>

<?php if (!$loggedIn): ?>

<!-- LOGIN PAGE -->

<div class="login-wrap">

    <form class="login-card" method="post">

        <div class="brand">

            <div class="logo">✈</div>

            <div>
                SKYPASS
                <span class="subtitle">AIRPORT CONTROL CENTER</span>
            </div>

        </div>

        <h1>Welcome Back</h1>

        <p class="description">
            Sign in to manage airport operations.
        </p>

        <?php if ($error): ?>
            <div class="error"><?= h($error) ?></div>
        <?php endif; ?>

        <input type="hidden" name="action" value="login">

        <label>Username</label>

        <input
            type="text"
            name="username"
            placeholder="Enter username"
            required
        >

        <label>Password</label>

        <input
            type="password"
            name="password"
            placeholder="Enter password"
            required
        >

        <button type="submit">LOGIN TO SKYPASS →</button>

    </form>

</div>

<?php else: ?>

<!-- MAIN DASHBOARD -->

<div class="layout">

    <aside class="sidebar">

        <div class="brand">

            <div class="logo">✈</div>

            <div>
                SKYPASS
                <span class="subtitle">AIRPORT CONTROL</span>
            </div>

        </div>

        <!-- DASHBOARD: CLICKABLE -->

        <nav>

            <a class="nav-link active" href="?page=dashboard">
                <span>▦</span>
                Dashboard
            </a>

            <!-- OTHER MODULES: PLAIN TEXT ONLY -->

            <div class="nav-label">
                <span>♙</span>
                Passengers
            </div>

            <div class="nav-label">
                <span>✈</span>
                Flights
            </div>

            <div class="nav-label">
                <span>▤</span>
                Tickets
            </div>

            <div class="nav-label">
                <span>▣</span>
                Passport Records
            </div>

            <div class="nav-label">
                <span>▥</span>
                Reports
            </div>

        </nav>

        <div class="sidebar-footer">
            SKYPASS AIRPORT CONTROL CENTER<br>
            Organized airport management.
        </div>

    </aside>

    <main class="main">

        <header class="topbar">

            <strong>
                Airport Operations / Dashboard
            </strong>

            <form method="post">
                <input type="hidden" name="action" value="logout">
                <button class="danger" type="submit">Log out</button>
            </form>

        </header>

        <section class="content">

            <div class="hero">

                <div class="eyebrow">
                    Airport Operations Overview
                </div>

                <h2>Welcome to SkyPass Control Center</h2>

                <p>
                    Manage passengers, flights, tickets, and airport
                    records in one place.
                </p>

            </div>

            <!-- DASHBOARD STATISTICS -->

            <div class="cards">

                <div class="stat-card">

                    <div class="stat-label">
                        Registered Passengers
                    </div>

                    <div class="stat-icon">♙</div>

                    <div class="stat-number">
                        <?= $passengerCount ?>
                    </div>

                </div>

                <div class="stat-card">

                    <div class="stat-label">
                        Flight Records
                    </div>

                    <div class="stat-icon">✈</div>

                    <div class="stat-number">
                        <?= $flightCount ?>
                    </div>

                </div>

                <div class="stat-card">

                    <div class="stat-label">
                        Tickets Booked
                    </div>

                    <div class="stat-icon">▤</div>

                    <div class="stat-number">
                        <?= $ticketCount ?>
                    </div>

                </div>

                <div class="stat-card">

                    <div class="stat-label">
                        Scheduled Flights
                    </div>

                    <div class="stat-icon">◷</div>

                    <div class="stat-number">
                        <?= $scheduledCount ?>
                    </div>

                </div>

            </div>

            <!-- RECENT FLIGHT SCHEDULE -->

            <div class="panel">

                <h2>Recent Flight Schedule</h2>

                <?php if (!$recentFlights): ?>

                    <p class="empty">
                        No flight records yet.
                    </p>

                <?php else: ?>

                    <table>

                        <tr>
                            <th>Flight Code</th>
                            <th>Origin</th>
                            <th>Destination</th>
                            <th>Departure</th>
                            <th>Status</th>
                        </tr>

                        <?php foreach ($recentFlights as $f): ?>

                        <tr>

                            <td><?= h($f["code"]) ?></td>

                            <td><?= h($f["origin"]) ?></td>

                            <td><?= h($f["destination"]) ?></td>

                            <td><?= h($f["departure"]) ?></td>

                            <td>
                                <span class="status">
                                    <?= h($f["status"]) ?>
                                </span>
                            </td>

                        </tr>

                        <?php endforeach; ?>

                    </table>

                <?php endif; ?>

            </div>

        </section>

    </main>

</div>

<?php endif; ?>

</body>
</html>
