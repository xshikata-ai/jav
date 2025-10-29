<?php
// File: admin/mass_import.php
require_once __DIR__ . '/../include/config.php';
require_once __DIR__ . '/../include/database.php';

$message = null;
$error = null;
$allowed_domains_input = $_POST['allowed_domains'] ?? '';
$selected_category_id = $_POST['mass_category_id'] ?? null;

// Ambil kategori untuk <select>
$allCategories = getCategories();

// Logika BARU: Tangani form submit untuk MENAMBAH KE ANTRIAN
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mass_data'])) {
    
    $massData = trim($_POST['mass_data']);
    $lines = explode("\n", $massData);
    $lines = array_filter($lines, 'trim'); // Hapus baris kosong

    if (empty($lines)) {
        $error = "Data video tidak boleh kosong.";
    } else {
        // Panggil fungsi database baru kita
        $count = addVideosToImportQueue($lines, $selected_category_id, $allowed_domains_input);
        
        if ($count > 0) {
            $message = "Berhasil! $count video telah ditambahkan ke antrian impor. Proses akan berjalan otomatis di latar belakang.";
            // Kosongkan form setelah berhasil
            $allowed_domains_input = '';
            $selected_category_id = null;
        } else {
            $error = "Gagal menambahkan video ke antrian. Periksa log error.";
        }
    }
}

require_once __DIR__ . '/templates/header.php';
?>

<h1 class="page-title">Mass Import & Clone Video</h1>
<p class="page-desc">Impor video sekaligus. Data akan dimasukkan ke antrian dan diproses oleh Cron Job di latar belakang.</p>

<?php if (isset($message)): ?><p class="message success"><?php echo htmlspecialchars($message); ?></p><?php endif; ?>
<?php if (isset($error)): ?><p class="message error"><?php echo htmlspecialchars($error); ?></p><?php endif; ?>

<form id="import-form" method="POST" action="">
    <div class="card">
        <div class="card-header">
            <h3>Konfigurasi Import</h3>
        </div>
        <div style="padding: 1.5rem;">
            <div class="form-group">
                <label for="mass_category_id">Pilih Kategori untuk Semua Video (Opsional)</label>
                <select name="mass_category_id" id="mass_category_id" class="form-select">
                    <option value="">-- Biarkan Sesuai Data di CSV --</option>
                    <?php foreach ($allCategories as $category): ?>
                        <option value="<?php echo htmlspecialchars($category['id']); ?>" <?php echo ($selected_category_id == $category['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($category['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <p class="form-hint">Jika kategori di sini dipilih, maka akan mengabaikan kolom kategori dari data CSV.</p>
            </div>
            
            <div class="form-group">
                <label for="allowed_domains">Domain yang Diizinkan untuk Cloning (Opsional)</label>
                <textarea name="allowed_domains" id="allowed_domains" class="form-textarea" rows="4" placeholder="Masukkan domain yang ingin di-clone, satu per baris. Contoh:&#10;ryderjet.com&#10;stbhg.click&#10;vidply.com&#10;trailerhg.xyz"><?php echo htmlspecialchars($allowed_domains_input); ?></textarea>
                <p class="form-hint">Masukkan semua domain yang bisa dikloning di sini, termasuk domain player dan trailer.</p>
            </div>
        </div>
    </div>

    <div class="card" style="margin-top: 2rem;">
        <div class="card-header">
            <h3>Input Data Video</h3>
        </div>
        <div class="form-group" style="padding: 1.5rem;">
            <label for="mass_data">Data Video (satu video per baris)</label>
            <p style="font-size: 0.9em; color: #666; margin-bottom: 10px;">
                <strong>Format:</strong><br>
                <code>judul|deskripsi|thumbnail|link embed player|link embed trailer|url gambar gallery|genres|kategori|release date|duration|actress|studio</code>
            </p>
            <textarea name="mass_data" id="mass_data" class="form-textarea" rows="15" placeholder="Tempel data video Anda di sini..."></textarea>
        </div>
        <div style="padding: 0 1.5rem 1.5rem 1.5rem;">
             <button type="submit" class="btn btn-primary"><i class="fas fa-tasks"></i> Tambahkan ke Antrian</button>
        </div>
    </div>
</form>

<?php
require_once __DIR__ . '/templates/footer.php';
?>