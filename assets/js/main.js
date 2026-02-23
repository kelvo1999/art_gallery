// assets/js/main.js — ArtVault

document.addEventListener('DOMContentLoaded', () => {

    // ── Disable right-click on artwork images ──────────────────────
    document.querySelectorAll('.art-protected').forEach(img => {
        img.addEventListener('contextmenu', e => e.preventDefault());
        img.addEventListener('dragstart',   e => e.preventDefault());
    });

    // Also block sitewide for all images
    document.addEventListener('contextmenu', e => {
        if (e.target.tagName === 'IMG') e.preventDefault();
    });

    // ── Disable keyboard shortcuts for saving images ───────────────
    document.addEventListener('keydown', e => {
        // Ctrl+S / Cmd+S  — save page
        if ((e.ctrlKey || e.metaKey) && e.key === 's') {
            e.preventDefault();
        }
        // Ctrl+U — view source
        if ((e.ctrlKey || e.metaKey) && e.key === 'u') {
            e.preventDefault();
        }
        // PrintScreen key (best effort)
        if (e.key === 'PrintScreen') {
            e.preventDefault();
        }
    });

    // ── Print protection: blur images when printing ───────────────
    // Handled via CSS @media print in the HTML head, but we add a JS layer too
    window.addEventListener('beforeprint', () => {
        document.querySelectorAll('.art-protected').forEach(img => {
            img.dataset.origSrc = img.src;
            img.src = '';
        });
    });
    window.addEventListener('afterprint', () => {
        document.querySelectorAll('.art-protected').forEach(img => {
            if (img.dataset.origSrc) img.src = img.dataset.origSrc;
        });
    });

    // ── Flash messages auto-dismiss ────────────────────────────────
    document.querySelectorAll('.alert[data-auto-dismiss]').forEach(el => {
        setTimeout(() => {
            el.style.transition = 'opacity 0.5s ease';
            el.style.opacity = '0';
            setTimeout(() => el.remove(), 500);
        }, 4000);
    });

    // ── Upload zone drag & drop preview ────────────────────────────
    const uploadZone = document.getElementById('upload-zone');
    const fileInput  = document.getElementById('artwork-file');
    const previewImg = document.getElementById('upload-preview');

    if (uploadZone && fileInput) {
        uploadZone.addEventListener('click', () => fileInput.click());

        uploadZone.addEventListener('dragover', e => {
            e.preventDefault();
            uploadZone.style.borderColor = 'var(--accent)';
        });

        uploadZone.addEventListener('dragleave', () => {
            uploadZone.style.borderColor = '';
        });

        uploadZone.addEventListener('drop', e => {
            e.preventDefault();
            uploadZone.style.borderColor = '';
            if (e.dataTransfer.files[0]) {
                fileInput.files = e.dataTransfer.files;
                showPreview(e.dataTransfer.files[0]);
            }
        });

        fileInput.addEventListener('change', () => {
            if (fileInput.files[0]) showPreview(fileInput.files[0]);
        });

        function showPreview(file) {
            if (!file.type.startsWith('image/')) return;
            const reader = new FileReader();
            reader.onload = e => {
                if (previewImg) {
                    previewImg.src = e.target.result;
                    previewImg.style.display = 'block';
                }
                uploadZone.querySelector('.upload-placeholder').style.display = 'none';
            };
            reader.readAsDataURL(file);
        }
    }

    // ── Navbar scroll effect ────────────────────────────────────────
    const navbar = document.querySelector('.navbar');
    if (navbar) {
        window.addEventListener('scroll', () => {
            navbar.classList.toggle('scrolled', window.scrollY > 40);
        });
    }

    // ── Confirm delete actions ──────────────────────────────────────
    document.querySelectorAll('[data-confirm]').forEach(el => {
        el.addEventListener('click', e => {
            if (!confirm(el.dataset.confirm)) e.preventDefault();
        });
    });

    // ── Simple fade-in on scroll ────────────────────────────────────
    const observer = new IntersectionObserver(entries => {
        entries.forEach(e => {
            if (e.isIntersecting) {
                e.target.style.opacity = '1';
                e.target.style.transform = 'translateY(0)';
                observer.unobserve(e.target);
            }
        });
    }, { threshold: 0.1 });

    document.querySelectorAll('.art-card, .stat-card').forEach(el => {
        el.style.opacity = '0';
        el.style.transform = 'translateY(20px)';
        el.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
        observer.observe(el);
    });
});