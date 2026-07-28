<!-- footer.php - Add this to all pages -->
<footer class="site-footer">
    <div class="footer-container">
        <div class="footer-section">
            <div class="footer-logo">
                <img src="assets/images/umpsa-logo.png" alt="UMPSA Logo" style="height: 40px;">
                <span>Lost & Found System</span>
            </div>
            <p>Helping UMPSA students and staff reunite with lost belongings.</p>
        </div>
        
        <div class="footer-section">
            <h4>Quick Links</h4>
            <ul>
                <li><a href="dashboard.php">Home</a></li>
                <li><a href="browse_items.php">Browse Items</a></li>
                <li><a href="my_reports.php">My Reports</a></li>
                <li><a href="my_messages.php">Messages</a></li>
                <li><a href="profile.php">Profile</a></li>
            </ul>
        </div>
        
        <div class="footer-section">
            <h4>Support</h4>
            <ul>
                <li><a href="contact.php">📞 Contact Admin</a></li>
                <li><a href="notifications.php">🔔 Notifications</a></li>
                <li><a href="forgot_password.php">🔑 Forgot Password</a></li>
            </ul>
        </div>
        
        <div class="footer-section">
            <h4>Contact Info</h4>
            <ul class="contact-info">
                <li>📧 <a href="mailto:admin@umpsa.edu.my">admin@umpsa.edu.my</a></li>
                <li>📱 09-1234567</li>
                <li>📍 UMPSA Pekan, Pahang</li>
            </ul>
        </div>
    </div>
    
    <div class="footer-bottom">
        <p>&copy; <?php echo date('Y'); ?> UMPSA Lost & Found Management System. All rights reserved.</p>
        <p>Need help? <a href="contact.php">Report an issue</a></p>
    </div>
</footer>

<style>
    .site-footer {
        background: #1a202c;
        color: #cbd5e0;
        margin-top: 60px;
        padding-top: 40px;
    }
    
    .footer-container {
        max-width: 1200px;
        margin: 0 auto;
        padding: 0 20px 30px;
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 30px;
        border-bottom: 1px solid #2d3748;
    }
    
    .footer-section h4 {
        color: white;
        font-size: 16px;
        margin-bottom: 15px;
        font-weight: 600;
    }
    
    .footer-section ul {
        list-style: none;
        padding: 0;
    }
    
    .footer-section ul li {
        margin-bottom: 8px;
    }
    
    .footer-section ul li a {
        color: #cbd5e0;
        text-decoration: none;
        transition: color 0.2s;
        font-size: 14px;
    }
    
    .footer-section ul li a:hover {
        color: #00a896;
    }
    
    .footer-logo {
        display: flex;
        align-items: center;
        gap: 10px;
        margin-bottom: 15px;
    }
    
    .footer-logo span {
        font-weight: bold;
        font-size: 18px;
        color: white;
    }
    
    .footer-section p {
        font-size: 14px;
        line-height: 1.6;
    }
    
    .contact-info li {
        font-size: 14px;
        margin-bottom: 8px;
    }
    
    .footer-bottom {
        max-width: 1200px;
        margin: 0 auto;
        padding: 20px;
        text-align: center;
        font-size: 13px;
        display: flex;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 10px;
    }
    
    .footer-bottom a {
        color: #00a896;
        text-decoration: none;
    }
    
    .footer-bottom a:hover {
        text-decoration: underline;
    }
    
    @media (max-width: 768px) {
        .footer-container {
            grid-template-columns: 1fr;
            text-align: center;
        }
        .footer-logo {
            justify-content: center;
        }
        .footer-bottom {
            flex-direction: column;
            text-align: center;
        }
    }
</style>