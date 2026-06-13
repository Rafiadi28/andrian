                <!-- TAB 1: PEMOHON -->
                <div id="tab-pemohon" class="tab-content active">
                    <h3 class="tab-title">1. Data Pemohon</h3>

                    <h4
                        style="margin-top:1.5rem; color:var(--primary-color); border-bottom:1px solid #ddd; padding-bottom:5px;">
                        A. Data Debitur</h4>
                    <div class="grid-2">
                        <div class="custom-form-group"><label>Nama Lengkap <span
                                    style="color:red">*</span></label><input type="text" name="nama_debitur" required
                                style="text-transform:uppercase;"></div>
                        <div class="custom-form-group"><label>Tempat Lahir</label><input type="text" name="tempat_lahir"
                                style="text-transform:uppercase;"></div>
                    </div>
                    <div class="grid-2">
                        <div class="custom-form-group"><label>Tanggal Lahir</label><input type="date"
                                name="tanggal_lahir"></div>
                        <div class="custom-form-group"><label>Status Perkawinan</label>
                            <select name="status_perkawinan" onchange="togglePasangan(this.value)">
                                <option value="lajang">Lajang</option>
                                <option value="menikah">Menikah</option>
                                <option value="janda">Janda</option>
                                <option value="duda">Duda</option>
                            </select>
                        </div>
                    </div>
                    <div class="grid-2">
                        <div class="custom-form-group"><label>Pekerjaan</label><input type="text" name="pekerjaan"
                                style="text-transform:uppercase;"></div>
                        <div class="custom-form-group"><label>Alamat Pekerjaan Debitur</label><textarea
                                name="alamat_pekerjaan" rows="2" style="text-transform:uppercase;"></textarea></div>
                    </div>

                    <div id="section-pasangan"
                        style="display:none; margin-top:1rem; padding:1.5rem; background:#f8fafc; border-radius:8px; border:1px solid #e2e8f0;">
                        <h4
                            style="color:var(--primary-color); border-bottom:1px solid #cbd5e1; padding-bottom:5px; margin-top:0;">
                            B. Data Pasangan</h4>
                        <div class="grid-2" style="margin-top:1rem;">
                            <div class="custom-form-group"><label>Nama Suami/Istri</label><input type="text"
                                    name="nama_pasangan" style="text-transform:uppercase;"></div>
                            <div class="custom-form-group"><label>Tempat Lahir Pasangan</label><input type="text"
                                    name="tempat_lahir_pasangan" style="text-transform:uppercase;"></div>
                        </div>
                        <div class="grid-2">
                            <div class="custom-form-group"><label>Tanggal Lahir Pasangan</label><input type="date"
                                    name="tanggal_lahir_pasangan"></div>
                            <div class="custom-form-group"><label>Pekerjaan Pasangan</label><input type="text"
                                    name="pekerjaan_pasangan" style="text-transform:uppercase;"></div>
                        </div>
                        <div class="custom-form-group"><label>Alamat Pekerjaan Pasangan</label><textarea
                                name="alamat_pekerjaan_pasangan" rows="2" style="text-transform:uppercase;"></textarea>
                        </div>
                    </div>

                    <h4
                        style="margin-top:2rem; color:var(--primary-color); border-bottom:1px solid #ddd; padding-bottom:5px;">
                        C. Data Identitas & Domisili</h4>
                    <div class="grid-2">
                        <div class="custom-form-group"><label>Nomor KTP <span style="color:red">*</span></label><input
                                type="text" name="nik" required maxlength="16" minlength="16" pattern="\d{16}"
                                title="Harus 16 digit angka"></div>
                        <div class="custom-form-group"><label>Nomor HP <span style="color:red">*</span></label><input
                                type="text" name="no_hp" required maxlength="15" minlength="10" pattern="\d{10,15}"
                                title="Harus 10-15 digit angka"></div>
                    </div>
                    <div class="custom-form-group"><label>Alamat Sesuai KTP <span
                                style="color:red">*</span></label><textarea name="alamat_ktp" rows="2" required
                            style="text-transform:uppercase;"></textarea></div>
                    <div class="grid-2">
                        <div class="custom-form-group"><label>Dukuh</label><input type="text" name="dukuh"
                                style="text-transform:uppercase;"></div>
                        <div class="custom-form-group"><label>Desa</label><input type="text" name="desa"
                                style="text-transform:uppercase;"></div>
                    </div>
                    <div class="grid-2">
                        <div class="custom-form-group"><label>Kecamatan</label><input type="text" name="kecamatan"
                                style="text-transform:uppercase;"></div>
                        <div class="custom-form-group"><label>Kota/Kabupaten</label><input type="text"
                                name="kota_kabupaten" style="text-transform:uppercase;"></div>
                    </div>
                    <div class="custom-form-group"><label>Alamat Rumah (Domisili)</label><textarea
                            name="alamat_domisili" rows="2" style="text-transform:uppercase;"></textarea></div>
                    <div class="grid-2">
                        <div class="custom-form-group"><label>Jumlah Tanggungan (Orang)</label><input type="number"
                                name="jumlah_tanggungan" min="0" value="0"></div>
                        <div class="custom-form-group"><label>Nama Ibu Kandung</label><input type="text"
                                name="nama_ibu_kandung" style="text-transform:uppercase;"></div>
                    </div>

                    <h4
                        style="margin-top:2rem; color:var(--primary-color); border-bottom:1px solid #ddd; padding-bottom:5px;">
                        D. Data Pekerjaan / Instansi</h4>
                    <div class="grid-2">
                        <div class="custom-form-group"><label>Nama Instansi/Perusahaan</label><input type="text"
                                name="nama_instansi" style="text-transform:uppercase;"></div>
                        <div class="custom-form-group"><label>Nomor Telepon Kantor</label><input type="text"
                                name="telepon_kantor" pattern="[\d\-+]*"></div>
                    </div>
                    <div class="custom-form-group"><label>Alamat Instansi/Perusahaan</label><textarea
                            name="alamat_instansi" rows="2" style="text-transform:uppercase;"></textarea></div>
                    <div class="grid-2">
                        <div class="custom-form-group"><label>Departemen/Bagian</label><input type="text"
                                name="departemen_bagian" style="text-transform:uppercase;"></div>
                        <div class="custom-form-group"><label>Jabatan</label><input type="text" name="jabatan"
                                style="text-transform:uppercase;"></div>
                    </div>

                    <h4
                        style="margin-top:2rem; color:var(--primary-color); border-bottom:1px solid #ddd; padding-bottom:5px;">
                        E. Data Kredit</h4>
                    <div class="grid-2">
                        <div class="custom-form-group"><label>Pinjaman Ke-</label><input type="number"
                                name="pinjaman_ke" min="1" value="1"></div>
                        <div class="custom-form-group"><label>Upload Dokumen Pendukung (KTP/KK)</label><input
                                type="file" name="file_pendukung"></div>
                    </div>

                    <button type="button" id="btn-save-pemohon" class="btn-save-section"
                        onclick="saveSection('pemohon')" style="margin-top:2rem;">
                        <span class="spinner"></span>
                        <span class="btn-text">💾 Simpan Data Pemohon</span>
                    </button>
                    <div id="toast-pemohon" class="toast-msg"></div>
                </div>

                <script>
                    function togglePasangan(status) {
                        const section = document.getElementById('section-pasangan');
                        if (status === 'menikah') {
                            section.style.display = 'block';
                        } else {
                            section.style.display = 'none';
                            // clear values if not menikah
                            section.querySelectorAll('input, textarea').forEach(el => el.value = '');
                        }
                    }
                    document.addEventListener('DOMContentLoaded', () => {
                        let sel = document.querySelector('select[name="status_perkawinan"]');
                        if (sel) togglePasangan(sel.value);
                    });
                </script>
