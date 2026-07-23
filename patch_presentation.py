import os

file_path = r'd:\laragon\www\andrian\create_pptx_presentation.py'
with open(file_path, 'r', encoding='utf-8') as f:
    content = f.read()

# Replace agenda_items
old_agenda = '''    agenda_items = [
        ("1", "Alur Sistem Aplikasi Kredit", "Overview lengkap alur pengajuan hingga persetujuan", BLUE),
        ("2", "Cara Kerja Analis (Input)", "Proses input data kredit oleh analis", TEAL),
        ("3", "Cara Kerja Kasubag Analis", "Review & verifikasi awal oleh Kasubag", GREEN),
        ("4", "Cara Kerja Kabag Kredit", "Evaluasi risiko kredit oleh Kabag", ORANGE),
        ("5", "Cara Kerja Kadiv Bisnis", "Approval divisi & threshold keputusan", PURPLE),
        ("6", "Cara Kerja Direksi", "Persetujuan final untuk kredit besar", RED),
        ("7", "Cara Kerja Admin / Superadmin", "Manajemen sistem, user & parameter", GOLD),
    ]

    for i, (num, title, desc, color) in enumerate(agenda_items):
        y = Inches(1.5) + Inches(i * 0.78)'''

new_agenda = '''    agenda_items = [
        ("1", "Alur Sistem", "Overview lengkap alur pengajuan hingga persetujuan", BLUE),
        ("2", "Cara Kerja Analis", "Proses input data kredit oleh analis", TEAL),
        ("3", "Cara Kerja Kepatuhan", "Penilaian kepatuhan & kelengkapan dokumen", DARK_GREEN),
        ("4", "Cara Kerja Kasubag", "Review & verifikasi awal oleh Kasubag", GREEN),
        ("5", "Cara Kerja Kabag", "Evaluasi risiko kredit oleh Kabag", ORANGE),
        ("6", "Cara Kerja Kadiv", "Approval divisi & threshold keputusan", PURPLE),
        ("7", "Cara Kerja Direksi", "Persetujuan final untuk kredit besar", RED),
        ("8", "Cara Kerja Admin", "Manajemen sistem, user & parameter", GOLD),
    ]

    for i, (num, title, desc, color) in enumerate(agenda_items):
        y = Inches(1.4) + Inches(i * 0.7)'''

content = content.replace(old_agenda, new_agenda)

# Replace roles flow
old_roles = '''    roles_flow = [
        ("ANALIS\\n(Input Data)", BLUE),
        ("KASUBAG\\n(Review Awal)", TEAL),
        ("KABAG\\n(Evaluasi Risiko)", ORANGE),
        ("KADIV\\n(Approval Divisi)", PURPLE),
        ("DIREKSI\\n(Final ≥500Jt)", RED),
    ]

    box_w = Inches(2.0)
    box_h = Inches(1.0)
    start_x = Inches(0.6)
    y_pos = Inches(2.2)
    gap = Inches(0.55)'''

new_roles = '''    roles_flow = [
        ("ANALIS\\n(Input)", BLUE),
        ("KEPATUHAN\\n(Review)", DARK_GREEN),
        ("KASUBAG\\n(Review)", TEAL),
        ("KABAG\\n(Evaluasi)", ORANGE),
        ("KADIV\\n(Approval)", PURPLE),
        ("DIREKSI\\n(Final)", RED),
    ]

    box_w = Inches(1.6)
    box_h = Inches(1.0)
    start_x = Inches(0.5)
    y_pos = Inches(2.2)
    gap = Inches(0.4)'''

content = content.replace(old_roles, new_roles)

# Insert Kepatuhan slide
old_kasubag_start = '''    # ========================================
    # SLIDE 8: CARA KERJA KASUBAG
    # ========================================'''

kepatuhan_slide = '''    # ========================================
    # SLIDE 8: CARA KERJA KEPATUHAN
    # ========================================
    slide = prs.slides.add_slide(prs.slide_layouts[6])
    set_slide_bg(slide, NAVY)
    add_section_header(slide, "3", "CARA KERJA KEPATUHAN", "Singkronisasi & Penilaian Dokumen Kepatuhan", DARK_GREEN)

    # Workflow
    add_textbox(slide, Inches(0.8), Inches(2.0), Inches(11.0), Inches(0.4),
                "ALUR KERJA KEPATUHAN:", font_size=14, font_color=GOLD, bold=True)

    kepatuhan_steps = [
        ("1", "Terima Data Analis", "Sistem auto-populate form dari\\ndata input awal Analis", BLUE),
        ("2", "Review Kepatuhan", "Periksa dokumen, checklist SOP,\\ndan validasi syarat kredit", DARK_GREEN),
        ("3", "Singkronisasi Data", "Melengkapi fasilitas kredit,\\ncatatan, & kesimpulan final", TEAL),
        ("4", "Simpan Assessment", "Data tersimpan di database,\\nsiap direview oleh Komite", ORANGE),
    ]

    for i, (num, title, desc, color) in enumerate(kepatuhan_steps):
        x = Inches(0.8) + i * Inches(3.1)
        add_step_box(slide, x, Inches(2.5), Inches(2.9), Inches(1.2), num, title, desc, color)

        if i < len(kepatuhan_steps) - 1:
            add_arrow_right(slide, x + Inches(2.95), Inches(2.95), Inches(0.1), GOLD)

    # Focus areas
    add_textbox(slide, Inches(0.8), Inches(4.1), Inches(11.0), Inches(0.4),
                "FITUR UTAMA PENILAIAN KEPATUHAN:", font_size=14, font_color=GOLD, bold=True)

    focus_items_kepatuhan = [
        ("📝", "Auto-Populate Data", "Data dari Analis langsung muncul\\ndi form Kepatuhan (tidak perlu\\ninput ulang).", TEAL),
        ("🔄", "AJAX Submission", "Penyimpanan data real-time\\ntanpa loading ulang halaman.", DARK_GREEN),
        ("🔍", "Validasi Dokumen", "Memastikan semua syarat legal,\\nagunan, dan administrasi telah\\nsesuai SOP Bank.", ORANGE),
        ("📊", "Tersentralisasi", "Hasil assessment langsung\\nterhubung ke pengajuan kredit\\ndan bisa dilihat Kasubag/Kabag.", BLUE),
    ]

    for i, (icon, title, desc, color) in enumerate(focus_items_kepatuhan):
        x = Inches(0.8) + i * Inches(3.1)
        y = Inches(4.6)

        box = add_shape(slide, MSO_SHAPE.ROUNDED_RECTANGLE, x, y, Inches(2.9), Inches(2.3), fill_color=DARK_BLUE)
        box.line.color.rgb = color
        box.line.width = Pt(1.5)

        # Icon
        add_textbox(slide, x + Inches(0.2), y + Inches(0.15), Inches(0.5), Inches(0.4),
                    icon, font_size=22, font_color=WHITE, alignment=PP_ALIGN.CENTER)

        add_textbox(slide, x + Inches(0.2), y + Inches(0.55), Inches(2.5), Inches(0.3),
                    title, font_size=13, font_color=color, bold=True)

        add_textbox(slide, x + Inches(0.2), y + Inches(0.9), Inches(2.5), Inches(1.2),
                    desc, font_size=10, font_color=LIGHT_GRAY)

    # ========================================
    # SLIDE 9: CARA KERJA KASUBAG
    # ========================================'''

content = content.replace(old_kasubag_start, kepatuhan_slide)

# Update Section numbers
content = content.replace('add_section_header(slide, "3", "CARA KERJA KASUBAG ANALIS"', 'add_section_header(slide, "4", "CARA KERJA KASUBAG ANALIS"')
content = content.replace('add_section_header(slide, "4", "CARA KERJA KABAG KREDIT"', 'add_section_header(slide, "5", "CARA KERJA KABAG KREDIT"')
content = content.replace('add_section_header(slide, "5", "CARA KERJA KADIV BISNIS"', 'add_section_header(slide, "6", "CARA KERJA KADIV BISNIS"')
content = content.replace('add_section_header(slide, "6", "CARA KERJA DIREKSI"', 'add_section_header(slide, "7", "CARA KERJA DIREKSI"')
content = content.replace('add_section_header(slide, "7", "CARA KERJA ADMIN / SUPERADMIN"', 'add_section_header(slide, "8", "CARA KERJA ADMIN / SUPERADMIN"')

with open(file_path, 'w', encoding='utf-8') as f:
    f.write(content)

print("Python file updated successfully!")
