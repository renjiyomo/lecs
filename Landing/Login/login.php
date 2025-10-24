<?php
session_start();
include 'lecs_db.php'; // DB connection: should set $conn (mysqli)

$error = "";
$email_prefill = "";

// If already logged in, send them where they belong
if (isset($_SESSION['teacher_id'])) {
    if (!empty($_SESSION['user_type']) && $_SESSION['user_type'] === 'a') {
        header("Location: /lecs/Landing/Login/Page/adminDashboard.php"); exit;
    } else {
        header("Location: /lecs/Landing/Login/Page/teacherDashboard.php"); exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $email_prefill = $email;

    if ($email === '' || $password === '') {
        $error = "Please fill in both fields.";
    } else {
        // Get account
        $stmt = $conn->prepare("SELECT teacher_id, CONCAT(first_name, ' ', COALESCE(middle_name, ''), ' ', last_name) AS full_name, email, password, user_type, user_status FROM teachers WHERE email = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $res = $stmt->get_result();

            if ($row = $res->fetch_assoc()) {
                // Check account status
                if ($row['user_status'] !== 'a') {
                    $error = "Your account is not active. Please contact the administrator.";
                } else {
                    $storedHash = $row['password'] ?? '';
                    $hashLen = strlen($storedHash);

                    // Detect common pitfall: truncated bcrypt hash (needs >= 60 chars)
                    if ($hashLen < 60) {
                        $error = "Your password can’t be verified because it was saved with a truncated hash (stored length: {$hashLen}). 
Please run: ALTER TABLE teachers MODIFY password VARCHAR(255) NOT NULL; then reset this account’s password.";
                    } else {
                        if (password_verify($password, $storedHash)) {
                            // Optional: upgrade hash if algorithm/options changed
                            if (password_needs_rehash($storedHash, PASSWORD_DEFAULT)) {
                                $newHash = password_hash($password, PASSWORD_DEFAULT);
                                $upd = $conn->prepare("UPDATE teachers SET password = ? WHERE teacher_id = ?");
                                if ($upd) {
                                    $upd->bind_param("si", $newHash, $row['teacher_id']);
                                    $upd->execute();
                                    $upd->close();
                                }
                            }

                            // Successful login
                            session_regenerate_id(true);
                            $_SESSION['teacher_id'] = $row['teacher_id'];
                            $_SESSION['full_name']  = $row['full_name'];
                            $_SESSION['user_type']  = $row['user_type'];

                            if ($row['user_type'] === 'a') {
                                header("Location: /lecs/Landing/Login/Page/adminDashboard.php"); exit;
                            } else {
                                header("Location: /lecs/Landing/Login/Page/teacherDashboard.php"); exit;
                            }
                        } else {
                            $error = "Invalid password.";
                        }
                    }
                }
            } else {
                $error = "No account found with that email.";
            }

            $stmt->close();
        } else {
            $error = "Database error. Please try again.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
    <link rel="icon" href="image/lecs-logo no bg.png" type="image/x-icon">
    <link rel="stylesheet" href="login.css">
    <link rel="stylesheet" href="css/all.min.css">
</head>
<body class="light">
  <div class="login-container">

    <div class="login-left">
      <div class="header-left">
        <img src="image/very slow logo.gif" alt="School Logo" class="logo-left">
        <div class="title-text">
          <h1>Welcome to</h1>
          <p>Online Student Grading System</p>
        </div>
      </div>
    </div>

    <div class="login-right">
      <div class="theme-toggle">
        <i class="fa-solid fa-moon toggle-icon" id="darkModeBtn"></i>
        <i class="fa-solid fa-sun toggle-icon" id="lightModeBtn" style="display:none;"></i>
      </div>

      <img src="image/lecs-logo no bg.png" alt="School Logo" class="logo">
      <h2>Libon East Central School</h2>
      <p class="subtext">Libon East District</p>

      <?php if (!empty($error)): ?>
        <p style="color:red; text-align:center; margin-bottom:10px;"><?php echo htmlspecialchars($error); ?></p>
      <?php endif; ?>

      <form action="" method="POST">
        <div class="label-row">
          <label for="email">Email</label>
          <i class="fa-solid fa-circle-question help-icon" id="helpBtn" title="Get help"></i>
        </div>
        <div class="input-box">
          <input type="email" id="email" name="email" placeholder="Enter your email" required value="<?php echo htmlspecialchars($email_prefill); ?>">
        </div>

        <div class="label-row">
          <label for="password">Password</label>
        </div>
        <div class="input-box">
          <input type="password" id="password" name="password" placeholder="Enter your password" required>
          <span class="toggle-pass" id="togglePass"><i class="fa-solid fa-eye"></i></span>
        </div>

        <button type="submit" class="btn">Sign In</button>
        <a href="#" class="forgot" id="forgotBtn">Forgot password?</a>
      </form>
    </div>
  </div>

  <!-- Help Modal -->
  <div id="helpModal" class="modal">
    <div class="modal-content">
      <h2>Account Help</h2>
      <p>
        You can get your account by requesting it from the 
        <b>school head</b> or <b>designated school system administrator</b> 
        or by emailing <b>email@example.com</b>.
      </p>
      <button class="closeBtn">Close</button>
    </div>
  </div>

  <!-- Forgot Modal -->
  <div id="forgotModal" class="modal">
    <div class="modal-content">
      <h2>Forgot Password</h2>
      <p>
        Please contact your <b>school system administrator</b> or email <b>email@example.com</b>
        to reset your password.
      </p>
      <button class="closeBtn">Close</button>
    </div>
  </div>

  <script>
    // Light/Dark toggle
const body = document.body;
const darkBtn = document.getElementById("darkModeBtn");
const lightBtn = document.getElementById("lightModeBtn");

function setMode(mode) {
    body.classList.remove("light", "dark");
    body.classList.add(mode);
    darkBtn.style.display = mode === "dark" ? "none" : "inline-block";
    lightBtn.style.display = mode === "light" ? "none" : "inline-block";
    // Save the mode to localStorage
    localStorage.setItem('theme', mode);
}

darkBtn.onclick = () => setMode("dark");
lightBtn.onclick = () => setMode("light");

// Apply the saved mode when the page loads
document.addEventListener('DOMContentLoaded', () => {
    const savedMode = localStorage.getItem('theme') || 'light'; // Default to light if no preference is saved
    setMode(savedMode);
});

    // Modals
    const helpBtn = document.getElementById("helpBtn");
    const forgotBtn = document.getElementById("forgotBtn");
    const helpModal = document.getElementById("helpModal");
    const forgotModal = document.getElementById("forgotModal");
    const closeBtns = document.querySelectorAll(".closeBtn");

    if (helpBtn) helpBtn.onclick = () => helpModal.style.display = "flex";
    if (forgotBtn) forgotBtn.onclick = (e) => { e.preventDefault(); forgotModal.style.display = "flex"; };

    closeBtns.forEach(btn => {
      btn.onclick = () => {
        helpModal.style.display = "none";
        forgotModal.style.display = "none";
      };
    });

    window.onclick = (e) => {
      if (e.target === helpModal) helpModal.style.display = "none";
      if (e.target === forgotModal) forgotModal.style.display = "none";
    };

    // Show/Hide password
    const togglePass = document.getElementById("togglePass");
    const pwdInput = document.getElementById("password");
    if (togglePass && pwdInput) {
      togglePass.addEventListener("click", () => {
        const icon = togglePass.querySelector("i");
        if (pwdInput.type === "password") {
          pwdInput.type = "text";
          icon.classList.remove("fa-eye");
          icon.classList.add("fa-eye-slash");
        } else {
          pwdInput.type = "password";
          icon.classList.remove("fa-eye-slash");
          icon.classList.add("fa-eye");
        }
      });
    }
  </script>
</body>
</html>