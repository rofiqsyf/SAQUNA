<!-- Policy Modal Overlay & Container -->
<div id="policy-modal-overlay" class="fixed inset-0 bg-black/40 backdrop-blur-sm z-[99] hidden opacity-0 transition-opacity duration-300" onclick="closePolicyModal()"></div>

<div id="policy-modal-container" class="fixed inset-0 z-[100] hidden items-center justify-center p-4 md:p-6 opacity-0 translate-y-8 transition-all duration-300 pointer-events-none">
    <div class="glass-panel w-full max-w-2xl max-h-[85vh] rounded-3xl shadow-2xl flex flex-col pointer-events-auto border border-white/20 dark:border-white/10 relative overflow-hidden bg-surface/90 dark:bg-inverse-surface/90">
        
        <!-- Decorative Header Background -->
        <div class="absolute top-0 left-0 w-full h-32 bg-gradient-to-b from-primary/10 to-transparent pointer-events-none"></div>

        <!-- Modal Header -->
        <div class="flex justify-between items-center p-6 border-b border-outline-variant/30 relative z-10">
            <div class="flex items-center gap-3">
                <div id="policy-modal-icon-bg" class="w-12 h-12 rounded-xl bg-primary/10 text-primary flex items-center justify-center">
                    <span id="policy-modal-icon" class="material-symbols-outlined text-[24px]">policy</span>
                </div>
                <div>
                    <h2 id="policy-modal-title" class="font-headline-md text-headline-md text-primary dark:text-primary-fixed m-0">Title</h2>
                    <p class="font-label-md text-label-md text-on-surface-variant m-0">SAQUNA Academic Portal</p>
                </div>
            </div>
            <button type="button" onclick="closePolicyModal()" class="w-10 h-10 rounded-full bg-surface-container-highest hover:bg-error-container text-on-surface-variant hover:text-error flex items-center justify-center transition-colors border-none cursor-pointer">
                <span class="material-symbols-outlined text-[20px]">close</span>
            </button>
        </div>

        <!-- Modal Content (Scrollable) -->
        <div id="policy-modal-content" class="p-6 overflow-y-auto font-body-md text-body-md text-on-surface-variant leading-relaxed space-y-4">
            <!-- Content gets injected here -->
        </div>
        
        <!-- Modal Footer -->
        <div class="p-4 border-t border-outline-variant/30 text-center bg-surface-container-lowest/50 backdrop-blur-md">
            <button type="button" onclick="closePolicyModal()" class="px-6 py-2 bg-primary text-on-primary rounded-xl font-label-md hover:bg-primary/90 transition-colors border-none cursor-pointer shadow-md">Mengerti</button>
        </div>
    </div>
</div>

<script>
    const policyData = {
        security: {
            title: "Security Policy",
            icon: "shield",
            content: `
                <h3 class="font-title-lg text-on-surface mb-2">Komitmen Keamanan Data</h3>
                <p>Universitas Sains Al-Qur'an (UNSIQ) berkomitmen penuh terhadap keamanan sistem akademik SAQUNA. Kami menerapkan enkripsi end-to-end dengan standar SSL/TLS industri untuk setiap transmisi data yang berlangsung di dalam portal ini.</p>
                <h3 class="font-title-lg text-on-surface mt-4 mb-2">Perlindungan Akses</h3>
                <p>Setiap akses masuk diawasi secara ketat. Kami menerapkan algoritma hashing tingkat lanjut untuk penyimpanan kata sandi. Pengguna wajib menjaga kerahasiaan kredensial dan tidak membagikannya kepada pihak manapun. Sistem juga dirancang untuk secara otomatis melakukan terminasi sesi (session timeout) setelah periode ketidakaktifan tertentu.</p>
            `
        },
        privacy: {
            title: "Privacy Center",
            icon: "privacy_tip",
            content: `
                <h3 class="font-title-lg text-on-surface mb-2">Pusat Privasi Informasi</h3>
                <p>Semua informasi pribadi mahasiswa, dosen, dan staf diperlakukan secara rahasia sesuai dengan peraturan perlindungan data yang berlaku di Indonesia.</p>
                <p>Data seperti rekam medis akademik, catatan pembayaran, hingga nomor kontak pribadi hanya dapat diakses oleh individu yang bersangkutan dan unit layanan akademik yang memiliki wewenang untuk keperluan pemrosesan pendidikan.</p>
                <h3 class="font-title-lg text-on-surface mt-4 mb-2">Pengumpulan dan Penggunaan Data</h3>
                <p>Kami hanya mengumpulkan data yang relevan dengan operasional akademik. Data tersebut tidak akan diperjualbelikan kepada pihak ketiga. Audit sistem dilakukan secara periodik untuk memastikan tidak ada pelanggaran privasi.</p>
            `
        },
        terms: {
            title: "Terms of Service",
            icon: "gavel",
            content: `
                <h3 class="font-title-lg text-on-surface mb-2">Ketentuan Layanan</h3>
                <p>Dengan mengakses platform SAQUNA, Anda setuju untuk terikat oleh ketentuan layanan Universitas Sains Al-Qur'an. Platform ini disediakan secara eksklusif untuk memfasilitasi kebutuhan akademik dan administratif bagi civitas academica UNSIQ.</p>
                <ul class="list-disc pl-5 mt-2 space-y-1">
                    <li>Dilarang keras menyalahgunakan sistem dengan melakukan aktivitas peretasan atau spamming.</li>
                    <li>Segala bentuk manipulasi data akademik (nilai, presensi, UKT) akan berujung pada sanksi hukum dan akademik yang tegas.</li>
                    <li>Sistem ini dapat mengalami masa pemeliharaan secara berkala yang akan diumumkan sebelumnya.</li>
                </ul>
            `
        },
        accessibility: {
            title: "Accessibility",
            icon: "accessibility_new",
            content: `
                <h3 class="font-title-lg text-on-surface mb-2">Komitmen Aksesibilitas</h3>
                <p>Kami di UNSIQ percaya bahwa teknologi akademik harus dapat diakses oleh semua lapisan pengguna, termasuk mereka yang memiliki kebutuhan khusus atau menggunakan teknologi bantu (assistive technologies).</p>
                <p>Desain SAQUNA telah mengadaptasi standar kontras warna tingkat tinggi (termasuk Dark Mode untuk kenyamanan mata) dan tata letak hierarkis berbasis keyboard-navigation. Kami terus mengembangkan portal ini agar senantiasa memenuhi pedoman Web Content Accessibility Guidelines (WCAG).</p>
            `
        }
    };

    function openPolicyModal(type) {
        if (!policyData[type]) return;
        
        const data = policyData[type];
        
        document.getElementById('policy-modal-title').textContent = data.title;
        document.getElementById('policy-modal-icon').textContent = data.icon;
        document.getElementById('policy-modal-content').innerHTML = data.content;

        const overlay = document.getElementById('policy-modal-overlay');
        const container = document.getElementById('policy-modal-container');
        
        // Show elements
        overlay.classList.remove('hidden');
        container.classList.remove('hidden');
        container.classList.add('flex');
        
        // Trigger reflow for animation
        void container.offsetWidth;
        
        // Add animation classes
        overlay.classList.remove('opacity-0');
        container.classList.remove('opacity-0', 'translate-y-8');
    }

    function closePolicyModal() {
        const overlay = document.getElementById('policy-modal-overlay');
        const container = document.getElementById('policy-modal-container');
        
        // Remove animation classes
        overlay.classList.add('opacity-0');
        container.classList.add('opacity-0', 'translate-y-8');
        
        // Wait for transition to finish
        setTimeout(() => {
            overlay.classList.add('hidden');
            container.classList.remove('flex');
            container.classList.add('hidden');
        }, 300);
    }
</script>
