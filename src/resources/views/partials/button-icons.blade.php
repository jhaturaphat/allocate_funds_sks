<script>
(() => {
    const icons = [
        [/dashboard|หน้าแรก/i, '📊'],
        [/นำเข้า|อัปโหลด|อัพโหลด/, '📥'],
        [/ส่งออก.*excel/i, '📗'],
        [/เลือกไฟล์เพื่อประมวลผล|ดูรายการ|รายการทุกงวด/, '📋'],
        [/บันทึก/, '💾'],
        [/แก้ไขยอดเงิน|ระบุยอดเงิน/, '✏️'],
        [/ประมวลผล/, '🧮'],
        [/พิมพ์/, '🖨️'],
        [/ยกเลิก/, '✖️'],
        [/ก่อนหน้า|ย้อนกลับ|กลับ/, '←'],
        [/ถัดไป/, '→'],
    ];

    function addButtonIcons() {
        for (const el of document.querySelectorAll('button, a.btn, .page-links a, .list-header a, .actions a')) {
            if (el.querySelector('svg, img, i, [aria-hidden="true"]')) continue;
            const label = el.textContent.trim();
            if (/^[\p{Extended_Pictographic}←→]/u.test(label)) continue;
            const match = icons.find(([pattern]) => pattern.test(label));
            if (!match) continue;

            const icon = document.createElement('span');
            icon.setAttribute('aria-hidden', 'true');
            icon.textContent = match[1];
            el.prepend(icon, document.createTextNode(' '));
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', addButtonIcons, { once: true });
    } else {
        addButtonIcons();
    }
})();
</script>
