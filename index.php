<?php

// At the very top of index.php, before any output
ini_set('display_errors', 0);
error_reporting(0); // Turn off all error reporting for production
// OR for development:
// error_reporting(E_ALL);
// ini_set('display_errors', 1);

require_once 'includes/config.php';

function getUserIpAddr() {
    if(!empty($_SERVER['HTTP_CLIENT_IP'])) {
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    } elseif(!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
    } else {
        $ip = $_SERVER['REMOTE_ADDR'];
    }
    return filter_var($ip, FILTER_VALIDATE_IP) ?: 'UNKNOWN';
}

$searchTerm = '';
$results = [];
$error = '';
$showExamples = false;

$exampleRentals = [
    [
        'id' => 1,
        'owner_name' => 'John Smith',
        'owner_photo' => 'driver6.jpg',
        'car_model' => 'Toyota Camry 2020',
        'destination' => 'Liverpool City Center',
        'pickup_location' => 'L1 1AB, Liverpool',
        'available_from' => 'Today, 15:30',
        'available_to' => 'Tomorrow, 10:00',
        'price_per_day' => '£45',
        'rating' => 4.8
    ],
    [
        'id' => 2,
        'owner_name' => 'Sarah Johnson',
        'owner_photo' => 'driver2.jpg',
        'car_model' => 'Honda Civic 2019',
        'destination' => 'Manchester Airport',
        'pickup_location' => 'M1 1AA, Manchester',
        'available_from' => 'Tomorrow, 08:00',
        'available_to' => 'Friday, 18:00',
        'price_per_day' => '£38',
        'rating' => 4.9
    ],
    [
        'id' => 3,
        'owner_name' => 'Michael Brown',
        'owner_photo' => 'driver3.jpg',
        'car_model' => 'Ford Focus 2021',
        'destination' => 'Albert Dock',
        'pickup_location' => 'L3 4AX, Liverpool',
        'available_from' => 'Today, 18:45',
        'available_to' => 'Sunday, 20:00',
        'price_per_day' => '£42',
        'rating' => 4.7
    ]
];

// Fetch latest 3 advertisements
try {
    $stmt = $pdo->prepare("SELECT a.*, d.name as owner_name, d.photo as owner_photo 
                          FROM advertisements a 
                          JOIN drivers d ON a.owner_id = d.id 
                          WHERE a.is_active = 1 
                          ORDER BY a.created_at DESC 
                          LIMIT 3");
    $stmt->execute();
    $latestAdvertisements = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Error fetching latest advertisements: " . $e->getMessage());
    $latestAdvertisements = [];
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['search'])) {
    $searchTerm = trim($_POST['searchTerm']);
    
    if (!empty($searchTerm)) {
        try {
            $stmt = $pdo->prepare("SELECT a.*, d.name as owner_name, d.photo as owner_photo 
                                  FROM advertisements a 
                                  JOIN drivers d ON a.owner_id = d.id 
                                  WHERE (a.destination LIKE :term1 
                                  OR a.pickup_location LIKE :term2)
                                  AND a.is_active = 1
                                  ORDER BY a.available_from ASC 
                                  LIMIT 12");
            $searchPattern = '%' . $searchTerm . '%';
            $stmt->bindParam(':term1', $searchPattern, PDO::PARAM_STR);
            $stmt->bindParam(':term2', $searchPattern, PDO::PARAM_STR);
            $stmt->execute();
            $results = $stmt->fetchAll();
            
            if (empty($results)) {
                $error = "No rentals found near '".htmlspecialchars($searchTerm)."'";
                $showExamples = true;
            }
        } catch (PDOException $e) {
            error_log("Search error: " . $e->getMessage());
            $error = "Database error: " . $e->getMessage();
            $showExamples = true;
        }
    } else {
        $error = "Please enter a location to search for rentals";
        $showExamples = true;
    }
} else {
    $showExamples = true;
}

$captcha_passed = true;
if ($captcha_passed) {
    try {
        $guest_session_id = session_id();
        $current_datetime = date('Y-m-d H:i:s');
        $ip_location = getUserIpAddr();
        
        $sql = "INSERT INTO guest_visits (session_id, visit_datetime, location) 
                VALUES (:session_id, :visit_datetime, :location)
                ON DUPLICATE KEY UPDATE visit_count = visit_count + 1";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(":session_id", $guest_session_id);
        $stmt->bindParam(":visit_datetime", $current_datetime);
        $stmt->bindParam(":location", $ip_location);
        $stmt->execute();
    } catch (PDOException $e) {
        error_log("Guest tracking error: " . $e->getMessage());
    }
}


// Add this function to check login status
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}





?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Who Is Coming - Find Car Rentals Near You</title>
    
    <!-- SEO Meta Tags -->
    <meta name="description" content="Find reliable car rentals from local owners. Connect with trusted drivers for your transportation needs. Easy booking, competitive prices, and verified owners.">
    <meta name="keywords" content="car rental, vehicle rental, local car owners, transportation, car sharing, ride sharing">
    <meta name="author" content="Who Is Coming">
    <meta name="robots" content="index, follow">
    
    <!-- Open Graph Tags -->
    <meta property="og:title" content="Who Is Coming - Find Car Rentals Near You">
    <meta property="og:description" content="Find reliable car rentals from local owners. Connect with trusted drivers for your transportation needs.">
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?php echo (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]"; ?>">
    <meta property="og:image" content="includes/images/logo.png">
    
    <!-- Twitter Card Tags -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="Who Is Coming - Find Car Rentals Near You">
    <meta name="twitter:description" content="Find reliable car rentals from local owners. Connect with trusted drivers for your transportation needs.">
    <meta name="twitter:image" content="includes/images/logo.png">

    <!-- Add these meta tags -->
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="language" content="English">
  
    
    <!-- Canonical URL -->
    <link rel="canonical" href="<?php echo (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]"; ?>">
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="includes/images/favicon.png">
    
    <!-- External CSS -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="stylesheet" href="css/index.css">
    
    <!-- Preload Critical Resources -->
    <link rel="preload" href="includes/images/default.jpg" as="image">
    <link rel="preload" href="css/index.css" as="style">
    
    <!-- Structured Data -->
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "WebSite",
        "name": "Who Is Coming",
        "url": "<?php echo (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]"; ?>",
        "potentialAction": {
            "@type": "SearchAction",
            "target": "<?php echo (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]"; ?>/index.php?searchTerm={search_term_string}",
            "query-input": "required name=search_term_string"
        }
    }
    </script>
</head>
<body>
<header role="banner">
    <div class="container">
        <div class="header-content">
            <div class="logo" role="img" aria-label="Who Is Coming Logo">
                <i class="fas fa-car" aria-hidden="true"></i>
                <span>WhoIsComing?</span>
            </div>

            <nav class="profile-icon" role="navigation" aria-label="User menu">
                <button onclick="toggleProfileDropdown()" aria-expanded="false" aria-controls="profileDropdown">
                    <i class="fas fa-user-circle" aria-hidden="true"></i>
                </button>
                <div class="profile-dropdown" id="profileDropdown" role="menu">
                    <?php if (isLoggedIn()): ?>
                        <a href="dashboard.php" role="menuitem"><i class="fas fa-user" aria-hidden="true"></i> My Dashboard</a>
                        <a href="logout.php" role="menuitem"><i class="fas fa-sign-out-alt" aria-hidden="true"></i> Logout</a>
                    <?php else: ?>
                        <a href="login.php" role="menuitem"><i class="fas fa-sign-in-alt" aria-hidden="true"></i> Login</a>
                        <a href="register.php" role="menuitem"><i class="fas fa-user-plus" aria-hidden="true"></i> Register</a>
                        <a href="driver_login.php" role="menuitem"><i class="fas fa-car" aria-hidden="true"></i> Driver Login</a>
                    <?php endif; ?>
                </div>
            </nav>
        </div>
    </div>
</header>
    
<main role="main">
    <div class="container">
        <section class="search-container" aria-labelledby="search-heading">
            <div class="search-header">
                <h1 id="search-heading">Who Is Coming?</h1>
                <p>Connect with owners renting their personal vehicles</p>
            </div>
            
            <form method="POST" action="index.php" role="search">
                <div class="search-box">
                    <label for="searchTerm" class="visually-hidden">Search for car rentals</label>
                    <input type="text" id="searchTerm" name="searchTerm" class="search-input" 
                           placeholder="Enter location (e.g. 'Liverpool City Center')" 
                           value="<?= htmlspecialchars($searchTerm) ?>"
                           aria-label="Search for car rentals by location">
                    <button type="submit" name="search" class="search-button">
                        <i class="fas fa-search" aria-hidden="true"></i> Find Owners
                    </button>
                </div>
            </form>
        </section>
        
        <?php if (!empty($error)): ?>
            <div class="error-message" role="alert">
                <i class="fas fa-exclamation-circle" aria-hidden="true"></i> <?= $error ?>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($results)): ?>
            <section class="results-container" aria-labelledby="results-heading">
                <div class="results-header">
                    <h2 id="results-heading">Owners Available Near "<?= htmlspecialchars($searchTerm) ?>"</h2>
                    <div class="results-count"><?= count($results) ?> owners available</div>
                </div>
                
                <div class="results-grid" role="list">
                    <?php foreach ($results as $rental): ?>
                        <article class="result-item" role="listitem">
                            <div class="owner-image-container">
                                <img src="includes/images/<?= $rental['owner_photo'] ?? 'default.jpg' ?>" 
                                     alt="Profile photo of <?= htmlspecialchars($rental['owner_name']) ?>" 
                                     class="owner-image"
                                     loading="lazy">
                                <div class="car-model"><?= htmlspecialchars($rental['car_model']) ?></div>
                            </div>
                            
                            <div class="result-content">
                                <div class="owner-info">
                                    <h3 class="owner-name"><?= htmlspecialchars($rental['owner_name']) ?></h3>
                                    <div class="rating" aria-label="Rating: <?= $rental['rating'] !== null ? number_format($rental['rating'], 1) : '0.0' ?> out of 5">
                                        <i class="fas fa-star" aria-hidden="true"></i>
                                        <?= $rental['rating'] !== null ? number_format($rental['rating'], 1) : '0.0' ?>
                                    </div>
                                </div>
                                
                                <div class="destination-info">
                                    <div class="destination-label">Available for trips to</div>
                                    <h4 class="destination-name"><?= htmlspecialchars($rental['destination']) ?></h4>
                                    <div class="pickup-location">Pickup: <?= htmlspecialchars($rental['pickup_location']) ?></div>
                                </div>
                                
                                <div class="availability-details">
                                    <div class="detail-item">
                                        <i class="fas fa-calendar-day detail-icon" aria-hidden="true"></i>
                                        <div class="detail-text">
                                            <strong>Available:</strong> <?= htmlspecialchars($rental['available_from']) ?>
                                        </div>
                                    </div>
                                    <div class="detail-item">
                                        <i class="fas fa-calendar-times detail-icon" aria-hidden="true"></i>
                                        <div class="detail-text">
                                            <strong>Until:</strong> <?= htmlspecialchars($rental['available_to']) ?>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="price-container">
                                    <div class="price-tag"><?= htmlspecialchars($rental['price_per_day']) ?> / day</div>
                                    <button class="contact-button" 
                                            onclick="openContactModal(
                                                '<?= htmlspecialchars($rental['owner_name']) ?>',
                                                '<?= htmlspecialchars($rental['owner_photo'] ?? 'default.jpg') ?>',
                                                '<?= htmlspecialchars($rental['car_model']) ?>',
                                                '<?= htmlspecialchars($rental['id']) ?>'
                                            )"
                                            aria-label="Contact <?= htmlspecialchars($rental['owner_name']) ?>">
                                        <i class="fas fa-comment-alt" aria-hidden="true"></i> Contact Owner
                                    </button>
                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php elseif ($showExamples): ?>
            <section class="examples-container" aria-labelledby="examples-heading">
                <div class="examples-header">
                    <h2 id="examples-heading">Latest Vehicle Owners</h2>
                    <p>Search for a location to see available owners</p>
                </div>
                
                <?php if (!empty($latestAdvertisements)): ?>
                    <div class="results-grid" role="list">
                        <?php foreach ($latestAdvertisements as $rental): ?>
                            <article class="result-item" role="listitem">
                                <div class="owner-image-container">
                                    <img src="includes/images/<?= $rental['owner_photo'] ?? 'default.jpg' ?>" 
                                         alt="Profile photo of <?= htmlspecialchars($rental['owner_name']) ?>" 
                                         class="owner-image"
                                         loading="lazy">
                                    <div class="car-model"><?= htmlspecialchars($rental['car_model']) ?></div>
                                </div>
                                
                                <div class="result-content">
                                    <div class="owner-info">
                                        <h3 class="owner-name"><?= htmlspecialchars($rental['owner_name']) ?></h3>
                                        <div class="rating" aria-label="Rating: <?= $rental['rating'] !== null ? number_format($rental['rating'], 1) : '0.0' ?> out of 5">
                                            <i class="fas fa-star" aria-hidden="true"></i>
                                            <?= $rental['rating'] !== null ? number_format($rental['rating'], 1) : '0.0' ?>
                                        </div>
                                    </div>
                                    
                                    <div class="destination-info">
                                        <div class="destination-label">Available for trips to</div>
                                        <h4 class="destination-name"><?= htmlspecialchars($rental['destination']) ?></h4>
                                        <div class="pickup-location">Pickup: <?= htmlspecialchars($rental['pickup_location']) ?></div>
                                    </div>
                                    
                                    <div class="availability-details">
                                        <div class="detail-item">
                                            <i class="fas fa-calendar-day detail-icon" aria-hidden="true"></i>
                                            <div class="detail-text">
                                                <strong>Available:</strong> <?= htmlspecialchars($rental['available_from']) ?>
                                            </div>
                                        </div>
                                        <div class="detail-item">
                                            <i class="fas fa-calendar-times detail-icon" aria-hidden="true"></i>
                                            <div class="detail-text">
                                                <strong>Until:</strong> <?= htmlspecialchars($rental['available_to']) ?>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="price-container">
                                        <div class="price-tag">£<?= htmlspecialchars($rental['price_per_day']) ?> / day</div>
                                        <button class="contact-button" 
                                                onclick="openContactModal(
                                                    '<?= htmlspecialchars($rental['owner_name']) ?>',
                                                    '<?= htmlspecialchars($rental['owner_photo'] ?? 'default.jpg') ?>',
                                                    '<?= htmlspecialchars($rental['car_model']) ?>',
                                                    '<?= htmlspecialchars($rental['id']) ?>'
                                                )"
                                                aria-label="Contact <?= htmlspecialchars($rental['owner_name']) ?>">
                                            <i class="fas fa-comment-alt" aria-hidden="true"></i> Contact Owner
                                        </button>
                                    </div>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                
                <div class="example-search">
                    <p>Search for a location to see vehicle owners available for rental</p>
                </div>
            </section>
        <?php endif; ?>
        
        <section class="popular-destinations" aria-labelledby="popular-heading">
            <h3 id="popular-heading" class="popular-header">Popular Rental Locations</h3>
            <div class="popular-tags" role="list">
                <button class="popular-tag" onclick="setSearch('Liverpool City Center')" role="listitem">Liverpool</button>
                <button class="popular-tag" onclick="setSearch('Manchester Airport')" role="listitem">Manchester</button>
                <button class="popular-tag" onclick="setSearch('Albert Dock')" role="listitem">Albert Dock</button>
                <button class="popular-tag" onclick="setSearch('Shopping Center')" role="listitem">Shopping Centers</button>
                <button class="popular-tag" onclick="setSearch('Train Station')" role="listitem">Train Stations</button>
                <button class="popular-tag" onclick="setSearch('University')" role="listitem">Universities</button>
            </div>
        </section>
    </div>
</main>

<!-- Contact Owner Modal -->
<div class="modal-overlay" id="contactModal">
    <div class="modal-container">
        <div class="modal-header">
            <h3 class="modal-title">Contact Owner</h3>
            <button class="close-modal" onclick="closeModal()">&times;</button>
        </div>
        
        <div class="timer-container">
            <div class="timer-text">Due to high demand, this page will refresh in:</div>
            <div class="timer" id="countdownTimer">60 seconds</div>
        </div>
        
        <div class="owner-details">
            <img src="" alt="Owner" class="modal-owner-image" id="modalOwnerImage">
            <div class="modal-owner-info">
                <h4 id="modalOwnerName"></h4>
                <p id="modalCarModel"></p>
                <p>Owner ID: <span id="modalOwnerId"></span></p>
            </div>
        </div>
        
        <div class="form-group">
            <label for="rentalDate">Rental Date Range</label>
            <div class="datetime-range">
                <input type="datetime-local" id="startDate" class="form-control" required>
                <input type="datetime-local" id="endDate" class="form-control" required>
            </div>
        </div>
        
        <div class="form-group">
            <label for="message">Your Message</label>
            <textarea id="message" class="form-control" rows="4" 
                      placeholder="Tell the owner about your rental needs..."></textarea>
        </div>
        
        <!-- Updated Modal Actions Section -->
        <div class="modal-actions">
            <button class="cancel-button" onclick="closeModal()">Cancel</button>
            <button class="confirm-button" onclick="checkLoginBeforeSend()">
                <i class="fas fa-paper-plane"></i> Send Request
            </button>
        </div>

        <!-- Add this login prompt div (hidden by default) -->
        <div class="login-prompt" id="loginPrompt" style="display: none;">
            <h4>You need an account to contact owners</h4>
            <p>Please login or create an account to send rental requests</p>
            <div class="login-actions">
                <button class="login-button" onclick="redirectToLogin()">
                    <i class="fas fa-sign-in-alt"></i> Login
                </button>
                <button class="continue-button" onclick="redirectToRegistration()">
                    <i class="fas fa-user-plus"></i> Create Account
                </button>
            </div>
        </div>
        
    </div>

    

    <div class="past-trips-section" id="pastTrips">
        <div class="results-header">
            <h2>Your Past Trips</h2>
        </div>
        <div class="no-trips-message">
            <p>You haven't taken any trips yet.</p>
            <button class="search-button" onclick="document.querySelector('.search-input').focus()">
                <i class="fas fa-search"></i> Find Owners
            </button>
        </div>
    </div>

</div>

<script>
    function setSearch(term) {
        const searchInput = document.querySelector('[name="searchTerm"]');
        const searchForm = document.querySelector('form');
        
        if (searchInput && searchForm) {
            searchInput.value = term;
            // Create and dispatch a submit event
            const submitEvent = new Event('submit', {
                bubbles: true,
                cancelable: true
            });
            searchForm.dispatchEvent(submitEvent);
        } else {
            console.error('Search elements not found');
        }
    }
    
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelector('.search-input').focus();
    });
    
    let countdownInterval;
    const contactModal = document.getElementById('contactModal');
    
    function openContactModal(ownerName, ownerPhoto, carModel, ownerId) {
        document.getElementById('modalOwnerName').textContent = ownerName;
        document.getElementById('modalOwnerImage').src = 'includes/images/' + ownerPhoto;
        document.getElementById('modalCarModel').textContent = carModel;
        document.getElementById('modalOwnerId').textContent = ownerId;
        
        const now = new Date();
        const timezoneOffset = now.getTimezoneOffset() * 60000;
        const localISOTime = (new Date(now - timezoneOffset)).toISOString().slice(0, 16);
        document.getElementById('startDate').min = localISOTime;
        document.getElementById('endDate').min = localISOTime;
        
        document.getElementById('startDate').value = '';
        document.getElementById('endDate').value = '';
        document.getElementById('message').value = '';
        
        contactModal.classList.add('active');
        startCountdown(60);
        adjustDateTimeInputs();
    }
    
    function closeModal() {
        contactModal.classList.remove('active');
        clearInterval(countdownInterval);
    }
    
    function startCountdown(seconds) {
        const timerElement = document.getElementById('countdownTimer');
        let timeLeft = seconds;
        
        timerElement.textContent = timeLeft + ' seconds';
        timerElement.style.color = '#e65100';
        
        countdownInterval = setInterval(() => {
            timeLeft--;
            
            if (timeLeft <= 0) {
                timerElement.textContent = 'Refreshing...';
                clearInterval(countdownInterval);
                setTimeout(() => {
                    closeModal();
                    window.location.reload();
                }, 1000);
            } else {
                timerElement.textContent = timeLeft + ' second' + (timeLeft !== 1 ? 's' : '');
                
                if (timeLeft <= 10) {
                    timerElement.style.color = '#e53935';
                }
            }
        }, 1000);
    }
    
 function sendContactRequest() {
        const startDate = document.getElementById('startDate').value;
        const endDate = document.getElementById('endDate').value;
        const message = document.getElementById('message').value;
        const ownerId = document.getElementById('modalOwnerId').textContent;
        
        if (!startDate || !endDate) {
            alert('Please select both start and end dates');
            return;
        }
        
        if (new Date(endDate) < new Date(startDate)) {
            alert('End date must be after start date');
            return;
        }
        
        // Show loading state
        const sendBtn = document.querySelector('.confirm-button');
        sendBtn.disabled = true;
        sendBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';
        
        // Send AJAX request to server
        fetch('handle_contact_request.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                owner_id: ownerId,
                start_date: startDate,
                end_date: endDate,
                message: message
            })
        })
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                alert('Your request has been sent to the owner!');
                closeModal();
            } else {
                alert('Error: ' + (data.message || 'Failed to send request'));
                sendBtn.disabled = false;
                sendBtn.innerHTML = '<i class="fas fa-paper-plane"></i> Send Request';
            }
        })
        .catch(error => {
            console.error('Error details:', error);
            alert('An error occurred while sending your request. Please try again later.');
            sendBtn.disabled = false;
            sendBtn.innerHTML = '<i class="fas fa-paper-plane"></i> Send Request';
        });
    }
    

    function adjustDateTimeInputs() {
        const isMobile = window.matchMedia("(max-width: 480px)").matches;
        const dateInputs = document.querySelectorAll('input[type="datetime-local"]');
        
        dateInputs.forEach(input => {
            input.style.minWidth = isMobile ? '100%' : '0';
        });
    }
    
    window.addEventListener('resize', adjustDateTimeInputs);
    
    contactModal.addEventListener('click', (e) => {
        if (e.target === contactModal) {
            closeModal();
        }
    });



    function checkLoginBeforeSend() {
        // Check login status via AJAX
        fetch('check_login.php')
            .then(response => response.json())
            .then(data => {
                if (data.loggedIn) {
                    // User is logged in, proceed with sending request
                    sendContactRequest();
                } else {
                    // Show login prompt
                    document.getElementById('loginPrompt').style.display = 'block';
                    document.querySelector('.modal-actions').style.display = 'none';
                }
            })
            .catch(error => {
                console.error('Error checking login status:', error);
                alert('Error checking your login status. Please try again.');
            });
    }

    function redirectToLogin() {
        // Store the current form data in sessionStorage
        const formData = {
            ownerId: document.getElementById('modalOwnerId').textContent,
            startDate: document.getElementById('startDate').value,
            endDate: document.getElementById('endDate').value,
            message: document.getElementById('message').value
        };
        sessionStorage.setItem('rentalRequestData', JSON.stringify(formData));
        
        // Redirect to login page
        window.location.href = 'login.php?redirect=rental_request';
    }

    function redirectToRegistration() {
        // Store the current form data in sessionStorage
        const formData = {
            ownerId: document.getElementById('modalOwnerId').textContent,
            startDate: document.getElementById('startDate').value,
            endDate: document.getElementById('endDate').value,
            message: document.getElementById('message').value
        };
        sessionStorage.setItem('rentalRequestData', JSON.stringify(formData));
        
        // Redirect to passenger registration page
        window.location.href = 'register.php?type=passenger&redirect=rental_request';
    }

    function sendContactRequest() {
        const startDate = document.getElementById('startDate').value;
        const endDate = document.getElementById('endDate').value;
        const message = document.getElementById('message').value;
        const ownerId = document.getElementById('modalOwnerId').textContent;
        
        if (!startDate || !endDate) {
            alert('Please select both start and end dates');
            return;
        }
        
        if (new Date(endDate) < new Date(startDate)) {
            alert('End date must be after start date');
            return;
        }
        
        // AJAX request to send the message
        fetch('handle_contact_request.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                owner_id: ownerId,
                start_date: startDate,
                end_date: endDate,
                message: message
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Your request has been sent to the owner!');
                closeModal();
            } else {
                alert('Error: ' + (data.message || 'Failed to send request'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred while sending your request');
        });
    }

    // Check for saved form data when page loads
    document.addEventListener('DOMContentLoaded', function() {
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('redirect') === 'rental_request') {
            const savedData = sessionStorage.getItem('rentalRequestData');
            if (savedData) {
                const formData = JSON.parse(savedData);
                // You could auto-fill the form here if needed
                sessionStorage.removeItem('rentalRequestData');
            }
        }
    });


    function toggleProfileDropdown() {
        const dropdown = document.getElementById('profileDropdown');
        dropdown.classList.toggle('show');
        
        // Close dropdown when clicking outside
        if (dropdown.classList.contains('show')) {
            document.addEventListener('click', closeProfileDropdownOnClickOutside);
        } else {
            document.removeEventListener('click', closeProfileDropdownOnClickOutside);
        }
    }

    function closeProfileDropdownOnClickOutside(e) {
        const dropdown = document.getElementById('profileDropdown');
        const profileIcon = document.querySelector('.profile-icon');
        
        if (!profileIcon.contains(e.target) && !dropdown.contains(e.target)) {
            dropdown.classList.remove('show');
            document.removeEventListener('click', closeProfileDropdownOnClickOutside);
        }
    }


















    // Auto-scroll to bottom of chat
    function scrollToBottom() {
        const chatMessages = document.getElementById('chatMessages');
        if (chatMessages) {
            chatMessages.scrollTop = chatMessages.scrollHeight;
        }
    }
    
    // Handle message submission
    document.getElementById('messageForm')?.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        const message = formData.get('message');
        const passengerId = formData.get('passenger_id');
        
        if (!message.trim()) return;
        
        // Show loading state
        const submitBtn = this.querySelector('button');
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
        
        // Send AJAX request
        fetch('handle_driver_message.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams({
                passenger_id: passengerId,
                message: message
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Add message to chat
                const chatMessages = document.getElementById('chatMessages');
                const now = new Date();
                
                const newMessage = document.createElement('div');
                newMessage.className = 'message sent';
                newMessage.innerHTML = `
                    <div class="message-content">${message}</div>
                    <div class="message-time">${now.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'})}</div>
                `;
                
                chatMessages.appendChild(newMessage);
                this.reset();
                scrollToBottom();
            } else {
                alert('Error: ' + (data.message || 'Failed to send message'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred while sending your message');
        })
        .finally(() => {
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="fas fa-paper-plane"></i>';
        });
    });
    
    // Scroll to bottom when page loads
    window.addEventListener('load', scrollToBottom);



</script>
</body>
</html>