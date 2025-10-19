<?php
if (!isset($page_title)) {
    $page_title = 'Hospital Appraisal System';
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo htmlspecialchars($page_title); ?></title>

    <!-- Bootstrap 5.3 CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- Project styles -->
    <link href="/has/HAS/sidebar.css" rel="stylesheet">
    <link href="/has/HAS/assets/css/app.css" rel="stylesheet">
</head>
<body>
<?php
// start session if not started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
