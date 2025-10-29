<?php
// File: templates/footer.php (V4 - RATA KIRI, MOBILE ORDER, TEXT LOGO)

// Ambil data yang mungkin diperlukan
$siteTitle = $siteTitle ?? getSetting('site_title') ?? 'Javpornsub';
// $siteLogoUrl tidak diperlukan lagi
?>
    </main> <footer class="site-footer-v4">
        <div class="container footer-content-v4">

            <div class="footer-section footer-disclaimer-v4">
                 <h4 class="footer-section-title-v4">
                     <i class="ph-fill ph-warning-circle"></i>
                     <span>Disclaimer</span>
                 </h4>
                 <p>Javpornsub links to third-party hosted Japanese Porn. We do not host JAV files. For DMCA issues regarding JAV Subtitle English content, contact the host directly.</p>
            </div>

            <div class="footer-section footer-about-v4">
                <h3 class="footer-site-title-v4"><?php echo htmlspecialchars($siteTitle); ?></h3>

                <p class="footer-seo-text-v4">
                    Watch JAV Porn Now! Javpornsub is your #1 source for JAV Subtitle English. Stream uncensored Japanese Porn with daily JAV Sub updates. Get your JAV Subbed fix instantly. Don't wait, Watch JAV Sub English here!
                </p>
            </div>

        </div>

        <div class="footer-bottom-v4">
            <div class="container">
               <p>&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($siteTitle); ?> <i class="ph-fill ph-heart" style="color:var(--primary-accent); vertical-align: middle;"></i> All Rights Reserved.</p>
            </div>
        </div>
    </footer>

</body>
</html>