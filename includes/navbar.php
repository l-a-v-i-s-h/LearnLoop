<nav class="sidebar">
    <div class="nav-item <?php echo ($current_page == 'dashboard') ? 'active' : ''; ?>">
        <a href="dashboard.php">
            <span class="icon-box"><i class="fa-solid fa-house"></i></span> Dashboard
        </a>
    </div>

    <div class="nav-item">
        <a href="mygroups.php">

    <div class="nav-item <?php echo ($current_page == 'groups') ? 'active' : ''; ?>">
        <a href="mygroups.php">

            <span class="icon-box"><i class="fa-solid fa-earth-americas"></i></span> My Groups
        </a>
    </div>
    <div class="nav-item <?php echo ($current_page == 'notes') ? 'active' : ''; ?>">
        <a href="notes.php">
            <span class="icon-box"><i class="fa-solid fa-file-lines"></i></span> Shared Notes
        </a>
    </div>
    <div class="nav-item <?php echo ($current_page == 'forums') ? 'active' : ''; ?>">
        <a href="forums.php">
            <span class="icon-box"><i class="fa-solid fa-comments"></i></span> Academic Forums
        </a>
    </div>
    <div class="nav-item <?php echo ($current_page == 'account') ? 'active' : ''; ?>">
        <a href="account.php">
            <span class="icon-box"><i class="fa-solid fa-user"></i></span> Account
        </a>
    </div>
</nav>