<!DOCTYPE html>
<html>
<head>
<title>KDC Help Desk</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

<style>
body {
    margin: 0;
    font-family: 'Segoe UI', Arial, sans-serif;
}

/* Hero Section with Dimmed Background */
.hero {
    background: linear-gradient(rgba(0,0,0,0.6), rgba(0,0,0,0.6)),
                url('assets/kdc_logo.png'); /* ✅ Make sure this file exists in assets folder */
    background-size: cover; /* ensures full coverage */
    background-repeat: no-repeat;
    background-position: center;
    height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    text-align: center;
}

/* Navbar Styling */
.navbar {
    box-shadow: 0 2px 4px rgba(0,0,0,0.3);
}

.navbar-brand img {
    height: 40px;
}

.hero h1 {
    font-size: 3rem;
    font-weight: 700;
    margin-bottom: 15px;
}

.hero p {
    font-size: 1.3rem;
    margin-bottom: 25px;
}

.btn-light.btn-lg {
    font-weight: 600;
    padding: 10px 30px;
    border-radius: 30px;
}
</style>
</head>

<body>

<!-- Navbar -->
<nav class="navbar navbar-dark bg-dark">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center">
            <img src="assets/kdc_logo.png" class="me-2">
            KDC Help Desk
        </a>
        <div>
            <a href="login.php" class="btn btn-outline-light">Login</a>
        </div>
    </div>
</nav>

<!-- Hero Section -->
<section class="hero">
    <div>
    
        <a href="login.php" class="btn btn-light btn-lg">Login</a>
    </div>
</section>

</body>
</html>
