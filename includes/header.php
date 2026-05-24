<header class="main-header">
    <a href="dashboard.php" class="logo-section" style="text-decoration: none; color: inherit;">
        LearnL<span class="logo-icon"><i class="fa-solid fa-infinity"></i></span>p
    </a>
    
    <div class="search-bar">
        <i class="fa-solid fa-magnifying-glass"></i>
        <input type="text" placeholder="Search">
    </div>

    <div class="user-controls" style="display: flex; align-items: center; gap: 20px;">
        <a href="profile.php" style="display: flex; align-items: center; gap: 12px; color: #1e3a5f; font-weight: 500; text-decoration: none;">
            <div style="background: white; width: 45px; height: 45px; border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                <i class="fa-solid fa-graduation-cap" style="font-size: 20px;"></i>
            </div>
            <span>Student</span>
        </a>
        
        <!-- Notification Bell Icon -->
        <div class="notification-container" style="position: relative;">
            <button 
                id="notificationBell" 
                type="button" 
                aria-label="Notifications"
                style="background: none; border: 0; padding: 0; cursor: pointer; color: #1e3a5f; font-size: 22px; position: relative;"
            >
                <i class="fa-solid fa-bell"></i>
            </button>
            <span 
                id="notificationBadge" 
                class="notification-badge"
                style="
                    position: absolute;
                    top: -5px;
                    right: -5px;
                    background-color: #ff4757;
                    color: white;
                    border-radius: 50%;
                    width: 20px;
                    height: 20px;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    font-size: 12px;
                    font-weight: bold;
                    display: none;
                "
            >0</span>
        </div>
        
        <form action="logout.php" method="POST" style="margin: 0;">
            <?php echo csrf_input(); ?>
            <button type="submit" aria-label="Logout" style="background: none; border: 0; padding: 0; cursor: pointer; color: #1e3a5f;">
                <i class="fa-solid fa-arrow-right-from-bracket" style="font-size: 22px;"></i>
            </button>
        </form>
    </div>
</header>
<!-- Global toast container -->
<div id="appToast" class="app-toast" aria-live="polite" aria-atomic="true"></div>

<script src="../assets/js/ui-notify.js"></script>
<script src="../assets/js/notifications.js"></script>