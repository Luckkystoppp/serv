<?php
require_once 'config.php';
startSession();
$user = currentUser();

$page = $_GET['page'] ?? 'home';
$validPages = ['home','login','register','logout','product','profile','account','chat','global-chat','post-product'];
if (!in_array($page, $validPages)) $page = 'home';

// Log traffic
logTraffic($page);

// Auto-upgrade accounts
if ($user) {
    // Upgrade new -> user after 7 days
    if ($user['role'] === 'new' && isAccountMature($user)) {
        $db = getDB();
        $db->prepare("UPDATE users SET role='user' WHERE id=?")->execute([$user['id']]);
        $user['role'] = 'user';
    }
}

include 'includes/header.php';

switch ($page) {
    case 'home':         include 'pages/home.php'; break;
    case 'login':        include 'pages/login.php'; break;
    case 'register':     include 'pages/register.php'; break;
    case 'logout':
        session_destroy();
        header('Location: index.php');
        exit;
    case 'product':      include 'pages/product.php'; break;
    case 'profile':      include 'pages/profile.php'; break;
    case 'account':
        if (!$user) { header('Location: index.php?page=login'); exit; }
        include 'pages/account.php'; break;
    case 'chat':
        if (!$user) { header('Location: index.php?page=login'); exit; }
        include 'pages/chat.php'; break;
    case 'global-chat':
        if (!$user) { header('Location: index.php?page=login'); exit; }
        include 'pages/global_chat.php'; break;
    case 'post-product':
        if (!$user) { header('Location: index.php?page=login'); exit; }
        include 'pages/post_product.php'; break;
}

include 'includes/footer.php';
