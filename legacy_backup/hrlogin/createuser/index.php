<?php
session_start();
// If the user is not logged in redirect to the login page...
if (!isset($_SESSION['loggedin'])) {
	header('Location: /');
	exit;
}
// fitch name from  account table to show to the user
$nameuser = $_SESSION['username'];
// Check if the logged-in user is a superadmin and set the session variable accordingly
if ($nameuser === $nameuser) { // Replace 'your_superadmin_username' with the actual superadmin's username
  $_SESSION['is_superadmin'] = true;
}
require_once dirname(__DIR__) . '/includes/db_mysqli.php';
try {
    $conn = hr_mysqli_connect();
} catch (Throwable $e) {
    die('Connection failed: ' . $e->getMessage());
}

// Slim list for manager assignment dropdown (capped)
$sql = "SELECT user_type, username, tablename FROM accounts WHERE is_active = 1 ORDER BY username ASC LIMIT 500";
$result = $conn->query($sql);

$allUsers = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        // normalize nothing in PHP; keep raw so we can display username/tablename correctly
        $allUsers[] = $row;
    }
} else {
    echo "Error: " . $sql . "<br>" . $conn->error;
}

// Close the connection
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create User</title>
    <link rel="shortcut icon" type="nobglogo.png" href="../assets/images/nobglogo.png" alt="text">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="./assets/css/style2.css">
    <style>
    .selection{width: 50%;padding: 10px;margin-left: 25%;border-radius: 21px;background-color: #6c757d;color: white;font-weight: 700;font-size: 16px;}
    </style>
</head>
<body>
    <form class="container" action="create.php" method="POST">
        <!-- <img src="assets/nobglogo.png" alt="Search Homes India" width="10%"> -->
        <h2 class="heading">Sign Up</h2>
        <div class="steps-container">
            <hr>
            <hr class="active">
            <div class="steps"><i class="fa-solid fa-user"></i></div>
            <div class="steps"><i class="fa-solid fa-envelope"></i></div>
            <div class="steps"><i class="fa-solid fa-key"></i></div>
            <div class="steps"><i class="fa-solid fa-money-bill-1"></i></div>
            <div class="steps"><i class="fa-solid fa-indian-rupee-sign"></i></div>
            <div class="steps"><i class="fa-solid fa-users"></i></div>
            <div class="steps"><i class="fa-solid fa-trophy"></i></div>
        </div>
        
        <div class="input-slide-contianer">
            <scroller>
                <div class="input-slide">
                <h3>Create User</h3>
                <p><i>Powerd By: <b>Search Homes India Pvt. Ltd.</b></i></p>
                <ul class="rules">

                    <li>Your username should only contain letters, numbers, underscores or hyphens. </li><li>No (' ,) this kinds of characters or special characters allowed.</li>
                    <li>Your username should not be offensive or contain profanity.</li>
                </ul>
                <input type="username" placeholder="Type Your Employee Name Here" name="username" class="username" required>
                <div class="full-name">
                    <input type="tel" placeholder="Phone Number Here" name="phone" required>
                    <input type="tel" name="salary" class="lastname" placeholder="Enter Monthly CTC" required>
                </div>
                <button class="button-nex" onclick="NextSlide(1)">Next</button>
            </div>
            <div class="input-slide user-detail">
                <h3>Email</h3>
                <p><i>Email is the gateway to your digital life</i></p>
                <ul class="rules">
                    <li>Please enter valid email in the format "example@example.com".</li>
                    <li>we value your privacy and will never use your email for any unauthorized purposes.</li>
                    <!-- <li>Please don't use email that belong to someone else.</li> -->
                    <li>Please don't use email that includes personal information.</li>
                </ul>
                <input type="email" name="email" class="email" placeholder="Enter Email" required>
                <div class="full-name">
                    <input type="text" placeholder="Enter Table Name" name="table" required>
                    <input type="tel" name="emId" class="lastname" placeholder="Enter Employee ID Here" required>
                </div>
                
                <button class="button-nex" onclick="NextSlide(2)">Next</button>
            </div>
            <div class="input-slide password-slide">
                <h3>Password</h3>
                <p><i>Secure your account with a strong password</i></p>
                <ul class="rules">
                    <li>Include a mix of uppercase and lowercase letters, numbers, and special characters.</li>
                    <li>Avoid using common or easily guessable passwords.</li>
                    <li>Do not use personal information.</li>
                </ul>
                <input type="password" name="password" class="password" placeholder="Create Password" required>
                <div class="full-name">
                    <label for="date of joining">DOJ</label>
                    <input type="date" name="doj" placeholder="Enter Date Of Joining" required>
                    <label for="date of birth">DOB</label>
                    <input type="date" name="dob" placeholder="Enter Employee DOB" required>
                </div>
                <button class="button-nex" onclick="NextSlide(3)">Next</button>
            </div>
            <div class="input-slide amount1-slide">
                <h3>Amount-S1</h3>
                <p><i>Secure your This Amount will be applicable according to condition</i></p>
                <ul class="rules">
                    <li>Frist Amount will be count on the bases of 1st booking</li>
                    <li>Secound Amount will be count on the bases of 2st booking</li>
                    <li>Third Amount will be count on the bases of 3rd booking</li>
                </ul>
                <input type="number" name="amountO" class="amount" placeholder="Enter 1st Amount">
                <div class="full-name">
                    <label for="date of joining">2nd Amount</label>
                    <input type="number" name="amountT" placeholder="Enter 2nd Amt">
                    <label for="date of birth">3rd Amount</label>
                    <input type="number" name="amountTh" placeholder="Enter 3rd Amt">
                </div>
                <button class="button-nex" onclick="NextSlide(4)">Next</button>
            </div>
            <div class="input-slide amount2-slide">
                <h3>Amount-S2</h3>
                <p><i>Secure your This Amount will be applicable according to condition</i></p>
                <ul class="rules">
                    <li>Forth Amount will be count on the bases of 4th booking</li>
                    <li>Fifth Amount will be count on the bases of 5th booking</li>
                    <li>Sixth Amount will be count on the bases more then 6 booking</li>
                </ul>
                <input type="number" name="amountF" class="amount" placeholder="Enter 4th Amount">
                <div class="full-name">
                    <label for="date of joining">5th Amount</label>
                    <input type="number" name="amountFf" placeholder="Enter 5th Amt">
                    <label for="date of birth">6th Amount</label>
                    <input type="number" name="amountS" placeholder="Enter 6th Amt">
                </div>
                <button class="button-nex" onclick="NextSlide(5)">Next</button>
            </div>
            <div class="input-slide amount2-slide">
                <h3>Account Verify and Fix</h3>
                <p><i>Here we are deciding the user for there project and departments</i></p>
                <ul class="rules">
                    <li>Always Verify there account where you have to put the user</li>
                    <li>Please Verify the project name before creating</li>
                </ul>
                <input type="text" name="project_name" class="amount" placeholder="Enter Project Name">
                
                <!-- form HTML -->
                <div class="full-name">
                    <select name="D_project" id="D_project" class="selection" style="margin-left: 2%;">
                        <option value="">Project Type</option>
                        <option value="mandate">Mandate</option>
                        <option value="retail">Retail</option>
                    </select>
                    
                    <input type="text" name="agent_city" id="agent_city" class="selection" list="agentCityList" autocomplete="off" placeholder="Agent City" style="margin-left: 2%;">
                    <datalist id="agentCityList">
                        <option value="Bangalore"></option>
                        <option value="Hyderabad"></option>
                        <option value="Pune"></option>
                        <option value="Chennai"></option>
                        <option value="Mumbai"></option>
                        <option value="Delhi"></option>
                        <option value="Gujarat"></option>
                    </datalist>
                
                    <!-- NOTE: use consistent canonical values (lowercase) for user_type options -->
                    <select name="user_type" id="user_type" class="selection" style="margin-left: 2%;">
                        <option value="">User Type</option>
                        <option value="promoter">Promoter</option>
                        <option value="business head">Business Head</option>
                        <option value="manager">Manager</option>
                        <option value="team lead">Team Lead</option>
                        <option value="user">User</option>
                    </select>
                </div>
                
                <!-- Assign User Dropdown -->
                <div id="assign_user_container" style="margin-top: 3%; display: none;">
                    <select name="assign_user" id="assign_user" class="selection">
                        <option value="">Assign User</option>
                        <!-- options will be injected by JS -->
                    </select>
                </div>
                <button class="button-nex" onclick="NextSlide(6)">Next</button>
            </div>
            <div class="input-slide finish-slide">
                <h3>Congratulations!</h3>
                <p><i>You have completed all the steps required for registration.</i></p>
                <ul class="rules">
                <li>Before submitting your information, please take a moment to ensure that all the details provided are correct. </li>
                <li> We take the privacy and security of our users very seriously, and it is important that all the information provided is accurate and up-to-date.</li> 
                <li>Once you have confirmed that everything is in order, simply click the 'Submit' button.</li>
                </ul> 
              
                <button type="submit" class="button-nex">Create User</button>
            </div>
        </scroller> 
        </div>
      <button class="GoBack" onclick="GoBack()"><i class="fa-solid fa-arrow-left"></i></button> 
    </form>

   <script src="./assets/js/script1.js"></script> 
   <!-- <script>
    function NextSlide(slideIndex) {
        var currentSlide = document.getElementsByClassName("input-slide")[slideIndex - 1];
        var inputs = currentSlide.querySelectorAll("input[required]");

        var isValid = true;
        for (var i = 0; i < inputs.length; i++) {
            if (inputs[i].value === "") {
                isValid = false;
                break;
            }
        }

        if (isValid) {
            // Proceed to the next slide
            // Your code to change slides goes here
            
            // Enable the "Create User" button
            var createButton = document.querySelector(".input-slide.finish-slide button[type='submit']");
            createButton.disabled = false;
        } 
        else {
            // Display an error message or take any other action
            alert("Please fill in all the required fields before proceeding.");
        }
    }
</script> -->
<script>
    // Get all the "Next" buttons
    var nextButtons = document.querySelectorAll(".button-nex");
  
    // Add event listeners to the "Next" buttons
    nextButtons.forEach(function(button, index) {
      button.addEventListener("click", function() {
        // Get the current slide and required inputs in that slide
        var currentSlide = document.getElementsByClassName("input-slide")[index];
        var inputs = currentSlide.querySelectorAll("input[required]");
  
        // Check if all the required inputs in the current slide are filled
        var isValid = true;
        for (var i = 0; i < inputs.length; i++) {
          if (inputs[i].value === "") {
            isValid = false;
            break;
          }
        }
  
        if (isValid) {
          // Proceed to the next slide
          // Your code to change slides goes here
  
          // Enable the "Create User" button if it's the last slide
          if (index === nextButtons.length - 1) {
            var createButton = document.querySelector(".input-slide.finish-slide button[type='submit']");
            createButton.disabled = false;
          }
        } else {
          // Display an error message or take any other action
          alert("Please fill in all the required fields before proceeding.");
        }
      });
    });
  </script>
  <script>
    // Pass PHP array to JS
    const allUsers = <?php echo json_encode($allUsers, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
    /*
     Hierarchy mapping (keys are lowercase)
     - promoter: none above it
     - business head: show promoter
     - manager: show business head + promoter
     - team lead: show manager + business head + promoter
     - user: show team lead + manager + business head + promoter
    */
    const hierarchy = {
        'promoter': [],
        'business head': ['promoter'],
        'manager': ['business head', 'promoter'],
        'team lead': ['manager', 'business head', 'promoter'],
        'user': ['team lead', 'manager', 'business head', 'promoter']
    };
    
    const userTypeSelect = document.getElementById('user_type');
    const assignUserContainer = document.getElementById('assign_user_container');
    const assignUserSelect = document.getElementById('assign_user');
    
    function populateAssignUsers(selectedType) {
        // clear previous options except the first placeholder
        assignUserSelect.length = 1; // keeps the "Assign User" placeholder at index 0
    
        if (!selectedType) {
            assignUserContainer.style.display = 'none';
            return;
        }
    
        const key = selectedType.trim().toLowerCase();
        const allowedTypes = hierarchy[key] || [];
    
        // If allowedTypes is empty, we may still want to show nothing (e.g., promoter)
        // But if the selected type is "user" we already have allowedTypes set appropriately
        if (allowedTypes.length === 0) {
            // If you want to show the full list when key === 'user' you'd handle that above.
            assignUserContainer.style.display = 'none';
            return;
        }
    
        // Filter allUsers by allowed types (case-insensitive)
        const filtered = allUsers.filter(u => {
            // Some DB values may have different casing or small typos; normalize to lowercase
            const ut = (u.user_type || '').toString().trim().toLowerCase();
            return allowedTypes.includes(ut);
        });
    
        if (filtered.length === 0) {
            // No matching users found
            assignUserContainer.style.display = 'none';
            return;
        }
    
        // Populate select in the desired order (we respect allowedTypes order)
        allowedTypes.forEach(type => {
            filtered
                .filter(u => (u.user_type || '').toString().trim().toLowerCase() === type)
                .forEach(u => {
                    const opt = document.createElement('option');
                    // use tablename as the value (as you did) and username for display
                    opt.value = u.tablename;
                    opt.textContent = u.username + ' (' + u.user_type + ')';
                    assignUserSelect.appendChild(opt);
                });
        });
    
        assignUserContainer.style.display = 'block';
    }
    
    userTypeSelect.addEventListener('change', function() {
        const selected = this.value;
        // special case: if user selects exact 'user' we want to show all above (already in mapping)
        populateAssignUsers(selected);
    });
    
    // Optional: on load, if a value is preselected (edit form), populate accordingly
    document.addEventListener('DOMContentLoaded', function() {
        if (userTypeSelect.value) populateAssignUsers(userTypeSelect.value);
    });
 </script>
</body>
</html>