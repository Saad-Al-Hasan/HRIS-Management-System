<?php

require_once __DIR__ . '/../../../core/Auth.php';

Auth::startSession();

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        <?= htmlspecialchars($pageTitle ?? 'Admin Dashboard') ?>
        - HRIS
    </title>

    <link
        rel="stylesheet"
        href="/HRIS-Management-System/public/assets/css/admin.css"
    >

</head>

<body>

<div class="admin-layout">