<?php
session_start();

const DATA_FILE = __DIR__ . '/skypass_data.json';

function defaultData() {
    return [
        'passengers' => [
            ['id'=>1,'name'=>'Juan Dela Cruz','email'=>'juan@example.com','phone'=>'09171234567','passport'=>'P1234567']
        ],
        'flights' => [
            ['id'=>1,'code'=>'SP101','origin'=>'Manila','destination'=>'Cebu','departure'=>'2026-12-01T08:00','status'=>'Scheduled']
        ],
        'tickets' => []
    ];
}

function loadData() {
    if (!file_exists(DATA_FILE)) {
        file_put_contents(DATA_FILE, json_encode(defaultData(), JSON_PRETTY_PRINT));
    }
    $data = json_decode(file_get_contents(DATA_FILE), true);
    return is_array($data) ? $data : defaultData();
}

function saveData($data) {
    file_put_contents(DATA_FILE, json_encode($data, JSON_PRETTY_PRINT), LOCK_EX);
}

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function go($page = 'dashboard') {
    header("Location: ?page=" . urlencode($page));
    exit;
}

$data = loadData();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'login') {
        if (($_POST['username'] ?? '') === 'admin' &&
            ($_POST['password'] ?? '') === 'admin123') {
            session_regenerate_id(true);
            $_SESSION['logged_in'] = true;
            go();
        } else {
            $error = 'Incorrect username or password.';
        }
    }

    if ($action === 'logout') {
        $_SESSION = [];
        session_destroy();
        header('Location: ?page=login');
        exit;
    }

    if (!empty($_SESSION['logged_in'])) {

        if ($action === 'add_passenger') {
            $ids = array_column($data['passengers'], 'id');
            $data['passengers'][] = [
                'id' => $ids ? max($ids) + 1 : 1,
                'name' => trim($_POST['name'] ?? ''),
                'email' => trim($_POST['email'] ?? ''),
                'phone' => trim($_POST['phone'] ?? ''),
                'passport' => trim($_POST['passport'] ?? '')
            ];
            saveData($data);
            go('passengers');
        }

        if ($action === 'delete_passenger') {
            $id = (int)($_POST['id'] ?? 0);
            $data['passengers'] = array_values(array_filter(
                $data['passengers'],
                fn($p) => (int)$p['id'] !== $id
            ));
            saveData($data);
            go('passengers');
        }

        if ($action === 'add_flight') {
            $ids = array_column($data['flights'], 'id');
            $data['flights'][] = [
                'id' => $ids ? max($ids) + 1 : 1,
                'code' => trim($_POST['code'] ?? ''),
                'origin' => trim($_POST['origin'] ?? ''),
                'destination' => trim($_POST['destination'] ?? ''),
                'departure' => trim($_POST['departure'] ?? ''),
                'status' => trim($_POST['status'] ?? 'Scheduled')
            ];
            saveData($data);
            go('flights');
        }

        if ($action === 'delete_flight') {
            $id = (int)($_POST['id'] ?? 0);
            $data['flights'] = array_values(array_filter(
                $data['flights'],
                fn($f) => (int)$f['id'] !== $id
            ));
            saveData($data);
            go('flights');
        }

        if ($action === 'book_ticket') {
            $passenger = null;
            $flight = null;

            foreach ($data['passengers'] as $p) {
                if ((int)$p['id'] === (int)($_POST['passenger_id'] ?? 0)) {
                    $passenger = $p;
                }
            }

            foreach ($data['flights'] as $f) {
                if ((int)$f['id'] === (int)($_POST['flight_id'] ?? 0)) {
                    $flight = $f;
                }
            }

            if ($passenger && $flight) {
                $ids = array_column($data['tickets'], 'id');
                $data['tickets'][] = [
                    'id' => $ids ? max($ids) + 1 : 1,
                    'reference' => 'SP-' . date('ymd') . '-' . random_int(1000,9999),
                    'passenger' => $passenger['name'],
                    'flight' => $flight['code'],
                    'route' => $flight['origin'] . ' → ' . $flight['destination'],
                    'created' => date('Y-m-d H:i:s')
                ];
                saveData($data);
            }
            go('tickets');
        }
    }
}

$loggedIn = !empty($_SESSION['logged_in']);
$page = $loggedIn ? ($_GET['page'] ?? 'dashboard') : 'login';

$passengerCount = count($data['passengers']);
$flightCount = count($data['flights']);
$ticketCount = count($data['tickets']);
$scheduledCount = count(array_filter($data['flights'], fn($f) => ($f['status'] ?? '') === 'Scheduled'));

function nav($page, $current, $label, $icon) {
    $active = $page === $current ? 'active' : '';
    echo '<a class="nav-link '.$active.'" href="?page='.$page.'"><span>'.$icon.'</span>'.$label.'</a>';
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
    --gold-light:#FFE89A;
    --gold-dark:#9C741B;
    --emerald:#087F5B;
    --emerald-light:#DDF5EA;
    --lightblue:#B9DDF5;
    --beige:#E8DCC5;
    --brown:#8B7355;
    --black:#050B14;
    --white:#FFFFFF;
    --background:#F4EFE5;
    --text:#172333;
    --muted:#718096;
    --border:#DED3C0;
}

* {box-sizing:border-box}

body {
    margin:0;
    font-family:Arial,sans-serif;
    background:var(--background);
    color:var(--text);
}

button,input,select {font:inherit}

button,.button {
    border:1px solid rgba(255,255,255,.15);
    padding:12px 17px;
    border-radius:9px;
    background:linear-gradient(135deg,var(--royal2),var(--royal),var(--navy2));
    color:white;
    font-weight:bold;
    cursor:pointer;
    text-decoration:none;
    display:inline-block;
    box-shadow:0 5px 15px rgba(7,22,41,.16);
    transition:.25s;
}

button:hover,.button:hover {
    transform:translateY(-2px);
    filter:brightness(1.13);
    box-shadow:0 8px 20px rgba(7,22,41,.23);
}

input,select {
    width:100%;
    padding:12px;
    border:1px solid var(--border);
    border-radius:9px;
    margin:7px 0 17px;
    background:#fff;
    color:var(--navy);
    outline:none;
    transition:.2s;
}

input:focus,select:focus {
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
    box-shadow:0 3px 15px rgba(212,175,55,.35),inset 0 1px 3px #fff;
    border:1px solid #FBE8A0;
}

.subtitle {
    display:block;
    font-size:9px;
    letter-spacing:2px;
    color:#c5d1df;
    margin-top:4px;
}

/* LUXURY AIRPORT LOGIN BACKGROUND */

.login-wrap {
    min-height:100vh;
    display:flex;
    justify-content:center;
    align-items:center;
    position:relative;
    overflow:hidden;
    padding:25px;
    isolation:isolate;
    background:
        linear-gradient(110deg,rgba(5,11,20,.76),rgba(7,22,41,.38),rgba(5,11,20,.68)),
        url('https://images.unsplash.com/photo-1436491865332-7a61a109cc05?auto=format&fit=crop&w=2200&q=90')
        center/cover no-repeat;
}

.login-wrap:before {
    content:"";
    position:absolute;
    inset:0;
    z-index:-1;
    background:
        linear-gradient(180deg,rgba(5,11,20,.15),rgba(5,11,20,.7)),
        radial-gradient(ellipse at 50% 45%,transparent 10%,rgba(5,11,20,.35) 100%);
}

.login-wrap:after {
    content:"";
    position:absolute;
    inset:0;
    z-index:-1;
    background:linear-gradient(90deg,rgba(5,11,20,.25),transparent 50%,rgba(5,11,20,.25));
}

/* LOGIN CARD */

.login-card {
    position:relative;
    z-index:10;
    width:100%;
    max-width:430px;
    padding:38px;
    border-radius:22px;
    background:linear-gradient(145deg,rgba(255,255,255,.97),rgba(244,239,229,.95));
    border:1px solid #E9CC70;
    box-shadow:
        0 25px 80px rgba(0,0,0,.5),
        0 0 25px rgba(212,175,55,.22),
        inset 0 1px 0 #fff;
    animation:appear .8s ease;
    backdrop-filter:blur(16px);
}

.login-card:before {
    content:"";
    position:absolute;
    top:0;
    left:12%;
    width:76%;
    height:3px;
    border-radius:10px;
    background:linear-gradient(90deg,transparent,#FFF0A8,#D4AF37,#FFF0A8,transparent);
}

@keyframes appear {
    from {opacity:0;transform:translateY(25px) scale(.98)}
    to {opacity:1;transform:translateY(0) scale(1)}
}

.login-card .brand {
    color:var(--navy);
    justify-content:center;
    margin-bottom:32px;
}

.login-card .subtitle {color:var(--emerald)}

.login-card h1 {
    color:var(--navy);
    text-align:center;
    margin-bottom:8px;
    font-size:27px;
}

.login-card .description {
    text-align:center;
    color:var(--muted);
    font-size:14px;
    margin-bottom:28px;
}

.login-card button {
    width:100%;
    padding:14px;
    color:var(--navy);
    border:1px solid #FBE7A0;
    background:linear-gradient(120deg,#FFF1A8,#D4AF37,#B58A25,#F8E28A);
    box-shadow:0 5px 20px rgba(156,116,27,.25),inset 0 1px 0 #fff;
    letter-spacing:1px;
}

.login-card button:hover {
    background:linear-gradient(120deg,#FFF8D2,#E7C85D,#C39A32);
    box-shadow:0 8px 25px rgba(156,116,27,.35);
}

.demo {
    margin-top:20px;
    padding:13px;
    border-radius:9px;
    background:linear-gradient(120deg,#F2E7D2,#E5D2B2);
    color:var(--emerald);
    font-size:12px;
    text-align:center;
    border:1px solid #D6C19A;
}

.error {
    background:#fff0ef;
    color:#a83232;
    padding:12px;
    border-radius:8px;
    margin-bottom:15px;
    font-size:13px;
}

/* REALISTIC MOVING AIRPLANES */

.airplane {
    position:absolute;
    z-index:2;
    left:-350px;
    pointer-events:none;
    filter:drop-shadow(0 12px 12px rgba(0,0,0,.55));
}

.airplane svg {
    width:100%;
    height:auto;
    display:block;
    overflow:visible;
}

.plane-one {
    top:13%;
    width:290px;
    animation:fly-one 25s linear infinite;
}

.plane-two {
    top:39%;
    width:205px;
    opacity:.9;
    animation:fly-two 32s linear infinite;
    animation-delay:6s;
}

.plane-three {
    top:68%;
    width:245px;
    opacity:.92;
    animation:fly-three 29s linear infinite;
    animation-delay:12s;
}

@keyframes fly-one {
    0% {left:-350px;top:13%;transform:rotate(-5deg) scale(.9)}
    25% {top:8%;transform:rotate(-2deg) scale(.96)}
    55% {top:11%;transform:rotate(-1deg) scale(1)}
    100% {left:120%;top:17%;transform:rotate(-4deg) scale(1.04)}
}

@keyframes fly-two {
    0% {left:-280px;top:39%;transform:rotate(-3deg) scale(.85)}
    30% {top:34%;transform:rotate(-1deg) scale(.9)}
    65% {top:38%;transform:rotate(-2deg) scale(.95)}
    100% {left:120%;top:43%;transform:rotate(-4deg) scale(1)}
}

@keyframes fly-three {
    0% {left:-320px;top:68%;transform:rotate(-4deg) scale(.9)}
    35% {top:62%;transform:rotate(-2deg) scale(.95)}
    70% {top:65%;transform:rotate(-1deg) scale(1)}
    100% {left:120%;top:70%;transform:rotate(-4deg) scale(1.03)}
}

/* DASHBOARD */

.layout {display:flex;min-height:100vh}

.sidebar {
    width:245px;
    background:linear-gradient(180deg,#030912 0%,var(--navy) 48%,var(--navy2) 100%);
    color:white;
    padding:25px 17px;
    display:flex;
    flex-direction:column;
    flex-shrink:0;
    border-right:2px solid rgba(212,175,55,.55);
    box-shadow:5px 0 20px rgba(7,22,41,.12);
}

.sidebar .brand {margin:0 5px 35px}

.sidebar nav {display:grid;gap:8px}

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

.nav-link:hover {
    background:linear-gradient(90deg,var(--emerald),#0B674D);
    color:white;
    transform:translateX(3px);
}

.nav-link.active {
    background:linear-gradient(110deg,#FFF0A8,#D4AF37,#B68B2B);
    color:var(--black);
    font-weight:bold;
    box-shadow:0 4px 15px rgba(212,175,55,.22),inset 0 1px 0 #fff;
}

.sidebar-footer {
    margin-top:auto;
    color:#aabbd0;
    font-size:11px;
    line-height:1.6;
    border-top:1px solid rgba(212,175,55,.3);
    padding-top:16px;
}

.main {flex:1;min-width:0}

.topbar {
    background:linear-gradient(100deg,#fff,#F5EBD9);
    padding:18px 30px;
    display:flex;
    justify-content:space-between;
    align-items:center;
    border-bottom:1px solid var(--gold);
    box-shadow:0 3px 12px rgba(7,22,41,.05);
}

.topbar strong {color:var(--navy)}

.content {padding:30px;max-width:1450px;margin:auto}

.heading {margin-bottom:22px}

.heading h1 {color:var(--navy);margin:5px 0}

.heading p {color:var(--muted);font-size:13px}

.eyebrow {
    color:var(--emerald);
    font-size:11px;
    font-weight:bold;
    letter-spacing:2px;
    text-transform:uppercase;
}

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
    box-shadow:0 10px 30px rgba(7,22,41,.15);
    position:relative;
    overflow:hidden;
}

.hero:after {
    content:"✦";
    position:absolute;
    right:35px;
    top:15px;
    font-size:90px;
    color:rgba(212,175,55,.22);
}

.hero h2 {font-size:26px;margin:10px 0}

.hero p {color:#dbe7f5;font-size:14px;line-height:1.6}

.hero .eyebrow {color:var(--gold-light)}

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
    transition:.25s;
}

.stat-card:hover {
    transform:translateY(-4px);
    box-shadow:0 12px 25px rgba(16,44,84,.12);
}

.stat-label {font-size:13px;color:var(--muted)}

.stat-number {
    color:var(--navy);
    font-size:32px;
    font-weight:bold;
    margin-top:12px;
}

.stat-icon {font-size:24px;color:var(--gold-dark)}

.panel {margin-bottom:20px;overflow-x:auto}

.panel h2 {font-size:17px;color:var(--navy);margin-top:0}

.panel-head {
    display:flex;
    justify-content:space-between;
    align-items:center;
    margin-bottom:15px;
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
    background:linear-gradient(90deg,#C5E4F7,#A9D2EF);
    font-size:11px;
    text-transform:uppercase;
}

tr:hover td {background:#FBF6EA}

.status {
    background:var(--emerald-light);
    color:var(--emerald);
    padding:6px 10px;
    border-radius:20px;
    font-size:11px;
    font-weight:bold;
    border:1px solid #B9E6D0;
}

.form-grid {
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:0 16px;
}

.wide {grid-column:1/-1}

.danger {
    background:linear-gradient(120deg,#FFF0EF,#F9D9D5);
    color:#A83232;
    border:1px solid #FFD6D6;
    box-shadow:none;
}

.light-button {
    background:linear-gradient(120deg,#F6EBD6,#E3D0AD);
    color:var(--navy);
    border:1px solid #D4BE95;
    box-shadow:none;
}

.empty {color:var(--muted);font-size:13px;padding:15px 0}

.report-grid {
    display:grid;
    grid-template-columns:repeat(3,1fr);
    gap:15px;
}

.report-box {
    background:linear-gradient(135deg,#F3E8D0,#E6D3B2);
    border:1px solid #D8C39B;
    border-radius:10px;
    padding:20px;
    color:var(--navy);
    box-shadow:inset 0 1px 0 #fff;
}

.report-box strong {
    display:block;
    color:var(--emerald);
    font-size:27px;
    margin-top:8px;
}

@media(max-width:900px) {
    .cards {grid-template-columns:repeat(2,1fr)}
    .sidebar {width:210px}
}

@media(max-width:650px) {
    .layout {display:block}
    .sidebar {width:100%;padding:15px}
    .sidebar .brand {margin-bottom:15px}
    .sidebar nav {display:flex;flex-wrap:wrap}
    .nav-link {font-size:12px;padding:9px}
    .sidebar-footer {display:none}
    .topbar {padding:15px}
    .content {padding:18px}
    .form-grid {grid-template-columns:1fr}
    .wide {grid-column:auto}
    .report-grid {grid-template-columns:1fr}
    .login-card {padding:25px}
    .airplane {opacity:.55}
}

@media(max-width:400px) {
    .cards {grid-template-columns:1fr}
}

@media(prefers-reduced-motion:reduce) {
    .airplane {animation:none;display:none}
    *,*:before,*:after {transition:none!important}
}
</style>
</head>

<body>

<?php if (!$loggedIn): ?>

<div class="login-wrap">

    <!-- AIRPLANE 1: LARGE PASSENGER JET -->
    <div class="airplane plane-one" aria-hidden="true">
        <svg viewBox="0 0 600 230" xmlns="http://www.w3.org/2000/svg">
            <defs>
                <linearGradient id="bodyGold1" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="0%" stop-color="#FFFFFF"/>
                    <stop offset="18%" stop-color="#E9F1F7"/>
                    <stop offset="42%" stop-color="#AFC4D5"/>
                    <stop offset="62%" stop-color="#F8E7A3"/>
                    <stop offset="82%" stop-color="#C49A2C"/>
                    <stop offset="100%" stop-color="#765719"/>
                </linearGradient>
                <linearGradient id="wingGold1" x1="0" y1="0" x2="1" y2="1">
                    <stop offset="0%" stop-color="#FFF7D5"/>
                    <stop offset="50%" stop-color="#D4AF37"/>
                    <stop offset="100%" stop-color="#80601D"/>
                </linearGradient>
                <linearGradient id="glass1" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="0%" stop-color="#B9DDF5"/>
                    <stop offset="100%" stop-color="#2457A7"/>
                </linearGradient>
            </defs>

            <!-- Main aircraft wings -->
            <path d="M265 108 L335 108 L480 202 L460 210 L290 143 L110 170 L92 159 L245 111Z"
                  fill="url(#wingGold1)" stroke="#FFF1B4" stroke-width="2"/>
            <path d="M265 108 L335 108 L480 20 L460 12 L290 75 L110 52 L92 63 L245 109Z"
                  fill="url(#wingGold1)" stroke="#FFF1B4" stroke-width="2"/>

            <!-- Tail wings -->
            <path d="M90 108 L125 108 L175 164 L160 169 L80 125Z"
                  fill="url(#wingGold1)" stroke="#FFF1B4" stroke-width="2"/>
            <path d="M90 112 L125 112 L175 55 L160 50 L80 95Z"
                  fill="url(#wingGold1)" stroke="#FFF1B4" stroke-width="2"/>

            <!-- Vertical tail fin -->
            <path d="M82 110 L120 110 L65 42 L45 45 L68 111 L45 175 L65 179 L120 114Z"
                  fill="#D4AF37" stroke="#FFF1B4" stroke-width="2"/>

            <!-- Engines -->
            <g fill="#8D9EAC" stroke="#EAF3FA" stroke-width="2">
                <path d="M335 74 Q360 67 377 83 L397 111 L366 116 L340 91Z"/>
                <path d="M335 145 Q360 152 377 136 L397 109 L366 104 L340 129Z"/>
            </g>
            <g fill="#071629">
                <ellipse cx="374" cy="91" rx="7" ry="13"/>
                <ellipse cx="374" cy="129" rx="7" ry="13"/>
            </g>

            <!-- Aircraft fuselage -->
            <path d="M35 111
                     Q55 99 92 99
                     L470 99
                     Q530 101 575 111
                     Q530 122 470 124
                     L92 124
                     Q55 123 35 111Z"
                  fill="url(#bodyGold1)" stroke="#FFFFFF" stroke-width="2"/>

            <!-- Nose highlight -->
            <path d="M35 111 Q55 99 92 99 L170 100 Q90 105 42 112Z"
                  fill="#FFFFFF" opacity=".8"/>

            <!-- Cockpit windows -->
            <path d="M72 102 L104 102 L116 109 L77 109Z" fill="url(#glass1)"/>
            <path d="M72 120 L104 120 L116 113 L77 113Z" fill="#2457A7" opacity=".8"/>

            <!-- Passenger windows -->
            <g fill="#2457A7" stroke="#FFFFFF" stroke-width="1">
                <rect x="145" y="105" width="10" height="5" rx="2"/>
                <rect x="165" y="105" width="10" height="5" rx="2"/>
                <rect x="185" y="105" width="10" height="5" rx="2"/>
                <rect x="205" y="105" width="10" height="5" rx="2"/>
                <rect x="225" y="105" width="10" height="5" rx="2"/>
                <rect x="245" y="105" width="10" height="5" rx="2"/>
                <rect x="265" y="105" width="10" height="5" rx="2"/>
                <rect x="285" y="105" width="10" height="5" rx="2"/>
                <rect x="305" y="105" width="10" height="5" rx="2"/>
                <rect x="325" y="105" width="10" height="5" rx="2"/>
                <rect x="345" y="105" width="10" height="5" rx="2"/>
                <rect x="365" y="105" width="10" height="5" rx="2"/>
                <rect x="385" y="105" width="10" height="5" rx="2"/>
                <rect x="405" y="105" width="10" height="5" rx="2"/>
                <rect x="425" y="105" width="10" height="5" rx="2"/>
                <rect x="445" y="105" width="10" height="5" rx="2"/>
            </g>

            <!-- Gold airline stripe -->
            <path d="M125 116 L470 116" stroke="#A27619" stroke-width="2" opacity=".8"/>
            <path d="M125 101 L465 101" stroke="#FFFFFF" stroke-width="1.5" opacity=".8"/>

            <!-- Wing lights -->
            <circle cx="470" cy="18" r="4" fill="#FFFFFF"/>
            <circle cx="470" cy="205" r="4" fill="#E53935"/>
        </svg>
    </div>

    <!-- AIRPLANE 2: WHITE AND BLUE PASSENGER JET -->
    <div class="airplane plane-two" aria-hidden="true">
        <svg viewBox="0 0 600 230" xmlns="http://www.w3.org/2000/svg">
            <defs>
                <linearGradient id="bodyBlue2" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="0%" stop-color="#FFFFFF"/>
                    <stop offset="45%" stop-color="#DCEAF4"/>
                    <stop offset="75%" stop-color="#A9C7DC"/>
                    <stop offset="100%" stop-color="#527D9F"/>
                </linearGradient>
                <linearGradient id="wingBlue2" x1="0" y1="0" x2="1" y2="1">
                    <stop offset="0%" stop-color="#FFFFFF"/>
                    <stop offset="55%" stop-color="#B9DDF5"/>
                    <stop offset="100%" stop-color="#2457A7"/>
                </linearGradient>
            </defs>

            <path d="M260 108 L330 108 L475 202 L455 210 L285 143 L105 170 L90 158 L240 111Z"
                  fill="url(#wingBlue2)" stroke="#FFFFFF" stroke-width="2"/>
            <path d="M260 108 L330 108 L475 20 L455 12 L285 75 L105 52 L90 64 L240 109Z"
                  fill="url(#wingBlue2)" stroke="#FFFFFF" stroke-width="2"/>
            <path d="M80 110 L125 110 L170 164 L150 170 L70 125Z"
                  fill="#2457A7" stroke="#FFFFFF" stroke-width="2"/>
            <path d="M80 112 L125 112 L170 55 L150 50 L70 95Z"
                  fill="#2457A7" stroke="#FFFFFF" stroke-width="2"/>

            <g fill="#71899B" stroke="#FFFFFF" stroke-width="2">
                <path d="M330 75 Q355 68 376 84 L395 110 L364 116 L338 92Z"/>
                <path d="M330 145 Q355 152 376 136 L395 110 L364 104 L338 128Z"/>
            </g>
            <g fill="#071629">
                <ellipse cx="373" cy="92" rx="7" ry="12"/>
                <ellipse cx="373" cy="128" rx="7" ry="12"/>
            </g>

            <path d="M30 111 Q55 98 95 99 L470 99 Q535 102 580 111 Q535 122 470 124 L95 124 Q55 123 30 111Z"
                  fill="url(#bodyBlue2)" stroke="#FFFFFF" stroke-width="2"/>
            <path d="M65 102 L105 102 L117 109 L72 109Z" fill="#2457A7"/>
            <g fill="#2457A7">
                <rect x="145" y="105" width="10" height="5" rx="2"/>
                <rect x="165" y="105" width="10" height="5" rx="2"/>
                <rect x="185" y="105" width="10" height="5" rx="2"/>
                <rect x="205" y="105" width="10" height="5" rx="2"/>
                <rect x="225" y="105" width="10" height="5" rx="2"/>
                <rect x="245" y="105" width="10" height="5" rx="2"/>
                <rect x="265" y="105" width="10" height="5" rx="2"/>
                <rect x="285" y="105" width="10" height="5" rx="2"/>
                <rect x="305" y="105" width="10" height="5" rx="2"/>
                <rect x="325" y="105" width="10" height="5" rx="2"/>
                <rect x="345" y="105" width="10" height="5" rx="2"/>
                <rect x="365" y="105" width="10" height="5" rx="2"/>
                <rect x="385" y="105" width="10" height="5" rx="2"/>
                <rect x="405" y="105" width="10" height="5" rx="2"/>
                <rect x="425" y="105" width="10" height="5" rx="2"/>
                <rect x="445" y="105" width="10" height="5" rx="2"/>
            </g>
            <path d="M120 117 L470 117" stroke="#2457A7" stroke-width="4"/>
            <path d="M120 101 L470 101" stroke="#FFFFFF" stroke-width="2"/>
            <circle cx="470" cy="18" r="4" fill="#E53935"/>
            <circle cx="470" cy="205" r="4" fill="#FFFFFF"/>
        </svg>
    </div>

    <!-- AIRPLANE 3: GOLD AND NAVY AIRLINER -->
    <div class="airplane plane-three" aria-hidden="true">
        <svg viewBox="0 0 600 230" xmlns="http://www.w3.org/2000/svg">
            <defs>
                <linearGradient id="bodyGold3" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="0%" stop-color="#FFFFFF"/>
                    <stop offset="35%" stop-color="#E8D9AD"/>
                    <stop offset="65%" stop-color="#D4AF37"/>
                    <stop offset="100%" stop-color="#80601D"/>
                </linearGradient>
                <linearGradient id="wingGold3" x1="0" y1="0" x2="1" y2="1">
                    <stop offset="0%" stop-color="#FFF4C5"/>
                    <stop offset="50%" stop-color="#D4AF37"/>
                    <stop offset="100%" stop-color="#102B4E"/>
                </linearGradient>
            </defs>

            <path d="M260 108 L330 108 L475 202 L455 210 L285 143 L105 170 L90 158 L240 111Z"
                  fill="url(#wingGold3)" stroke="#FFF1B4" stroke-width="2"/>
            <path d="M260 108 L330 108 L475 20 L455 12 L285 75 L105 52 L90 64 L240 109Z"
                  fill="url(#wingGold3)" stroke="#FFF1B4" stroke-width="2"/>
            <path d="M80 110 L125 110 L170 164 L150 170 L70 125Z"
                  fill="#102B4E" stroke="#D4AF37" stroke-width="2"/>
            <path d="M80 112 L125 112 L170 55 L150 50 L70 95Z"
                  fill="#102B4E" stroke="#D4AF37" stroke-width="2"/>

            <g fill="#9C741B" stroke="#FFF1B4" stroke-width="2">
                <path d="M330 75 Q355 68 376 84 L395 110 L364 116 L338 92Z"/>
                <path d="M330 145 Q355 152 376 136 L395 110 L364 104 L338 128Z"/>
            </g>
            <g fill="#071629">
                <ellipse cx="373" cy="92" rx="7" ry="12"/>
                <ellipse cx="373" cy="128" rx="7" ry="12"/>
            </g>

            <path d="M30 111 Q55 98 95 99 L470 99 Q535 102 580 111 Q535 122 470 124 L95 124 Q55 123 30 111Z"
                  fill="url(#bodyGold3)" stroke="#FFFFFF" stroke-width="2"/>
            <path d="M65 102 L105 102 L117 109 L72 109Z" fill="#102B4E"/>
            <g fill="#102B4E" stroke="#FFF4C5" stroke-width=".7">
                <rect x="145" y="105" width="10" height="5" rx="2"/>
                <rect x="165" y="105" width="10" height="5" rx="2"/>
                <rect x="185" y="105" width="10" height="5" rx="2"/>
                <rect x="205" y="105" width="10" height="5" rx="2"/>
                <rect x="225" y="105" width="10" height="5" rx="2"/>
                <rect x="245" y="105" width="10" height="5" rx="2"/>
                <rect x="265" y="105" width="10" height="5" rx="2"/>
                <rect x="285" y="105" width="10" height="5" rx="2"/>
                <rect x="305" y="105" width="10" height="5" rx="2"/>
                <rect x="325" y="105" width="10" height="5" rx="2"/>
                <rect x="345" y="105" width="10" height="5" rx="2"/>
                <rect x="365" y="105" width="10" height="5" rx="2"/>
                <rect x="385" y="105" width="10" height="5" rx="2"/>
                <rect x="405" y="105" width="10" height="5" rx="2"/>
                <rect x="425" y="105" width="10" height="5" rx="2"/>
                <rect x="445" y="105" width="10" height="5" rx="2"/>
            </g>
            <path d="M120 117 L470 117" stroke="#102B4E" stroke-width="4"/>
            <path d="M120 101 L470 101" stroke="#FFFFFF" stroke-width="2"/>
            <circle cx="470" cy="18" r="4" fill="#FFFFFF"/>
            <circle cx="470" cy="205" r="4" fill="#E53935"/>
        </svg>
    </div>

    <form class="login-card" method="post">

        <div class="brand">
            <div class="logo">✈</div>
            <div>
                SKYPASS
                <span class="subtitle">AIRPORT CONTROL CENTER</span>
            </div>
        </div>

        <h1>Welcome Back</h1>
        <p class="description">Sign in to manage airport operations.</p>

        <?php if ($error): ?>
            <div class="error"><?= h($error) ?></div>
        <?php endif; ?>

        <input type="hidden" name="action" value="login">

        <label>Username</label>
        <input type="text" name="username" placeholder="Enter username" required>

        <label>Password</label>
        <input type="password" name="password" placeholder="Enter password" required>

        <button type="submit">LOGIN TO SKYPASS →</button>

        <div class="demo">
            <strong>DEMO LOGIN</strong><br>
            Username: admin<br>
            Password: admin123
        </div>

    </form>
</div>

<?php else: ?>

<div class="layout">

    <aside class="sidebar">

        <div class="brand">
            <div class="logo">✈</div>
            <div>
                SKYPASS
                <span class="subtitle">AIRPORT CONTROL</span>
            </div>
        </div>

        <nav>
            <?php nav('dashboard',$page,'Dashboard','▦'); ?>
            <?php nav('passengers',$page,'Passengers','♙'); ?>
            <?php nav('flights',$page,'Flights','✈'); ?>
            <?php nav('tickets',$page,'Tickets','▤'); ?>
            <?php nav('passports',$page,'Passport Records','▣'); ?>
            <?php nav('reports',$page,'Reports','▥'); ?>
        </nav>

        <div class="sidebar-footer">
            SKYPASS AIRPORT CONTROL CENTER<br>
            Organized airport management.
        </div>

    </aside>

    <main class="main">

        <header class="topbar">
            <strong>Airport Operations / <?= h(ucfirst($page)) ?></strong>

            <form method="post">
                <input type="hidden" name="action" value="logout">
                <button class="danger" type="submit">Log out</button>
            </form>
        </header>

        <section class="content">

        <?php if ($page === 'dashboard'): ?>

            <div class="hero">
                <div class="eyebrow">Airport Operations Overview</div>
                <h2>Welcome to SkyPass Control Center</h2>
                <p>Manage passengers, flights, tickets, and airport records in one place.</p>
            </div>

            <div class="cards">

                <div class="stat-card">
                    <div class="stat-label">Registered Passengers</div>
                    <div class="stat-icon">♙</div>
                    <div class="stat-number"><?= $passengerCount ?></div>
                </div>

                <div class="stat-card">
                    <div class="stat-label">Flight Records</div>
                    <div class="stat-icon">✈</div>
                    <div class="stat-number"><?= $flightCount ?></div>
                </div>

                <div class="stat-card">
                    <div class="stat-label">Tickets Issued</div>
                    <div class="stat-icon">▤</div>
                    <div class="stat-number"><?= $ticketCount ?></div>
                </div>

                <div class="stat-card">
                    <div class="stat-label">Scheduled Flights</div>
                    <div class="stat-icon">◷</div>
                    <div class="stat-number"><?= $scheduledCount ?></div>
                </div>

            </div>

            <div class="panel">
                <div class="panel-head">
                    <h2>Recent Flights</h2>
                    <a class="button light-button" href="?page=flights">Manage Flights</a>
                </div>

                <?php if (!$data['flights']): ?>
                    <p class="empty">No flight records available.</p>
                <?php else: ?>
                    <table>
                        <tr>
                            <th>Flight Code</th>
                            <th>Origin</th>
                            <th>Destination</th>
                            <th>Departure</th>
                            <th>Status</th>
                        </tr>
                        <?php foreach (array_slice(array_reverse($data['flights']),0,5) as $f): ?>
                        <tr>
                            <td><strong><?= h($f['code']) ?></strong></td>
                            <td><?= h($f['origin']) ?></td>
                            <td><?= h($f['destination']) ?></td>
                            <td><?= h($f['departure']) ?></td>
                            <td><span class="status"><?= h($f['status']) ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    </table>
                <?php endif; ?>
            </div>

        <?php elseif ($page === 'passengers'): ?>

            <div class="heading">
                <div class="eyebrow">Passenger Services</div>
                <h1>Passenger Management</h1>
                <p>Register and manage passenger information.</p>
            </div>

            <div class="panel">
                <h2>Add Passenger</h2>

                <form method="post" class="form-grid">
                    <input type="hidden" name="action" value="add_passenger">

                    <div>
                        <label>Full Name</label>
                        <input name="name" required placeholder="Passenger name">
                    </div>

                    <div>
                        <label>Email</label>
                        <input type="email" name="email" placeholder="Email address">
                    </div>

                    <div>
                        <label>Phone Number</label>
                        <input name="phone" placeholder="Phone number">
                    </div>

                    <div>
                        <label>Passport Number</label>
                        <input name="passport" placeholder="Passport number">
                    </div>

                    <div class="wide">
                        <button type="submit">＋ Save Passenger</button>
                    </div>
                </form>
            </div>

            <div class="panel">
                <div class="panel-head">
                    <h2>Passenger Directory</h2>
                    <span><?= $passengerCount ?> records</span>
                </div>

                <?php if (!$data['passengers']): ?>
                    <p class="empty">No passenger records yet.</p>
                <?php else: ?>
                    <table>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Passport</th>
                            <th>Action</th>
                        </tr>

                        <?php foreach ($data['passengers'] as $p): ?>
                        <tr>
                            <td><strong><?= h($p['name']) ?></strong></td>
                            <td><?= h($p['email']) ?></td>
                            <td><?= h($p['phone']) ?></td>
                            <td><?= h($p['passport']) ?></td>
                            <td>
                                <form method="post" onsubmit="return confirm('Delete passenger?')">
                                    <input type="hidden" name="action" value="delete_passenger">
                                    <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                                    <button class="danger" type="submit">Delete</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </table>
                <?php endif; ?>
            </div>

        <?php elseif ($page === 'flights'): ?>

            <div class="heading">
                <div class="eyebrow">Flight Operations</div>
                <h1>Flight Management</h1>
                <p>Add and manage flight schedules.</p>
            </div>

            <div class="panel">
                <h2>Add Flight</h2>

                <form method="post" class="form-grid">
                    <input type="hidden" name="action" value="add_flight">

                    <div>
                        <label>Flight Code</label>
                        <input name="code" required placeholder="SP202">
                    </div>

                    <div>
                        <label>Status</label>
                        <select name="status">
                            <option>Scheduled</option>
                            <option>Boarding</option>
                            <option>Departed</option>
                            <option>Delayed</option>
                            <option>Cancelled</option>
                        </select>
                    </div>

                    <div>
                        <label>Origin</label>
                        <input name="origin" required placeholder="Departure city">
                    </div>

                    <div>
                        <label>Destination</label>
                        <input name="destination" required placeholder="Arrival city">
                    </div>

                    <div class="wide">
                        <label>Departure Date and Time</label>
                        <input type="datetime-local" name="departure">
                    </div>

                    <div class="wide">
                        <button type="submit">＋ Save Flight</button>
                    </div>
                </form>
            </div>

            <div class="panel">
                <h2>Flight Schedule</h2>

                <?php if (!$data['flights']): ?>
                    <p class="empty">No flight records yet.</p>
                <?php else: ?>
                    <table>
                        <tr>
                            <th>Flight Code</th>
                            <th>Origin</th>
                            <th>Destination</th>
                            <th>Departure</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>

                        <?php foreach ($data['flights'] as $f): ?>
                        <tr>
                            <td><strong><?= h($f['code']) ?></strong></td>
                            <td><?= h($f['origin']) ?></td>
                            <td><?= h($f['destination']) ?></td>
                            <td><?= h($f['departure']) ?></td>
                            <td><span class="status"><?= h($f['status']) ?></span></td>
                            <td>
                                <form method="post" onsubmit="return confirm('Delete flight?')">
                                    <input type="hidden" name="action" value="delete_flight">
                                    <input type="hidden" name="id" value="<?= (int)$f['id'] ?>">
                                    <button class="danger" type="submit">Delete</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </table>
                <?php endif; ?>
            </div>

        <?php elseif ($page === 'tickets'): ?>

            <div class="heading">
                <div class="eyebrow">Booking Services</div>
                <h1>Ticket Booking</h1>
                <p>Create ticket records for registered passengers.</p>
            </div>

            <div class="panel">
                <h2>Create Ticket</h2>

                <?php if (!$data['passengers'] || !$data['flights']): ?>
                    <p class="empty">Add a passenger and a flight before creating a ticket.</p>
                <?php else: ?>
                    <form method="post" class="form-grid">
                        <input type="hidden" name="action" value="book_ticket">

                        <div>
                            <label>Select Passenger</label>
                            <select name="passenger_id">
                                <?php foreach ($data['passengers'] as $p): ?>
                                    <option value="<?= (int)$p['id'] ?>"><?= h($p['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label>Select Flight</label>
                            <select name="flight_id">
                                <?php foreach ($data['flights'] as $f): ?>
                                    <option value="<?= (int)$f['id'] ?>">
                                        <?= h($f['code'].' - '.$f['origin'].' to '.$f['destination']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="wide">
                            <button type="submit">＋ Create Ticket</button>
                        </div>
                    </form>
                <?php endif; ?>
            </div>

            <div class="panel">
                <h2>Issued Tickets</h2>

                <?php if (!$data['tickets']): ?>
                    <p class="empty">No tickets issued yet.</p>
                <?php else: ?>
                    <table>
                        <tr>
                            <th>Reference</th>
                            <th>Passenger</th>
                            <th>Flight</th>
                            <th>Route</th>
                            <th>Created</th>
                        </tr>

                        <?php foreach ($data['tickets'] as $t): ?>
                        <tr>
                            <td><strong><?= h($t['reference']) ?></strong></td>
                            <td><?= h($t['passenger']) ?></td>
                            <td><?= h($t['flight']) ?></td>
                            <td><?= h($t['route']) ?></td>
                            <td><?= h($t['created']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </table>
                <?php endif; ?>
            </div>

        <?php elseif ($page === 'passports'): ?>

            <div class="heading">
                <div class="eyebrow">Passenger Documentation</div>
                <h1>Passport Records</h1>
                <p>View passport information saved in passenger profiles.</p>
            </div>

            <div class="panel">
                <h2>Passport Record List</h2>
                <p class="empty">This page displays recorded passport numbers. It does not issue or verify official passports.</p>

                <table>
                    <tr>
                        <th>Passenger Name</th>
                        <th>Passport Number</th>
                    </tr>

                    <?php foreach ($data['passengers'] as $p): ?>
                    <tr>
                        <td><?= h($p['name']) ?></td>
                        <td><?= h($p['passport'] ?: 'Not provided') ?></td>
                    </tr>
                    <?php endforeach; ?>
                </table>
            </div>

        <?php elseif ($page === 'reports'): ?>

            <div class="heading">
                <div class="eyebrow">System Insights</div>
                <h1>Reports</h1>
                <p>Summary of records stored in SkyPass.</p>
            </div>

            <div class="panel">
                <div class="report-grid">
                    <div class="report-box">
                        Total Passengers
                        <strong><?= $passengerCount ?></strong>
                    </div>

                    <div class="report-box">
                        Total Flights
                        <strong><?= $flightCount ?></strong>
                    </div>

                    <div class="report-box">
                        Total Tickets
                        <strong><?= $ticketCount ?></strong>
                    </div>
                </div>
            </div>

        <?php else: ?>

            <div class="heading">
                <h1>Page not found</h1>
                <a class="button" href="?page=dashboard">Back to Dashboard</a>
            </div>

        <?php endif; ?>

        </section>
    </main>
</div>

<?php endif; ?>

</body>
</html>
