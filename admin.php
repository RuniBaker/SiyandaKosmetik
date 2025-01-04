<?php
// Start the session
session_start();

// Dummy credentials for the admin login
$adminUsername = "admin";
$adminPassword = "cigerxwin";

// Log out functionality
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: admin.php");
    exit();
}

// Handle login
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['login'])) {
    $username = $_POST['username'];
    $password = $_POST['password'];

    if ($username === $adminUsername && $password === $adminPassword) {
        $_SESSION['loggedIn'] = true;
        header("Location: admin.php");
        exit();
    } else {
        $error = "Invalid username or password!";
    }
}

// Check if the user is logged in
if (!isset($_SESSION['loggedIn']) || !$_SESSION['loggedIn']) {
    // Display the login form if not logged in
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Admin Login</title>
        <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.4.1/css/bootstrap.min.css" rel="stylesheet">
        <style>
            body {
                display: flex;
                justify-content: center;
                align-items: center;
                height: 100vh;
                background-color: #f8f9fa;
            }
            .login-container {
                width: 400px;
                padding: 20px;
                background: white;
                border-radius: 10px;
                box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
            }
        </style>
    </head>
    <body>
        <div class="login-container">
            <h2 class="text-center">Admin Login</h2>
            <?php if (isset($error)): ?>
                <div class="alert alert-danger"><?= $error ?></div>
            <?php endif; ?>
            <form method="POST">
                <div class="form-group">
                    <label for="username">Username:</label>
                    <input type="text" id="username" name="username" class="form-control" required>
                </div>
                <div class="form-group">
                    <label for="password">Password:</label>
                    <input type="password" id="password" name="password" class="form-control" required>
                </div>
                <button type="submit" name="login" class="btn btn-primary btn-block">Login</button>
            </form>
        </div>
    </body>
    </html>
    <?php
    exit();
}

// Connect to the database
$servername = "localhost";
$username = "root";
$password = "root";
$dbname = "booking_system";

$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Delete appointments older than 24 hours
$conn->query("DELETE FROM appointments WHERE CONCAT(date, ' ', time) < NOW()");

// Handle manual appointment deletion
if (isset($_GET['delete'])) {
    $deleteId = intval($_GET['delete']);
    $conn->query("DELETE FROM appointments WHERE id = $deleteId");
    header("Location: admin.php");
    exit();
}

// Fetch all appointments sorted by day
$query = "SELECT * FROM appointments ORDER BY date, time";
$result = $conn->query($query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.4.1/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
        }
        .button {
            padding: 10px 20px;
            margin-top: 10px;
            background-color: #007BFF;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            display: inline-block;
        }
        .button:hover {
            background-color: #0056b3;
        }
        .logout {
            background-color: #dc3545;
        }
        .logout:hover {
            background-color: #a71d2a;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1 class="text-center mb-4">Admin Dashboard</h1>
        <div class="d-flex justify-content-between mb-3">
            <a href="index.html" class="button">Back to Home</a>
            <a href="?logout=true" class="button logout">Logout</a>
        </div>
        <h2 class="mb-3">All Appointments</h2>
        <?php if (!$result): ?>
            <p class="text-danger">Error fetching appointments: <?= $conn->error ?></p>
        <?php else: ?>
            <?php if ($result->num_rows > 0): ?>
                <div class="table-responsive">
                    <table class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Date</th>
                                <th>Time</th>
                                <th>Service</th>
                                <th>Name</th>
                                <th>Last Name</th>
                                <th>Phone</th>
                                <th>Email</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = $result->fetch_assoc()): ?>
                                <tr>
                                    <td><?= $row['id'] ?></td>
                                    <td><?= $row['date'] ?></td>
                                    <td><?= $row['time'] ?></td>
                                    <td><?= $row['service'] ?></td>
                                    <td><?= $row['name'] ?></td>
                                    <td><?= $row['last_name'] ?></td>
                                    <td><?= $row['phone'] ?></td>
                                    <td><?= $row['email'] ?></td>
                                    <td><a href="?delete=<?= $row['id'] ?>" class="btn btn-danger btn-sm">Delete</a></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p class="text-warning">No appointments found.</p>
            <?php endif; ?>
        <?php endif; ?>
    </div>
    <script src="https://code.jquery.com/jquery-3.4.1.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.4.1/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php
$conn->close();
?>
