import QRCode from 'qrcode';

// رسم رموز QR في صفحة الطباعة: <div data-qr="https://..."></div>
document.querySelectorAll('[data-qr]').forEach(async (el) => {
    el.innerHTML = await QRCode.toString(el.dataset.qr, {
        type: 'svg',
        margin: 0,
        errorCorrectionLevel: 'M',
        color: { dark: '#0f172a', light: '#ffffff' },
    });
    if (el.dataset.autoprint !== undefined) {
        setTimeout(() => window.print(), 300);
    }
});
