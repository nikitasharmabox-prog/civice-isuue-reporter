<footer class="footer">
    <div class="footer-container">
        <div class="footer-col">
            <h4><i class="fas fa-city"></i> Civic Issue Reporter</h4>
            <p>Empowering citizens to build better communities by reporting and tracking civic issues in real-time.</p>
        </div>
        <div class="footer-col">
            <h4>Quick Links</h4>
            <ul>
                <li><a href="<?= BASE_URL ?>/report.php">Report an Issue</a></li>
                <li><a href="<?= BASE_URL ?>/track.php">Track Complaint</a></li>
                <li><a href="<?= BASE_URL ?>/map.php">Live Issue Map</a></li>
            </ul>
        </div>
        <div class="footer-col">
            <h4>Issue Categories</h4>
            <ul>
                <li>Potholes & Road Damage</li>
                <li>Garbage & Waste</li>
                <li>Street Lights</li>
                <li>Water Leaks & Sewage</li>
            </ul>
        </div>
    </div>
    <div class="footer-bottom">
        <p>&copy; <?= date('Y') ?> <?= APP_NAME ?> &mdash; Built with PHP &amp; MySQL</p>
    </div>
</footer>
<script src="<?= BASE_URL ?>/assets/js/main.js"></script>
</body>
</html>
