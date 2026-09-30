<?php
$currentPage = basename($_SERVER['PHP_SELF']);
$admin = getCurrentAdmin();
?>

<!-- Mobile Top Header Bar (visible on mobile screens <= 768px) -->
<div class="mobile-nav-bar md:hidden flex items-center justify-between bg-amber-950 text-white px-4 py-3 shadow-md border-b border-amber-900 sticky top-0 z-40 w-full">
    <div class="flex items-center gap-3">
        <button id="mobileMenuBtn" onclick="openMobileSidebar()" class="p-2 text-amber-200 hover:text-white rounded-lg hover:bg-amber-900/60 focus:outline-none transition-colors">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
            </svg>
        </button>
        <div class="flex items-center gap-2">
            <img src="<?php echo htmlspecialchars(getLogoUrl() ?: LOGO_PATH); ?>" alt="Logo" class="w-7 h-7 rounded-full object-cover border border-amber-500/50">
            <span class="font-extrabold text-sm tracking-wide text-amber-100">Kaldis Admin</span>
        </div>
    </div>
    <div class="flex items-center gap-2">
        <a href="dashboard.php" class="text-amber-200 hover:text-white text-xs font-semibold px-2.5 py-1.5 bg-amber-900/60 hover:bg-amber-800 rounded-lg transition-colors flex items-center gap-1.5">
            <i class="fas fa-home text-xs"></i>
            <span>Home</span>
        </a>
    </div>
</div>

<!-- Mobile Sidebar Backdrop -->
<div id="sidebarBackdrop" class="fixed inset-0 bg-black/60 backdrop-blur-xs z-40 hidden transition-opacity md:hidden" onclick="closeMobileSidebar()"></div>

<div class="sidebar-wrapper">
    <!-- Sidebar -->
    <aside class="sidebar" id="sidebar">
        <!-- Toggle Button -->
        <button class="sidebar-toggle" id="sidebarToggle">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M15 18L9 12L15 6" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
        </button>

        <!-- Header Section with Logo -->
        <div class="sidebar-header">
            <div class="logo-container">
                <div class="logo-icon">
                    <img src="<?php echo htmlspecialchars(getLogoUrl() ?: LOGO_PATH); ?>" alt="Kaldis Coffee Logo" class="logo-image">
                </div>
                <div class="logo-text">
                    <h1>Kaldis Coffee</h1>
                    <span>ECA Branch Admin</span>
                </div>
            </div>
            
            <!-- Mobile Close Button -->
            <button class="sidebar-close" id="sidebarClose">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>

        <!-- Navigation -->
        <nav class="sidebar-nav">
            <!-- Main Menu Group -->
            <div class="nav-group">
                <div class="nav-group-header">
                    <span class="nav-group-title">Main Menu</span>
                </div>
                
                <div class="nav-items">
                    <a href="dashboard.php" class="nav-item <?php echo $currentPage == 'dashboard.php' ? 'active' : ''; ?>" data-tooltip="Dashboard">
                        <span class="nav-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                <rect x="3" y="3" width="7" height="7" rx="1"/>
                                <rect x="14" y="3" width="7" height="7" rx="1"/>
                                <rect x="3" y="14" width="7" height="7" rx="1"/>
                                <rect x="14" y="14" width="7" height="7" rx="1"/>
                            </svg>
                        </span>
                        <span class="nav-label">Dashboard</span>
                        <?php if($currentPage == 'dashboard.php'): ?>
                            <span class="nav-indicator"></span>
                        <?php endif; ?>
                    </a>
                    
                    <?php if (function_exists('hasPermission') ? hasPermission('orders') : true): ?>
                    <a href="orders.php" class="nav-item <?php echo $currentPage == 'orders.php' ? 'active' : ''; ?>" data-tooltip="Orders">
                        <span class="nav-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                <path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/>
                                <path d="M3 6h18"/>
                                <path d="M16 10a4 4 0 0 1-8 0"/>
                            </svg>
                        </span>
                        <span class="nav-label">Orders</span>
                        <?php
                        try {
                            $stmt = db()->prepare("SELECT COUNT(*) FROM pre_orders WHERE status = 'Pending'");
                            $stmt->execute();
                            $pendingCount = $stmt->fetchColumn();
                            if ($pendingCount > 0): ?>
                                <span class="nav-badge"><?php echo $pendingCount; ?></span>
                        <?php endif;
                        } catch (Exception $e) {}
                        ?>
                        <?php if($currentPage == 'orders.php'): ?>
                            <span class="nav-indicator"></span>
                        <?php endif; ?>
                    </a>
                    <?php endif; ?>
                    
                    <?php if (function_exists('hasPermission') ? hasPermission('products') : true): ?>
                    <a href="products.php" class="nav-item <?php echo $currentPage == 'products.php' ? 'active' : ''; ?>" data-tooltip="Products">
                        <span class="nav-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                <path d="M20 7L4 7" stroke="currentColor" stroke-linecap="round"/>
                                <path d="M12 3L12 11" stroke="currentColor" stroke-linecap="round"/>
                                <rect x="3" y="7" width="18" height="14" rx="1"/>
                                <path d="M8 12L16 12" stroke="currentColor" stroke-linecap="round"/>
                            </svg>
                        </span>
                        <span class="nav-label">Products</span>
                        <?php if($currentPage == 'products.php'): ?>
                            <span class="nav-indicator"></span>
                        <?php endif; ?>
                    </a>
                    <?php endif; ?>
                    
                    <?php if (function_exists('hasPermission') ? hasPermission('customers') : true): ?>
                    <a href="customers.php" class="nav-item <?php echo $currentPage == 'customers.php' ? 'active' : ''; ?>" data-tooltip="Customers">
                        <span class="nav-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                                <circle cx="12" cy="7" r="4"/>
                            </svg>
                        </span>
                        <span class="nav-label">Customers</span>
                        <?php if($currentPage == 'customers.php'): ?>
                            <span class="nav-indicator"></span>
                        <?php endif; ?>
                    </a>
                    <?php endif; ?>
                    
                    <?php if (function_exists('hasPermission') ? hasPermission('delivery_locations') : true): ?>
                    <a href="delivery_locations.php" class="nav-item <?php echo $currentPage == 'delivery_locations.php' ? 'active' : ''; ?>" data-tooltip="Delivery Locations">
                        <span class="nav-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/>
                                <circle cx="12" cy="10" r="3"/>
                            </svg>
                        </span>
                        <span class="nav-label">Delivery Locations</span>
                        <?php if($currentPage == 'delivery_locations.php'): ?>
                            <span class="nav-indicator"></span>
                        <?php endif; ?>
                    </a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Reports & Feedback Group -->
            <?php if ((function_exists('hasPermission') && (hasPermission('reports') || hasPermission('feedback'))) || isSuperAdmin()): ?>
            <div class="nav-group">
                <div class="nav-group-header">
                    <span class="nav-group-title">Reports & Feedback</span>
                </div>
                
                <div class="nav-items">
                    <?php if (function_exists('hasPermission') ? hasPermission('reports') : true): ?>
                    <a href="reports.php" class="nav-item <?php echo $currentPage == 'reports.php' ? 'active' : ''; ?>" data-tooltip="Sales Reports">
                        <span class="nav-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                <path d="M12 20V10M18 20V4M6 20v-4"/>
                            </svg>
                        </span>
                        <span class="nav-label">Sales Reports</span>
                        <?php if($currentPage == 'reports.php'): ?>
                            <span class="nav-indicator"></span>
                        <?php endif; ?>
                    </a>
                    <?php endif; ?>

                    <?php if (function_exists('hasPermission') ? hasPermission('feedback') : true): ?>
                    <a href="feedback.php" class="nav-item <?php echo $currentPage == 'feedback.php' ? 'active' : ''; ?>" data-tooltip="Customer Feedback">
                        <span class="nav-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
                            </svg>
                        </span>
                        <span class="nav-label">Customer Feedback</span>
                        <?php if($currentPage == 'feedback.php'): ?>
                            <span class="nav-indicator"></span>
                        <?php endif; ?>
                    </a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Administration Group -->
            <?php if ((function_exists('hasPermission') && (hasPermission('users') || hasPermission('settings'))) || isSuperAdmin()): ?>
            <div class="nav-group">
                <div class="nav-group-header">
                    <span class="nav-group-title">Administration</span>
                </div>
                
                <div class="nav-items">
                    <?php if (function_exists('hasPermission') ? hasPermission('users') : isSuperAdmin()): ?>
                    <a href="users.php" class="nav-item <?php echo $currentPage == 'users.php' ? 'active' : ''; ?>" data-tooltip="Admin Users">
                        <span class="nav-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                                <circle cx="12" cy="7" r="4"/>
                                <path d="M17 3.5a4 4 0 0 1 0 7"/>
                            </svg>
                        </span>
                        <span class="nav-label">Admin Users & Roles</span>
                        <?php if($currentPage == 'users.php'): ?>
                            <span class="nav-indicator"></span>
                        <?php endif; ?>
                    </a>
                    <?php endif; ?>
                    
                    <?php if (function_exists('hasPermission') ? hasPermission('settings') : isSuperAdmin()): ?>
                    <a href="settings.php" class="nav-item <?php echo $currentPage == 'settings.php' ? 'active' : ''; ?>" data-tooltip="System Settings">
                        <span class="nav-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                <path d="M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6z"/>
                                <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/>
                            </svg>
                        </span>
                        <span class="nav-label">System Settings</span>
                        <?php if($currentPage == 'settings.php'): ?>
                            <span class="nav-indicator"></span>
                        <?php endif; ?>
                    </a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </nav>

        <!-- User Profile Section -->
        <div class="sidebar-footer">
            <div class="user-card">
                <div class="user-avatar">
                    <?php if (!empty($admin['avatar'])): ?>
                        <img src="<?php echo htmlspecialchars($admin['avatar']); ?>" alt="User Avatar" class="user-avatar-image">
                    <?php else: ?>
                        <span class="avatar-initials">
                            <?php echo strtoupper(substr($admin['full_name'] ?? $admin['username'], 0, 2)); ?>
                        </span>
                    <?php endif; ?>
                </div>
                <div class="user-info">
                    <p class="user-name"><?php echo htmlspecialchars($admin['full_name'] ?? $admin['username']); ?></p>
                    <p class="user-role"><?php echo ucfirst($admin['role'] ?? 'Admin'); ?></p>
                </div>
                <button class="user-menu-btn" id="userMenuBtn">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="1"/>
                        <circle cx="19" cy="12" r="1"/>
                        <circle cx="5" cy="12" r="1"/>
                    </svg>
                </button>
            </div>
            
            <!-- User Action Menu -->
            <div class="user-action-menu" id="userActionMenu">
                <a href="profile.php" class="action-item">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                        <circle cx="12" cy="7" r="4"/>
                    </svg>
                    <span>Profile Settings</span>
                </a>
                <div class="action-divider"></div>
                <a href="logout.php" class="action-item logout">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                        <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
                        <polyline points="16 17 21 12 16 7"/>
                        <line x1="21" y1="12" x2="9" y2="12"/>
                    </svg>
                    <span>Logout</span>
                </a>
            </div>
        </div>
    </aside>
</div>

<style>
/* Modern Sidebar CSS with Toggle Functionality - Compatible with Dashboard */
:root {
    --sidebar-width-expanded: 280px;
    --sidebar-width-collapsed: 80px;
    --sidebar-bg: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
    --sidebar-hover: rgba(16, 185, 129, 0.1);
    --sidebar-active: linear-gradient(135deg, rgba(16, 185, 129, 0.15) 0%, rgba(5, 150, 105, 0.15) 100%);
    --sidebar-border: rgba(255, 255, 255, 0.05);
    --text-primary: #f1f5f9;
    --text-secondary: #94a3b8;
    --accent-color: #10b981;
    --accent-glow: 0 0 20px rgba(16, 185, 129, 0.3);
    --transition-speed: 0.3s;
}

/* Sidebar Wrapper */
.sidebar-wrapper {
    position: relative;
    z-index: 50;
}

.sidebar {
    position: fixed;
    top: 0;
    left: 0;
    height: 100vh;
    width: var(--sidebar-width-expanded);
    background: var(--sidebar-bg);
    backdrop-filter: blur(10px);
    display: flex;
    flex-direction: column;
    overflow: visible;
    box-shadow: 4px 0 20px rgba(0, 0, 0, 0.3);
    border-right: 1px solid var(--sidebar-border);
    transition: width var(--transition-speed) cubic-bezier(0.4, 0, 0.2, 1);
    z-index: 1000;
}

/* Collapsed State */
.sidebar.collapsed {
    width: var(--sidebar-width-collapsed);
}

.sidebar.collapsed .logo-text,
.sidebar.collapsed .nav-label,
.sidebar.collapsed .nav-group-header,
.sidebar.collapsed .nav-badge,
.sidebar.collapsed .user-info,
.sidebar.collapsed .nav-indicator {
    display: none;
}

.sidebar.collapsed .nav-item {
    justify-content: center;
    padding: 10px;
}

.sidebar.collapsed .nav-icon {
    margin: 0;
}

.sidebar.collapsed .user-card {
    justify-content: center;
    padding: 8px;
}

.sidebar.collapsed .user-avatar {
    margin: 0;
}

.sidebar.collapsed .sidebar-toggle svg {
    transform: rotate(180deg);
}

/* Toggle Button */
.sidebar-toggle {
    position: absolute;
    top: 20px;
    right: -12px;
    width: 24px;
    height: 24px;
    background: var(--accent-color);
    border: none;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    z-index: 1001;
    transition: all 0.3s ease;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
}

.sidebar-toggle:hover {
    transform: scale(1.1);
    box-shadow: 0 4px 12px rgba(16, 185, 129, 0.4);
}

.sidebar-toggle svg {
    width: 14px;
    height: 14px;
    color: white;
    transition: transform 0.3s ease;
}

/* Header Styles */
.sidebar-header {
    padding: 24px 20px;
    border-bottom: 1px solid var(--sidebar-border);
    position: relative;
    transition: padding var(--transition-speed) ease;
}

.sidebar.collapsed .sidebar-header {
    padding: 24px 20px;
}

.logo-container {
    display: flex;
    align-items: center;
    gap: 12px;
    transition: gap var(--transition-speed) ease;
}

.sidebar.collapsed .logo-container {
    justify-content: center;
    gap: 0;
}

.logo-icon {
    width: 48px;
    height: 48px;
    border-radius: 50%;
    background: linear-gradient(135deg, #10b981, #059669);
    display: flex;
    align-items: center;
    justify-content: center;
    position: relative;
    flex-shrink: 0;
    animation: pulseGlow 2s infinite;
    overflow: hidden;
}

.logo-icon::before {
    content: '';
    position: absolute;
    inset: -2px;
    background: linear-gradient(135deg, #10b981, #059669);
    border-radius: 50%;
    opacity: 0.5;
    filter: blur(8px);
    z-index: -1;
}

.logo-image {
    width: 70%;
    height: 70%;
    object-fit: contain;
    border-radius: 50%;
}

.logo-text h1 {
    font-size: 1.25rem;
    font-weight: 700;
    background: linear-gradient(135deg, #fff, #94a3b8);
    -webkit-background-clip: text;
    background-clip: text;
    color: transparent;
    margin: 0;
    line-height: 1.2;
    white-space: nowrap;
}

.logo-text span {
    font-size: 0.75rem;
    color: var(--text-secondary);
    letter-spacing: 1px;
    white-space: nowrap;
}

/* Navigation Styles */
.sidebar-nav {
    flex: 1;
    overflow-y: auto;
    overflow-x: hidden;
    padding: 24px 16px;
    scrollbar-width: thin;
    scrollbar-color: var(--accent-color) transparent;
}

.sidebar-nav::-webkit-scrollbar {
    width: 4px;
}

.sidebar-nav::-webkit-scrollbar-track {
    background: transparent;
}

.sidebar-nav::-webkit-scrollbar-thumb {
    background: var(--accent-color);
    border-radius: 10px;
}

.nav-group {
    margin-bottom: 32px;
    transition: margin var(--transition-speed) ease;
}

.nav-group-header {
    padding: 0 12px;
    margin-bottom: 12px;
    overflow: hidden;
    transition: all var(--transition-speed) ease;
}

.nav-group-title {
    font-size: 0.7rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 1.5px;
    color: var(--text-secondary);
    position: relative;
    display: inline-block;
    white-space: nowrap;
}

.nav-group-title::after {
    content: '';
    position: absolute;
    bottom: -4px;
    left: 0;
    width: 20px;
    height: 2px;
    background: var(--accent-color);
    border-radius: 2px;
}

.nav-items {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.nav-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 10px 12px;
    border-radius: 12px;
    color: var(--text-secondary);
    text-decoration: none;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    position: relative;
    overflow: hidden;
    white-space: nowrap;
}

.nav-item::before {
    content: '';
    position: absolute;
    left: 0;
    top: 0;
    width: 0;
    height: 100%;
    background: var(--sidebar-hover);
    transition: width 0.3s ease;
    z-index: -1;
}

.nav-item:hover::before,
.nav-item.active::before {
    width: 100%;
}

.nav-item:hover {
    color: var(--text-primary);
    transform: translateX(4px);
}

.sidebar.collapsed .nav-item:hover {
    transform: translateX(0);
}

.nav-item.active {
    color: var(--accent-color);
    background: var(--sidebar-active);
    box-shadow: var(--accent-glow);
}

.nav-icon {
    width: 24px;
    height: 24px;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: transform 0.2s ease;
    flex-shrink: 0;
}

.nav-icon svg {
    width: 20px;
    height: 20px;
    stroke: currentColor;
}

.nav-item:hover .nav-icon {
    transform: scale(1.1);
}

.nav-label {
    flex: 1;
    font-size: 0.875rem;
    font-weight: 500;
}

.nav-badge {
    background: linear-gradient(135deg, #ef4444, #dc2626);
    color: white;
    font-size: 0.7rem;
    font-weight: 600;
    padding: 2px 6px;
    border-radius: 20px;
    min-width: 24px;
    text-align: center;
    animation: pulseGlow 1s infinite;
}

.nav-indicator {
    width: 3px;
    height: 20px;
    background: var(--accent-color);
    border-radius: 3px;
    animation: slideIn 0.3s ease;
}

/* Tooltip for collapsed state */
.nav-item[data-tooltip] {
    position: relative;
}

.sidebar.collapsed .nav-item[data-tooltip]:hover::after {
    content: attr(data-tooltip);
    position: absolute;
    left: 100%;
    top: 50%;
    transform: translateY(-50%);
    background: #1e293b;
    color: var(--text-primary);
    padding: 6px 12px;
    border-radius: 8px;
    font-size: 0.75rem;
    white-space: nowrap;
    margin-left: 12px;
    z-index: 100;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
    border: 1px solid var(--sidebar-border);
    pointer-events: none;
    animation: fadeIn 0.2s ease;
}

/* Footer Styles */
.sidebar-footer {
    padding: 20px;
    border-top: 1px solid var(--sidebar-border);
    position: relative;
    transition: padding var(--transition-speed) ease;
}

.user-card {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 8px;
    border-radius: 12px;
    background: rgba(255, 255, 255, 0.03);
    cursor: pointer;
    transition: all 0.2s ease;
    position: relative;
}

.user-card:hover {
    background: rgba(255, 255, 255, 0.05);
}

.user-avatar {
    width: 40px;
    height: 40px;
    background: linear-gradient(135deg, #10b981, #059669);
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    position: relative;
    flex-shrink: 0;
    overflow: hidden;
}

.user-avatar-image {
    width: 100%;
    height: 100%;
    object-fit: cover;
    border-radius: 12px;
}

.avatar-initials {
    color: white;
    font-weight: 600;
    font-size: 0.875rem;
}

.user-info {
    flex: 1;
    overflow: hidden;
}

.user-name {
    font-size: 0.875rem;
    font-weight: 500;
    color: var(--text-primary);
    margin: 0;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.user-role {
    font-size: 0.7rem;
    color: var(--text-secondary);
    margin: 0;
    white-space: nowrap;
}

.user-menu-btn {
    background: none;
    border: none;
    color: var(--text-secondary);
    cursor: pointer;
    padding: 4px;
    border-radius: 6px;
    transition: all 0.2s;
    flex-shrink: 0;
}

.user-menu-btn svg {
    width: 16px;
    height: 16px;
}

.user-menu-btn:hover {
    background: rgba(255, 255, 255, 0.1);
    color: var(--text-primary);
}

.user-action-menu {
    position: absolute;
    bottom: 80px;
    left: 16px;
    right: 16px;
    background: #1e293b;
    border-radius: 12px;
    padding: 8px;
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.3);
    border: 1px solid var(--sidebar-border);
    transform: translateY(20px);
    opacity: 0;
    visibility: hidden;
    transition: all 0.3s ease;
    z-index: 100;
}

.user-action-menu.show {
    transform: translateY(0);
    opacity: 1;
    visibility: visible;
}

.action-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 8px 12px;
    border-radius: 8px;
    color: var(--text-secondary);
    text-decoration: none;
    transition: all 0.2s;
}

.action-item svg {
    width: 16px;
    height: 16px;
}

.action-item:hover {
    background: var(--sidebar-hover);
    color: var(--accent-color);
}

.action-item.logout:hover {
    background: rgba(239, 68, 68, 0.1);
    color: #ef4444;
}

.action-divider {
    height: 1px;
    background: var(--sidebar-border);
    margin: 8px 0;
}

/* Mobile Responsive */
@media (max-width: 768px) {
    .sidebar {
        transform: translateX(-100%);
        transition: transform 0.3s ease;
        width: var(--sidebar-width-expanded) !important;
    }
    
    .sidebar.mobile-open {
        transform: translateX(0);
    }
    
    .sidebar-close {
        display: flex;
        position: absolute;
        top: 20px;
        right: 20px;
        background: none;
        border: none;
        color: var(--text-secondary);
        cursor: pointer;
        padding: 4px;
    }
    
    .sidebar-close svg {
        width: 20px;
        height: 20px;
    }

    .sidebar-toggle {
        display: none;
    }
}

/* Desktop Styles */
@media (min-width: 769px) {
    .sidebar-close {
        display: none;
    }
}

/* Animations */
@keyframes pulseGlow {
    0%, 100% {
        box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.4);
    }
    50% {
        box-shadow: 0 0 0 8px rgba(16, 185, 129, 0);
    }
}

@keyframes slideIn {
    from {
        transform: scaleY(0);
        opacity: 0;
    }
    to {
        transform: scaleY(1);
        opacity: 1;
    }
}

@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateX(-10px);
    }
    to {
        opacity: 1;
        transform: translateX(0);
    }
}

/* Main Content Adjustment - Compatible with Dashboard */
.main-content {
    margin-left: var(--sidebar-width-expanded);
    transition: margin-left var(--transition-speed) cubic-bezier(0.4, 0, 0.2, 1);
    min-height: 100vh;
}

.sidebar.collapsed ~ .main-content,
.sidebar.collapsed + .main-content {
    margin-left: var(--sidebar-width-collapsed);
}

/* For pages without main-content wrapper, adjust body */
body:has(.sidebar.collapsed) .flex-1 {
    margin-left: 0;
}

/* Responsive content */
@media (max-width: 768px) {
    .main-content,
    .sidebar.collapsed ~ .main-content,
    .sidebar.collapsed + .main-content {
        margin-left: 0;
    }
}
</style>

<script>
// Sidebar Toggle Functionality
document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.getElementById('sidebar');
    const toggleBtn = document.getElementById('sidebarToggle');
    const userMenuBtn = document.getElementById('userMenuBtn');
    const userActionMenu = document.getElementById('userActionMenu');
    
    // Load saved state from localStorage
    const isCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
    if (isCollapsed && sidebar) {
        sidebar.classList.add('collapsed');
        // Adjust main content if it exists
        const mainContent = document.querySelector('.main-content, .flex-1');
        if (mainContent) {
            mainContent.style.marginLeft = '80px';
        }
    }
    
    // Toggle sidebar
    if (toggleBtn && sidebar) {
        toggleBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            sidebar.classList.toggle('collapsed');
            
            // Find main content (either .main-content or .flex-1 from dashboard)
            const mainContent = document.querySelector('.main-content, .flex-1');
            if (mainContent) {
                if (sidebar.classList.contains('collapsed')) {
                    mainContent.style.marginLeft = '80px';
                } else {
                    mainContent.style.marginLeft = '280px';
                }
            }
            
            // Save state to localStorage
            const collapsed = sidebar.classList.contains('collapsed');
            localStorage.setItem('sidebarCollapsed', collapsed);
            
            // Trigger resize event for any charts or components
            setTimeout(() => {
                window.dispatchEvent(new Event('resize'));
            }, 300);
        });
    }
    
    // User menu toggle
    if (userMenuBtn && userActionMenu) {
        userMenuBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            userActionMenu.classList.toggle('show');
        });
        
        document.addEventListener('click', function(e) {
            if (userMenuBtn && userActionMenu && 
                !userMenuBtn.contains(e.target) && 
                !userActionMenu.contains(e.target)) {
                userActionMenu.classList.remove('show');
            }
        });
    }
    
    // Mobile sidebar toggle
    const sidebarClose = document.getElementById('sidebarClose');
    
    if (sidebarClose && sidebar) {
        sidebarClose.addEventListener('click', function() {
            sidebar.classList.remove('mobile-open');
        });
    }
    
    // Function to open sidebar on mobile
    window.openMobileSidebar = function() {
        const sb = document.getElementById('sidebar');
        const bd = document.getElementById('sidebarBackdrop');
        if (sb) sb.classList.add('mobile-open');
        if (bd) bd.classList.remove('hidden');
    };
    
    // Function to close sidebar on mobile
    window.closeMobileSidebar = function() {
        const sb = document.getElementById('sidebar');
        const bd = document.getElementById('sidebarBackdrop');
        if (sb) sb.classList.remove('mobile-open');
        if (bd) bd.classList.add('hidden');
    };
    
    // Close sidebar on mobile when clicking a link
    const navLinks = document.querySelectorAll('.nav-item');
    navLinks.forEach(link => {
        link.addEventListener('click', function() {
            if (window.innerWidth <= 768) {
                closeMobileSidebar();
            }
        });
    });
});

// Handle window resize
window.addEventListener('resize', function() {
    const sidebar = document.getElementById('sidebar');
    const mainContent = document.querySelector('.main-content, .flex-1');
    const bd = document.getElementById('sidebarBackdrop');
    
    if (window.innerWidth <= 768) {
        if (mainContent) mainContent.style.marginLeft = '0';
    } else {
        if (bd) bd.classList.add('hidden');
        if (sidebar) sidebar.classList.remove('mobile-open');
        if (mainContent && sidebar) {
            const isCollapsed = sidebar.classList.contains('collapsed');
            mainContent.style.marginLeft = isCollapsed ? '80px' : '280px';
        }
    }
});
</script>

<!-- Global Mobile Responsiveness Enhancements -->
<style>
/* Dashboard & Main Layout spacing */
.flex.h-screen > .flex-1 {
    transition: margin-left 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    margin-left: 280px;
}

.sidebar.collapsed + .flex-1,
.sidebar.collapsed ~ .flex-1 {
    margin-left: 80px;
}

/* Mobile Responsiveness Rules (<= 768px) */
@media (max-width: 768px) {
    .flex.h-screen {
        flex-direction: column !important;
        height: auto !important;
        min-height: 100vh !important;
    }
    .flex.h-screen > .flex-1 {
        margin-left: 0 !important;
        width: 100% !important;
    }
    .sidebar {
        position: fixed !important;
        top: 0 !important;
        left: 0 !important;
        bottom: 0 !important;
        z-index: 50 !important;
        transform: translateX(-100%) !important;
        transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1) !important;
        width: 280px !important;
        box-shadow: 0 20px 25px -5px rgba(0,0,0,0.3) !important;
    }
    .sidebar.mobile-open {
        transform: translateX(0) !important;
    }
    /* Auto table scrolling on mobile */
    .table-container, .overflow-x-auto, div:has(> table) {
        overflow-x: auto !important;
        -webkit-overflow-scrolling: touch !important;
        max-width: 100vw !important;
    }
    table {
        min-width: 600px;
    }
    /* Modal responsiveness */
    .modal-content, [role="dialog"], div[class*="rounded"]:has(table), div[class*="bg-white"]:has(form) {
        max-width: 95vw !important;
    }
}

.nav-item, .user-card, .sidebar-toggle {
    will-change: transform;
    user-select: none;
}
</style>