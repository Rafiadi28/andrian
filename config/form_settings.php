<?php
/**
 * FORM SETTINGS GLOBAL CONFIGURATION
 * ===================================
 * Central configuration untuk semua form requirement settings
 * Hindari duplikasi logic dan memudahkan maintenance
 */

// Konfigurasi Angsuran Bank Wonosobo
$FORM_CONFIG = [
    // Apakah angsuran wajib atau opsional?
    'angsuran_required' => false,  // Set ke true jika ingin wajib
    
    // Minimal berapa angsuran jika required?
    'angsuran_min_count' => 1,
    
    // Pesan error jika angsuran tidak terpenuhi
    'angsuran_error_msg' => 'Harap tambahkan minimal 1 data angsuran bank',
    
    // Pesan helper untuk form
    'angsuran_helper_optional' => 'Angsuran bank Wonosobo bersifat opsional. Jika ada, informasi ini akan membantu analisa.',
    'angsuran_helper_required' => 'Angsuran bank Wonosobo wajib ditambahkan minimal 1 data.',
];

/**
 * Helper function: Cek apakah angsuran dibutuhkan
 */
function isAngsuranRequired() {
    global $FORM_CONFIG;
    return isset($FORM_CONFIG['angsuran_required']) ? $FORM_CONFIG['angsuran_required'] : false;
}

/**
 * Helper function: Dapatkan helper text untuk angsuran
 */
function getAngsuranHelperText() {
    global $FORM_CONFIG;
    if (isAngsuranRequired()) {
        return $FORM_CONFIG['angsuran_helper_required'] ?? '';
    } else {
        return $FORM_CONFIG['angsuran_helper_optional'] ?? '';
    }
}

/**
 * Helper function: Dapatkan error message angsuran
 */
function getAngsuranErrorMessage() {
    global $FORM_CONFIG;
    return $FORM_CONFIG['angsuran_error_msg'] ?? 'Data angsuran tidak valid';
}
