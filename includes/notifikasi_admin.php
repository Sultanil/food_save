<?php
// includes/notifikasi_admin.php - Helper untuk menampilkan notifikasi

function tampilkanNotifikasi() {
    if (!isset($_GET['msg'])) return;
    
    $notifikasi = [
        'deleted' => [
            'icon' => '✅',
            'pesan' => 'User berhasil dinonaktifkan (soft delete)',
            'warna' => 'yellow'
        ],
        'restored' => [
            'icon' => '✅',
            'pesan' => 'User berhasil diaktifkan kembali',
            'warna' => 'green'
        ],
        'approved' => [
            'icon' => '✅',
            'pesan' => 'Pendaftaran toko penjual berhasil disetujui!',
            'warna' => 'green'
        ],
        'rejected' => [
            'icon' => '❌',
            'pesan' => 'Pendaftaran toko penjual telah ditolak.',
            'warna' => 'red'
        ]
    ];
    
    $msg = $_GET['msg'];
    if (!isset($notifikasi[$msg])) return;
    
    $notif = $notifikasi[$msg];
    $warna = $notif['warna'];
    
    echo <<<HTML
    <div class="bg-{$warna}-50 text-{$warna}-700 p-3.5 rounded-xl mb-6 border border-{$warna}-200 shadow-sm text-sm">
        {$notif['icon']} {$notif['pesan']}
    </div>
    HTML;
}
?>