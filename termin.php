<?php
// Database connection
$conn = new mysqli("localhost", "root", "root", "booking_system");

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Define services with durations
$services = [
    "Hydrofacial" => 90,
    "Tiefenreinigung" => 90,
    "Algen Peeling" => 60,
    "Saure Peeling" => 60,
    "BB Glowing" => 60,
    "Hyalnano" => 60,
    "Microneedling" => 60,
    "Wimpern & Augenbrauenlifting" => 60,
    "Abend Make Up" => 70,
    "Braut Make Up" => 90
];

// Define working hours
$working_hours = [
    "Monday" => [["10:00", "14:00"], ["17:00", "18:00"]],
    "Tuesday" => [["10:00", "14:00"], ["17:00", "18:00"]],
    "Wednesday" => [["10:00", "14:00"], ["17:00", "18:00"]],
    "Thursday" => [["10:00", "14:00"], ["17:00", "18:00"]],
    "Friday" => [["10:00", "14:00"], ["17:00", "18:00"]],
    "Saturday" => [["13:00", "18:00"]],
    "Sunday" => [] // Closed
];

// Fetch available slots dynamically
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["selected_date"])) {
    $selected_date = $_POST["selected_date"];
    $day_of_week = date("l", strtotime($selected_date));

    if (!isset($working_hours[$day_of_week]) || empty($working_hours[$day_of_week])) {
        echo json_encode([]);
        exit();
    }

    $query = $conn->prepare("SELECT time, service FROM appointments WHERE date = ?");
    $query->bind_param("s", $selected_date);
    $query->execute();
    $result = $query->get_result();
    $booked_slots = [];

    while ($row = $result->fetch_assoc()) {
        $booked_slots[] = [
            "time" => $row["time"],
            "duration" => $services[$row["service"]]
        ];
    }

    $available_slots = [];
    foreach ($working_hours[$day_of_week] as $range) {
        [$start, $end] = $range;
        $current_time = strtotime($start);
        $end_time = strtotime($end);
    
        // Allow the last appointment to start at 17:00 only
        $is_last_slot_exception = ($day_of_week !== "Saturday" && $day_of_week !== "Sunday" && strtotime($start) >= strtotime("17:00"));
    
        while ($current_time < $end_time) {
            $slot = date("H:i", $current_time);
            $valid_slot = true;
    
            foreach ($booked_slots as $booked) {
                $booked_start = strtotime($booked["time"]);
                $booked_end = $booked_start + ($booked["duration"] * 60);
    
                if ($current_time < $booked_end && ($current_time + (10 * 60)) > $booked_start) {
                    $valid_slot = false;
                    break;
                }
            }
    
            // Ensure service can start at 17:00 but no later
            $service_duration = $services[$_POST['service'] ?? array_key_first($services)];
            if ($current_time + ($service_duration * 60) > $end_time && $current_time !== strtotime("17:00")) {
                $valid_slot = false;
            }
    
            if ($valid_slot) {
                $available_slots[] = $slot;
            }
    
            $current_time += 10 * 60; // Increment by 10 minutes
        }
    }
       

    echo json_encode($available_slots);
    exit();
}

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] === "POST" && !isset($_POST["selected_date"])) {
    $date = trim($_POST["date"]);
    $time = trim($_POST["time"]);
    $service = trim($_POST["service"]);
    $name = trim($_POST["name"]);
    $last_name = trim($_POST["last_name"]);
    $phone = trim($_POST["phone"]);
    $email = trim($_POST["email"]);

    $duration = $services[$service];
    $end_time = date("H:i", strtotime($time) + $duration * 60);

    $day_of_week = date("l", strtotime($date));
    $day_hours = $working_hours[$day_of_week] ?? [];

    $is_valid = false;
    foreach ($day_hours as [$start, $end]) {
        $is_last_slot_exception = ($day_of_week !== "Saturday" && $day_of_week !== "Sunday" && strtotime($start) >= strtotime("17:00"));
    
        if (strtotime($time) >= strtotime($start) && 
            (strtotime($end_time) <= strtotime($end) || ($is_last_slot_exception && strtotime($time) === strtotime("17:00")))) {
            $is_valid = true;
            break;
        }
    }
    
    

    if (!$is_valid) {
        $message = "Der Termin überschreitet die Arbeitszeit. Bitte wählen Sie einen früheren Zeitpunkt.";
        $alert_color = "#f0cede";
    } elseif (empty($email)) {
        $message = "E-Mail ist erforderlich. Bitte füllen Sie das E-Mail-Feld aus.";
        $alert_color = "#f0cede";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "Ungültiges E-Mail-Format. Bitte geben Sie eine gültige E-Mail-Adresse ein.";
        $alert_color = "#f0cede";
    } else {
        $check_query = $conn->prepare(
            "SELECT * FROM appointments WHERE date = ? AND (
                (time <= ? AND ADDTIME(time, SEC_TO_TIME(? * 60)) > ?) OR
                (? <= time AND ADDTIME(?, SEC_TO_TIME(? * 60)) > time)
            )"
        );
        $check_query->bind_param("sssssss", $date, $time, $duration, $time, $time, $time, $duration);
        $check_query->execute();
        $result = $check_query->get_result();

        if ($result->num_rows > 0) {
            $message = "Dieser Termin steht im Konflikt mit einer bestehenden Buchung. Bitte wählen Sie einen anderen Slot.";
            $alert_color = "#f0cede";
        } else {
            $stmt = $conn->prepare("INSERT INTO appointments (date, time, service, name, last_name, phone, email) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sssssss", $date, $time, $service, $name, $last_name, $phone, $email);

            if ($stmt->execute()) {
                $message = "Ihr Termin wurde erfolgreich gebucht!";
                $alert_color = "#d4edda";
            } else {
                $message = "Fehler bei der Terminbuchung. Bitte versuchen Sie es erneut oder kontaktieren Sie uns über WhatsApp";
                $alert_color = "#f0cede";
            }
        }
    }
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Siyanda Kosmetik</title>
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <meta content="Appointment Booking" name="keywords">
    <meta content="Siyanda Kosmetik" name="description">
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.4.1/css/bootstrap.min.css" rel="stylesheet">
    <link href="css/style.css" rel="stylesheet">
    <style>
       .form-container {
            max-width: 1000px;
            margin: 20px auto;
            padding: 20px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
            display: flex;
            flex-wrap: wrap;
        }
        .form-wrapper {
            flex: 1;
            margin-right: 20px;
        }
        .price-list {
            flex: 1;
            border-left: 2px solid #f0cede;
            padding-left: 20px;
        }
        .price-list h3 {
            font-size: 24px;
            margin-bottom: 20px;
            color: #092a49;
        }
        .price-list ul {
            list-style: none;
            padding: 0;
        }
        .price-list ul li {
            margin-bottom: 10px;
            font-size: 18px;
        }
        .btn-primary {
            background-color: #f0cede;
            border-color: #f0cede;
        }
        .btn-primary:hover {
            background-color: #092a49;
            border-color: #092a49;
        }
        .alert-message {
            background-color: #f0cede;
            color: #000;
            padding: 15px;
            margin-top: 20px;
            border-radius: 5px;
            text-align: center;
        }
  

    </style>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const dateInput = document.getElementById('date');
            const timeSelect = document.getElementById('time');

            dateInput.addEventListener('change', function () {
                const selectedDate = dateInput.value;

                if (selectedDate) {
                    fetch(window.location.href, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: new URLSearchParams({ selected_date: selectedDate }),
                    })
                    .then(response => response.json())
                    .then(data => {
                        timeSelect.innerHTML = '<option value="">Wählen Sie ein Zeitfenster aus</option>';
                        if (data.length === 0) {
                            timeSelect.innerHTML += '<option value="">Keine verfügbaren Slots</option>';
                        } else {
                            data.forEach(time => {
                                timeSelect.innerHTML += `<option value="${time}">${time}</option>`;
                            });
                        }
                    })
                    .catch(error => console.error('Error fetching time slots:', error));
                }
            });
        });
    </script>
</head>
<body>
    <!-- Top Bar Start -->
    <div class="top-bar d-none d-md-block">
        <div class="container-fluid">
            <div class="row">
                <div class="col-md-8">
                    <div class="top-bar-left">
                       
                        <div class="text">
                            <i class="fa fa-phone-alt"></i>
                            <h2>+436701855553</h2>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="top-bar-right">
                        <div class="social">
                           
                            <a href="https://www.instagram.com/siyanda.kosmetik/"><i class="fab fa-instagram"></i></a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- Top Bar End -->

    <!-- Nav Bar Start -->
    <div class="navbar navbar-expand-lg bg-dark navbar-dark">
        <div class="container-fluid">
            <a href="index.html" class="navbar-brand"><img src="img/logo white png.png"></a>
            <button type="button" class="navbar-toggler" data-toggle="collapse" data-target="#navbarCollapse">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse justify-content-between" id="navbarCollapse">
                <div class="navbar-nav ml-auto">
                    <a href="index.html" class="nav-item nav-link">Startseite</a>
                    <a href="termin.php" class="nav-item nav-link active">Einen Termin vereinbaren</a>
                    <a href="contact.html" class="nav-item nav-link">Kontaktieren Sie uns</a>
                </div>
            </div>
        </div>
    </div>
    <!-- Nav Bar End -->

  <!-- Seitenüberschrift Start -->
<div class="page-header">
    <div class="container">
        <div class="row">
            <div class="col-12">
                <h2>Termin Vereinbaren</h2>
            </div>
            <div class="col-12">
                <a href="index.html">Startseite</a>
                <a href="termin.php">Termin Vereinbaren</a>
            </div>
        </div>
    </div>
</div>
<!-- Seitenüberschrift Ende -->

<div class="form-container">
    <!-- Formular Abschnitt -->
    <div class="form-wrapper">
        <h2 class="text-center">Termin Buchen</h2>
        <form method="POST">
            <div class="form-group">
                <label for="date">Datum:</label>
                <input type="date" id="date" name="date" class="form-control" required>
            </div>
            <div class="form-group">
                <label for="time">Uhrzeit:</label>
                <select id="time" name="time" class="form-control" required>
                    <option value="">Zeitslot auswählen</option>
                </select>
            </div>
            <div class="form-group">
                <label for="service">Dienstleistung:</label>
                <select id="service" name="service" class="form-control" required>
                    <option value="Hydrofacial">Hydrofacial</option>
                    <option value="Tiefenreinigung">Tiefenreinigung</option>
                    <option value="Algen Peeling">Algen Peeling</option>
                    <option value="Saure Peeling">Saure Peeling</option>
                    <option value="BB Glowing">BB Glowing</option>
                    <option value="Hyalnano">Hyalnano</option>
                    <option value="Microneedling">Microneedling</option>
                    <option value="Wimpern & Augenbrauenlifting">Wimpern & Augenbrauenlifting</option>
                    <option value="Abend Make Up">Abend Make Up</option>
                    <option value="Braut Make Up">Braut Make Up</option>
                </select>
            </div>
            <div class="form-group">
                <label for="name">Vorname:</label>
                <input type="text" id="name" name="name" class="form-control" required>
            </div>
            <div class="form-group">
                <label for="last_name">Nachname:</label>
                <input type="text" id="last_name" name="last_name" class="form-control" required>
            </div>
            <div class="form-group">
                <label for="phone">Telefonnummer:</label>
                <input type="text" id="phone" name="phone" class="form-control" required>
            </div>
            <div class="form-group">
                <label for="email">E-Mail:</label>
                <input type="email" id="email" name="email" class="form-control" required>
            </div>
            <button type="submit" class="btn btn-primary btn-block">Termin Buchen</button>
        </form>
        <?php if (!empty($message)): ?>
            <div class="alert-message"> <?= $message ?> </div>
        <?php endif; ?>
    </div>

    <!-- Preisliste Abschnitt -->
    <div class="price-list">
        <h3>Preisliste</h3>
        <ul>
            <li>Microneedling: €75</li>
            <li>Algen Peeling: €120</li>
            <li>Saure Peeling: €75</li>
            <li>Hyalnano Filling: €75</li>
            <li>BB Glowing: €75</li>
            <li>Tiefenreinigung Basic: €35</li>
            <li>Tiefenreinigung Premium: €45</li>
            <li>Hydrofacial Deluxe: €60</li>
            <li>Augenbrauen Lifting: €35</li>
            <li>Wimpernlifting: €40</li>
            <li>Tages Make up: €45 </li>
            <li>Abend Make up: €55 </li>
            <li>Braut Make up: €110 </li>
        </ul>
    </div>
</div>


    <!-- Footer Start -->
    <div class="footer wow fadeIn" data-wow-delay="0.3s">
        <div class="container-fluid">
            <div class="container">
                <div class="footer-info">
                    <a href="index.html" class="footer-logo"><img src="img/logo white footer.png"></a>
                    <h3>Breitenfurter Str.21, 1120 Wien</h3>
                    <div class="footer-menu">
                        <p>+436701855553</p>
                        <p>siyandakosmetik@gmail.com</p>
                    </div>
                    <div class="footer-social">
                       
                    <a href="https://www.instagram.com/siyanda.kosmetik/"><i class="fab fa-instagram"></i></a>
                    </div>
                </div>
            </div>
            <div class="container copyright">
                <div class="row">
                    <div class="col-md-6">
                        <p>&copy; <a href="#">Siyanda Kosmetik</a>, All Right Reserved.</p>
                    </div>
                    <div class="col-md-6">
                        <p>Designed By <a href="admin.php">Runi Baker</a></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- Footer End -->
</body>

</html>

