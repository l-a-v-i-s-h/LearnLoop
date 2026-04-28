<header class="main-header">
    <a href="dashboard.php" class="logo-section" style="text-decoration: none; color: inherit;">
        LearnL<span class="logo-icon"><i class="fa-solid fa-infinity"></i></span>p
    </a>
    
    <div class="search-bar">
        <i class="fa-solid fa-magnifying-glass"></i>
        <input type="text" placeholder="Search">
    </div>

    <div class="user-controls" style="display: flex; align-items: center; gap: 20px;">
        <div style="display: flex; align-items: center; gap: 12px; color: #1e3a5f; font-weight: 500;">
            <div style="background: white; width: 45px; height: 45px; border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                <i class="fa-solid fa-graduation-cap" style="font-size: 20px;"></i>
            </div>
            <span>Student</span>
        </div>
        <form action="logout.php" method="POST" style="margin: 0;">
            <?php echo csrf_input(); ?>
            <button type="submit" aria-label="Logout" style="background: none; border: 0; padding: 0; cursor: pointer; color: #1e3a5f;">
                <i class="fa-solid fa-arrow-right-from-bracket" style="font-size: 22px;"></i>
            </button>
        </form>
    </div>
</header>