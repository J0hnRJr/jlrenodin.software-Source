<html>

<head>
    <link rel="stylesheet" type="text/css" href="./styles/default.css" />
</head>

<body>
    <?php $path_to_root = './../'; ?>
    <?php require_once './components/header.php'; ?>
    <h1>History</h1>
    <?php include_once './components/about/history.php';?>
    <h1>Education</h1>
    <?php include_once './components/about/education.php';?>
    <h1>Experience</h1>
    <?php include_once './components/about/experience.php';?>
    <?php require_once './components/footer.php'; ?>
</body>

</html>
